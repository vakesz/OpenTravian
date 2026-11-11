<?php

declare(strict_types=1);

namespace Game\SocialAuth;

use Core\Security\Password;

/**
 * A verified social identity waiting to become a game account. The callback
 * stores it; the #registration overlay's second step reads it (email locked,
 * no password fields); /activate.php finalizes it once the user exists.
 */
final class PendingSignup
{
    private const TTL = 1800;

    /** @param array<string,mixed> $session */
    public function __construct(private array &$session, private readonly string $key, private readonly \Closure $clock)
    {
    }

    public static function fromGlobals(): self
    {
        return new self($_SESSION, \Core\Helper\WebService::fixSessionPrefix('social_signup'), static fn (): int => time());
    }

    public function store(IdentityClaims $claims): void
    {
        $this->session[$this->key] = [
            'provider' => $claims->provider->value,
            'subject' => $claims->subject,
            'email' => $claims->email,
            'verified' => $claims->emailVerified,
            'name' => $claims->name,
            'expires' => ($this->clock)() + self::TTL,
            'token' => '',
        ];
    }

    public function current(): ?IdentityClaims
    {
        $entry = $this->entry();
        $provider = $entry === null ? null : Provider::tryFrom((string) $entry['provider']);

        return $entry === null || $provider === null ? null : new IdentityClaims(
            $provider,
            (string) $entry['subject'],
            (string) $entry['email'],
            (bool) $entry['verified'],
            (string) $entry['name']
        );
    }

    /** True when the pending social identity belongs to this activation token (and has not expired). */
    public function isBoundTo(string $activationToken): bool
    {
        $entry = $this->entry();

        return $entry !== null && $activationToken !== '' && hash_equals((string) $entry['token'], $activationToken);
    }

    /** Called when the registration overlay has stored the pending activation row for this signup. */
    public function bindActivationToken(string $token): void
    {
        if ($this->entry() !== null) {
            $this->session[$this->key]['token'] = $token;
        }
    }

    /** Called by /activate.php after addUser(): records the link, only for the bound activation token. */
    public function finalize(int $userId, string $activationToken, IdentityRepository $identities): bool
    {
        $entry = $this->entry();
        $claims = $this->current();
        if ($entry === null || $claims === null || $activationToken === '' || !hash_equals((string) $entry['token'], $activationToken)) {
            return false;
        }
        $this->clear();
        // The created account must carry exactly the verified provider email.
        if ($identities->userEmail($userId) !== strtolower($claims->email)) {
            return false;
        }

        return $identities->link($userId, $claims);
    }

    public function clear(): void
    {
        unset($this->session[$this->key]);
    }

    /** An unusable password: social users have none, and nobody knows this value. */
    public static function unusablePasswordHash(): string
    {
        return Password::hash(bin2hex(random_bytes(32)));
    }

    /** @return array<string,mixed>|null */
    private function entry(): ?array
    {
        $entry = $this->session[$this->key] ?? null;
        if (!is_array($entry) || (int) ($entry['expires'] ?? 0) < ($this->clock)()) {
            unset($this->session[$this->key]);

            return null;
        }

        return $entry;
    }
}
