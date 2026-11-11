<?php

declare(strict_types=1);

use Game\Installer\InstallInput;
use Model\NatarsModel;

// Natar farm villages: the install option, the headless variable, the spread, and the Natar AI rules.

$root = dirname(__DIR__, 2);
foreach (['WorldSpeedProfile', 'FinishConstruction', 'Tribes', 'WorldOverrides', 'SupportContact', 'SmtpConfig', 'Installer/InstallInput'] as $class) {
    require_once $root . '/game/include/Game/' . $class . '.php';
}

function expectFarmVillages(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

expectFarmVillages(array_keys(InstallInput::FARM_VILLAGE_PRESETS) === [0, 40, 150, 400], 'The Quick install offers None, 40, 150 and 400.');
$defaults = InstallInput::defaults();
$base = ['setup_mode' => 'simple', 'multihunter_password' => 'a-long-password-1'];
expectFarmVillages(InstallInput::fromSubmission($base + ['farm_preset' => '150'], $defaults)[0]['farm_villages'] === '150', 'The Quick install takes a preset.');
expectFarmVillages(InstallInput::fromSubmission($base + ['farm_preset' => '77'], $defaults)[0]['farm_villages'] === '0', 'The Quick install ignores a count that is not a preset.');
[$values, $errors] = InstallInput::fromSubmission(['setup_mode' => 'advanced', 'farm_villages' => '77'] + $base, $defaults);
expectFarmVillages($values['farm_villages'] === '77' && !preg_grep('/farm village/', $errors), 'Advanced takes any count.');
[, $errors] = InstallInput::fromSubmission(['setup_mode' => 'advanced', 'farm_villages' => '1001'] + $base, $defaults);
expectFarmVillages(count(preg_grep('/farm village count/', $errors)) === 1, 'Advanced refuses more than the limit.');

// The spread is exact and weighted to the inside.
require_once $root . '/game/include/Model/NatarsModel.php';
foreach ([1, 7, 40, 150, 400, 1000] as $count) {
    $cells = NatarsModel::farmCells($count);
    expectFarmVillages(count($cells) === 12 && array_sum($cells) === $count && min($cells) >= 0, "$count villages fill twelve cells exactly.");
    if ($count >= 40) {
        expectFarmVillages(array_sum(array_slice($cells, 0, 4)) > array_sum(array_slice($cells, 8, 4)), "$count villages have more in the inner band than the outer one.");
        expectFarmVillages(max(array_slice($cells, 0, 4)) - min(array_slice($cells, 0, 4)) <= 1, "$count villages are even over the quarters.");
    }
}

$natars = (string) file_get_contents($root . '/game/include/Model/NatarsModel.php');
expectFarmVillages(str_contains($natars, 'v.isFarm=0 AND (SELECT COUNT(id) FROM artefacts'), 'Farm villages do not count toward the Natar expansion target.');
expectFarmVillages(str_contains($natars, 'owner=1 AND isWW=0 AND isFarm=0 AND isArtifact=0'), 'The Natar AI skips farm villages.');
expectFarmVillages(substr_count($natars, 'addMovement(') === 1 && str_contains($natars, '$cap_kid,'), 'The only Natar attack leaves the Natar capital.');

$register = (string) file_get_contents($root . '/game/include/Model/RegisterModel.php');
expectFarmVillages(str_contains($register, 'garrisonFarmVillage($kid,'), 'Every farm village gets a garrison.');
expectFarmVillages(str_contains($register, "sprintf('Farm %03d'"), 'Farm villages are named Farm 001, Farm 002 and so on.');

$install = (string) file_get_contents($root . '/game/include/Game/Installer/WorldInstaller.php') . (string) file_get_contents($root . '/tools/install.php');
expectFarmVillages(str_contains($install, 'seedFarmVillages(') && str_contains($install, "'farm_villages' => 'FARM_VILLAGES'"), 'The installer seeds them and the headless installer reads TRAVIAN_INSTALL_FARM_VILLAGES.');
expectFarmVillages(is_file($root . '/game/include/admin/include/Controllers/SeedFarmVillagesCtrl.php') && str_contains((string) file_get_contents($root . '/game/include/Game/Admin/AdminMenu.php'), 'action=seedFarmVillages'), 'The control panel has the Seed farm villages page.');

echo "Farm villages contract passed.\n";
