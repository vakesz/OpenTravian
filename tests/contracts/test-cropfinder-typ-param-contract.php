<?php

declare(strict_types=1);

/**
 * Contract: Crop finder form radios, controller, and pager all use `typ`
 * (classic Travian param). Radios must not post `type`, which the controller
 * never reads — that mismatch silently drops search results.
 */

function failCropfinderTypParamContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ctrlPath = $root . '/game/include/Controller/CropfinderCtrl.php';
$settingsPath = $root . '/game/include/resources/Templates/cropfinder/settings.php';

$ctrl = file_get_contents($ctrlPath);
$settings = file_get_contents($settingsPath);
if ($ctrl === false || $settings === false) {
    failCropfinderTypParamContract('Unable to read cropfinder controller/template.');
}

if (!preg_match('/name="typ"\s+value="15"/', $settings)
    || !preg_match('/name="typ"\s+value="9"/', $settings)
    || !preg_match('/name="typ"\s+value="all"/', $settings)
) {
    failCropfinderTypParamContract('settings.php radios must submit name="typ" for 15/9/all.');
}

if (preg_match('/name="type"\s+value="(15|9|all)"/', $settings)) {
    failCropfinderTypParamContract('settings.php must not submit crop-type radios as name="type".');
}

if (!str_contains($ctrl, "\$_REQUEST['typ']")) {
    failCropfinderTypParamContract('CropfinderCtrl must read $_REQUEST[\'typ\'].');
}

if (!str_contains($ctrl, "\$prefix['typ']")) {
    failCropfinderTypParamContract('CropfinderCtrl pager prefix must keep typ.');
}

if (!str_contains($ctrl, "in_array(\$typ, ['all', '9', '15'], true)")) {
    failCropfinderTypParamContract('CropfinderCtrl must strict-validate typ as all|9|15 strings.');
}

if (!str_contains($ctrl, 'round((float)$row[\'distance\'], 1)')
    && !str_contains($ctrl, 'round((float) $row[\'distance\'], 1)')) {
    failCropfinderTypParamContract('CropfinderCtrl must cast MySQL distance string before round().');
}

fwrite(STDOUT, "Cropfinder typ param contract passed.\n");
