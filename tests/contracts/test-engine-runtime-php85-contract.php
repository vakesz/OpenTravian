<?php

declare(strict_types=1);

$formulas = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Formulas.php');
$register = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/RegisterModel.php');
$natars = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/NatarsModel.php');
$session = file_get_contents(dirname(__DIR__, 2) . '/game/include/Core/Session.php');
$dispatcher = file_get_contents(dirname(__DIR__, 2) . '/game/include/Core/Dispatcher.php');
$dorf1 = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/ResourceFieldsCtrl.php');
$dorf2 = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/VillageCenterCtrl.php');
$heroAuction = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/HeroAuctionCtrl.php');
$dorf2Template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/village-center/main.php');
$buildingReadyWrapper = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/build/buildingReadyWrapper.php');
$extraModules = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/ExtraModules.php');
$reports = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/ReportsCtrl.php');

if ($formulas === false
    || !str_contains($formulas, 'mt_rand((int)($r - $half), (int)($r + $half))')) {
    fwrite(STDERR, "Adventure coordinates must pass integer bounds to mt_rand.\n");
    exit(1);
}

if ($register === false || $natars === false) {
    fwrite(STDERR, "Could not read the Natar runtime models.\n");
    exit(1);
}

if (!str_contains($natars, '(int) ($pop - $twentyPercent)')
    || !str_contains($natars, '(int) ($pop + $twentyPercent)')) {
    fwrite(STDERR, "Natar expansion must pass integer population bounds to mt_rand.\n");
    exit(1);
}

if ($session === false || $dispatcher === false || $dorf1 === false || $dorf2 === false || $heroAuction === false
    || $dorf2Template === false || $buildingReadyWrapper === false || $extraModules === false || $reports === false) {
    fwrite(STDERR, "Could not read the request-runtime sources.\n");
    exit(1);
}

if (str_contains($session, 'FILTER_SANITIZE_STRING')
    || str_contains($dispatcher, 'FILTER_SANITIZE_STRING')
    || str_contains($dorf1, 'FILTER_SANITIZE_STRING')
    || str_contains($dorf2, 'FILTER_SANITIZE_STRING')
    || str_contains($heroAuction, 'FILTER_SANITIZE_STRING')) {
    fwrite(STDERR, "Core request paths must not use the deprecated FILTER_SANITIZE_STRING filter.\n");
    exit(1);
}

// The village views no longer read their own cancel query (BuildRequest owns ?cancel=); only the
// auction action selector still reads a mutation-adjacent request value here.
if (!str_contains($heroAuction, "is_string(\$_REQUEST['action'])")
    || !str_contains($heroAuction, "in_array(\$_REQUEST['action'], ['buy', 'sell', 'bids'], true)")) {
    fwrite(STDERR, "Mutation-adjacent request values must only accept scalar, known-safe strings.\n");
    exit(1);
}

if (!str_contains($dorf2Template, "\$buildingField['g16e'] ?? \$buildingField['g0']")) {
    fwrite(STDERR, "Village-center rendering must fall back when the optional g16e SVG path is absent.\n");
    exit(1);
}

if (!str_contains($buildingReadyWrapper, "\$vars['contractLink']['extraModuleButton'] ?? ''")) {
    fwrite(STDERR, "Building contracts must allow an absent optional extra-module button.\n");
    exit(1);
}

if (!str_contains($extraModules, 'private const ACTION_METHODS = [')
    || !str_contains($extraModules, 'return is_string($action) && isset(self::ACTION_METHODS[$action]);')
    || !str_contains($extraModules, '$method = self::ACTION_METHODS[$action];')
    || str_contains($extraModules, 'get_class()')) {
    fwrite(STDERR, "Extra-module action lookup must use the explicit action whitelist, not a deprecated dynamic class lookup.\n");
    exit(1);
}

if (substr_count($reports, "(\$this->content->vars['content'] ?? '') . \$view->output()") < 2) {
    fwrite(STDERR, "Report pages must initialize their optional content buffers.\n");
    exit(1);
}

$natarFarmSelection = strstr($register, 'public function generateNatarsVillageInSide');
$nextMethod = 'public function generateWWNatarsVillage';
$natarFarmSelection = $natarFarmSelection === false
    ? false
    : strstr($natarFarmSelection, $nextMethod, true);

if ($natarFarmSelection === false
    || !str_contains($natarFarmSelection, "return rtrim((string)\$result, ',');")) {
    fwrite(STDERR, "Natar farm selection must convert an empty GROUP_CONCAT result to a string.\n");
    exit(1);
}

echo "Engine PHP 8.5 compatibility contract passed.\n";
