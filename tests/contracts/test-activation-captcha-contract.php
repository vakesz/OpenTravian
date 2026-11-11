<?php

declare(strict_types=1);

// The activation CAPTCHA is optional: no keys means no widget, no script and no server check; keys
// live only in a protected state file; reCAPTCHA v3 tokens are verified before a village exists.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

foreach (['Payment/HttpTransport', 'Payment/CurlTransport', 'SocialAuth/HttpResponse', 'Captcha/CaptchaProvider', 'Captcha/RecaptchaProvider', 'Captcha/CaptchaConfig', 'Captcha/ActivationCaptcha'] as $class) {
    require_once $root . '/game/include/Game/' . $class . '.php';
}

use Game\Captcha\ActivationCaptcha;
use Game\Captcha\CaptchaConfig;
use Game\Captcha\RecaptchaProvider;
use Game\Payment\HttpTransport;
use Game\SocialAuth\HttpResponse;

// ---- configuration: protected file, write-only secret -------------------------------------
$directory = sys_get_temp_dir() . '/travian-captcha-' . bin2hex(random_bytes(4));
mkdir($directory, 0700);
$path = $directory . '/captcha.local.php';
$config = new CaptchaConfig($path);
($config->provider() === null && $config->formValues() === ['site_key' => '', 'configured' => false]) || $fail('Without a state file there must be no provider.');
$parsed = $config->fromSubmission(['captcha_site_key' => 'site-key', 'captcha_secret_key' => '']);
($parsed['keys'] === null && $parsed['errors'] !== []) || $fail('A site key without a secret must be rejected.');
$parsed = $config->fromSubmission(['captcha_site_key' => 'site-key', 'captcha_secret_key' => 'secret-key']);
($parsed['errors'] === [] && $parsed['keys'] === ['site_key' => 'site-key', 'secret_key' => 'secret-key']) || $fail('A complete key pair must be accepted.');
$config->save($parsed['keys']) || $fail('Saving the keys must succeed.');
(decoct(fileperms($path) & 0777) === '600') || $fail('The key file must be mode 0600.');
($config->provider() instanceof RecaptchaProvider) || $fail('Stored keys must yield a reCAPTCHA provider.');
(!in_array('secret-key', $config->formValues(), true) && $config->formValues()['configured'] === true) || $fail('The secret must never be returned to the form.');
$kept = $config->fromSubmission(['captcha_site_key' => 'site-key', 'captcha_secret_key' => '']);
($kept['keys']['secret_key'] ?? '') === 'secret-key' || $fail('A blank secret must keep the stored one.');
$removed = $config->fromSubmission(['captcha_remove' => '1']);
($removed['remove'] === true && $config->save(null) && $config->provider() === null && !is_file($path)) || $fail('Removing the keys must delete the file and turn the CAPTCHA off.');
rmdir($directory);

// ---- reCAPTCHA verification ----------------------------------------------------------------
$transport = static function (int $status, string $body, bool $throws = false): HttpTransport {
    return new class ($status, $body, $throws) implements HttpTransport {
        public ?string $lastBody = null;

        public function __construct(private int $status, private string $body, private bool $throws)
        {
        }

        public function send(string $method, string $url, array $headers, ?string $body): HttpResponse
        {
            $this->lastBody = $body;
            if ($this->throws) {
                throw new RuntimeException('down');
            }
            if (!str_starts_with($url, 'https://www.recaptcha.net/recaptcha/api/siteverify') || $method !== 'POST') {
                throw new RuntimeException('unexpected request');
            }

            return new HttpResponse($this->status, $this->body);
        }
    };
};
$good = new RecaptchaProvider('site', 'secret', $transport(200, '{"success":true,"action":"activation","score":0.9}'));
$good->verify('token', 'activation', '203.0.113.7') || $fail('A high-score token for the activation action must pass.');
(!$good->verify('', 'activation', '203.0.113.7')) || $fail('An empty token must fail.');
$cases = [
    'wrong action' => $transport(200, '{"success":true,"action":"login","score":0.9}'),
    'low score' => $transport(200, '{"success":true,"action":"activation","score":0.1}'),
    'rejected' => $transport(200, '{"success":false}'),
    'http error' => $transport(500, '{"success":true,"action":"activation","score":0.9}'),
    'unreachable' => $transport(200, '', true),
];
foreach ($cases as $label => $http) {
    (!(new RecaptchaProvider('site', 'secret', $http))->verify('token', 'activation', '203.0.113.7')) || $fail("A {$label} response must fail closed.");
}
str_contains($good->scriptUrl(), 'https://recaptcha.net/recaptcha/api.js?render=site') || $fail('The script URL must carry the site key.');
str_contains($good->tokenExpression('activation'), 'grecaptcha.execute("site"') || $fail('The token expression must run reCAPTCHA v3.');
ActivationCaptcha::accepts(null, null, '') || $fail('Without a provider every submission is accepted.');
(!ActivationCaptcha::accepts($good, null, '203.0.113.7')) || $fail('With a provider a missing token must fail.');

// ---- wiring: no script without keys, server check before the village -----------------------
$wizard = $read('game/include/resources/Templates/activation/wizard.php');
(substr_count($wizard, '<script src=') === 1 && str_contains($wizard, "<?php if (\$captcha !== null): ?>\n<script src=")) || $fail('The wizard may load a script only inside the configured-provider branch.');
(!str_contains($wizard, 'recaptcha') && !str_contains($wizard, 'google')) || $fail('The wizard template must stay provider-neutral.');
foreach (['Controller/ActivateCtrl.php', 'Controller/RespawnCtrl.php'] as $controller) {
    $source = $read('game/include/' . $controller);
    (str_contains($source, 'ActivationCaptcha::provider()') && str_contains($source, 'ActivationCaptcha::accepts(')) || $fail("{$controller} must check the optional CAPTCHA.");
}
$activate = $read('game/include/Controller/ActivateCtrl.php');
(strpos($activate, 'ActivationCaptcha::accepts(') < strpos($activate, 'return $this->completeRegistration(')) || $fail('The CAPTCHA must be checked before the village is created.');
str_contains($read('game/include/Game/Admin/AdminMenu.php'), "admin.php?action=captcha") || $fail('The admin menu must link the CAPTCHA page.');
$admin = $read('game/include/admin/include/Controllers/CaptchaCtrl.php');
(str_contains($admin, 'AdminUi::secret(') && !str_contains($admin, "['secret_key']") && !str_contains($admin, 'GlobalDB')) || $fail('The admin page must keep the secret write-only and out of SQL.');

echo "Activation CAPTCHA contract passed.\n";
