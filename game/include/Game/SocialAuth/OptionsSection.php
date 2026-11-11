<?php

declare(strict_types=1);

namespace Game\SocialAuth;

use function T;

/**
 * What the Options Account tab shows about social accounts: a row for every configured provider (and for any
 * provider the player is linked to, even one that was unconfigured later), with the linked address, and the notice
 * left by the last link or unlink.
 */
final class OptionsSection
{
    private function __construct()
    {
    }

    /**
     * @return array{rows: list<array{provider: Provider, label: string, email: ?string}>, notice: ?array{text: string, error: bool}}|null null when there is nothing to show
     */
    public static function build(int $userId, ProviderConfig $config, string $baseUrl, SocialFlash $flash): ?array
    {
        $linked = [];
        foreach (LinkedIdentities::forUser($userId) as $identity) {
            $linked[$identity['provider']->value] = $identity['email'];
        }
        $https = str_starts_with(strtolower($baseUrl), 'https://');
        $rows = [];
        foreach (Provider::cases() as $provider) {
            $offered = in_array($provider, $config->configuredProviders(), true) && ($provider !== Provider::Apple || $https);
            if ($offered || array_key_exists($provider->value, $linked)) {
                $rows[] = ['provider' => $provider, 'label' => $provider->label(), 'email' => $linked[$provider->value] ?? null];
            }
        }
        $notice = self::notice($flash->pullNotice());

        return $rows === [] && $notice === null ? null : ['rows' => $rows, 'notice' => $notice];
    }

    /**
     * @param array{kind: string, provider: Provider, error: ?FlowError}|null $notice
     * @return array{text: string, error: bool}|null
     */
    private static function notice(?array $notice): ?array
    {
        if ($notice === null) {
            return null;
        }
        $name = $notice['provider']->label();
        $fill = static fn (string $text): string => str_replace('%s', $name, $text);

        return match ($notice['kind']) {
            'linked' => ['text' => $fill((string) T('Login', 'Your %s account is now linked.')), 'error' => false],
            'unlinked' => ['text' => $fill((string) T('Login', 'Your %s account has been unlinked.')), 'error' => false],
            'passwordWrong' => ['text' => (string) T('Options', 'password wrong'), 'error' => true],
            default => ['text' => $fill((string) T(...($notice['error'] ?? FlowError::InvalidState)->translationKey())), 'error' => true],
        };
    }
}
