#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Core\Village;
use Game\FinishConstruction;
use Game\GoldHelper;
use Game\WorldOverrides;

function failFinishConstructionCapability(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function expectFinishConstructionCapability(bool $condition, string $message): void
{
    if (!$condition) {
        failFinishConstructionCapability($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$hadFinishConstruction = property_exists($config->dynamic, 'finishConstruction');
$originalFinishConstruction = $config->dynamic->finishConstruction ?? null;

try {
    expectFinishConstructionCapability(!FinishConstruction::isSpeedWorld(1), 'x1 must not enable the speed-world option.');
    expectFinishConstructionCapability(FinishConstruction::isSpeedWorld(2), 'x2 must enable the speed-world option.');

    $config->game->speed = 1;
    $config->dynamic->finishConstruction = 1;
    expectFinishConstructionCapability(!FinishConstruction::isEnabled(), 'The capability must remain disabled at x1.');
    expectFinishConstructionCapability(
        !FinishConstruction::canFinishWithGold(25, false),
        'Residence gold completion must be rejected when the world is not a speed world.'
    );

    $config->game->speed = 10;
    $config->dynamic->finishConstruction = 0;
    expectFinishConstructionCapability(
        !FinishConstruction::canFinishWithGold(26, false),
        'Palace gold completion must remain rejected until the world capability is enabled.'
    );
    expectFinishConstructionCapability(
        !FinishConstruction::hasEligibleFinishNowQueues([25, 26], false, false, false),
        'A disabled Palace/Residence-only building queue must not render Finish now.'
    );

    // Exercise the exact queue-reading predicate used by GoldHelper::finishNowButton(),
    // rather than only its array-level helper. The negative kid keeps the database
    // side-query for research/demolition empty without mutating the installed world.
    $villageFactory = static function (array $buildingGids, bool $isWorldWonder = false): Village {
        $village = (new \ReflectionClass(Village::class))->newInstanceWithoutConstructor();
        $village->village = ['kid' => -1, 'isWW' => $isWorldWonder ? 1 : 0];
        $village->onLoadBuildings = ['normal' => [], 'master' => []];
        $village->buildings = [];
        foreach ($buildingGids as $field => $gid) {
            $fieldId = (int)$field + 1;
            $village->buildings[$fieldId] = ['item_id' => (int)$gid];
            $village->onLoadBuildings['normal'][$fieldId] = ['building_field' => $fieldId];
        }

        return $village;
    };
    $palaceResidenceOnly = $villageFactory([25, 26]);
    expectFinishConstructionCapability(
        !FinishConstruction::hasEligibleFinishNowOrder($palaceResidenceOnly),
        'The queue-reading eligibility predicate must reject a disabled Palace/Residence-only queue.'
    );

    // The render method reads Village::getInstance(). Substitute only for this
    // no-side-effect branch and restore the real singleton immediately.
    $villageReflection = new \ReflectionClass(Village::class);
    $villageSingleton = $villageReflection->getProperty('_self');
    $originalVillage = $villageSingleton->getValue();
    try {
        $villageSingleton->setValue(null, $palaceResidenceOnly);
        expectFinishConstructionCapability(
            (new GoldHelper())->finishNowButton() === '',
            'GoldHelper must render no Finish now affordance for a disabled Palace/Residence-only queue.'
        );
    } finally {
        $villageSingleton->setValue(null, $originalVillage);
    }
    expectFinishConstructionCapability(
        FinishConstruction::hasEligibleFinishNowQueues([25], true, false, false)
            && FinishConstruction::hasEligibleFinishNowQueues([26], false, true, false)
            && FinishConstruction::hasEligibleFinishNowQueues([25, 1], false, false, false),
        'Research, demolition, or another eligible building must retain Finish now.'
    );

    $config->dynamic->finishConstruction = 1;
    expectFinishConstructionCapability(
        FinishConstruction::canFinishWithGold(25, false)
            && FinishConstruction::canFinishWithGold(26, false)
            && FinishConstruction::hasEligibleFinishNowQueues([25, 26], false, false, false),
        'An enabled speed world must permit both Residence and Palace gold completion.'
    );
    expectFinishConstructionCapability(
        FinishConstruction::hasEligibleFinishNowOrder($palaceResidenceOnly),
        'The queue-reading predicate must permit Palace/Residence-only completion when the speed-world option is enabled.'
    );
    expectFinishConstructionCapability(
        FinishConstruction::canFinishWithGold(1, false),
        'The capability must not make ordinary Finish now construction ineligible.'
    );
    expectFinishConstructionCapability(
        !FinishConstruction::canFinishWithGold(40, true)
            && !FinishConstruction::canFinishWithGold(40, false)
            && !FinishConstruction::hasEligibleFinishNowQueues([40], false, false, false)
            && !FinishConstruction::hasEligibleFinishNowQueues([1], true, true, true),
        'World Wonder construction must be denied even when the world capability is enabled or directly targeted.'
    );
} finally {
    $config->game->speed = $originalSpeed;
    if ($hadFinishConstruction) {
        $config->dynamic->finishConstruction = $originalFinishConstruction;
    } else {
        unset($config->dynamic->finishConstruction);
    }
}

expectFinishConstructionCapability(
    WorldOverrides::normalize(['finishConstruction' => '1']) === ['finishConstruction' => true],
    'The installer must accept the speed-world construction capability as a boolean world option.'
);
$overrideProbe = (object)['dynamic' => (object)['finishConstruction' => false]];
WorldOverrides::apply($overrideProbe, ['finishConstruction' => true]);
expectFinishConstructionCapability(
    $overrideProbe->dynamic->finishConstruction === false,
    'The install-time setting must not overwrite an admin-edited world capability.'
);

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        failFinishConstructionCapability('Unable to read ' . $path . '.');
    }

    return $contents;
};

$goldHelper = $read('game/include/Game/GoldHelper.php');
$popup = $read('game/include/Controller/Ajax/premium/instantCompletion.php');
$premium = $read('game/include/Controller/Ajax/premium.php');
$extraModules = $read('game/include/Game/ExtraModules.php');
$villageRuntime = $read('game/include/Core/Village.php');
$admin = $read('game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php');
$setup = $read('game/deployment/public/setup.php') . $read('game/include/Game/Installer/InstallInput.php') . $read('game/include/Game/Installer/WorldInstaller.php');
$schema = $read('database/world.sql');
$update = $read('game/include/update.php');
$version = $read('game/include/Game/WorldSchemaVersion.php');
$translation = $read('game/include/resources/Translation/en-US.php');

$finishStart = strpos($goldHelper, 'public function finishImmediatelyBuildings(): string');
$wwGuard = $finishStart === false ? false : strpos($goldHelper, 'if ($village->isWW())', $finishStart);
$wwReturn = $finishStart === false ? false : strpos($goldHelper, "return 'worldWonder';", $finishStart);
$transaction = $finishStart === false ? false : strpos($goldHelper, '$db->begin_transaction()', $finishStart);
$eligibleMutationGuard = $finishStart === false
    ? false
    : strpos($goldHelper, 'if (!FinishConstruction::canFinishWithGold($itemId, $village->isWW()))', $finishStart);
$eligibleMutationSkip = $eligibleMutationGuard === false
    ? false
    : strpos($goldHelper, 'continue;', $eligibleMutationGuard);
$noEligibleOrders = $finishStart === false
    ? false
    : strpos($goldHelper, 'if ($buildings === [] && $researchRows === [] && $demolitionRows === [])', $finishStart);
$goldDebit = $finishStart === false
    ? false
    : strpos($goldHelper, 'self::decreaseGold($uid, $cost, true)', $finishStart);
expectFinishConstructionCapability(
    $wwGuard !== false && $wwReturn !== false && $transaction !== false && $wwGuard < $transaction && $wwReturn < $transaction,
    'finishNow must reject a World Wonder before it can lock or debit a construction queue.'
);
expectFinishConstructionCapability(
    $eligibleMutationGuard !== false
        && $eligibleMutationSkip !== false
        && $noEligibleOrders !== false
        && $goldDebit !== false
        && $eligibleMutationGuard < $eligibleMutationSkip
        && $eligibleMutationSkip < $noEligibleOrders
        && $noEligibleOrders < $goldDebit,
    'The gold mutation must skip disabled Palace/Residence orders and return noOrders before any debit.'
);
expectFinishConstructionCapability(
    str_contains($goldHelper, 'FinishConstruction::canFinishWithGold($itemId, $village->isWW())')
        && str_contains($popup, 'FinishConstruction::canFinishWithGold($itemId, $village->isWW())')
        && str_contains($goldHelper, 'FinishConstruction::hasEligibleFinishNowOrder($village)')
        && str_contains($popup, 'FinishConstruction::hasEligibleFinishNowQueues('),
    'The Finish now mutation, popup, and button must share Palace/Residence eligibility.'
);
expectFinishConstructionCapability(
    str_contains($translation, "['finishNow']['finishNow'] = 'Complete construction immediately'"),
    'The Finish now button label must match capitalization exactly.'
);
$finishNowButtonStart = strpos($goldHelper, 'public function finishNowButton()');
$finishNowEligibility = $finishNowButtonStart === false
    ? false
    : strpos($goldHelper, 'FinishConstruction::hasEligibleFinishNowOrder($village)', $finishNowButtonStart);
$finishNowSitter = $finishNowButtonStart === false
    ? false
    : strpos($goldHelper, 'if (!$hasPerm)', $finishNowButtonStart);
$finishNowEligibilityGuard = $finishNowButtonStart === false || $finishNowSitter === false
    ? ''
    : substr($goldHelper, $finishNowButtonStart, $finishNowSitter - $finishNowButtonStart);
expectFinishConstructionCapability(
    $finishNowEligibility !== false
        && $finishNowSitter !== false
        && $finishNowEligibility < $finishNowSitter
        && !str_contains($finishNowEligibilityGuard, '!$village->isWW() &&')
        && str_contains($finishNowEligibilityGuard, "return ''"),
    'The Finish now button must hide any ineligible queue, including Palace/Residence-only and World Wonder queues, before rendering an active or sitter-disabled control.'
);
expectFinishConstructionCapability(
    str_contains($popup, 'if ($village->isWW())') && str_contains($popup, "return T('inGame', 'finishNowWWDisabled')"),
    'The instant-completion endpoint must also deny direct World Wonder requests.'
);
expectFinishConstructionCapability(
    !str_contains($premium, "'finishNow' => 'finishNow'")
        && str_contains($popup, '(new GoldHelper())->finishImmediatelyBuildings()'),
    'The instant-completion action must continue through the guarded GoldHelper mutation.'
);
expectFinishConstructionCapability(
    str_contains($extraModules, "'upgradeToMaxLevel' => 'upgradeToMaxLevel'")
        && str_contains($extraModules, 'BuildingAction::upgrade(')
        && str_contains($premium, 'ExtraModules::runAction('),
    'Upgrade to max level is the gold ExtraModules action: instant max for gold, no resources or queue.'
);
$buyBuildingsStart = strpos($premium, 'private function buyBuildings($featureKey)');
$buyBuildingsEnd = $buyBuildingsStart === false
    ? false
    : strpos($premium, "\n    public function ", $buyBuildingsStart + 1);
$buyBuildings = $buyBuildingsStart === false || $buyBuildingsEnd === false
    ? ''
    : substr($premium, $buyBuildingsStart, $buyBuildingsEnd - $buyBuildingsStart);
$buyBuildingsPolicy = strpos($buyBuildings, 'FinishConstruction::canFinishWithGold($buildingGid, $village->isWW())');
$buyBuildingsGold = strpos($buyBuildings, '$this->spendGold(');
expectFinishConstructionCapability(
    $buyBuildingsPolicy !== false
        && $buyBuildingsGold !== false
        && $buyBuildingsPolicy < $buyBuildingsGold,
    'The direct premium buyBuildings action must deny World Wonder and disabled Palace/Residence construction before charging gold.'
);
expectFinishConstructionCapability(
    str_contains($buyBuildings, "!\$config->extraSettings->buyBuildings['enabled']")
        && str_contains($buyBuildings, '!$realPackage->enabled'),
    'The direct premium buyBuildings action must respect both its global and package feature switches.'
);
$constructStart = strpos($villageRuntime, 'public function constructBuilding($field, $gid, $isMaster)');
$constructEnd = $constructStart === false
    ? false
    : strpos($villageRuntime, "\n    public function ", $constructStart + 1);
$constructBuilding = $constructStart === false || $constructEnd === false
    ? ''
    : substr($villageRuntime, $constructStart, $constructEnd - $constructStart);
$constructWwGuard = strpos($constructBuilding, 'if ($isMaster && $this->isWW() && $item_id <> 40)');
$constructQueueInsert = strpos($constructBuilding, 'INSERT INTO building_upgrade');
expectFinishConstructionCapability(
    $constructWwGuard !== false
        && $constructQueueInsert !== false
        && $constructWwGuard < $constructQueueInsert,
    'The legacy master-builder construct action must deny non-World-Wonder building queues in World Wonder villages before queuing or charging gold.'
);

foreach ([
    'finishConstructionForm()',
    "isset(\$_POST['finishConstruction'])",
    "UPDATE config SET finishConstruction=?",
    'Session::getCheckerInput',
    "Caching::getInstance()->delete('WorldConfig')",
] as $needle) {
    expectFinishConstructionCapability(
        str_contains($admin, $needle),
        'The admin setting must be CSRF-protected, persisted, and cache-invalidated: missing ' . $needle
    );
}
expectFinishConstructionCapability(
    str_contains($setup, 'finishConstruction, message')
        && str_contains($setup, "bind_param('iiiisssi'")
        && str_contains($setup, 'FinishConstruction::isSpeedWorld')
        && str_contains($setup, "'FinishConstruction'")
        && str_contains($setup, 'use Game\\FinishConstruction;'),
    'The pre-bootstrap installer must load and persist the option while rejecting it at x1.'
);
expectFinishConstructionCapability(
    str_contains($schema, chr(96) . 'finishConstruction' . chr(96) . " TINYINT(1) UNSIGNED NOT NULL DEFAULT '0'")
        && preg_match('/public const CURRENT = (\d+);/', $version, $versionMatch) === 1
        && (int)$versionMatch[1] >= 110
        && str_contains($update, 'if ($currentVersion < 110)')
        && str_contains($update, 'ADD COLUMN IF NOT EXISTS ' . chr(96) . 'finishConstruction' . chr(96)),
    'Fresh and existing worlds must receive the persisted finishConstruction capability.'
);
$finishMigrationStart = strpos($update, 'if ($currentVersion < 110)');
$finishMigrationEnd = $finishMigrationStart === false
    ? false
    : strpos($update, "\n}", $finishMigrationStart);
$finishMigration = $finishMigrationStart === false || $finishMigrationEnd === false
    ? ''
    : substr($update, $finishMigrationStart, $finishMigrationEnd - $finishMigrationStart);
expectFinishConstructionCapability(
    $finishMigration !== ''
        && !str_contains($finishMigration, 'AFTER ' . chr(96) . 'supportEmail' . chr(96)),
    'The finishConstruction migration must also work on older config tables without supportEmail.'
);

echo "Finish construction capability contract passed.\n";
