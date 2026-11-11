<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/**
 * Operator social sign-in credentials. Like SMTP, they are deployment secrets:
 * stored only in a separate 0600 PHP state file in the generated state
 * directory, never in SQL, globalConfig.php, logs, forms or the repository.
 */
final class ProviderConfig
{
    public const MAX_VALUE_LENGTH = 256;
    public const MAX_KEY_LENGTH = 4096;

    /** @var array<string,list<string>> required fields per provider */
    private const FIELDS = [
        'google' => ['client_id', 'client_secret'],
        'facebook' => ['client_id', 'client_secret'],
        'apple' => ['client_id', 'team_id', 'key_id', 'private_key'],
    ];

    public function __construct(private readonly string $path)
    {
    }

    public static function fromEnvironment(): self
    {
        $path = '';
        $override = getenv('TRAVIAN_SOCIAL_CONFIG');
        if (is_string($override) && $override !== '') {
            $path = $override;
        } elseif (defined('SOCIAL_CONFIG_FILE')) {
            $path = (string) constant('SOCIAL_CONFIG_FILE');
        } elseif (defined('STATE_DIR')) {
            $path = rtrim((string) constant('STATE_DIR'), '/') . '/social.local.php';
        }

        return new self($path);
    }

    /**
     * Provider id => canonical credential fields, for complete and valid providers only.
     *
     * @return array<string,array<string,string>>
     */
    public function all(): array
    {
        if ($this->path === '' || !is_file($this->path) || is_link($this->path)) {
            return [];
        }
        try {
            $config = (static function (string $file): mixed {
                $socialConfig = null;
                require $file;

                /** @var mixed $socialConfig */

                return $socialConfig;
            })($this->path);
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($config)) {
            return [];
        }
        $valid = [];
        foreach (self::FIELDS as $id => $fields) {
            $entry = $config[$id] ?? null;
            if (is_array($entry) && self::errors($id, $entry) === []) {
                $valid[$id] = self::canonical($id, $entry);
            }
        }

        return $valid;
    }

    /** @return array<string,string>|null the credential fields, or null when not fully configured */
    public function credentials(Provider $provider): ?array
    {
        return $this->all()[$provider->value] ?? null;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return list<\Game\SocialAuth\Provider>
     */
    public function configuredProviders(): array
    {
        $configured = $this->all();

        return array_values(array_filter(
            Provider::cases(),
            static fn (Provider $provider): bool => isset($configured[$provider->value])
        ));
    }

    /**
     * Parses the admin form. A provider whose fields are all blank is removed;
     * a blank secret on an already-configured provider keeps the stored one.
     * Partially filled providers are rejected.
     *
     * @param array<string,mixed> $submitted
     * @return array{config:array<string,array<string,string>>|null,errors:list<string>}
     */
    public function fromSubmission(array $submitted): array
    {
        $existing = $this->all();
        $result = [];
        $errors = [];
        foreach (self::FIELDS as $id => $fields) {
            $entry = [];
            $touched = false;
            // Changing any identifying field (client/team/key id) invalidates
            // the stored secret: it must be re-entered, never silently reused
            // with a different app.
            $identityChanged = false;
            foreach ($fields as $field) {
                $value = $submitted['social_' . $id . '_' . $field] ?? '';
                $value = is_string($value) ? trim($value) : '';
                if (!self::isSecret($field) && isset($existing[$id][$field]) && $value !== $existing[$id][$field]) {
                    $identityChanged = true;
                }
            }
            foreach ($fields as $field) {
                $value = $submitted['social_' . $id . '_' . $field] ?? '';
                $value = is_string($value) ? trim($value) : '';
                $touched = $touched || $value !== '';
                if ($value === '' && !$identityChanged && isset($existing[$id][$field]) && self::isSecret($field)) {
                    $value = $existing[$id][$field];
                }
                $entry[$field] = $value;
            }
            if (($submitted['social_' . $id . '_remove'] ?? '') === '1') {
                continue;
            }
            if (!$touched) {
                // Nothing submitted for this provider: keep whatever is stored.
                if (isset($existing[$id])) {
                    $result[$id] = $existing[$id];
                }
                continue;
            }
            $problems = self::errors($id, $entry);
            if ($problems !== []) {
                foreach ($problems as $problem) {
                    $errors[] = ucfirst($id) . ': ' . $problem;
                }
                continue;
            }
            $result[$id] = self::canonical($id, $entry);
        }

        return ['config' => $errors === [] ? $result : null, 'errors' => $errors];
    }

    /**
     * Form values; secrets never leave the state file.
     *
     * @return array<string,array{values:array<string,string>,secretsConfigured:bool}>
     */
    public function formValues(): array
    {
        $configured = $this->all();
        $values = [];
        foreach (self::FIELDS as $id => $fields) {
            $entry = [];
            foreach ($fields as $field) {
                $entry[$field] = self::isSecret($field) ? '' : ($configured[$id][$field] ?? '');
            }
            $values[$id] = ['values' => $entry, 'secretsConfigured' => isset($configured[$id])];
        }

        return $values;
    }

    /** @param array<string,array<string,string>> $config */
    public function save(array $config): bool
    {
        $directory = $this->path === '' ? '' : dirname($this->path);
        if ($directory === '' || !is_dir($directory) || !is_writable($directory) || is_link($this->path)) {
            return false;
        }
        $clean = [];
        foreach (self::FIELDS as $id => $fields) {
            if (isset($config[$id]) && self::errors($id, $config[$id]) === []) {
                $clean[$id] = self::canonical($id, $config[$id]);
            }
        }
        $lockPath = $this->path . '.lock';
        if (is_link($lockPath) || ($lock = fopen($lockPath, 'c')) === false) {
            return false;
        }
        $temporary = null;
        try {
            if (!chmod($lockPath, 0600) || !flock($lock, LOCK_EX)) {
                return false;
            }
            $temporary = tempnam($directory, '.travian-social-');
            if ($temporary === false) {
                return false;
            }
            $contents = "<?php\n\n/** Generated deployment-only social sign-in credentials. */\n\$socialConfig = "
                . var_export($clean, true) . ";\n";
            if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0600)) {
                return false;
            }
            if (!rename($temporary, $this->path) || !chmod($this->path, 0600)) {
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

    public static function redirectUri(Provider $provider, string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/oauth/' . $provider->value . '/callback';
    }

    private static function isSecret(string $field): bool
    {
        return $field === 'client_secret' || $field === 'private_key';
    }

    /**
     * @param array<string,mixed> $entry
     * @return list<string>
     */
    private static function errors(string $id, array $entry): array
    {
        $errors = [];
        foreach (self::FIELDS[$id] ?? [] as $field) {
            $value = $entry[$field] ?? null;
            $max = $field === 'private_key' ? self::MAX_KEY_LENGTH : self::MAX_VALUE_LENGTH;
            if (!is_string($value) || $value === '') {
                $errors[] = $field . ' is required.';
            } elseif (strlen($value) > $max || ($field !== 'private_key' && preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1)) {
                $errors[] = $field . ' is not valid.';
            }
        }
        if ($errors === [] && $id === 'apple') {
            foreach (['team_id', 'key_id'] as $field) {
                if (preg_match('/\A[A-Z0-9]{10}\z/', (string) $entry[$field]) !== 1) {
                    $errors[] = $field . ' must be 10 letters or digits.';
                }
            }
            if (!self::isEcP256Key((string) $entry['private_key'])) {
                $errors[] = 'private_key must be an unencrypted PKCS#8 P-256 (.p8) key.';
            }
        }

        return $errors;
    }

    private static function isEcP256Key(string $pem): bool
    {
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            return false;
        }
        $details = openssl_pkey_get_details($key);

        return is_array($details)
            && ($details['type'] ?? null) === OPENSSL_KEYTYPE_EC
            && ($details['ec']['curve_name'] ?? null) === 'prime256v1';
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,string>
     */
    private static function canonical(string $id, array $entry): array
    {
        $canonical = [];
        foreach (self::FIELDS[$id] as $field) {
            $canonical[$field] = str_replace("\r\n", "\n", trim((string) $entry[$field]));
        }

        return $canonical;
    }
}
