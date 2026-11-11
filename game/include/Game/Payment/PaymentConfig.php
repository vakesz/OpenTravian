<?php

declare(strict_types=1);

namespace Game\Payment;

/**
 * Operator payment settings: test/live mode, the one shop currency and the
 * provider credentials. Like SMTP and social sign-in these are deployment
 * secrets: kept only in a separate 0600 PHP state file in the generated state
 * directory (payments.local.php), never in SQL, globalConfig.php, logs, forms
 * or the repository. The file is global to the account database, so every
 * world on this installation shares it.
 */
final class PaymentConfig
{
    /** Two-decimal currencies both providers accept; prices are stored in major units. */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK'];

    private const MAX_VALUE_LENGTH = 256;

    /** @var array<string,list<string>> */
    private const FIELDS = [
        'stripe' => ['secret_key', 'webhook_secret'],
        'paypal' => ['client_id', 'client_secret', 'webhook_id'],
    ];
    private const SECRETS = ['secret_key', 'webhook_secret', 'client_secret'];

    public function __construct(private readonly string $path)
    {
    }

    public static function fromEnvironment(): self
    {
        $override = getenv('TRAVIAN_PAYMENTS_CONFIG');
        $state = getenv('TRAVIAN_STATE_DIR');
        if (is_string($override) && $override !== '') {
            return new self($override);
        }
        if (defined('STATE_DIR')) {
            return new self(rtrim((string) constant('STATE_DIR'), '/') . '/payments.local.php');
        }

        return new self(is_string($state) && $state !== '' ? rtrim($state, '/') . '/payments.local.php' : '');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function mode(): string
    {
        return ($this->read()['mode'] ?? 'test') === 'live' ? 'live' : 'test';
    }

    public function isLive(): bool
    {
        return $this->mode() === 'live';
    }

    public function currency(): string
    {
        $currency = $this->read()['currency'] ?? 'USD';

        return in_array($currency, self::CURRENCIES, true) ? $currency : 'USD';
    }

    /** @return array<string,string>|null credentials of a fully configured provider */
    public function credentials(string $provider): ?array
    {
        $entry = $this->read()[$provider] ?? null;

        return is_array($entry) && self::errors($provider, $entry) === [] ? self::canonical($provider, $entry) : null;
    }

    /** @return array<string,Provider> configured providers by id */
    public function providers(HttpTransport $http): array
    {
        $providers = [];
        if (($stripe = $this->credentials('stripe')) !== null) {
            $providers['stripe'] = new StripeProvider($stripe['secret_key'], $stripe['webhook_secret'], $http);
        }
        if (($paypal = $this->credentials('paypal')) !== null) {
            $providers['paypal'] = new PayPalProvider(
                $paypal['client_id'],
                $paypal['client_secret'],
                $paypal['webhook_id'],
                $this->isLive(),
                $http
            );
        }

        return $providers;
    }

    /**
     * Admin form values. Secrets never leave the state file: only whether they are set.
     *
     * @return array{mode:string,currency:string,providers:array<string,array{values:array<string,string>,configured:bool}>}
     */
    public function formValues(): array
    {
        $providers = [];
        foreach (self::FIELDS as $id => $fields) {
            $values = [];
            foreach ($fields as $field) {
                $values[$field] = in_array($field, self::SECRETS, true) ? '' : (string) ($this->credentials($id)[$field] ?? '');
            }
            $providers[$id] = ['values' => $values, 'configured' => $this->credentials($id) !== null];
        }

        return ['mode' => $this->mode(), 'currency' => $this->currency(), 'providers' => $providers];
    }

    /**
     * Parses the admin form. A blank secret keeps the stored one, except that
     * changing the PayPal client id requires its secret again. A provider whose
     * fields are all blank is left as it was; "remove" deletes it; a partly
     * filled provider is rejected.
     *
     * @param array<string,mixed> $submitted
     * @return array{config:array<string,mixed>|null,errors:list<string>}
     */
    public function fromSubmission(array $submitted): array
    {
        $errors = [];
        $mode = ($submitted['pay_mode'] ?? '') === 'live' ? 'live' : 'test';
        $currency = is_string($submitted['pay_currency'] ?? null) ? strtoupper(trim($submitted['pay_currency'])) : '';
        if (!in_array($currency, self::CURRENCIES, true)) {
            $errors[] = 'Currency must be one of ' . implode(', ', self::CURRENCIES) . '.';
        }
        $config = ['mode' => $mode, 'currency' => $currency];
        foreach (self::FIELDS as $id => $fields) {
            $existing = $this->credentials($id) ?? [];
            if (($submitted['pay_' . $id . '_remove'] ?? '') === '1') {
                continue;
            }
            $entry = [];
            $touched = false;
            foreach ($fields as $field) {
                $value = $submitted['pay_' . $id . '_' . $field] ?? '';
                $entry[$field] = is_string($value) ? trim($value) : '';
                $touched = $touched || $entry[$field] !== '';
            }
            $identityChanged = $id === 'paypal' && $existing !== [] && $entry['client_id'] !== '' && $entry['client_id'] !== $existing['client_id'];
            foreach ($fields as $field) {
                if ($entry[$field] === '' && !$identityChanged && isset($existing[$field])
                    && (in_array($field, self::SECRETS, true) || !$touched)) {
                    $entry[$field] = $existing[$field];
                }
            }
            if (!$touched && $existing === []) {
                continue;
            }
            $problems = self::errors($id, $entry);
            if ($id === 'stripe' && $problems === [] && preg_match('/\A[sr]k_' . $mode . '_/', $entry['secret_key']) !== 1) {
                $problems[] = 'secret_key does not match ' . $mode . ' mode; enter the ' . $mode . ' key.';
            }
            foreach ($problems as $problem) {
                $errors[] = ucfirst($id) . ': ' . $problem;
            }
            if ($problems === []) {
                $config[$id] = self::canonical($id, $entry);
            }
        }

        return ['config' => $errors === [] ? $config : null, 'errors' => $errors];
    }

    /** @param array<string,mixed> $config as returned by fromSubmission() */
    public function save(array $config): bool
    {
        $directory = $this->path === '' ? '' : dirname($this->path);
        if ($directory === '' || !is_dir($directory) || !is_writable($directory) || is_link($this->path)) {
            return false;
        }
        $clean = ['mode' => ($config['mode'] ?? '') === 'live' ? 'live' : 'test', 'currency' => (string) ($config['currency'] ?? 'USD')];
        foreach (self::FIELDS as $id => $unused) {
            if (isset($config[$id]) && is_array($config[$id]) && self::errors($id, $config[$id]) === []) {
                $clean[$id] = self::canonical($id, $config[$id]);
            }
        }
        $temporary = tempnam($directory, '.travian-payments-');
        if ($temporary === false) {
            return false;
        }
        $contents = "<?php\n\n/** Generated deployment-only payment provider credentials. */\n\$paymentsConfig = "
            . var_export($clean, true) . ";\n";
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0600)
            || !rename($temporary, $this->path) || !chmod($this->path, 0600)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            return false;
        }

        return true;
    }

    /** @return array<string,mixed> */
    private function read(): array
    {
        if ($this->path === '' || !is_file($this->path) || is_link($this->path)) {
            return [];
        }
        try {
            $config = (static function (string $file): mixed {
                $paymentsConfig = null;
                require $file;
                /** @var mixed $paymentsConfig */

                return $paymentsConfig;
            })($this->path);
        } catch (\Throwable) {
            return [];
        }

        return is_array($config) ? $config : [];
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
            if (!is_string($value) || $value === '') {
                $errors[] = $field . ' is required.';
            } elseif (strlen($value) > self::MAX_VALUE_LENGTH || preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1) {
                $errors[] = $field . ' is not valid.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,string>
     */
    private static function canonical(string $id, array $entry): array
    {
        $canonical = [];
        foreach (self::FIELDS[$id] as $field) {
            $canonical[$field] = trim((string) $entry[$field]);
        }

        return $canonical;
    }
}
