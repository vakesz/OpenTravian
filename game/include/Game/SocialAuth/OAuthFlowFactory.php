<?php

declare(strict_types=1);

namespace Game\SocialAuth;

use Core\Config;

/** Wires the production collaborators for the OAuth routes. */
final class OAuthFlowFactory
{
    private function __construct()
    {
    }

    public static function baseUrl(): string
    {
        return rtrim((string) Config::getProperty('settings', 'gameWorldUrl'), '/');
    }

    public static function create(): OAuthFlow
    {
        $http = new CurlHttpClient();
        $store = new RedisTransientStore();
        $clock = static fn (): int => time();

        return new OAuthFlow(
            ProviderConfig::fromEnvironment(),
            $http,
            $store,
            new JwtVerifier(new JwksCache($http, $store, $clock), $clock),
            new DbIdentityRepository(),
            self::baseUrl(),
            $clock,
        );
    }
}
