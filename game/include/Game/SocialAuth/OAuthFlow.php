<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/**
 * Provider-independent authorization-code flow. All collaborators are injected
 * so the protocol logic is testable with mocked HTTP, JWKS and storage.
 */
final class OAuthFlow
{
    private const STATE_TTL = 600;

    /**
     * @param \Closure():int $clock
     */
    public function __construct(
        private readonly ProviderConfig $config,
        private readonly HttpClient $http,
        private readonly TransientStore $store,
        private readonly JwtVerifier $verifier,
        private readonly IdentityRepository $identities,
        private readonly string $baseUrl,
        private readonly \Closure $clock,
    ) {
    }

    /**
     * @param string $referral a validated refer-a-friend code or ''
     * @param int|null $linkUserId the signed-in player who asks to link this identity (Options); null for sign-in and sign-up
     * @return array{url:string,state:string,binding:?string}|null null when the provider is not configured; `binding` is the
     *         secret for the Apple browser-binding cookie (never put it in a URL)
     */
    public function begin(Provider $provider, ?string $locale, string $referral, ?int $linkUserId = null): ?array
    {
        $credentials = $this->config->credentials($provider);
        if ($credentials === null) {
            return null;
        }
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(24));
        $verifier = Base64Url::encode(random_bytes(48));
        $binding = $provider->usesFormPost() ? bin2hex(random_bytes(32)) : null;
        $this->store->put('flow_' . hash('sha256', $state), [
            'provider' => $provider->value,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'locale' => $locale,
            'referral' => $referral,
            'link_uid' => $linkUserId,
            'binding_hash' => $binding === null ? null : hash('sha256', $binding),
        ], self::STATE_TTL);

        $query = [
            'client_id' => $credentials['client_id'],
            'redirect_uri' => ProviderConfig::redirectUri($provider, $this->baseUrl),
            'response_type' => 'code',
            'scope' => $provider->scope(),
            'state' => $state,
        ];
        if ($provider->usesIdToken()) {
            $query['nonce'] = $nonce;
        }
        if ($provider->usesPkce()) {
            $query['code_challenge'] = Base64Url::encode(hash('sha256', $verifier, true));
            $query['code_challenge_method'] = 'S256';
        }
        if ($provider->usesFormPost()) {
            $query['response_mode'] = 'form_post';
        }

        return [
            'url' => $provider->authorizeEndpoint() . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            'state' => $state,
            'binding' => $binding,
        ];
    }

    /**
     * @param array<string,mixed> $params the callback query (GET) or body (POST)
     * @param string|null $sessionState the state remembered in this browser's session (Google, Facebook)
     * @param string|null $bindingCookie the Apple start-time binding cookie value (Apple only)
     */
    public function complete(Provider $provider, array $params, ?string $sessionState, ?string $bindingCookie = null): FlowResult
    {
        $state = is_string($params['state'] ?? null) ? $params['state'] : '';
        if (preg_match('/\A[a-f0-9]{64}\z/', $state) !== 1) {
            return FlowResult::failure(FlowError::InvalidState);
        }
        // Consumed before anything else: a replayed callback finds nothing.
        $flow = $this->store->take('flow_' . hash('sha256', $state));
        if ($flow === null || ($flow['provider'] ?? null) !== $provider->value) {
            return FlowResult::failure(FlowError::InvalidState);
        }
        $locale = is_string($flow['locale'] ?? null) ? $flow['locale'] : null;
        $referral = is_string($flow['referral'] ?? null) ? $flow['referral'] : '';
        $linkUserId = is_int($flow['link_uid'] ?? null) ? $flow['link_uid'] : null;
        $fail = static fn (FlowError $error): FlowResult => FlowResult::failure($error, $locale, $referral, $linkUserId);

        // Browser binding (login-CSRF defence). Apple's cross-site POST cannot
        // rely on the session cookie, so it must present the separate
        // SameSite=None binding cookie whose hash was stored at start.
        if ($provider->usesFormPost()) {
            $expected = $flow['binding_hash'] ?? null;
            $bound = is_string($expected) && is_string($bindingCookie) && $bindingCookie !== ''
                && hash_equals($expected, hash('sha256', $bindingCookie));
        } else {
            $bound = $sessionState !== null && hash_equals($sessionState, $state);
        }
        if (!$bound) {
            return $fail(FlowError::InvalidState);
        }
        if (isset($params['error'])) {
            return $fail(FlowError::Denied);
        }
        $code = $params['code'] ?? null;
        $credentials = $this->config->credentials($provider);
        if (!is_string($code) || $code === '' || strlen($code) > 2048) {
            return $fail(FlowError::InvalidState);
        }
        if ($credentials === null) {
            return $fail(FlowError::Unavailable);
        }

        try {
            $claims = $provider->usesIdToken()
                ? $this->idTokenClaims($provider, $credentials, $flow, $code, $params)
                : $this->facebookClaims($credentials, $code);
        } catch (TokenException) {
            return $fail(FlowError::InvalidToken);
        } catch (\RuntimeException) {
            return $fail(FlowError::ProviderFailure);
        }

        $userId = $this->identities->findUserId($provider, $claims->subject);
        if ($linkUserId !== null) {
            // Linking is explicit: whoever started it keeps the identity, whatever the provider's email says.
            if ($userId !== null && $userId !== $linkUserId) {
                return $fail(FlowError::LinkedElsewhere);
            }
            if ($userId === null && $this->identities->providerLinked($linkUserId, $provider)) {
                return $fail(FlowError::ProviderLinked);
            }

            return FlowResult::link($linkUserId, $claims, $locale, $referral);
        }
        if ($userId !== null) {
            return FlowResult::login($userId, $locale, $referral);
        }
        // A new identity never attaches to an existing account by email.
        if (!$claims->emailVerified) {
            return $fail(FlowError::EmailMissing);
        }
        if ($claims->email === '' || filter_var($claims->email, FILTER_VALIDATE_EMAIL) === false || strlen($claims->email) > 90) {
            return $fail(FlowError::EmailMissing);
        }
        if ($this->identities->emailInUse($claims->email)) {
            return $fail(FlowError::EmailInUse);
        }

        return FlowResult::signup($claims, $locale, $referral);
    }

    /**
     * @param array<string,string> $credentials
     * @param array<string,mixed> $flow
     * @param array<string,mixed> $params
     */
    private function idTokenClaims(Provider $provider, array $credentials, array $flow, string $code, array $params): IdentityClaims
    {
        $secret = $provider === Provider::Apple
            ? AppleClientSecret::build($credentials['team_id'], $credentials['client_id'], $credentials['key_id'], $credentials['private_key'], ($this->clock)())
            : $credentials['client_secret'];
        $fields = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => ProviderConfig::redirectUri($provider, $this->baseUrl),
            'client_id' => $credentials['client_id'],
            'client_secret' => $secret,
        ];
        if ($provider->usesPkce()) {
            $fields['code_verifier'] = (string) ($flow['verifier'] ?? '');
        }
        $response = $this->http->postForm($provider->tokenEndpoint(), $fields);
        $body = $response->status === 200 ? $response->json() : null;
        $idToken = is_array($body) ? ($body['id_token'] ?? null) : null;
        if (!is_string($idToken)) {
            throw new TokenException('No id_token.');
        }
        $claims = $this->verifier->verify(
            $idToken,
            (string) $provider->jwksUri(),
            $provider->issuers(),
            $credentials['client_id'],
            (string) ($flow['nonce'] ?? '')
        );
        $email = is_string($claims['email'] ?? null) ? strtolower(trim($claims['email'])) : '';
        $verified = ($claims['email_verified'] ?? false) === true || ($claims['email_verified'] ?? '') === 'true';

        return new IdentityClaims($provider, (string) $claims['sub'], $email, $verified, $this->displayName($provider, $claims, $params));
    }

    /**
     * @param array<string,mixed> $claims
     * @param array<string,mixed> $params
     */
    private function displayName(Provider $provider, array $claims, array $params): string
    {
        if ($provider === Provider::Apple && is_string($params['user'] ?? null)) {
            $user = json_decode($params['user'], true, 6);
            $name = is_array($user) && is_array($user['name'] ?? null) ? $user['name'] : [];
            $full = trim((string) ($name['firstName'] ?? '') . ' ' . (string) ($name['lastName'] ?? ''));

            return mb_substr($full, 0, 60);
        }

        return is_string($claims['name'] ?? null) ? mb_substr($claims['name'], 0, 60) : '';
    }

    /** @param array<string,string> $credentials */
    private function facebookClaims(array $credentials, string $code): IdentityClaims
    {
        $provider = Provider::Facebook;
        $response = $this->http->postForm($provider->tokenEndpoint(), [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'redirect_uri' => ProviderConfig::redirectUri($provider, $this->baseUrl),
            'code' => $code,
        ]);
        $body = $response->status === 200 ? $response->json() : null;
        $token = is_array($body) ? ($body['access_token'] ?? null) : null;
        if (!is_string($token) || $token === '') {
            throw new TokenException('No access token.');
        }
        // Graph API: debug_token and /me accept their tokens as query parameters
        // (access_token / input_token). Meta documents no header or POST form for
        // debug_token, and this cannot be verified without live credentials, so
        // the query string stays; the HTTP client sends no Referer, follows no
        // redirects and nothing here logs URLs. appsecret_proof limits /me misuse.
        $proof = hash_hmac('sha256', $token, $credentials['client_secret']);
        $appToken = $credentials['client_id'] . '|' . $credentials['client_secret'];

        $debug = $this->http->get('https://graph.facebook.com/v19.0/debug_token?' . http_build_query([
            'input_token' => $token,
            'access_token' => $appToken,
        ], '', '&', PHP_QUERY_RFC3986));
        $data = $debug->status === 200 ? ($debug->json()['data'] ?? null) : null;
        if (!is_array($data) || ($data['is_valid'] ?? false) !== true
            || (string) ($data['app_id'] ?? '') !== $credentials['client_id']
            || !is_string($data['user_id'] ?? null) || $data['user_id'] === '') {
            throw new TokenException('Token not valid for this app.');
        }

        $me = $this->http->get('https://graph.facebook.com/v19.0/me?' . http_build_query([
            'fields' => 'id,name,email',
            'access_token' => $token,
            'appsecret_proof' => $proof,
        ], '', '&', PHP_QUERY_RFC3986));
        $profile = $me->status === 200 ? $me->json() : null;
        if (!is_array($profile) || ($profile['id'] ?? null) !== $data['user_id']) {
            throw new TokenException('Profile mismatch.');
        }
        $email = is_string($profile['email'] ?? null) ? strtolower(trim($profile['email'])) : '';

        // Facebook only returns an email it has confirmed.
        return new IdentityClaims(
            $provider,
            $data['user_id'],
            $email,
            $email !== '',
            is_string($profile['name'] ?? null) ? mb_substr($profile['name'], 0, 60) : ''
        );
    }
}
