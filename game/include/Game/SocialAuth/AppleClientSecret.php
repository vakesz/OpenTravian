<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** Builds the ES256 client_secret JWT Sign in with Apple expects, signed with the operator's .p8 key. */
final class AppleClientSecret
{
    private const LIFETIME = 300;

    private function __construct()
    {
    }

    /** @throws TokenException */
    public static function build(string $teamId, string $clientId, string $keyId, string $privateKeyPem, int $now): string
    {
        $header = Base64Url::encode((string) json_encode(['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT']));
        $claims = Base64Url::encode((string) json_encode([
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + self::LIFETIME,
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ]));
        $key = openssl_pkey_get_private($privateKeyPem);
        $signature = '';
        if ($key === false || !openssl_sign($header . '.' . $claims, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new TokenException('Client secret could not be signed.');
        }
        $raw = Asn1::ecdsaDerToRaw($signature, 32);
        if ($raw === null) {
            throw new TokenException('Client secret could not be signed.');
        }

        return $header . '.' . $claims . '.' . Base64Url::encode($raw);
    }
}
