<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** Builds the `socialProviders` view variable: only fully configured providers. */
final class SocialProviderList
{
    private function __construct()
    {
    }

    /**
     * Each entry is a POST target: the template renders a form with the guest
     * CSRF field, so no token ever appears in a URL (or an access log).
     *
     * @return list<array{id:string,url:string}>
     */
    public static function build(ProviderConfig $config, string $baseUrl, ?string $locale, string $referral): array
    {
        $https = str_starts_with(strtolower($baseUrl), 'https://');
        $list = [];
        foreach ($config->configuredProviders() as $provider) {
            // Sign in with Apple only accepts HTTPS redirect URIs.
            if ($provider === Provider::Apple && !$https) {
                continue;
            }
            $query = [];
            if ($locale !== null) {
                $query[] = 'homepageLocale=' . rawurlencode($locale);
            }
            if ($referral !== '') {
                $query[] = 'uc=' . rawurlencode($referral);
            }
            $list[] = [
                'id' => $provider->value,
                'url' => '/oauth/' . $provider->value . '/start' . ($query === [] ? '' : '?' . implode('&', $query)),
            ];
        }

        return $list;
    }
}
