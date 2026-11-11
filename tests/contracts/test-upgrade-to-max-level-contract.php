<?php

declare(strict_types=1);

/**
 * Contract: gold "Upgrade to max level" (speed-world shortcut) is ExtraModules::upgradeToMaxLevel,
 * gated by SpeedShortcuts, priced via extraSettings.upgradeToMaxLevel.coins (World rules editable),
 * and accepts nested WayOfPayment additionalData ({additionalData: {gid}} as well as {gid}).
 */

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/WorldOverrides.php';
require_once $root . '/game/include/Game/SpeedShortcuts.php';

function expectUpgradeToMaxLevel(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$extra = (string) file_get_contents($root . '/game/include/Game/ExtraModules.php');
$configSource = (string) file_get_contents($root . '/game/include/config.php');
$buildCtrl = (string) file_get_contents($root . '/game/include/Controller/BuildCtrl.php');
$buildRequest = (string) file_get_contents($root . '/game/include/Game/BuildRequest.php');
$wrapper = (string) file_get_contents($root . '/game/include/resources/Templates/build/buildingReadyWrapper.php');
$smithyLayout = (string) file_get_contents($root . '/game/include/resources/Templates/build/building/SmithyLayout.php');

expectUpgradeToMaxLevel(
    str_contains($extra, "'upgradeToMaxLevel' => 'upgradeToMaxLevel'")
        && str_contains($extra, 'public static function upgradeToMaxLevel(&$response, $additionalData): string'),
    'upgradeToMaxLevel must be a registered ExtraModules action.'
);

expectUpgradeToMaxLevel(
    str_contains($extra, 'private static function unwrapAdditionalData(mixed $additionalData): ?array')
        && preg_match(
            '/function upgradeToMaxLevel[\s\S]*?unwrapAdditionalData\(\$additionalData\)/',
            $extra
        ) === 1
        && preg_match(
            '/function smithyMaxLevel[\s\S]*?unwrapAdditionalData\(\$additionalData\)/',
            $extra
        ) === 1
        && str_contains($extra, "isset(\$additionalData['additionalData']) && is_array(\$additionalData['additionalData'])"),
    'upgradeToMaxLevel and smithyMaxLevel must unwrap nested WayOfPayment additionalData.'
);

expectUpgradeToMaxLevel(
    str_contains($extra, "in_array(\$name, ['upgradeToMaxLevel', 'smithyMaxLevel', 'smithyUpgradeAllToMax'], true)")
        && str_contains($extra, 'SpeedShortcuts::enabled()'),
    'upgradeToMaxLevel must be gated by SpeedShortcuts::enabled().'
);

expectUpgradeToMaxLevel(
    preg_match(
        "/'upgradeToMaxLevel'\\s*=>\\s*\\(object\\)\\s*\\[[^\\]]*?'coins'\\s*=>\\s*20/",
        $configSource
    ) === 1,
    'Default upgradeToMaxLevel gold price must be 20.'
);

expectUpgradeToMaxLevel(
    isset(\Game\WorldOverrides::DEFINITIONS['upgradeToMaxLevelGold'])
        && \Game\WorldOverrides::DEFINITIONS['upgradeToMaxLevelGold'][0] === 'extraSettings.upgradeToMaxLevel.coins',
    'World rules must expose upgradeToMaxLevelGold targeting the coins field.'
);

$probe = (object) [
    'extraSettings' => (object) [
        'upgradeToMaxLevel' => (object) ['enabled' => false, 'coins' => 20],
    ],
];
\Game\WorldOverrides::apply($probe, ['upgradeToMaxLevelGold' => 35]);
expectUpgradeToMaxLevel(
    (int) $probe->extraSettings->upgradeToMaxLevel->coins === 35,
    'WorldOverrides::apply must write nested upgradeToMaxLevel coin price.'
);

expectUpgradeToMaxLevel(
    str_contains($buildCtrl, "ExtraModules::showButton('upgradeToMaxLevel'")
        && str_contains($extra, '"class" => "textButtonV1 gold $name"'),
    'BuildCtrl must render the yellow gold upgradeToMaxLevel button via ExtraModules::showButton.'
);

expectUpgradeToMaxLevel(
    str_contains($buildRequest, 'ExtraModules::upgradeToMaxLevel')
        && !str_contains($buildRequest, 'upgradeBuildingToMax'),
    'BuildRequest must not call the old green &max queue path.'
);

expectUpgradeToMaxLevel(
    substr_count($wrapper, 'function getGidX()') >= 2
        && str_contains($wrapper, "ExtraModules::isEnabled('upgradeToMaxLevel')"),
    'buildingReadyWrapper must define getGidX while the shortcut is on.'
);

expectUpgradeToMaxLevel(
    str_contains($smithyLayout, 'return {additionalData: {nr:'),
    'Smithy getNr callbacks return nested additionalData (unwrap must keep working).'
);

echo "Upgrade to max level contract passed.\n";
