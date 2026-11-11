<?php

declare(strict_types=1);

namespace Game\Captcha;

/**
 * Operator CAPTCHA settings for the activation wizard. Like SMTP, social
 * sign-in and payments, the keys are a deployment secret kept only in a 0600
 * PHP state file in the generated state directory (captcha.local.php), never
 * in SQL, globalConfig.php, forms, logs or the repository. Without a complete
 * key pair there is no provider: the wizard shows no CAPTCHA and loads no script.
 */
final class CaptchaConfig
{
    private const MAX_VALUE_LENGTH = 256;

    public function __construct(private readonly string $path)
    {
    }

    public static function fromEnvironment(): self
    {
        $override = getenv('TRAVIAN_CAPTCHA_CONFIG');
        $state = getenv('TRAVIAN_STATE_DIR');
        if (is_string($override) && $override !== '') {
            return new self($override);
        }
        if (defined('STATE_DIR')) {
            return new self(rtrim((string) constant('STATE_DIR'), '/') . '/captcha.local.php');
        }

        return new self(is_string($state) && $state !== '' ? rtrim($state, '/') . '/captcha.local.php' : '');
    }

    public function path(): string
    {
        return $this->path;
    }

    /** The configured provider, or null when no complete key pair is stored. */
    public function provider(): ?CaptchaProvider
    {
        $keys = $this->keys();

        return $keys === null ? null : new RecaptchaProvider($keys['site_key'], $keys['secret_key']);
    }

    /** @return array{site_key:string,configured:bool} the secret never leaves the state file */
    public function formValues(): array
    {
        $keys = $this->keys();

        return ['site_key' => $keys['site_key'] ?? '', 'configured' => $keys !== null];
    }

    /**
     * Parses the admin form. "remove" clears the keys; a blank secret keeps the
     * stored one; the other combinations must provide both values.
     *
     * @param array<string,mixed> $submitted
     * @return array{keys:array{site_key:string,secret_key:string}|null,remove:bool,errors:list<string>}
     */
    public function fromSubmission(array $submitted): array
    {
        if (($submitted['captcha_remove'] ?? '') === '1') {
            return ['keys' => null, 'remove' => true, 'errors' => []];
        }
        $site = is_string($submitted['captcha_site_key'] ?? null) ? trim($submitted['captcha_site_key']) : '';
        $secret = is_string($submitted['captcha_secret_key'] ?? null) ? trim($submitted['captcha_secret_key']) : '';
        $existing = $this->keys();
        if ($secret === '' && $existing !== null && ($site === '' || $site === $existing['site_key'])) {
            $secret = $existing['secret_key'];
            $site = $existing['site_key'];
        }
        if ($site === '' && $secret === '') {
            return ['keys' => null, 'remove' => false, 'errors' => []];
        }
        $errors = [];
        foreach (['Site key' => $site, 'Secret key' => $secret] as $label => $value) {
            if ($value === '') {
                $errors[] = $label . ' is required.';
            } elseif (strlen($value) > self::MAX_VALUE_LENGTH || preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1) {
                $errors[] = $label . ' is not valid.';
            }
        }

        return ['keys' => $errors === [] ? ['site_key' => $site, 'secret_key' => $secret] : null, 'remove' => false, 'errors' => $errors];
    }

    /** @param array{site_key:string,secret_key:string}|null $keys null removes the stored keys */
    public function save(?array $keys): bool
    {
        $directory = $this->path === '' ? '' : dirname($this->path);
        if ($directory === '' || !is_dir($directory) || !is_writable($directory) || is_link($this->path)) {
            return false;
        }
        if ($keys === null) {
            return !is_file($this->path) || unlink($this->path);
        }
        $temporary = tempnam($directory, '.travian-captcha-');
        if ($temporary === false) {
            return false;
        }
        $contents = "<?php\n\n/** Generated deployment-only CAPTCHA keys. */\n\$captchaConfig = "
            . var_export(['provider' => 'recaptcha', 'site_key' => $keys['site_key'], 'secret_key' => $keys['secret_key']], true) . ";\n";
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0600)
            || !rename($temporary, $this->path) || !chmod($this->path, 0600)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            return false;
        }

        return true;
    }

    /** @return array{site_key:string,secret_key:string}|null */
    private function keys(): ?array
    {
        if ($this->path === '' || !is_file($this->path) || is_link($this->path)) {
            return null;
        }
        try {
            $config = (static function (string $file): mixed {
                $captchaConfig = null;
                require $file;
                /** @var mixed $captchaConfig */

                return $captchaConfig;
            })($this->path);
        } catch (\Throwable) {
            return null;
        }
        $site = is_array($config) ? ($config['site_key'] ?? null) : null;
        $secret = is_array($config) ? ($config['secret_key'] ?? null) : null;

        return is_string($site) && $site !== '' && is_string($secret) && $secret !== ''
            ? ['site_key' => $site, 'secret_key' => $secret]
            : null;
    }
}
