<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/deployment/include/env.php';
foreach (['WorldSpeedProfile', 'FinishConstruction', 'Tribes', 'WorldOverrides', 'WorldSchemaVersion', 'SupportContact', 'SmtpConfig', 'Installer/InstallInput', 'Installer/WorldInstaller'] as $installerClass) {
    require_once dirname(__DIR__, 2) . '/game/include/Game/' . $installerClass . '.php';
}

function expectInstallerAdvanced(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$config = (string)file_get_contents($root . '/game/include/config.php');

// Every advanced profile field maps to a connection key config.php really reads.
foreach (array_keys(\Game\Installer\InstallInput::PROFILE_FIELDS) as $key) {
    expectInstallerAdvanced(str_contains($config, "\$connection['$key']"), "Profile field $key is not read from the connection file.");
}
// Every feature flag maps to a real property of the runtime config.
foreach (\Game\WorldOverrides::DEFINITIONS as $key => [$path, $type]) {
    $property = substr($path, (int)strrpos($path, '.') + 1);
    if (in_array($key, \Game\WorldOverrides::INSTALL_ONLY, true)) {
        // Install-only keys land in a column of the world's config row (see WorldInstaller::runWorldInstaller).
        $columnType = $type === 'bool' ? 'TINYINT' : 'VARCHAR';
        expectInstallerAdvanced(str_contains((string)file_get_contents($root . '/database/world.sql'), "`$property` $columnType"), "Install-only override $key has no config column $property in world.sql.");
        continue;
    }
    expectInstallerAdvanced(str_contains($config, "'$property'"), "Override $key points at $path, which config.php does not define.");
}

// Profile parsing: blank derives, units convert, bad input is reported.
[$parsed, $errors] = \Game\Installer\InstallInput::parseProfileOverrides(['protection_time' => '2', 'storage_multiplier' => '', 'movement_speed_increase' => '2.5', 'round_length' => '30']);
expectInstallerAdvanced($errors === [] && $parsed === ['movement_speed_increase' => 2.5, 'protection_time' => 7200, 'round_length' => 30], 'Profile overrides must convert hours to seconds and skip blanks.');
[$parsed, $errors] = \Game\Installer\InstallInput::parseProfileOverrides(['protection_time' => '0', 'cranny_multiplier' => 'abc', 'oasisAnimalCap' => '999999', 'bogus' => '5']);
expectInstallerAdvanced($parsed === [] && count($errors) === 3, 'Out-of-range, non-numeric and unknown profile inputs must not be accepted.');
[$parsed] = \Game\Installer\InstallInput::parseProfileOverrides('not-an-array');
expectInstallerAdvanced($parsed === [], 'A non-array profile payload must be ignored.');

// Effective profile: derived unless overridden, and a movement override re-derives the oasis pool.
$derived = \Game\Installer\InstallInput::effectiveProfile(['speed' => 100]);
expectInstallerAdvanced($derived['movement_speed_increase'] === 20 && $derived['storage_multiplier'] === 50, 'Without overrides the profile must equal the speed-derived values.');
$custom = \Game\Installer\InstallInput::effectiveProfile(['speed' => 100, 'profile' => ['storage_multiplier' => 3, 'oasisAnimalCap' => 11]]);
expectInstallerAdvanced($custom['storage_multiplier'] === 3 && $custom['oasisAnimalCap'] === 11 && $custom['cranny_multiplier'] === $derived['cranny_multiplier'], 'Overrides must replace only the named keys.');
$extreme = \Game\Installer\InstallInput::effectiveProfile(['speed' => 1000000]);
expectInstallerAdvanced(
    $extreme['movement_speed_increase'] === 3000
    && $extreme['storage_multiplier'] === 1000000
    && $extreme['cranny_multiplier'] === 125000,
    'The installer must derive the documented local profile at its 1,000,000x maximum speed.'
);

// World overrides: whitelist only, bounded, tri-state flags.
$overrides = \Game\Installer\InstallInput::parseWorldOverrides(['vacationDays' => '7', 'fakeAuctions' => '0', 'refLimit' => '5000', 'registerAutoClose' => '', 'unknown' => '1', 'wwPlansEnabled' => '1']);
expectInstallerAdvanced($overrides === ['vacationDays' => 7, 'wwPlansEnabled' => true, 'fakeAuctions' => false], 'World overrides must keep whitelisted, in-range values only.');
$config1 = (object)['game' => (object)['vacationDays' => 21], 'auction' => (object)['fakeAuction' => (object)['enabled' => true]], 'custom' => (object)[]];
\Game\WorldOverrides::apply($config1, ['vacationDays' => 3, 'fakeAuctions' => false, 'nonsense' => 1, 'wwPlansEnabled' => true]);
expectInstallerAdvanced($config1->game->vacationDays === 3 && $config1->auction->fakeAuction->enabled === false && $config1->custom->wwPlansEnabled === true, 'Overrides must apply to nested config properties.');
\Game\WorldOverrides::apply((object)['game' => (object)[]], ['refLimit' => 5]);

// The connection file: simple values equal the derived defaults, advanced values are written.
$base = [
    'speed' => 2, 'starting_gold' => 0, 'world_id' => 'tst', 'title' => 'T', 'world_url' => 'http://x/',
    'database_host' => 'db', 'database_user' => 'u', 'database_password' => 'p', 'world_database' => 'w',
];
$simple = \Game\Installer\WorldInstaller::buildConnection($base);
expectInstallerAdvanced(str_contains($simple, "'protection_time' => 10800") && str_contains($simple, "'overrides' => \n  array (\n  )"), 'Simple mode must write the derived profile and no overrides.');
$advanced = \Game\Installer\WorldInstaller::buildConnection($base + ['profile' => ['protection_time' => 7200], 'overrides' => ['vacationDays' => 7, 'evil' => 1]]);
expectInstallerAdvanced(str_contains($advanced, "'protection_time' => 7200") && str_contains($advanced, "'vacationDays' => 7") && !str_contains($advanced, 'evil'), 'Advanced mode must write overrides and drop unknown keys.');

// Mode handling and wiring.
$setup = (string)file_get_contents($root . '/game/deployment/public/setup.php');
$view = (string)file_get_contents($root . '/game/deployment/include/setup-view.php');
$installerSources = (string)file_get_contents($root . '/game/include/Game/Installer/InstallInput.php')
    . (string)file_get_contents($root . '/game/include/Game/Installer/WorldInstaller.php');
expectInstallerAdvanced(
    \Game\Installer\InstallInput::validSpeed('1')
    && \Game\Installer\InstallInput::validSpeed('1000000')
    && \Game\Installer\InstallInput::validSpeed('1000000000')
    && !\Game\Installer\InstallInput::validSpeed('0')
    && !\Game\Installer\InstallInput::validSpeed('1000000001')
    && !\Game\Installer\InstallInput::validSpeed('9999999999'),
    'The installer must accept only the explicit 1x through 1,000,000,000x speed range.'
);
expectInstallerAdvanced(str_contains($installerSources, 'function validSpeed') && str_contains($installerSources, '(int)$speed <= self::MAX_SPEED'), 'The installer must enforce its speed maximum on the server.');
expectInstallerAdvanced(str_contains($installerSources, 'ADVANCED_FIELDS') && str_contains($installerSources, "\$useDefault = !\$advanced && in_array(\$key, self::ADVANCED_FIELDS, true);"), 'Simple mode must ignore the advanced-only fields.');
expectInstallerAdvanced(str_contains($installerSources, ': Tribes::DEFAULT_PLAYABLE;') && str_contains($installerSources, 'SPEED_PRESETS'), 'Simple mode must use the tribe set and the speed presets.');
expectInstallerAdvanced(str_contains($view, 'InstallInput::PROFILE_FIELDS as') && str_contains($view, '\Game\WorldOverrides::DEFINITIONS as') && str_contains($view, "'Quick install'") && str_contains($view, "'Advanced install'") && str_contains($view, 'data-step="Review"'), 'The wizard must render the mode tabs, every advanced field and the review step.');
expectInstallerAdvanced(str_contains($view, 'data-step="Services"') && str_contains($view, 'What next'), 'The wizard has an optional Services step and the finished page says what to do next.');
$setup = (string)file_get_contents($root . '/game/deployment/public/setup.php');
expectInstallerAdvanced(str_contains($setup, 'AdminView::SETUP_COOKIE') && str_contains($setup, "header('Location: /setup/')"), 'The wizard remembers its mode in a cookie and ends on the What next page.');
expectInstallerAdvanced(str_contains($view, 'function reviewValue(field)') && str_contains($view, "field.name === 'speed'") && str_contains($view, "toLocaleString('en-US') + '×'"), 'The Review step must display high-speed worlds with grouped values and the speed suffix.');
expectInstallerAdvanced(!preg_match('#(?:src|href)="https?://#i', $view) && !str_contains($view, '<style'), 'The wizard must use only self-hosted Gpack assets.');
expectInstallerAdvanced(str_contains((string)file_get_contents($root . '/game/include/config/config.after.php'), '\Game\WorldOverrides::apply($config, $connection[\'overrides\']);'), 'config.after.php must apply the connection overrides.');
foreach (['hash_equals($setupToken, $providedToken)', 'setup_throttle_remaining', 'SETUP_LOCK_FILE', "\$_SESSION['travian_setup_clear']"] as $security) {
    expectInstallerAdvanced(str_contains($setup, $security) || str_contains($setup, str_replace('SETUP_LOCK_FILE', '$lockFile', $security)), "Installer security element missing: $security");
}

echo "Installer advanced contract passed.\n";
