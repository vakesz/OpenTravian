<?php

declare(strict_types=1);

namespace Core\Database;

/**
 * The opt-in encrypted state archive of a backup (backup-<ms>-<random>-state.enc).
 *
 * A database backup alone cannot rebuild a world on a fresh state volume: the operator's SMTP,
 * payment, social-sign-in, CAPTCHA and Discord webhook credentials and the administrator two-factor enrolment live
 * in generated state files (*.local.*), not in the databases. When TRAVIAN_BACKUP_STATE_PASSPHRASE
 * is set, each backup also carries those files, sealed with libsodium: the key comes from the
 * passphrase through Argon2id and the payload is sodium_crypto_secretbox (authenticated, so a wrong
 * passphrase or any change to the file is rejected). connection.local.php is left out on purpose:
 * it holds the database login that belongs to one database instance and is created again by an
 * install. The passphrase is only ever read from the environment: it is never written to a file or
 * to a log, and no message here contains it.
 *
 * File layout: "TSA1", opslimit (u32), memlimit (u32), salt (16), nonce (24), secretbox of a JSON
 * document {"v":1,"files":{"<name>":{"mode":<int>,"data":"<base64>"}}}.
 */
final class StateArchive
{
    public const ENVIRONMENT_VARIABLE = 'TRAVIAN_BACKUP_STATE_PASSPHRASE';

    private const MAGIC = 'TSA1';
    private const HEADER_LENGTH = 4 + 4 + 4 + 16 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
    private const OPSLIMIT = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
    private const MEMLIMIT = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
    /** Bounds for the cost parameters read back from a file, so a crafted file cannot demand unbounded work. */
    private const MAX_OPSLIMIT = 16;
    private const MAX_MEMLIMIT = 1073741824;
    private const EXCLUDED = ['connection.local.php'];

    private function __construct()
    {
    }

    /** The configured passphrase, or null when the archive is switched off. */
    public static function passphrase(): ?string
    {
        $passphrase = getenv(self::ENVIRONMENT_VARIABLE);

        return is_string($passphrase) && $passphrase !== '' ? $passphrase : null;
    }

    /**
     * The state files an archive carries: every generated *.local.* file except the database login.
     *
     * @return list<string> absolute paths
     */
    public static function files(string $stateDirectory): array
    {
        $files = [];
        foreach (glob(rtrim($stateDirectory, '/') . '/*.local.*') ?: [] as $file) {
            if (is_file($file) && !is_link($file) && !in_array(basename($file), self::EXCLUDED, true)) {
                $files[] = $file;
            }
        }
        sort($files);

        return $files;
    }

    /** Writes the sealed archive for the state directory to $path (mode 0600, complete or absent). */
    public static function write(string $path, string $passphrase, string $stateDirectory): bool
    {
        $contents = [];
        foreach (self::files($stateDirectory) as $file) {
            $data = @file_get_contents($file);
            if ($data === false) {
                return false;
            }
            $contents[basename($file)] = ['mode' => (int) (@fileperms($file) & 0777), 'data' => base64_encode($data)];
        }
        $blob = self::seal($contents, $passphrase);
        $partial = $path . '.partial';
        if (@file_put_contents($partial, $blob) === false || !@chmod($partial, 0600) || !@rename($partial, $path)) {
            @unlink($partial);

            return false;
        }

        return true;
    }

    /**
     * @param array<string, array{mode: int, data: string}> $files
     */
    public static function seal(array $files, string $passphrase): string
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $passphrase, $salt, self::OPSLIMIT, self::MEMLIMIT, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        try {
            $sealed = sodium_crypto_secretbox(json_encode(['v' => 1, 'files' => (object) $files], JSON_THROW_ON_ERROR), $nonce, $key);
        } finally {
            sodium_memzero($key);
        }

        return self::MAGIC . pack('NN', self::OPSLIMIT, self::MEMLIMIT) . $salt . $nonce . $sealed;
    }

    /**
     * The files of an archive, or null for a wrong passphrase, a damaged file or a file that is not an archive.
     *
     * @return array<string, array{mode: int, data: string}>|null data is decoded
     */
    public static function open(string $blob, string $passphrase): ?array
    {
        if (!self::hasHeader($blob)) {
            return null;
        }
        $unpacked = unpack('Nops/Nmem', substr($blob, 4, 8));
        $ops = (int) $unpacked['ops'];
        $mem = (int) $unpacked['mem'];
        if ($ops < 1 || $ops > self::MAX_OPSLIMIT || $mem < 8192 || $mem > self::MAX_MEMLIMIT) {
            return null;
        }
        $salt = substr($blob, 12, SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = substr($blob, 28, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $passphrase, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        try {
            $plain = sodium_crypto_secretbox_open(substr($blob, self::HEADER_LENGTH), $nonce, $key);
        } finally {
            sodium_memzero($key);
        }
        if ($plain === false) {
            return null;
        }
        $document = json_decode($plain, true);
        if (!is_array($document) || ($document['v'] ?? null) !== 1 || !is_array($document['files'] ?? null)) {
            return null;
        }
        $files = [];
        foreach ($document['files'] as $name => $file) {
            $data = is_array($file) && is_string($file['data'] ?? null) ? base64_decode($file['data'], true) : false;
            if (!is_string($name) || !self::isSafeName($name) || $data === false) {
                return null;
            }
            $files[$name] = ['mode' => 0600, 'data' => $data];
        }

        return $files;
    }

    /** Whether the file looks like an archive; needs no passphrase. */
    public static function hasHeader(string $blob): bool
    {
        return strlen($blob) > self::HEADER_LENGTH + SODIUM_CRYPTO_SECRETBOX_MACBYTES && str_starts_with($blob, self::MAGIC);
    }

    /**
     * Why an archive file cannot be trusted, or null. With a passphrase the contents are decrypted and
     * authenticated; without one only the header can be checked.
     */
    public static function problem(string $path, ?string $passphrase): ?string
    {
        $blob = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if (!is_string($blob) || !self::hasHeader($blob)) {
            return 'the state archive is missing or damaged';
        }

        return $passphrase !== null && self::open($blob, $passphrase) === null
            ? 'the state archive cannot be opened with the configured passphrase'
            : null;
    }

    /**
     * Writes the decrypted files into the state directory, each atomically with mode 0600.
     *
     * @param array<string, array{mode: int, data: string}> $files from open()
     * @return list<string> the names written
     */
    public static function restore(array $files, string $stateDirectory): array
    {
        $written = [];
        foreach ($files as $name => $file) {
            $target = rtrim($stateDirectory, '/') . '/' . $name;
            $partial = $target . '.restore';
            if (@file_put_contents($partial, $file['data']) === false || !@chmod($partial, 0600) || !@rename($partial, $target)) {
                @unlink($partial);
                throw new \RuntimeException('Cannot write the state file ' . $name . '.');
            }
            $written[] = $name;
        }

        return $written;
    }

    private static function isSafeName(string $name): bool
    {
        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_.-]*\.local\.[A-Za-z0-9]+\z/', $name) === 1 && !in_array($name, self::EXCLUDED, true);
    }
}
