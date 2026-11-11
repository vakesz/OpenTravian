<?php

declare(strict_types=1);

namespace Game;

/**
 * SMTP settings are deployment secrets, not world data. They are stored in a
 * separate 0600 PHP state file so the password is never written to SQL,
 * generated public configuration, logs, or the admin form.
 */
final class SmtpConfig
{
    public const MAX_HOST_LENGTH = 253;
    public const MAX_USERNAME_LENGTH = 254;
    public const MAX_PASSWORD_LENGTH = 1024;

    /** @var list<string> */
    public const ENCRYPTIONS = ['starttls', 'smtps'];

    private function __construct()
    {
    }

    /**
     * @return array{host:string,port:int,encryption:string,from:string,auth:bool,username:string,password:string}|null
     */
    public static function current(): ?array
    {
        $path = self::configFile();
        if ($path === '' || !is_file($path) || is_link($path)) {
            return null;
        }

        try {
            $config = (static function (string $file): mixed {
                $smtpConfig = null;
                require $file;
                /** @var mixed $smtpConfig */

                return $smtpConfig;
            })($path);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($config) || self::validationErrors($config) !== []) {
            return null;
        }

        return self::canonical($config);
    }

    public static function isConfigured(): bool
    {
        return self::current() !== null;
    }

    /**
     * Parses setup/admin form fields without ever returning a password in the
     * UI data. With $preservePassword, a blank password means keep the current
     * one only when SMTP authentication remains enabled.
     *
     * @param array<string,mixed> $submitted
     * @param array{host:string,port:int,encryption:string,from:string,auth:bool,username:string,password:string}|null $existing
     * @return array{config:array{host:string,port:int,encryption:string,from:string,auth:bool,username:string,password:string}|null,errors:list<string>}
     */
    public static function fromSubmission(array $submitted, ?array $existing = null, bool $preservePassword = false): array
    {
        $host = trim(self::submittedString($submitted, 'smtp_host'));
        $portValue = trim(self::submittedString($submitted, 'smtp_port'));
        $encryption = trim(self::submittedString($submitted, 'smtp_encryption'));
        $from = SupportContact::normalize(self::submittedString($submitted, 'smtp_from'));
        $authValue = self::submittedString($submitted, 'smtp_auth');
        $username = self::submittedString($submitted, 'smtp_username');
        $password = self::submittedString($submitted, 'smtp_password');
        $errors = [];

        if (!self::isValidHost($host)) {
            $errors[] = 'Enter a valid SMTP host name or IP address.';
        }
        if (!preg_match('/^[0-9]{1,5}$/', $portValue) || (int) $portValue < 1 || (int) $portValue > 65535) {
            $errors[] = 'Enter an SMTP port from 1 to 65535.';
        }
        if (!in_array($encryption, self::ENCRYPTIONS, true)) {
            $errors[] = 'Choose STARTTLS or SMTPS for SMTP encryption.';
        }
        if (!SupportContact::isValid($from)) {
            $errors[] = 'Enter a valid SMTP sender email address.';
        }
        if (!in_array($authValue, ['0', '1'], true)) {
            $errors[] = 'Choose whether the SMTP server requires authentication.';
        }

        $auth = $authValue === '1';
        if (!$auth) {
            $username = '';
            $password = '';
        } elseif ($preservePassword && $password === '' && $existing !== null) {
            $password = (string) $existing['password'];
        }

        $config = [
            'host' => $host,
            'port' => (int) $portValue,
            'encryption' => $encryption,
            'from' => $from,
            'auth' => $auth,
            'username' => $username,
            'password' => $password,
        ];
        foreach (self::validationErrors($config) as $error) {
            if (!in_array($error, $errors, true)) {
                $errors[] = $error;
            }
        }

        return ['config' => $errors === [] ? $config : null, 'errors' => $errors];
    }

    /**
     * Values suitable for a form. The password intentionally never leaves the
     * 0600 state file.
     *
     * @param array{host:string,port:int,encryption:string,from:string,auth:bool,username:string,password:string}|null $config
     * @return array{host:string,port:string,encryption:string,from:string,auth:string,username:string,passwordConfigured:bool}
     */
    public static function formValues(?array $config = null): array
    {
        return [
            'host' => (string) ($config['host'] ?? ''),
            'port' => (string) ($config['port'] ?? 587),
            'encryption' => (string) ($config['encryption'] ?? 'starttls'),
            'from' => (string) ($config['from'] ?? ''),
            'auth' => !empty($config['auth']) ? '1' : '0',
            'username' => (string) ($config['username'] ?? ''),
            'passwordConfigured' => isset($config['password']) && $config['password'] !== '',
        ];
    }

    /**
     * Atomically publishes a complete, validated SMTP configuration with mode
     * 0600. Callers must never include this array in diagnostics.
     *
     * @param array{host:string,port:int,encryption:string,from:string,auth:bool,username:string,password:string} $config
     */
    public static function save(array $config): bool
    {
        if (self::validationErrors($config) !== []) {
            return false;
        }

        $path = self::configFile();
        $directory = $path === '' ? '' : dirname($path);
        if ($directory === '' || !is_dir($directory) || !is_writable($directory) || is_link($path)) {
            return false;
        }

        $lockPath = $path . '.lock';
        if (is_link($lockPath)) {
            return false;
        }
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            return false;
        }

        $temporary = null;
        try {
            if (!chmod($lockPath, 0600) || !flock($lock, LOCK_EX)) {
                return false;
            }
            $temporary = tempnam($directory, '.travian-smtp-');
            if ($temporary === false) {
                return false;
            }
            $contents = "<?php\n\n/** Generated deployment-only SMTP configuration. */\n\$smtpConfig = "
                . var_export(self::canonical($config), true) . ";\n";
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0600)) {
                return false;
            }
            if (!rename($temporary, $path) || !chmod($path, 0600)) {
                return false;
            }
            $temporary = null;

            return true;
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function configFile(): string
    {
        if (!defined('SMTP_CONFIG_FILE')) {
            return '';
        }

        return (string) constant('SMTP_CONFIG_FILE');
    }

    /** @param array<string,mixed> $submitted */
    private static function submittedString(array $submitted, string $key): string
    {
        return is_string($submitted[$key] ?? null) ? $submitted[$key] : '';
    }

    private static function isValidHost(string $host): bool
    {
        if ($host === '' || strlen($host) > self::MAX_HOST_LENGTH || preg_match('/[\s\/:@\x00]/', $host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return preg_match(
            '/\A(?=.{1,253}\z)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*\z/',
            $host
        ) === 1;
    }

    /**
     * @param array<string,mixed> $config
     * @return list<string>
     */
    private static function validationErrors(array $config): array
    {
        $host = is_string($config['host'] ?? null) ? $config['host'] : '';
        $port = is_int($config['port'] ?? null) ? $config['port'] : 0;
        $encryption = is_string($config['encryption'] ?? null) ? $config['encryption'] : '';
        $from = is_string($config['from'] ?? null) ? $config['from'] : '';
        $auth = is_bool($config['auth'] ?? null) ? $config['auth'] : null;
        $username = is_string($config['username'] ?? null) ? $config['username'] : '';
        $password = is_string($config['password'] ?? null) ? $config['password'] : '';
        $errors = [];

        if (!self::isValidHost($host)) {
            $errors[] = 'Enter a valid SMTP host name or IP address.';
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = 'Enter an SMTP port from 1 to 65535.';
        }
        if (!in_array($encryption, self::ENCRYPTIONS, true)) {
            $errors[] = 'Choose STARTTLS or SMTPS for SMTP encryption.';
        }
        if (!SupportContact::isValid($from)) {
            $errors[] = 'Enter a valid SMTP sender email address.';
        }
        if ($auth === null) {
            $errors[] = 'Choose whether the SMTP server requires authentication.';
        }
        if ($auth) {
            if ($username === '' || strlen($username) > self::MAX_USERNAME_LENGTH || preg_match('/[\r\n\0]/', $username)) {
                $errors[] = 'Enter a valid SMTP username.';
            }
            if ($password === '' || strlen($password) > self::MAX_PASSWORD_LENGTH || preg_match('/[\r\n\0]/', $password)) {
                $errors[] = 'Enter a valid SMTP password.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $config
     * @return array{host:string,port:int,encryption:string,from:string,auth:bool,username:string,password:string}
     */
    private static function canonical(array $config): array
    {
        $auth = (bool) $config['auth'];

        return [
            'host' => (string) $config['host'],
            'port' => (int) $config['port'],
            'encryption' => (string) $config['encryption'],
            'from' => (string) $config['from'],
            'auth' => $auth,
            'username' => $auth ? (string) $config['username'] : '',
            'password' => $auth ? (string) $config['password'] : '',
        ];
    }
}
