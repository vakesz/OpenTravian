<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/**
 * Verifies RS256 OpenID Connect id_tokens against a provider JWKS: signature,
 * issuer, audience, expiry, issued-at and nonce. Any other algorithm
 * (none, HS256, ES256, ...) is rejected, which rules out algorithm confusion.
 */
final class JwtVerifier
{
    private const LEEWAY = 60;
    private const MAX_TOKEN_LENGTH = 16384;

    public function __construct(private readonly JwksCache $jwks, private readonly \Closure $clock)
    {
    }

    /**
     * @param list<string> $issuers
     * @return array<string,mixed> the verified claims
     * @throws TokenException
     */
    public function verify(string $jwt, string $jwksUri, array $issuers, string $audience, string $nonce): array
    {
        if (strlen($jwt) > self::MAX_TOKEN_LENGTH) {
            throw new TokenException('Token malformed.');
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new TokenException('Token malformed.');
        }
        $header = $this->decodeJson($parts[0]);
        $claims = $this->decodeJson($parts[1]);
        $signature = Base64Url::decode($parts[2]);
        if ($signature === null) {
            throw new TokenException('Token malformed.');
        }
        $kid = $header['kid'] ?? null;
        if (($header['alg'] ?? null) !== 'RS256' || !is_string($kid) || $kid === '') {
            throw new TokenException('Token algorithm rejected.');
        }
        $jwk = $this->jwks->key($jwksUri, $kid);
        if ($jwk === null || ($jwk['kty'] ?? null) !== 'RSA' || (isset($jwk['alg']) && $jwk['alg'] !== 'RS256')) {
            throw new TokenException('Signing key not found.');
        }
        $modulus = is_string($jwk['n'] ?? null) ? Base64Url::decode($jwk['n']) : null;
        $exponent = is_string($jwk['e'] ?? null) ? Base64Url::decode($jwk['e']) : null;
        if ($modulus === null || $exponent === null || strlen($modulus) < 256) {
            throw new TokenException('Signing key invalid.');
        }
        $key = openssl_pkey_get_public(Asn1::rsaPublicKeyPem($modulus, $exponent));
        if ($key === false || openssl_verify($parts[0] . '.' . $parts[1], $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new TokenException('Signature invalid.');
        }

        $this->checkClaims($claims, $issuers, $audience, $nonce);

        return $claims;
    }

    /**
     * @param array<string,mixed> $claims
     * @param list<string> $issuers
     */
    private function checkClaims(array $claims, array $issuers, string $audience, string $nonce): void
    {
        $now = ($this->clock)();
        if (!is_string($claims['iss'] ?? null) || !in_array($claims['iss'], $issuers, true)) {
            throw new TokenException('Issuer mismatch.');
        }
        $aud = $claims['aud'] ?? null;
        $audiences = is_string($aud) ? [$aud] : (is_array($aud) ? $aud : []);
        if (!in_array($audience, $audiences, true)) {
            throw new TokenException('Audience mismatch.');
        }
        if (count($audiences) > 1 && ($claims['azp'] ?? null) !== $audience) {
            throw new TokenException('Authorized party mismatch.');
        }
        if (isset($claims['azp']) && $claims['azp'] !== $audience) {
            throw new TokenException('Authorized party mismatch.');
        }
        if (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > $now + self::LEEWAY)) {
            throw new TokenException('Token not yet valid.');
        }
        if (!is_int($claims['exp'] ?? null) || $claims['exp'] + self::LEEWAY < $now) {
            throw new TokenException('Token expired.');
        }
        if (isset($claims['iat']) && (!is_int($claims['iat']) || $claims['iat'] > $now + self::LEEWAY)) {
            throw new TokenException('Token issued in the future.');
        }
        if ($nonce === '' || !is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
            throw new TokenException('Nonce mismatch.');
        }
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255) {
            throw new TokenException('Subject missing.');
        }
    }

    /** @return array<string,mixed> */
    private function decodeJson(string $segment): array
    {
        $json = Base64Url::decode($segment);
        $decoded = $json === null ? null : json_decode($json, true, 8);
        if (!is_array($decoded)) {
            throw new TokenException('Token malformed.');
        }

        return $decoded;
    }
}
