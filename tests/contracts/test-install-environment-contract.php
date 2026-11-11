<?php

declare(strict_types=1);

use Game\Installer\InstallInput;
use Game\WorldOverrides;

// Every world rule and speed-profile override of the wizard has a headless variable, passed to the
// installer service, documented, and parsed by the same rules as the wizard.

$root = dirname(__DIR__, 2);
foreach (['WorldSpeedProfile', 'FinishConstruction', 'Tribes', 'WorldOverrides', 'SupportContact', 'SmtpConfig', 'Installer/InstallInput'] as $class) {
    require_once $root . '/game/include/Game/' . $class . '.php';
}

function expectInstallEnvironment(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

expectInstallEnvironment(InstallInput::environmentName('RULE', 'oneFarmListPerAccount') === 'TRAVIAN_INSTALL_RULE_ONE_FARM_LIST_PER_ACCOUNT', 'camelCase keys become UPPER_SNAKE_CASE.');
expectInstallEnvironment(InstallInput::environmentName('PROFILE', 'movement_speed_increase') === 'TRAVIAN_INSTALL_PROFILE_MOVEMENT_SPEED_INCREASE', 'snake_case keys are upper-cased.');

$compose = (string) file_get_contents($root . '/compose.yaml');
$docs = (string) file_get_contents($root . '/docs/install.md');
$names = [];
foreach (array_keys(WorldOverrides::DEFINITIONS) as $key) {
    $names[] = InstallInput::environmentName('RULE', $key);
}
foreach (array_keys(InstallInput::PROFILE_FIELDS) as $key) {
    $names[] = InstallInput::environmentName('PROFILE', $key);
}
expectInstallEnvironment(count(array_unique($names)) === count($names), 'The variable names are unique.');
foreach ($names as $name) {
    expectInstallEnvironment(str_contains($compose, "      $name: \${{$name}:-}\n"), "$name must be passed to the installer service.");
    expectInstallEnvironment(str_contains($docs, "`$name`"), "$name must be documented in install.md.");
}

$installer = (string) file_get_contents($root . '/tools/install.php');
expectInstallEnvironment(str_contains($installer, 'InstallInput::environmentName($kind, $key)'), 'The CLI installer reads the variables through InstallInput::environmentName().');

// The values land where the wizard puts them.
$submitted = ['setup_mode' => 'advanced', 'override' => ['vacationDays' => '30', 'starvation' => '0'], 'profile' => ['protection_time' => '48']];
[$values, $errors] = InstallInput::fromSubmission($submitted, InstallInput::defaults());
expectInstallEnvironment($values['overrides']['vacationDays'] === 30 && $values['overrides']['starvation'] === false, 'Rules are parsed as in the wizard.');
expectInstallEnvironment($values['profile']['protection_time'] === 48 * 3600, 'Profile values use the wizard units.');

echo "Install environment contract passed.\n";
