<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** One-shot error handed from the OAuth routes to the public registration overlay. */
final class SocialFlash
{
    /** @param array<string,mixed> $session */
    public function __construct(private array &$session, private readonly string $key)
    {
    }

    public static function fromGlobals(): self
    {
        return new self($_SESSION, \Core\Helper\WebService::fixSessionPrefix('social_error'));
    }

    public function set(FlowError $error): void
    {
        $this->session[$this->key] = $error->value;
    }

    /** What Options shows after a link or unlink attempt: kind is linked, unlinked, passwordWrong or error. */
    public function setNotice(string $kind, Provider $provider, ?FlowError $error = null): void
    {
        $this->session[$this->key . '_notice'] = ['kind' => $kind, 'provider' => $provider->value, 'error' => $error?->value];
    }

    /** @return array{kind: string, provider: Provider, error: ?FlowError}|null the notice, then forgotten */
    public function pullNotice(): ?array
    {
        $notice = $this->session[$this->key . '_notice'] ?? null;
        unset($this->session[$this->key . '_notice']);
        $provider = is_array($notice) && is_string($notice['provider'] ?? null) ? Provider::tryFrom($notice['provider']) : null;
        if ($provider === null || !in_array($notice['kind'] ?? null, ['linked', 'unlinked', 'passwordWrong', 'error'], true)) {
            return null;
        }

        return ['kind' => $notice['kind'], 'provider' => $provider, 'error' => is_string($notice['error'] ?? null) ? FlowError::tryFrom($notice['error']) : null];
    }

    /** @return array{0:string,1:string}|null the existing translation [base, key] to render, then forgotten */
    public function pullTranslationKey(): ?array
    {
        $code = $this->session[$this->key] ?? null;
        unset($this->session[$this->key]);

        return is_string($code) && FlowError::tryFrom($code) !== null ? FlowError::from($code)->translationKey() : null;
    }
}
