<?php

declare(strict_types=1);

use Game\SocialAuth\AppleClientSecret;
use Game\SocialAuth\Base64Url;
use Game\SocialAuth\FlowError;
use Game\SocialAuth\FlowResult;
use Game\SocialAuth\HttpClient;
use Game\SocialAuth\HttpResponse;
use Game\SocialAuth\IdentityClaims;
use Game\SocialAuth\IdentityRepository;
use Game\SocialAuth\JwksCache;
use Game\SocialAuth\JwtVerifier;
use Game\SocialAuth\OAuthFlow;
use Game\SocialAuth\PendingSignup;
use Game\SocialAuth\Provider;
use Game\SocialAuth\ProviderConfig;
use Game\SocialAuth\SocialProviderList;
use Game\SocialAuth\TransientStore;

error_reporting(-1);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    $file = $root . '/game/include/' . str_replace('\\', '/', $class) . '.php';
    if (str_starts_with($class, 'Game\\SocialAuth\\') && is_file($file)) {
        require $file;
    }
});

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

final class ArrayStore implements TransientStore
{
    /** @var array<string,array<string,mixed>> */
    public array $data = [];

    public function put(string $key, array $value, int $ttl): void
    {
        $this->data[$key] = $value;
    }

    public function peek(string $key): ?array
    {
        return $this->data[$key] ?? null;
    }

    public function take(string $key): ?array
    {
        $value = $this->data[$key] ?? null;
        unset($this->data[$key]);

        return $value;
    }
}

final class FakeHttp implements HttpClient
{
    /** @var array<string,HttpResponse> keyed by URL prefix */
    public array $routes = [];
    /** @var list<array{url:string,fields:array<string,string>}> */
    public array $posts = [];
    public int $jwksFetches = 0;

    public function get(string $url, array $headers = []): HttpResponse
    {
        if (str_contains($url, '/certs') || str_contains($url, '/auth/keys')) {
            ++$this->jwksFetches;
        }

        return $this->route($url);
    }

    public function postForm(string $url, array $fields): HttpResponse
    {
        $this->posts[] = ['url' => $url, 'fields' => $fields];

        return $this->route($url);
    }

    private function route(string $url): HttpResponse
    {
        foreach ($this->routes as $prefix => $response) {
            if (str_starts_with($url, $prefix)) {
                return $response;
            }
        }
        throw new RuntimeException('unrouted ' . $url);
    }
}

final class FakeIdentities implements IdentityRepository
{
    /** @var array<string,int> */
    public array $linked = [];
    /** @var list<string> */
    public array $emails = [];

    public function findUserId(Provider $provider, string $subject): ?int
    {
        return $this->linked[$provider->value . ':' . $subject] ?? null;
    }

    public function emailInUse(string $email): bool
    {
        return in_array($email, $this->emails, true);
    }

    /** @var array<string,bool> uid:provider */
    public array $providers = [];

    public function providerLinked(int $userId, Provider $provider): bool
    {
        return $this->providers[$userId . ':' . $provider->value] ?? false;
    }

    public function link(int $userId, IdentityClaims $claims): bool
    {
        $this->linked[$claims->provider->value . ':' . $claims->subject] = $userId;

        return true;
    }

    /** @var array<int,string> */
    public array $userEmails = [];

    public function userEmail(int $userId): ?string
    {
        return isset($this->userEmails[$userId]) ? strtolower($this->userEmails[$userId]) : null;
    }

    public function userForLogin(int $userId): ?array
    {
        return ['id' => $userId, 'name' => 'u' . $userId, 'password' => 'hash'];
    }
}

function newRsa(): OpenSSLAsymmetricKey
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    check($key !== false, 'rsa key generation');

    return $key;
}

/** @return array<string,string> */
function jwkFor(OpenSSLAsymmetricKey $key, string $kid): array
{
    $rsa = openssl_pkey_get_details($key)['rsa'];

    return ['kty' => 'RSA', 'kid' => $kid, 'alg' => 'RS256', 'use' => 'sig', 'n' => Base64Url::encode($rsa['n']), 'e' => Base64Url::encode($rsa['e'])];
}

/** @param array<string,mixed> $claims @param array<string,mixed> $headerOverride */
function signJwt(OpenSSLAsymmetricKey $key, string $kid, array $claims, array $headerOverride = []): string
{
    $header = Base64Url::encode((string) json_encode($headerOverride + ['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT']));
    $payload = Base64Url::encode((string) json_encode($claims));
    openssl_sign($header . '.' . $payload, $signature, $key, OPENSSL_ALGO_SHA256);

    return $header . '.' . $payload . '.' . Base64Url::encode($signature);
}

$now = 1_800_000_000;
$clock = static fn (): int => $now;
$key = newRsa();
$otherKey = newRsa();
$jwks = new HttpResponse(200, (string) json_encode(['keys' => [jwkFor($key, 'k1')]]));

// --- ProviderConfig gating and protected storage ---------------------------------------------
$dir = sys_get_temp_dir() . '/travian-social-' . bin2hex(random_bytes(4));
mkdir($dir, 0700);
$config = new ProviderConfig($dir . '/social.local.php');
check($config->configuredProviders() === [], 'nothing configured by default');
check(SocialProviderList::build($config, 'https://x.test', null, '') === [], 'empty socialProviders when unconfigured');

$ec = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
openssl_pkey_export($ec, $p8);
$submission = [
    'social_google_client_id' => 'gid.apps.example', 'social_google_client_secret' => 'gsecret',
    'social_facebook_client_id' => '12345',   // partial: secret missing
];
check($config->fromSubmission($submission)['config'] === null, 'partial provider is rejected');
unset($submission['social_facebook_client_id']);
$submission += [
    'social_apple_client_id' => 'com.example.web', 'social_apple_team_id' => 'ABCDE12345',
    'social_apple_key_id' => 'KEYID12345', 'social_apple_private_key' => $p8,
];
$parsed = $config->fromSubmission($submission);
check($parsed['config'] !== null && $config->save($parsed['config']), 'valid submission saved');
check((fileperms($dir . '/social.local.php') & 0777) === 0600, 'state file is 0600');
check(array_map(static fn (Provider $p): string => $p->value, $config->configuredProviders()) === ['google', 'apple'], 'only complete providers are configured');
$list = SocialProviderList::build($config, 'http://localhost:8080', 'de', '');
check(array_column($list, 'id') === ['google'], 'Apple hidden on a non-HTTPS base URL');
$list = SocialProviderList::build($config, 'https://x.test', 'de', 'wimpel_7');
check(array_column($list, 'id') === ['google', 'apple'] && str_starts_with($list[0]['url'], '/oauth/google/start?'), 'root-absolute start URLs');
check(str_contains($list[0]['url'], 'homepageLocale=de') && str_contains($list[0]['url'], 'uc=wimpel_7') && !str_contains($list[0]['url'], 'mpvt_token'), 'locale and referral preserved; no CSRF token in the URL');
$forms = json_encode($config->formValues());
check(!str_contains((string) $forms, 'gsecret') && !str_contains((string) $forms, 'BEGIN'), 'secrets never reach form values');
$kept = $config->fromSubmission(['social_google_client_id' => 'gid.apps.example', 'social_google_client_secret' => '']);
check($kept['config']['google']['client_secret'] === 'gsecret', 'blank secret keeps the stored one');
check($config->fromSubmission(['social_apple_client_id' => 'a', 'social_apple_team_id' => 'ABCDE12345', 'social_apple_key_id' => 'KEYID12345', 'social_apple_private_key' => 'junk'])['config'] === null, 'non-EC apple key rejected');
foreach (['database/world.sql', 'database/account.sql'] as $file) {
    $contents = (string) file_get_contents($root . '/' . $file);
    if (preg_match('/CREATE TABLE[^;]*account_oauth_identity[^;]*;/is', $contents, $table) === 1) {
        check(!preg_match('/secret|private|token/i', $table[0]), 'identity table holds no credentials');
    }
}
// globalConfig.php is generated and gitignored, so assert on the generator that writes it.
$installer = (string) file_get_contents($root . '/game/include/Game/Installer/WorldInstaller.php');
$builderStart = strpos($installer, 'function buildGlobalConfig');
$builderEnd = strpos($installer, 'public static function buildConnection');
check($builderStart !== false && $builderEnd !== false && $builderEnd > $builderStart, 'global-config builder located');
check(!preg_match('/oauth|social_(google|facebook|apple)/i', substr($installer, $builderStart, $builderEnd - $builderStart)), 'no OAuth settings in globalConfig.php');

// --- Base64Url strictness -------------------------------------------------------------------------------
check(Base64Url::decode('QQ') === 'A' && Base64Url::decode('QR') === null && Base64Url::decode('QQ==') === null && Base64Url::decode('QUJD') === 'ABC' && Base64Url::decode('Q') === null, 'base64url is canonical (no non-zero trailing bits)');

// --- Changing the client id drops the stored secret -----------------------------------------------------
$changed = $config->fromSubmission(['social_google_client_id' => 'another-client', 'social_google_client_secret' => '']);
check($changed['config'] === null, 'changed google client id without a new secret is rejected');
$changed = $config->fromSubmission(['social_apple_client_id' => 'com.example.web', 'social_apple_team_id' => 'ZZZZZ12345', 'social_apple_key_id' => 'KEYID12345', 'social_apple_private_key' => '']);
check($changed['config'] === null, 'changed apple team id without a new key is rejected');
$same = $config->fromSubmission(['social_google_client_id' => 'gid.apps.example']);
check($same['config']['apple']['client_id'] === 'com.example.web' && $same['config']['google']['client_secret'] === 'gsecret', 'untouched providers and unchanged ids keep their secrets');

// --- JWT verification -----------------------------------------------------------------------
$http = new FakeHttp();
$http->routes['https://www.googleapis.com/oauth2/v3/certs'] = $jwks;
$store = new ArrayStore();
$verifier = new JwtVerifier(new JwksCache($http, $store, $clock), $clock);
$iss = ['https://accounts.google.com'];
$base = ['iss' => 'https://accounts.google.com', 'aud' => 'cid', 'sub' => 'sub-1', 'exp' => $now + 600, 'iat' => $now, 'nonce' => 'n0'];
$uri = 'https://www.googleapis.com/oauth2/v3/certs';
$rejects = static function (string $jwt, string $label) use ($verifier, $uri, $iss): void {
    try {
        $verifier->verify($jwt, $uri, $iss, 'cid', 'n0');
    } catch (Game\SocialAuth\TokenException) {
        return;
    }
    check(false, "must reject: {$label}");
};
check($verifier->verify(signJwt($key, 'k1', $base), $uri, $iss, 'cid', 'n0')['sub'] === 'sub-1', 'valid token verifies');
$rejects(signJwt($otherKey, 'k1', $base), 'bad signature');
$rejects(signJwt($key, 'k1', ['aud' => 'other'] + $base), 'wrong aud');
$rejects(signJwt($key, 'k1', ['aud' => ['x', 'cid']] + $base), 'aud list without azp');
$rejects(signJwt($key, 'k1', ['aud' => ['x', 'cid'], 'azp' => 'x'] + $base), 'aud list with foreign azp');
$rejects(signJwt($key, 'k1', ['azp' => 'x'] + $base), 'single aud with foreign azp');
check($verifier->verify(signJwt($key, 'k1', ['aud' => ['x', 'cid'], 'azp' => 'cid'] + $base), $uri, $iss, 'cid', 'n0')['sub'] === 'sub-1', 'aud list with matching azp verifies');
$rejects(signJwt($key, 'k1', ['nbf' => $now + 3600] + $base), 'not yet valid (nbf)');
check($verifier->verify(signJwt($key, 'k1', ['nbf' => $now + 30] + $base), $uri, $iss, 'cid', 'n0')['sub'] === 'sub-1', 'nbf within leeway verifies');
$rejects(signJwt($key, 'k1', $base, ['alg' => 'ES256']), 'ES256 header on an RSA key (alg confusion)');
$rejects(signJwt($key, 'k1', ['iss' => 'https://evil.example'] + $base), 'wrong iss');
$rejects(signJwt($key, 'k1', ['exp' => $now - 120] + $base), 'expired');
$rejects(signJwt($key, 'k1', ['nonce' => 'other'] + $base), 'nonce mismatch');
$rejects(signJwt($key, 'k1', ['nonce' => null] + $base), 'missing nonce');
$rejects(signJwt($key, 'k1', ['iat' => $now + 3600] + $base), 'issued in the future');
$rejects(signJwt($key, 'k1', $base, ['alg' => 'none']), 'alg none');
$rejects(signJwt($key, 'k1', $base, ['alg' => 'HS256']), 'alg HS256');
$rejects(signJwt($key, 'unknown', $base), 'unknown kid');
$rejects(substr(signJwt($key, 'k1', $base), 0, -4) . 'AAAA', 'tampered signature');
$rejects('a.b', 'malformed');
$before = $http->jwksFetches;
for ($i = 0; $i < 5; ++$i) {
    $rejects(signJwt($key, 'unknown-' . $i % 2, $base), 'repeated unknown kid');
}
check($http->jwksFetches - $before <= 2, 'unknown kids are negative-cached');
$fetches = $http->jwksFetches;
$verifier->verify(signJwt($key, 'k1', $base), $uri, $iss, 'cid', 'n0');
check($http->jwksFetches === $fetches, 'JWKS served from cache');
check($fetches <= 2, 'unknown kid refetch is rate limited');

// --- Apple client secret (ES256) ----------------------------------------------------------------
$secret = AppleClientSecret::build('ABCDE12345', 'com.example.web', 'KEYID12345', $p8, $now);
[$h, $c, $s] = explode('.', $secret);
$headerJson = json_decode((string) Base64Url::decode($h), true);
$claimsJson = json_decode((string) Base64Url::decode($c), true);
check($headerJson['alg'] === 'ES256' && $headerJson['kid'] === 'KEYID12345', 'apple secret header');
check($claimsJson['iss'] === 'ABCDE12345' && $claimsJson['sub'] === 'com.example.web' && $claimsJson['aud'] === 'https://appleid.apple.com' && $claimsJson['exp'] - $now <= 300, 'apple secret claims');
$raw = (string) Base64Url::decode($s);
check(strlen($raw) === 64, 'raw r||s signature is 64 bytes');
$der = static function (string $r, string $sPart): string {
    $int = static function (string $v): string {
        $v = ltrim($v, "\0");
        if ($v === '' || (ord($v[0]) & 0x80)) {
            $v = "\0" . $v;
        }

        return "\x02" . chr(strlen($v)) . $v;
    };
    $body = $int($r) . $int($sPart);

    return "\x30" . chr(strlen($body)) . $body;
};
check(openssl_verify($h . '.' . $c, $der(substr($raw, 0, 32), substr($raw, 32)), openssl_pkey_get_public(openssl_pkey_get_details($ec)["key"]), OPENSSL_ALGO_SHA256) === 1, "apple secret signature verifies");

// --- Flow ---------------------------------------------------------------------------------------------
$config->save(['google' => ['client_id' => 'cid', 'client_secret' => 'gsec'],
    'facebook' => ['client_id' => '999', 'client_secret' => 'fbsec'],
    'apple' => ['client_id' => 'com.example.web', 'team_id' => 'ABCDE12345', 'key_id' => 'KEYID12345', 'private_key' => $p8]]);

$build = static function () use ($config, $clock, $key, $jwks): array {
    $http = new FakeHttp();
    $http->routes['https://www.googleapis.com/oauth2/v3/certs'] = $jwks;
    $http->routes['https://appleid.apple.com/auth/keys'] = $jwks;
    $store = new ArrayStore();
    $ids = new FakeIdentities();
    $flow = new OAuthFlow($config, $http, $store, new JwtVerifier(new JwksCache($http, $store, $clock), $clock), $ids, 'https://x.test', $clock);

    return [$flow, $http, $store, $ids];
};
$tokenResponse = static fn (string $idToken): HttpResponse => new HttpResponse(200, (string) json_encode(['id_token' => $idToken, 'access_token' => 'at']));
$nonceOf = static fn (ArrayStore $store): string => (string) array_values($store->data)[0]['nonce'];
$stateOf = static function (string $url): string {
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return (string) $query['state'];
};

// Google happy path with PKCE, new identity
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Google, 'de', 'wimpel_7');
check($begin !== null && str_starts_with($begin['url'], 'https://accounts.google.com/o/oauth2/v2/auth?'), 'google authorize URL');
parse_str((string) parse_url($begin['url'], PHP_URL_QUERY), $q);
check($q['code_challenge_method'] === 'S256' && $q['redirect_uri'] === 'https://x.test/oauth/google/callback' && !isset($q['redirect_to']), 'PKCE and fixed redirect URI');
$http->routes['https://oauth2.googleapis.com/token'] = $tokenResponse(signJwt($key, 'k1', ['nonce' => $nonceOf($store), 'email' => 'New@Example.com', 'email_verified' => true] + $base));
$result = $flow->complete(Provider::Google, ['code' => 'abc', 'state' => $begin['state']], $begin['state']);
check($result->kind === 'signup' && $result->claims?->email === 'new@example.com' && $result->locale === 'de' && $result->referral === 'wimpel_7', 'new google identity -> signup with locale and referral');
check(isset($http->posts[0]['fields']['code_verifier']) && $http->posts[0]['fields']['client_secret'] === 'gsec', 'verifier and secret sent server-side');
$replay = $flow->complete(Provider::Google, ['code' => 'abc', 'state' => $begin['state']], $begin['state']);
check($replay->error === FlowError::InvalidState, 'replayed state rejected');

// state / binding failures
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Google, null, '');
check($flow->complete(Provider::Google, ['code' => 'c', 'state' => str_repeat('a', 64)], 'x')->error === FlowError::InvalidState, 'unknown state');
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Google, null, '');
check($flow->complete(Provider::Google, ['code' => 'c', 'state' => $begin['state']], null)->error === FlowError::InvalidState, 'google requires session-bound state');
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Google, null, '');
check($flow->complete(Provider::Apple, ['code' => 'c', 'state' => $begin['state']], null)->error === FlowError::InvalidState, 'state from another provider rejected');
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Google, null, '');
check($flow->complete(Provider::Google, ['error' => 'access_denied', 'state' => $begin['state']], $begin['state'])->error === FlowError::Denied, 'denied consent');
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Google, null, '');
$http->routes['https://oauth2.googleapis.com/token'] = $tokenResponse(signJwt($key, 'k1', ['nonce' => 'forged'] + $base));
check($flow->complete(Provider::Google, ['code' => 'c', 'state' => $begin['state']], $begin['state'])->error === FlowError::InvalidToken, 'nonce mismatch through the flow');
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Google, null, '');
$http->routes['https://oauth2.googleapis.com/token'] = new HttpResponse(400, '{"error":"invalid_grant"}');
check($flow->complete(Provider::Google, ['code' => 'c', 'state' => $begin['state']], $begin['state'])->error === FlowError::InvalidToken, 'provider rejects the code');

// existing identity logs in; email collision never links
[$flow, $http, $store, $ids] = $build();
$ids->linked['google:sub-1'] = 42;
$begin = $flow->begin(Provider::Google, null, '');
$http->routes['https://oauth2.googleapis.com/token'] = $tokenResponse(signJwt($key, 'k1', ['nonce' => $nonceOf($store)] + $base));
$result = $flow->complete(Provider::Google, ['code' => 'c', 'state' => $begin['state']], $begin['state']);
check($result->kind === 'login' && $result->userId === 42, 'known identity logs in');
[$flow, $http, $store, $ids] = $build();
$ids->emails[] = 'taken@example.com';
$begin = $flow->begin(Provider::Google, null, '');
$http->routes['https://oauth2.googleapis.com/token'] = $tokenResponse(signJwt($key, 'k1', ['nonce' => $nonceOf($store), 'email' => 'taken@example.com', 'email_verified' => true] + $base));
$result = $flow->complete(Provider::Google, ['code' => 'c', 'state' => $begin['state']], $begin['state']);
check($result->error === FlowError::EmailInUse && $ids->linked === [], 'email collision is refused, never linked');
check($result->error->translationKey() === ['EVerify', 'errors.emailAlreadyExists'], 'email-in-use maps to an existing key');
check(FlowError::RateLimited->translationKey() === ['Login', 'tooManyRequests'] && FlowError::Denied->translationKey() === ['Support', 'errors.Invalid request'], 'error copy maps to existing keys');

// linking from Options: explicit, to the player who asked, never by email
$linkFlow = static function (?int $linkUid, callable $arrange) use ($build, $tokenResponse, $nonceOf, $key, $base): array {
    [$flow, $http, $store, $ids] = $build();
    $arrange($ids);
    $begin = $flow->begin(Provider::Google, null, '', $linkUid);
    $http->routes['https://oauth2.googleapis.com/token'] = $tokenResponse(signJwt($key, 'k1', ['nonce' => $nonceOf($store), 'email' => 'taken@example.com', 'email_verified' => false] + $base));

    return [$flow->complete(Provider::Google, ['code' => 'c', 'state' => $begin['state']], $begin['state']), $ids];
};
[$result, $ids] = $linkFlow(7, static function (FakeIdentities $ids): void {
    $ids->emails[] = 'taken@example.com';
});
check($result->kind === 'link' && $result->userId === 7 && $result->linkUserId === 7 && $result->claims?->subject === 'sub-1' && $ids->linked === [], 'a link flow returns the identity for the player who asked, whatever the email says, and links nothing itself');
[$result] = $linkFlow(7, static function (FakeIdentities $ids): void {
    $ids->linked['google:sub-1'] = 9;
});
check($result->error === FlowError::LinkedElsewhere && $result->linkUserId === 7, 'an identity of another player is refused');
[$result] = $linkFlow(7, static function (FakeIdentities $ids): void {
    $ids->providers['7:google'] = true;
});
check($result->error === FlowError::ProviderLinked && $result->linkUserId === 7, 'a second identity from one provider is refused');
[$result] = $linkFlow(7, static function (FakeIdentities $ids): void {
    $ids->linked['google:sub-1'] = 7;
    $ids->providers['7:google'] = true;
});
check($result->kind === 'link', 'linking the identity the player already has is a no-op success');
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Google, null, '', 7);
$result = $flow->complete(Provider::Google, ['error' => 'access_denied', 'state' => $begin['state']], $begin['state']);
check($result->error === FlowError::Denied && $result->linkUserId === 7, 'a refused consent still belongs to the link flow');
[$result] = $linkFlow(null, static function (FakeIdentities $ids): void {
    $ids->linked['google:sub-1'] = 5;
});
check($result->kind === 'login' && $result->linkUserId === null, 'sign-in flows carry no link player');
check(FlowError::LinkedElsewhere->translationKey() === ['Login', 'This %s account is already linked to another player.'], 'linked-elsewhere copy is the approved Login key');

// Apple: form_post, no session cookie, private relay email, name from POST user
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Apple, null, '');
parse_str((string) parse_url($begin['url'], PHP_URL_QUERY), $q);
check($q['response_mode'] === 'form_post' && !isset($q['code_challenge']), 'apple form_post authorize URL');
$appleBase = ['iss' => 'https://appleid.apple.com', 'aud' => 'com.example.web', 'sub' => 'apple-1', 'exp' => $now + 600, 'iat' => $now];
$http->routes['https://appleid.apple.com/auth/token'] = $tokenResponse(signJwt($key, 'k1', ['nonce' => $nonceOf($store), 'email' => 'abc@privaterelay.appleid.com', 'email_verified' => 'true'] + $appleBase));
check(is_string($begin['binding']) && strlen($begin['binding']) === 64 && !str_contains($begin['url'], $begin['binding']), 'apple binding secret is cookie-only');
check(array_values($store->data)[0]['binding_hash'] === hash('sha256', $begin['binding']) && !in_array($begin['binding'], array_values($store->data)[0], true), 'only the binding hash is stored');
check($flow->begin(Provider::Google, null, '')['binding'] === null, 'no binding cookie for non-Apple providers');
$result = $flow->complete(Provider::Apple, ['code' => 'c', 'state' => $begin['state'], 'user' => '{"name":{"firstName":"Ada","lastName":"L"}}'], null, $begin['binding']);
check($result->kind === 'signup' && $result->claims?->email === 'abc@privaterelay.appleid.com' && $result->claims->emailVerified && $result->claims->name === 'Ada L', 'apple private relay email accepted');
check(substr_count($http->posts[0]['fields']['client_secret'], '.') === 2, 'apple client secret is a JWT');
foreach ([null, '', str_repeat('b', 64)] as $wrongCookie) {
    [$flow, $http, $store, $ids] = $build();
    $begin = $flow->begin(Provider::Apple, null, '');
    $http->routes['https://appleid.apple.com/auth/token'] = $tokenResponse(signJwt($key, 'k1', ['nonce' => $nonceOf($store), 'email' => 'a@example.com', 'email_verified' => 'true'] + $appleBase));
    check($flow->complete(Provider::Apple, ['code' => 'c', 'state' => $begin['state']], null, $wrongCookie)->error === FlowError::InvalidState, 'apple callback without/with wrong binding cookie fails');
    check($http->posts === [], 'no token exchange happens before the binding check passes');
}
[$flow, $http, $store, $ids] = $build();
$begin = $flow->begin(Provider::Apple, null, '');
check($flow->complete(Provider::Apple, ['code' => 'c', 'state' => $begin['state']], $begin['state'], null)->error === FlowError::InvalidState, 'a matching session state does not substitute for the apple cookie');

// M2: email verification is mandatory for a new identity ("true"/"false" strings and bools)
foreach ([[false, FlowError::EmailMissing], ['false', FlowError::EmailMissing], [null, FlowError::EmailMissing], ['true', null], [true, null]] as [$verifiedClaim, $expectedError]) {
    [$flow, $http, $store, $ids] = $build();
    $begin = $flow->begin(Provider::Apple, null, '');
    $http->routes['https://appleid.apple.com/auth/token'] = $tokenResponse(signJwt($key, 'k1', ['nonce' => $nonceOf($store), 'email' => 'v@example.com', 'email_verified' => $verifiedClaim] + $appleBase));
    $result = $flow->complete(Provider::Apple, ['code' => 'c', 'state' => $begin['state']], null, $begin['binding']);
    check($result->error === $expectedError && ($expectedError !== null || $result->kind === 'signup'), 'email_verified=' . var_export($verifiedClaim, true));
}

// Facebook: debug_token + /me with appsecret_proof
$facebook = static function (string $appId, string $userId) use ($build): FlowResult {
    [$flow, $http] = $build();
    $begin = $flow->begin(Provider::Facebook, null, '');
    $http->routes['https://graph.facebook.com/v19.0/oauth/access_token'] = new HttpResponse(200, '{"access_token":"fbtoken"}');
    $http->routes['https://graph.facebook.com/v19.0/debug_token'] = new HttpResponse(200, (string) json_encode(['data' => ['is_valid' => true, 'app_id' => $appId, 'user_id' => $userId]]));
    $http->routes['https://graph.facebook.com/v19.0/me'] = new HttpResponse(200, '{"id":"77","name":"Fb User","email":"fb@example.com"}');

    return $flow->complete(Provider::Facebook, ['code' => 'c', 'state' => $begin['state']], $begin['state']);
};
$result = $facebook('999', '77');
check($result->kind === 'signup' && $result->claims?->subject === '77', 'facebook happy path');
check($facebook('111', '77')->error === FlowError::InvalidToken, 'facebook token for another app rejected');
check($facebook('999', '78')->error === FlowError::InvalidToken, 'facebook profile/user mismatch rejected');

// --- Pending signup ---------------------------------------------------------------------------------------
$session = [];
$pending = new PendingSignup($session, 'k', $clock);
check($pending->current() === null, 'no pending signup initially');
$claims = new IdentityClaims(Provider::Google, 'sub-9', 'p@example.com', true, 'P');
$pending->store($claims);
$pending->bindActivationToken(str_repeat('a', 64));
check($pending->current()?->subject === 'sub-9', 'pending signup readable');
$ids = new FakeIdentities();
check($pending->isBoundTo(str_repeat('a', 64)) && !$pending->isBoundTo(str_repeat('b', 64)) && !$pending->isBoundTo(''), 'isBoundTo matches only the bound activation token');
check(!$pending->finalize(5, str_repeat('b', 64), $ids) && $ids->linked === [], 'finalize requires the bound activation token');
$ids->userEmails[5] = 'other@example.com';
check(!$pending->finalize(5, str_repeat('a', 64), $ids) && $ids->linked === [], 'finalize refuses an account whose email differs from the verified claim');
$pending->store($claims);
$pending->bindActivationToken(str_repeat('a', 64));
$ids->userEmails[5] = 'P@Example.com';
check($pending->finalize(5, str_repeat('a', 64), $ids) && $ids->linked['google:sub-9'] === 5 && $pending->current() === null, 'finalize links once and clears');
$late = new PendingSignup($session, 'k', static fn (): int => $now + 99999);
$pending->store($claims);
check($late->current() === null, 'pending signup expires');

// --- Wiring guards (phase B) -----------------------------------------------------------------------------
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$mainInclude = $read('game/include/mainInclude.php');
check(str_contains($mainInclude, '#^/oauth/(google|facebook|apple)/(start|callback|link|unlink)$#D') && str_contains($mainInclude, "'oauth_callback' => 'OAuthCallbackCtrl'"), 'oauth routes are wired');
$homepage = $read('game/include/Controller/HomepageCtrl.php');
check(str_contains($homepage, 'SocialProviderList::build(') && !str_contains($homepage, "\$_GET['socialError']"), 'homepage supplies socialProviders and has no ?socialError= path');
$activate = $read('game/include/Controller/ActivateCtrl.php');
check(str_contains($activate, 'PendingSignup') && str_contains($activate, '->finalize('), 'activation finalizes the social identity');
check(str_contains($read('game/include/Model/InactiveModel.php'), 'DELETE FROM account_oauth_identity'), 'account deletion removes identities');
check(preg_match('/CREATE TABLE[^;]*account_oauth_identity[^;]*UNIQUE KEY[^;]*`provider`, `subject`[^;]*UNIQUE KEY `uid_provider` \\(`uid`, `provider`\\)/is', $read('database/world.sql')) === 1, 'identity table schema: one identity per provider and account');
check(!str_contains($read('game/include/resources/Templates/system/registrationDialog.php'), 'disabled aria-label'), 'no disabled stub buttons');
foreach (['social-google.svg', 'social-apple.svg'] as $icon) {
    check(is_file($root . "/assets/gpack/travian/mainPage/img_common/homepage/{$icon}"), "img_common/homepage/{$icon} present (identical in both directions)");
}

// --- Static guards --------------------------------------------------------------------------------------------
foreach (glob($root . '/game/include/Game/SocialAuth/*.php') ?: [] as $file) {
    $source = (string) file_get_contents($file);
    check(str_contains($source, 'declare(strict_types=1);'), basename($file) . ' declares strict types');
    check(!preg_match('/error_log\s*\(|var_dump|print_r/', $source), basename($file) . ' does not log or dump');
}
$start = $read('game/include/Controller/OAuthStartCtrl.php');
check(str_contains($start, '!WebService::isPost()') && str_contains($start, "\$_POST[Session::getCheckerName()]") && !str_contains($start, "\$_GET[Session::getCheckerName()]"), 'start is POST-only and reads the CSRF token from the body');
check(str_contains($read('game/include/mainInclude.php'), "'/oauth/cancel'") && str_contains($read('game/include/Controller/OAuthCancelCtrl.php'), 'matchesChecker'), 'cancel route is CSRF-checked');
$dialog = $read('game/include/resources/Templates/system/registrationDialog.php');
check(str_contains($dialog, 'method="post" action="<?= $escape($provider[\'url\']); ?>"') && str_contains($dialog, 'type="submit" form="socialStart_') && !str_contains($dialog, 'SignInButton" href='), 'providers render as POST form buttons');
foreach (['OAuthLinkCtrl', 'OAuthUnlinkCtrl'] as $controller) {
    $source = $read("game/include/Controller/{$controller}.php");
    check(str_contains($source, 'extends GameCtrl') && str_contains($source, '!WebService::isPost()') && str_contains($source, 'Session::matchesChecker') && str_contains($source, 'isSitter()'), "{$controller} is a signed-in, POST-only, CSRF-checked, sitter-refusing route");
}
check(str_contains($read('game/include/Controller/OAuthUnlinkCtrl.php'), 'Password::verify') && str_contains($read('game/include/Controller/OAuthUnlinkCtrl.php'), 'throttle_public_form'), 'unlink asks for the current password and is throttled');
$routes = $read('game/include/mainInclude.php');
check(str_contains($routes, '(start|callback|link|unlink)') && str_contains($routes, "'oauth_link' => 'OAuthLinkCtrl'") && str_contains($routes, "'oauth_unlink' => 'OAuthUnlinkCtrl'"), 'link and unlink routes exist');
foreach (['Social accounts', 'Link', 'Unlink', 'Not linked', 'This %s account is already linked to another player.', 'Your %s account is now linked.', 'Your %s account has been unlinked.'] as $text) {
    check(str_contains($read('game/include/resources/Translation/en-US.php'), "\$Definition['Login']['{$text}'] = '{$text}';"), "approved Options copy: {$text}");
}
foreach (['OAuthStartCtrl', 'OAuthCallbackCtrl', 'OAuthCancelCtrl'] as $controller) {
    $source = (string) file_get_contents($root . "/game/include/Controller/{$controller}.php");
    check(!str_contains($source, 'redirect_to') && !str_contains($source, "\$_GET['next']"), "{$controller} has no return-URL parameter");
}

$en = $read('game/include/resources/Translation/en-US.php');
foreach (['Sign in with Google', 'Continue with Facebook', 'Sign in with Apple'] as $label) {
    check(str_contains($en, "\$Definition['Login']['{$label}'] = '{$label}';"), "canonical Login key: {$label}");
    check(str_contains($dialog, "T('Login', '{$label}')"), "dialog uses the canonical key: {$label}");
}

echo "Social sign-in contract OK\n";
