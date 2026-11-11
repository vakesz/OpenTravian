<?php

declare(strict_types=1);

namespace Game\Installer;

use Game\FinishConstruction;
use Game\SmtpConfig;
use Game\SupportContact;
use Game\Tribes;
use Game\WorldOverrides;
use Game\WorldSpeedProfile;

/**
 * Parses and validates one install request. The setup wizard (form fields) and
 * the CLI installer (environment variables mapped onto the same field names)
 * both go through fromSubmission(), so there is exactly one rule set.
 */
final class InstallInput
{
    /** Fields only the Advanced mode exposes; Simple mode always uses the defaults. */
    public const ADVANCED_FIELDS = ['account_database', 'world_database', 'world_id', 'starting_gold', 'map_size', 'index_url', 'world_url'];

    /** Farm villages seeded at install: the Quick install presets (None, Few, Some, Many), and the most Advanced accepts. */
    public const FARM_VILLAGE_PRESETS = [0 => 'None', 40 => 'Few', 150 => 'Some', 400 => 'Many'];
    public const MAX_FARM_VILLAGES = 1000;

    /** Speed presets offered in Simple mode (the published Legends speeds). */
    public const SPEED_PRESETS = [1, 2, 3, 5, 10];

    /** The highest world speed the installer, the speed tool and the preflight accept. */
    public const MAX_SPEED = 1_000_000_000;

    /**
     * Advanced-mode profile overrides. Each key is written to the connection file
     * under the same name config.php reads; a blank field keeps the value derived
     * from the speed profile. [label, type, unit multiplier, min, max, unit label]
     */
    public const PROFILE_FIELDS = [
        'movement_speed_increase' => ['Troop and hero map movement', 'float', 1, 1, self::MAX_SPEED, 'x'],
        'storage_multiplier' => ['Warehouse and granary capacity', 'int', 1, 1, self::MAX_SPEED, 'x'],
        'cranny_multiplier' => ['Cranny capacity', 'int', 1, 1, self::MAX_SPEED, 'x'],
        'trap_multiplier' => ['Trapper capacity', 'int', 1, 1, self::MAX_SPEED, 'x'],
        'cage_multiplier' => ['Cage capacity', 'int', 1, 1, self::MAX_SPEED, 'x'],
        'protection_time' => ['Beginner protection', 'int', 3600, 1, 8760, 'hours'],
        'oasisInitialProtectionTime' => ['Oasis animal protection at world start', 'int', 3600, 1, 8760, 'hours'],
        'dailyQuestInterval' => ['Daily quest cycle', 'int', 3600, 1, 168, 'hours'],
        'heroRegenerationMultiplier' => ['Hero regeneration', 'float', 1, 1, self::MAX_SPEED, 'x'],
        'oasisAnimalCap' => ['Oasis animal cap', 'int', 1, 1, 100000, 'animals'],
        'oasisRespawnInterval' => ['Oasis respawn interval', 'int', 60, 1, 43200, 'minutes'],
        'round_length' => ['Round length (World Wonder finish)', 'int', 1, 1, 3650, 'days'],
    ];

    /**
     * The headless install variable for a world rule or a speed-profile override: TRAVIAN_INSTALL_RULE_<KEY>
     * or TRAVIAN_INSTALL_PROFILE_<KEY>, with the key in UPPER_SNAKE_CASE
     * (oneFarmListPerAccount becomes TRAVIAN_INSTALL_RULE_ONE_FARM_LIST_PER_ACCOUNT).
     *
     * @param 'RULE'|'PROFILE' $kind
     */
    public static function environmentName(string $kind, string $key): string
    {
        return 'TRAVIAN_INSTALL_' . $kind . '_' . strtoupper(preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key));
    }

    /** Name of the dedicated application database user (TRAVIAN_APP_DB_USER). */
    public static function appDatabaseUser(): string
    {
        return getenv('TRAVIAN_APP_DB_USER') ?: 'travian';
    }

    /** @return array<string,string> */
    public static function defaults(): array
    {
        $baseUrl = rtrim(getenv('TRAVIAN_BASE_URL') ?: 'http://localhost:8080/', '/') . '/';

        return [
            'database_host' => getenv('TRAVIAN_DB_HOST') ?: 'db',
            'database_user' => getenv('TRAVIAN_DB_USER') ?: 'root',
            'account_database' => getenv('TRAVIAN_ACCOUNT_DATABASE') ?: 'main',
            'world_database' => getenv('TRAVIAN_WORLD_DATABASE') ?: 'world',
            'world_id' => getenv('TRAVIAN_WORLD_ID') ?: 'local',
            'title' => getenv('TRAVIAN_WORLD_TITLE') ?: 'Local OpenTravian World',
            'speed' => '1',
            'starting_gold' => '0',
            'farm_villages' => '0',
            'map_size' => '100',
            'index_url' => $baseUrl,
            'world_url' => $baseUrl,
            'player_name' => 'Player',
            'player_race' => '1',
            'support_email' => getenv('TRAVIAN_SUPPORT_EMAIL') ?: '',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'starttls',
            'smtp_from' => '',
            'smtp_auth' => '1',
            'smtp_username' => '',
        ];
    }

    public static function validSpeed(mixed $speed): bool
    {
        return is_string($speed)
            && preg_match('/^[1-9][0-9]{0,9}$/', $speed) === 1
            && (int)$speed <= self::MAX_SPEED;
    }

    /**
     * Normalizes a submission against the defaults and validates it. Email
     * delivery is optional: an empty smtp_host installs without SMTP.
     *
     * @param array<string,mixed> $submitted
     * @param array<string,string> $defaults
     * @return array{0:array<string,mixed>,1:list<string>} [values, errors]
     */
    public static function fromSubmission(array $submitted, array $defaults): array
    {
        $errors = [];
        $values = $defaults;
        $values['setup_mode'] = isset($submitted['setup_mode']) && $submitted['setup_mode'] === 'advanced' ? 'advanced' : 'simple';
        $advanced = $values['setup_mode'] === 'advanced';
        foreach ($defaults as $key => $default) {
            $useDefault = !$advanced && in_array($key, self::ADVANCED_FIELDS, true);
            $values[$key] = trim(!$useDefault && isset($submitted[$key]) ? (string)$submitted[$key] : $default);
        }
        // SMTP passwords must not be trimmed or reflected into the browser.
        $values['smtp_password'] = isset($submitted['smtp_password']) && is_string($submitted['smtp_password'])
            ? $submitted['smtp_password']
            : '';
        if (!$advanced) {
            // Simple mode offers speed presets only; the numeric field belongs to Advanced.
            $preset = (string)($submitted['speed_preset'] ?? $submitted['speed'] ?? $defaults['speed']);
            $values['speed'] = in_array((int)$preset, self::SPEED_PRESETS, true) ? $preset : (string)$defaults['speed'];
        }
        if (!$advanced) {
            $preset = (int)($submitted['farm_preset'] ?? $submitted['farm_villages'] ?? 0);
            $values['farm_villages'] = (string)(array_key_exists($preset, self::FARM_VILLAGE_PRESETS) ? $preset : 0);
        }
        $values['create_starter_player'] = isset($submitted['create_starter_player']) && (string)$submitted['create_starter_player'] === '1' ? '1' : '0';
        $values['playable_tribes'] = $advanced
            ? Tribes::parse($submitted['playable_tribes'] ?? [])
            : Tribes::DEFAULT_PLAYABLE;
        // Raw advanced inputs are kept so the database-clear confirmation can parse them again.
        $values['profile_input'] = $advanced && is_array($submitted['profile_input'] ?? $submitted['profile'] ?? null)
            ? ($submitted['profile_input'] ?? $submitted['profile']) : [];
        $values['override_input'] = $advanced && is_array($submitted['override_input'] ?? $submitted['override'] ?? null)
            ? ($submitted['override_input'] ?? $submitted['override']) : [];
        [$values['profile'], $profileErrors] = self::parseProfileOverrides($values['profile_input']);
        $values['overrides'] = self::parseWorldOverrides($values['override_input']);
        $values['database_password'] = (string)($submitted['database_password'] ?? '');
        $values['multihunter_password'] = (string)($submitted['multihunter_password'] ?? '');
        $values['player_password'] = (string)($submitted['player_password'] ?? '');

        if (($values['overrides']['finishConstruction'] ?? false)
            && !FinishConstruction::isSpeedWorld((int)$values['speed'])) {
            $errors[] = 'Finish Palace and Residence construction with gold is available only on speed worlds above 1x.';
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,190}$/', $values['database_host'])) {
            $errors[] = 'Enter a valid database host name or IP address.';
        }
        foreach (['database_user', 'account_database', 'world_database'] as $key) {
            if ($values[$key] === '' || strlen($values[$key]) > 64) {
                $errors[] = 'Enter valid database credentials and database names.';
                break;
            }
        }
        foreach (['account_database', 'world_database'] as $key) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $values[$key])) {
                $errors[] = 'Database names may contain only letters, numbers, and underscores.';
                break;
            }
        }
        if ($values['account_database'] === $values['world_database']) {
            $errors[] = 'Use separate account and world database names.';
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,5}$/', $values['world_id'])) {
            $errors[] = 'World IDs may contain letters, numbers, underscores, and hyphens (up to 5 characters).';
        }
        if (!self::validSpeed($values['speed'])) {
            $errors[] = 'Enter a game speed from 1 to 1000000000.';
        }
        if (!preg_match('/^[0-9]{1,9}$/', $values['starting_gold'])) {
            $errors[] = 'Enter a starting gold amount from 0 to 999999999.';
        }
        if (!preg_match('/^[0-9]{1,4}$/', $values['farm_villages']) || (int)$values['farm_villages'] > self::MAX_FARM_VILLAGES) {
            $errors[] = 'Enter a farm village count from 0 to ' . self::MAX_FARM_VILLAGES . '.';
        }
        if (!in_array($values['map_size'], ['100', '200', '400'], true)) {
            $errors[] = 'Choose a supported map size: 100, 200, or 400.';
        }
        foreach (['index_url', 'world_url'] as $urlField) {
            $parts = parse_url($values[$urlField]);
            if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) ||
                !in_array(strtolower($parts['scheme']), ['http', 'https'], true) ||
                isset($parts['user']) || isset($parts['pass'])) {
                $errors[] = 'Index and game URLs must be full HTTP or HTTPS URLs without embedded credentials.';
                break;
            }
        }
        if (strlen($values['database_password']) > 1024 || str_contains($values['database_password'], "\0")) {
            $errors[] = 'The database password is invalid.';
        }
        if (strlen($values['multihunter_password']) < 12) {
            $errors[] = 'Choose a Multihunter account password that is at least 12 characters long.';
        }

        // Email delivery is optional. Without an SMTP host the world installs with
        // no smtp.local.php, mail stays queued and the support form reports that
        // delivery is not configured. With a host, the recipient is required too.
        $values['support_email'] = SupportContact::normalize($values['support_email']);
        $values['smtp'] = null;
        if ($values['smtp_host'] !== '') {
            if (!SupportContact::isValid($values['support_email'])) {
                $errors[] = 'Enter a valid support recipient email address.';
            }
            $smtp = SmtpConfig::fromSubmission($values);
            array_push($errors, ...$smtp['errors']);
            $values['smtp'] = $smtp['config'];
        } elseif ($values['support_email'] !== '' && !SupportContact::isValid($values['support_email'])) {
            $errors[] = 'Enter a valid support recipient email address.';
        }

        array_push($errors, ...$profileErrors);
        if (!$values['playable_tribes']) {
            $errors[] = 'Select at least one playable tribe.';
        }
        if ($values['create_starter_player'] === '1') {
            if (!in_array((int)$values['player_race'], $values['playable_tribes'], true)) {
                $errors[] = 'Choose a starter player tribe that is one of the playable tribes.';
            }
            if (!preg_match('/^[A-Za-z0-9 _.-]{3,15}$/', $values['player_name'])) {
                $errors[] = 'The starter player name must be 3–15 letters, numbers, spaces, dots, underscores, or hyphens.';
            }
            if (strlen($values['player_password']) < 12) {
                $errors[] = 'Choose a Player account password that is at least 12 characters long.';
            }
        }
        $values['speed'] = (int)$values['speed'];
        $values['starting_gold'] = (int)$values['starting_gold'];
        $values['map_size'] = (int)$values['map_size'];
        $values['player_race'] = (int)$values['player_race'];
        $values['title'] = substr($values['title'], 0, 100);

        if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', self::appDatabaseUser())) {
            $errors[] = 'TRAVIAN_APP_DB_USER may contain only letters, numbers, and underscores (up to 32 characters).';
        }

        return [$values, $errors];
    }

    /**
     * Parses the raw advanced profile inputs. Blank means "derive"; anything out of
     * range is reported and left out. Returns [parsed values in stored units, errors].
     *
     * @return array{0:array<string,int|float>,1:list<string>}
     */
    public static function parseProfileOverrides(mixed $raw): array
    {
        $parsed = [];
        $errors = [];
        $raw = is_array($raw) ? $raw : [];
        foreach (self::PROFILE_FIELDS as $key => [$label, $type, $multiplier, $min, $max, $unit]) {
            $text = isset($raw[$key]) && is_string($raw[$key]) ? trim($raw[$key]) : '';
            if ($text === '') {
                continue;
            }
            $pattern = $type === 'float' ? '/^[0-9]{1,7}(\.[0-9]{1,2})?$/' : '/^[0-9]{1,7}$/';
            if (!preg_match($pattern, $text) || (float)$text < $min || (float)$text > $max) {
                $errors[] = $label . ' must be a number from ' . $min . ' to ' . $max . ' (' . $unit . ').';
                continue;
            }
            $parsed[$key] = $type === 'float' ? (float)$text : (int)round((float)$text * $multiplier);
        }

        return [$parsed, $errors];
    }

    /**
     * Parses the advanced feature-flag inputs against the WorldOverrides whitelist.
     * Blank means "keep the built-in default".
     *
     * @return array<string,mixed>
     */
    public static function parseWorldOverrides(mixed $raw): array
    {
        $input = [];
        $raw = is_array($raw) ? $raw : [];
        foreach (WorldOverrides::DEFINITIONS as $key => $definition) {
            if (isset($raw[$key]) && is_string($raw[$key]) && $raw[$key] !== '') {
                $input[$key] = $raw[$key];
            }
        }

        return WorldOverrides::normalize($input);
    }

    /**
     * Speed-derived values with the advanced overrides applied on top.
     *
     * @param array<string,mixed> $values
     * @return array<string,int|float>
     */
    public static function effectiveProfile(array $values): array
    {
        $speed = (int)$values['speed'];
        $profile = WorldSpeedProfile::profile($speed);
        $overrides = isset($values['profile']) && is_array($values['profile']) ? $values['profile'] : [];
        foreach ($overrides as $key => $value) {
            if (isset(self::PROFILE_FIELDS[$key])) {
                $profile[$key] = $value;
            }
        }
        // Pool and respawn defaults follow an overridden movement rate, as they do for derived worlds.
        if (isset($overrides['movement_speed_increase'])) {
            if (!isset($overrides['oasisAnimalCap'])) {
                $profile['oasisAnimalCap'] = WorldSpeedProfile::oasisAnimalCap($speed, (float)$profile['movement_speed_increase']);
            }
            if (!isset($overrides['oasisRespawnInterval'])) {
                $profile['oasisRespawnInterval'] = WorldSpeedProfile::oasisRespawnInterval($speed, (float)$profile['movement_speed_increase']);
            }
        }

        return $profile;
    }
}
