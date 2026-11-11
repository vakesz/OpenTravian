<?php

declare(strict_types=1);

namespace Game\Admin;

/**
 * Administrator two-factor state: the sign-in mode, each enrolled admin's TOTP
 * secret, hashed single-use recovery codes and the last accepted time step.
 * Like SMTP, social sign-in and the CAPTCHA keys it is deployment state kept in
 * a 0600 file in the generated state directory (admin-2fa.local.json), never in
 * SQL, globalConfig.php, forms, logs or the repository. Writes are atomic and
 * serialised by a lock file so concurrent sign-ins cannot replay a code or
 * resurrect a spent recovery code.
 *
 * TRAVIAN_ADMIN_TWO_FACTOR=off|optional|required can only LOWER the stored mode
 * (an emergency break-glass for a locked-out operator); raising it is an admin
 * setting, so the environment can never silently demand codes nobody enrolled.
 */
final class TwoFactorStore
{
    public const MODES = ['off', 'optional', 'required'];
    public const RECOVERY_CODE_COUNT = 10;

    public function __construct(private readonly string $path, private readonly ?string $overrideMode = null)
    {
    }

    public static function fromEnvironment(): self
    {
        $override = getenv('TRAVIAN_ADMIN_TWO_FACTOR');
        $override = is_string($override) && in_array($override, self::MODES, true) ? $override : null;
        $explicit = getenv('TRAVIAN_ADMIN_TWO_FACTOR_FILE');
        if (is_string($explicit) && $explicit !== '') {
            return new self($explicit, $override);
        }
        $state = defined('STATE_DIR') ? (string) constant('STATE_DIR') : (string) getenv('TRAVIAN_STATE_DIR');

        return new self($state === '' ? '' : rtrim($state, '/') . '/admin-2fa.local.json', $override);
    }

    public function path(): string
    {
        return $this->path;
    }

    /** The mode in force: the stored mode, lowered (never raised) by the environment override. */
    public function mode(): string
    {
        $stored = $this->storedMode();
        if ($this->overrideMode === null) {
            return $stored;
        }

        return array_search($this->overrideMode, self::MODES, true) < array_search($stored, self::MODES, true)
            ? $this->overrideMode
            : $stored;
    }

    /** The mode saved by the admin setting; a missing file means off, an unreadable one fails closed (required). */
    public function storedMode(): string
    {
        $state = $this->read();

        return $state === null ? 'required' : $state['mode'];
    }

    /** Whether the stored mode is currently lowered by the environment. */
    public function isOverridden(): bool
    {
        return $this->mode() !== $this->storedMode();
    }

    public function setMode(string $mode): bool
    {
        if (!in_array($mode, self::MODES, true)) {
            return false;
        }

        return $this->mutate(static function (array &$state) use ($mode): bool {
            $state['mode'] = $mode;

            return true;
        });
    }

    public function isEnrolled(int $adminId): bool
    {
        $state = $this->read();

        return $state !== null && isset($state['accounts'][$adminId]['secret']);
    }

    /** The secret for an enrolled admin (needed to show it again while confirming enrolment is not supported). */
    public function secret(int $adminId): ?string
    {
        $state = $this->read();

        return $state['accounts'][$adminId]['secret'] ?? null;
    }

    /**
     * Stores a confirmed enrolment and returns the plaintext recovery codes,
     * shown once; only their password_hash digests are kept.
     *
     * @return list<string>|null null when the state file cannot be written
     */
    public function enrol(int $adminId, string $secret): ?array
    {
        $codes = self::newRecoveryCodes();
        $hashes = array_map(static fn (string $code): string => password_hash(self::normalise($code), PASSWORD_DEFAULT), $codes);
        $saved = $this->mutate(static function (array &$state) use ($adminId, $secret, $hashes): bool {
            $state['accounts'][$adminId] = ['secret' => $secret, 'recovery' => $hashes, 'lastStep' => 0, 'enrolledAt' => time()];

            return true;
        });

        return $saved ? $codes : null;
    }

    /** @return list<string>|null a fresh set of recovery codes (the old ones stop working) */
    public function regenerateRecoveryCodes(int $adminId): ?array
    {
        $codes = self::newRecoveryCodes();
        $hashes = array_map(static fn (string $code): string => password_hash(self::normalise($code), PASSWORD_DEFAULT), $codes);
        $saved = $this->mutate(static function (array &$state) use ($adminId, $hashes): bool {
            if (!isset($state['accounts'][$adminId])) {
                return false;
            }
            $state['accounts'][$adminId]['recovery'] = $hashes;

            return true;
        });

        return $saved ? $codes : null;
    }

    public function disable(int $adminId): bool
    {
        return $this->mutate(static function (array &$state) use ($adminId): bool {
            unset($state['accounts'][$adminId]);

            return true;
        });
    }

    public function recoveryCodesLeft(int $adminId): int
    {
        $state = $this->read();

        return count($state['accounts'][$adminId]['recovery'] ?? []);
    }

    /**
     * Checks an authenticator code for an enrolled admin and records its step
     * so it cannot be used twice.
     */
    public function verifyCode(int $adminId, string $code, ?int $now = null): bool
    {
        $accepted = false;
        $this->mutate(static function (array &$state) use ($adminId, $code, $now, &$accepted): bool {
            $account = $state['accounts'][$adminId] ?? null;
            if ($account === null) {
                return false;
            }
            $step = Totp::verify($account['secret'], $code, $now, 1, (int) $account['lastStep']);
            if ($step === null) {
                return false;
            }
            $state['accounts'][$adminId]['lastStep'] = $step;
            $accepted = true;

            return true;
        });

        return $accepted;
    }

    /** Consumes a recovery code (single use). */
    public function verifyRecoveryCode(int $adminId, string $code): bool
    {
        $accepted = false;
        $candidate = self::normalise($code);
        $this->mutate(static function (array &$state) use ($adminId, $candidate, &$accepted): bool {
            $hashes = $state['accounts'][$adminId]['recovery'] ?? [];
            $matched = null;
            // Every hash is checked so the timing does not reveal how many codes are left.
            foreach ($hashes as $index => $hash) {
                if (password_verify($candidate, $hash) && $matched === null) {
                    $matched = $index;
                }
            }
            if ($matched === null) {
                return false;
            }
            unset($hashes[$matched]);
            $state['accounts'][$adminId]['recovery'] = array_values($hashes);
            $accepted = true;

            return true;
        });

        return $accepted;
    }

    /** Ten codes such as "k3m9x-q7w2d", from an alphabet without look-alike characters. @return list<string> */
    public static function newRecoveryCodes(): array
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; ++$i) {
            $code = '';
            for ($j = 0; $j < 10; ++$j) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($code, 0, 5) . '-' . substr($code, 5);
        }

        return $codes;
    }

    private static function normalise(string $code): string
    {
        return strtolower(str_replace([' ', '-'], '', trim($code)));
    }

    /** @return array{mode: string, accounts: array<int, array{secret: string, recovery: list<string>, lastStep: int, enrolledAt: int}>}|null null when the file is unreadable or invalid */
    private function read(): ?array
    {
        if ($this->path !== '' && is_link($this->path)) {
            return null;
        }
        if ($this->path === '' || !is_file($this->path)) {
            return $this->path === '' || !file_exists($this->path) ? ['mode' => 'off', 'accounts' => []] : null;
        }
        $json = file_get_contents($this->path);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || !in_array($data['mode'] ?? null, self::MODES, true) || !is_array($data['accounts'] ?? null)) {
            return null;
        }
        $accounts = [];
        foreach ($data['accounts'] as $id => $account) {
            if (!is_array($account) || !is_string($account['secret'] ?? null) || !is_array($account['recovery'] ?? null)) {
                return null;
            }
            $accounts[(int) $id] = [
                'secret' => $account['secret'],
                'recovery' => array_values(array_filter($account['recovery'], 'is_string')),
                'lastStep' => (int) ($account['lastStep'] ?? 0),
                'enrolledAt' => (int) ($account['enrolledAt'] ?? 0),
            ];
        }

        return ['mode' => $data['mode'], 'accounts' => $accounts];
    }

    /**
     * Read-modify-write under an exclusive lock. The callback returns whether
     * the state changed; an unreadable existing file is replaced only by an
     * explicit change (enrol, mode), never by a read.
     *
     * @param callable(array &$state): bool $change
     */
    private function mutate(callable $change): bool
    {
        $directory = $this->path === '' ? '' : dirname($this->path);
        if ($directory === '' || !is_dir($directory) || !is_writable($directory) || is_link($this->path)) {
            return false;
        }
        $lock = fopen($this->path . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            return false;
        }
        try {
            $state = $this->read() ?? ['mode' => 'required', 'accounts' => []];
            if (!$change($state)) {
                return false;
            }
            $temporary = tempnam($directory, '.travian-2fa-');
            if ($temporary === false) {
                return false;
            }
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            if (file_put_contents($temporary, $json) === false || !chmod($temporary, 0600) || !rename($temporary, $this->path)) {
                if (is_file($temporary)) {
                    unlink($temporary);
                }

                return false;
            }

            return chmod($this->path, 0600);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
