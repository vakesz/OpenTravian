<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/game/include/';
$fail = static function (string $m): never {
    fwrite(STDERR, $m . PHP_EOL);
    exit(1);
};
$read = static fn (string $p): string => (string) file_get_contents($root . $p);

$overview = $read('Controller/Ajax/alliance/bonusOverview.php');
if (!preg_match('/BONUS_PERCENTAGE = \[(.*?)\];/s', $overview, $m)) {
    $fail('BONUS_PERCENTAGE not found.');
}
foreach (['TRAINING' => 2, 'CP' => 4, 'ARMOR' => 2, 'TRADE' => 30] as $type => $step) {
    if (!preg_match('/TYPE_' . $type . ' => ' . $step . ',/', $m[1])) {
        $fail("Bonus step for $type must be $step.");
    }
}

$bonus = $read('Game/AllianceBonus/AllianceBonus.php');
foreach (['getTrainingBonus' => '1 - ($level * 2 / 100)', 'getTradersBonus' => '1 + ($level * 30 / 100)', 'getArmorBonus' => '1 + ($level * 2 / 100)'] as $fn => $expr) {
    if (!preg_match('/function ' . $fn . '\(.*?\n    }/s', $bonus, $f) || !str_contains($f[0], $expr)) {
        $fail("$fn must use $expr.");
    }
}

if (!preg_match('/function getCulturePointProductionBonus\\(.*?\\n    }/s', $bonus, $culturePointBonus)
    || !str_contains($culturePointBonus[0], 'return self::culturePointProductionMultiplier((int)$level, (int)$joinTime);')) {
    $fail('getCulturePointProductionBonus must delegate to the shared culture-point multiplier.');
}
if (!preg_match('/function culturePointProductionMultiplier\\(.*?\\n    }/s', $bonus, $culturePointMultiplier)
    || !str_contains($culturePointMultiplier[0], '1 + (self::availableLevel(max(0, $level), $joinTime) * 4 / 100)')) {
    $fail('culturePointProductionMultiplier must retain the 4% per-level culture-point formula.');
}

$main = $read('resources/Templates/alliance/bonuses/main.php');
if (!str_contains($main, 'TYPE_TRADE => 30') || !str_contains($main, 'TYPE_CP => 4')) {
    $fail('Level-up animation percentages must match the bonus steps.');
}

$session = $read('Core/Session.php');
if (!preg_match("/'embassyBuildingSubTabs' => 0,\s*'allyPageOverview' => 0,/", $session)) {
    $fail('allyPageOverview must be appended after embassyBuildingSubTabs in favorTabs.');
}
if (!str_contains($read('Controller/Ajax/favouriteTab.php'), "'allianceOverview' => 'allyPageOverview'")) {
    $fail('favouriteTab must handle allyPageOverview.');
}
if (!str_contains($read('resources/Templates/alliance/overviewSubNavi.php'), "name: 'allianceOverview'")) {
    $fail('Overview favourite button must post allianceOverview.');
}

echo "alliance bonus values contract passed\n";
