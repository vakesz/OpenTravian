<?php

declare(strict_types=1);

namespace Game;

/**
 * Operator settings for backups, engine catch-up and audit-log retention.
 * The control panel stores them in operations.local.json in the state
 * directory; a value that was never saved falls back to its environment
 * variable and then to the built-in default, so existing deployments keep
 * working unchanged. The file holds no secrets.
 */
final class OperationsSettings
{
    /** @var array<string,array{env:string,default:int,min:int,max:int,label:string}> */
    public const FIELDS = [
        'backupsEnabled' => ['env' => 'TRAVIAN_BACKUP_ENABLED', 'default' => 1, 'min' => 0, 'max' => 1, 'label' => 'Daily backups'],
        'backupKeepLast' => ['env' => 'TRAVIAN_BACKUP_KEEP_LAST', 'default' => 3, 'min' => 1, 'max' => 365, 'label' => 'Newest backups to keep'],
        'backupKeepDaily' => ['env' => 'TRAVIAN_BACKUP_KEEP_DAILY', 'default' => 7, 'min' => 0, 'max' => 365, 'label' => 'Days to keep the newest backup of each'],
        'catchupAfter' => ['env' => 'TRAVIAN_CATCHUP_AFTER_SECONDS', 'default' => 300, 'min' => 1, 'max' => 604800, 'label' => 'Engine away for at least (seconds) before catching up'],
        'catchupRate' => ['env' => 'TRAVIAN_CATCHUP_RATE_PER_SECOND', 'default' => 50, 'min' => 1, 'max' => 10000, 'label' => 'Overdue rows completed per second'],
        'catchupMax' => ['env' => 'TRAVIAN_CATCHUP_MAX_SECONDS', 'default' => 600, 'min' => 1, 'max' => 86400, 'label' => 'Longest catch-up pass (seconds)'],
        'auditRetentionDays' => ['env' => '', 'default' => 365, 'min' => 0, 'max' => 3650, 'label' => 'Keep audit log entries for (days, 0 = forever)'],
    ];

    private function __construct()
    {
    }

    public static function path(): string
    {
        $state = defined('STATE_DIR') ? (string) constant('STATE_DIR') : (string) getenv('TRAVIAN_STATE_DIR');

        return $state === '' ? '' : rtrim($state, '/') . '/operations.local.json';
    }

    /** @return array<string,int> every field, from the saved file, the environment or the default */
    public static function all(?string $path = null): array
    {
        $saved = self::saved($path ?? self::path());
        $values = [];
        foreach (self::FIELDS as $key => $field) {
            $values[$key] = $saved[$key] ?? self::fromEnvironment($key);
        }

        return $values;
    }

    public static function get(string $key): int
    {
        return self::all()[$key];
    }

    /** The value without the saved file: the environment variable when valid, else the default. */
    public static function fromEnvironment(string $key): int
    {
        $field = self::FIELDS[$key];
        $raw = $field['env'] === '' ? false : getenv($field['env']);
        if (!is_string($raw) || preg_match('/^[0-9]{1,9}$/', $raw) !== 1 || (int) $raw < $field['min'] || (int) $raw > $field['max']) {
            return $field['default'];
        }

        return (int) $raw;
    }

    /**
     * Validates a submitted form and returns the clean values, or the errors.
     *
     * @param array<string,mixed> $input
     * @return array{values:array<string,int>,errors:list<string>}
     */
    public static function fromSubmission(array $input): array
    {
        $values = [];
        $errors = [];
        foreach (self::FIELDS as $key => $field) {
            $raw = $input[$key] ?? null;
            if ($field['max'] === 1) {
                $values[$key] = $raw === '1' ? 1 : 0;
                continue;
            }
            if (!is_string($raw) || preg_match('/^[0-9]{1,9}$/', trim($raw)) !== 1 || (int) $raw < $field['min'] || (int) $raw > $field['max']) {
                $errors[] = $field['label'] . ' must be a whole number from ' . $field['min'] . ' to ' . $field['max'] . '.';
                continue;
            }
            $values[$key] = (int) $raw;
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** @param array<string,int> $values */
    public static function save(array $values, ?string $path = null): bool
    {
        $path ??= self::path();
        if ($path === '') {
            return false;
        }
        $clean = [];
        foreach (self::FIELDS as $key => $field) {
            if (isset($values[$key])) {
                $clean[$key] = min($field['max'], max($field['min'], (int) $values[$key]));
            }
        }
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, json_encode($clean, JSON_PRETTY_PRINT) . "\n", LOCK_EX) === false) {
            return false;
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    /** @return array<string,int> */
    private static function saved(string $path): array
    {
        $json = $path !== '' && is_file($path) ? @file_get_contents($path) : false;
        $data = is_string($json) ? json_decode($json, true) : null;
        $saved = [];
        foreach (self::FIELDS as $key => $field) {
            if (is_array($data) && isset($data[$key]) && is_int($data[$key]) && $data[$key] >= $field['min'] && $data[$key] <= $field['max']) {
                $saved[$key] = $data[$key];
            }
        }

        return $saved;
    }
}
