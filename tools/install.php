<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Headless world installer: the CLI twin of the setup wizard. It maps
 * TRAVIAN_INSTALL_* environment variables onto the wizard's form fields and
 * runs the same validation and installer (Game\Installer), so the two cannot
 * drift apart.
 *
 *   php tools/install.php [--if-configured] [--reset]
 *
 *   --if-configured  exit 0 when a world is already installed or
 *                    TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD is unset (for `compose up`)
 *   --reset          clear non-empty account/world databases first (destructive)
 *
 * Required: TRAVIAN_INSTALL_DB_PASSWORD (administrative database password) and
 * TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD. Optional: _SPEED (any 1..1000000000),
 * _PLAYER_NAME + _PLAYER_PASSWORD (+ _PLAYER_TRIBE) for a starter player,
 * _TRIBES (comma-separated ids), _MAP_SIZE, _STARTING_GOLD, _FARM_VILLAGES, every world rule
 * as _RULE_<NAME> and every speed-profile override as _PROFILE_<NAME> (names in
 * docs/install.md), and the email
 * block _SMTP_HOST/_PORT/_ENCRYPTION/_FROM/_AUTH/_USERNAME/_PASSWORD. Without
 * _SMTP_HOST the world installs with email delivery disabled. World names,
 * database names and URLs come from the usual TRAVIAN_* container variables.
 * Exit status: 0 installed or already installed, 1 failed, 2 bad configuration.
 */

require_once dirname(__DIR__) . '/game/deployment/include/env.php';
$gameInclude = dirname(__DIR__) . '/game/include/Game/';
foreach (['WorldSpeedProfile', 'FinishConstruction', 'Tribes', 'WorldOverrides', 'WorldSchemaVersion', 'SupportContact', 'SmtpConfig', 'Installer/AppDatabaseGrants', 'Installer/InstallInput', 'Installer/WorldInstaller'] as $gameClass) {
    require_once $gameInclude . $gameClass . '.php';
}

use Game\Installer\InstallInput;
use Game\Installer\WorldInstaller;

const INSTALL_EXIT_FAILURE = 1;
const INSTALL_EXIT_USAGE = 2;

$options = array_slice($argv, 1);
$unknown = array_diff($options, ['--if-configured', '--reset']);
if ($unknown !== []) {
    fwrite(STDERR, "Usage: php tools/install.php [--if-configured] [--reset]\n");
    exit(INSTALL_EXIT_USAGE);
}
$ifConfigured = in_array('--if-configured', $options, true);
$reset = in_array('--reset', $options, true);

$env = static fn (string $name): string => (string)(getenv('TRAVIAN_INSTALL_' . $name) ?: '');

if (!$reset && (is_file(SETUP_LOCK_FILE) || WorldInstaller::installationComplete())) {
    echo "World already installed; nothing to do.\n";
    exit(0);
}
if ($ifConfigured && $env('MULTIHUNTER_PASSWORD') === '') {
    echo "No install requested: set TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD in .env, or use the setup wizard.\n";
    exit(0);
}

$fields = [
    'database_password' => 'DB_PASSWORD',
    'multihunter_password' => 'MULTIHUNTER_PASSWORD',
    'speed' => 'SPEED',
    'map_size' => 'MAP_SIZE',
    'starting_gold' => 'STARTING_GOLD',
    'farm_villages' => 'FARM_VILLAGES',
    'playable_tribes' => 'TRIBES',
    'player_name' => 'PLAYER_NAME',
    'player_password' => 'PLAYER_PASSWORD',
    'player_race' => 'PLAYER_TRIBE',
    'smtp_host' => 'SMTP_HOST',
    'smtp_port' => 'SMTP_PORT',
    'smtp_encryption' => 'SMTP_ENCRYPTION',
    'smtp_from' => 'SMTP_FROM',
    'smtp_auth' => 'SMTP_AUTH',
    'smtp_username' => 'SMTP_USERNAME',
    'smtp_password' => 'SMTP_PASSWORD',
];
// Advanced mode accepts every field the CLI can set; unset ones keep the defaults.
$submitted = ['setup_mode' => 'advanced', 'create_starter_player' => $env('PLAYER_NAME') !== '' ? '1' : '0'];
foreach ($fields as $field => $name) {
    if ($env($name) !== '') {
        $submitted[$field] = $env($name);
    }
}
foreach (['override' => ['RULE', array_keys(\Game\WorldOverrides::DEFINITIONS)], 'profile' => ['PROFILE', array_keys(InstallInput::PROFILE_FIELDS)]] as $field => [$kind, $keys]) {
    foreach ($keys as $key) {
        $value = (string)(getenv(InstallInput::environmentName($kind, $key)) ?: '');
        if ($value !== '') {
            $submitted[$field][$key] = $value;
        }
    }
}
$submitted['playable_tribes'] ??= \Game\Tribes::DEFAULT_PLAYABLE;
if ($env('SMTP_HOST') !== '' && $env('SMTP_AUTH') === '') {
    // Authenticated unless the operator says otherwise, as in the wizard.
    $submitted['smtp_auth'] = $env('SMTP_USERNAME') !== '' ? '1' : '0';
}
if ($env('SMTP_HOST') !== '' && $env('SMTP_FROM') === '') {
    $submitted['smtp_from'] = (string)getenv('TRAVIAN_SUPPORT_EMAIL');
}

[$values, $errors] = InstallInput::fromSubmission($submitted, InstallInput::defaults());
if ($env('DB_PASSWORD') === '') {
    $errors[] = 'Set TRAVIAN_INSTALL_DB_PASSWORD to the administrative database password.';
}
if ($errors !== []) {
    fwrite(STDERR, "Install configuration is invalid:\n- " . implode("\n- ", $errors) . "\n");
    exit(INSTALL_EXIT_USAGE);
}

try {
    mysqli_report(MYSQLI_REPORT_OFF);
    $nonEmpty = WorldInstaller::nonEmptyDatabases(
        $values['database_host'],
        $values['database_user'],
        $values['database_password'],
        [$values['account_database'], $values['world_database']]
    );
    if ($nonEmpty !== [] && !$reset) {
        fwrite(STDERR, 'The databases ' . implode(', ', $nonEmpty) . " are not empty. Re-run with --reset to clear them.\n");
        exit(INSTALL_EXIT_FAILURE);
    }
    echo "Installing the world (map generation can take a few minutes) ...\n";
    WorldInstaller::install($values, InstallInput::appDatabaseUser(), $nonEmpty !== []);
} catch (Throwable $exception) {
    $cause = $exception->getPrevious() ?? $exception;
    fwrite(STDERR, 'Install failed: ' . $cause->getMessage() . "\n");
    exit(INSTALL_EXIT_FAILURE);
}
echo $values['smtp'] === null
    ? "World installed. Email delivery is not configured; mail stays queued until SMTP is set in the admin panel.\n"
    : "World installed.\n";
