<?php

declare(strict_types=1);

// Smithy gold max (speed-world shortcut): gated by speedShortcuts, gold-only (no resources),
// default prices 20 / 100, World rules can edit the coins, buttons use the gold (yellow) style.

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/WorldOverrides.php';
require_once $root . '/game/include/Game/SpeedShortcuts.php';

function expectSmithyGold(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$configSource = (string) file_get_contents($root . '/game/include/config.php');
expectSmithyGold(
    str_contains($configSource, "'smithyMaxLevel'")
    && preg_match("/'smithyMaxLevel'\\s*=>\\s*\\(object\\)\\s*\\[[^\\]]*?'coins'\\s*=>\\s*20/", $configSource) === 1,
    'Default per-unit Smithy gold price must be 20.'
);
expectSmithyGold(
    preg_match("/'smithyUpgradeAllToMax'\\s*=>\\s*\\(object\\)\\s*\\[[^\\]]*?'coins'\\s*=>\\s*100/", $configSource) === 1,
    'Default upgrade-all Smithy gold price must be 100.'
);

expectSmithyGold(
    isset(\Game\WorldOverrides::DEFINITIONS['smithyMaxLevelGold'], \Game\WorldOverrides::DEFINITIONS['smithyUpgradeAllToMaxGold']),
    'World rules must expose both Smithy gold price keys.'
);
expectSmithyGold(
    \Game\WorldOverrides::DEFINITIONS['smithyMaxLevelGold'][0] === 'extraSettings.smithyMaxLevel.coins'
    && \Game\WorldOverrides::DEFINITIONS['smithyUpgradeAllToMaxGold'][0] === 'extraSettings.generalOptions.smithyUpgradeAllToMax.coins',
    'Smithy gold WorldOverrides paths must target the coins fields.'
);

$probe = (object) [
    'extraSettings' => (object) [
        'smithyMaxLevel' => (object) ['enabled' => false, 'coins' => 20],
        'generalOptions' => (object) [
            'smithyUpgradeAllToMax' => (object) ['enabled' => false, 'coins' => 100],
        ],
    ],
];
\Game\WorldOverrides::apply($probe, ['smithyMaxLevelGold' => 25, 'smithyUpgradeAllToMaxGold' => 80]);
expectSmithyGold(
    (int) $probe->extraSettings->smithyMaxLevel->coins === 25
    && (int) $probe->extraSettings->generalOptions->smithyUpgradeAllToMax->coins === 80,
    'WorldOverrides::apply must write nested Smithy coin prices.'
);

$extra = (string) file_get_contents($root . '/game/include/Game/ExtraModules.php');
expectSmithyGold(
    str_contains($extra, "in_array(\$name, ['upgradeToMaxLevel', 'smithyMaxLevel', 'smithyUpgradeAllToMax'], true)")
    && str_contains($extra, 'SpeedShortcuts::enabled()'),
    'Smithy gold actions must be gated by speedShortcuts.'
);
expectSmithyGold(
    str_contains($extra, 'public static function smithyUpgradeAllToMax(&$response): string')
    && str_contains($extra, 'public static function smithyMaxLevel(&$response, $additionalData): string'),
    'ExtraModules Smithy gold actions must keep their outcome-returning signatures.'
);
expectSmithyGold(
    preg_match(
        '/function smithyMaxLevel[\s\S]*?unwrapAdditionalData\(\$additionalData\)/',
        $extra
    ) === 1,
    'smithyMaxLevel must unwrap nested WayOfPayment additionalData from getNr callbacks.'
);
expectSmithyGold(
    str_contains($extra, '"class" => "textButtonV1 gold $name"')
    || str_contains($extra, '"class" => "textButtonV1 gold {$name}"'),
    'Smithy gold buttons must use the yellow gold button class.'
);
$smithyMaxBody = preg_match(
    '/public static function smithyMaxLevel\b[\s\S]*?(?=\n    public static function |\n    private static function |\n}\s*$)/',
    $extra,
    $smithyMaxMatch
) === 1 ? $smithyMaxMatch[0] : '';
$smithyAllBody = preg_match(
    '/public static function smithyUpgradeAllToMax\b[\s\S]*?(?=\n    public static function |\n    private static function |\n}\s*$)/',
    $extra,
    $smithyAllMatch
) === 1 ? $smithyAllMatch[0] : '';
expectSmithyGold(
    $smithyMaxBody !== ''
    && $smithyAllBody !== ''
    && !preg_match('/\b(wood|clay|iron|crop|modifyResources|deductResources)\b/', $smithyMaxBody)
    && !preg_match('/\b(wood|clay|iron|crop|modifyResources|deductResources)\b/', $smithyAllBody),
    'Smithy gold upgrades must not charge resources.'
);

$ctrl = (string) file_get_contents($root . '/game/include/Controller/Build/SmithyCtrl.php');
expectSmithyGold(
    str_contains($ctrl, "ExtraModules::smithyMaxLevelButton")
    && str_contains($ctrl, "ExtraModules::showButton(\"smithyUpgradeAllToMax\")"),
    'SmithyCtrl must render the per-row and upgrade-all gold buttons.'
);

$agents = (string) file_get_contents($root . '/docs/development.md');
expectSmithyGold(
    str_contains($agents, 'Smithy gold max')
    && str_contains($agents, 'smithyMaxLevelGold')
    && str_contains($agents, 'Train maximum'),
    'docs/development.md must document Train maximum and the Smithy gold max shortcut.'
);
$worldSpeed = (string) file_get_contents($root . '/docs/world-speed.md');
expectSmithyGold(
    str_contains($worldSpeed, 'Train maximum')
    && str_contains($worldSpeed, 'Smithy gold max')
    && str_contains($worldSpeed, 'smithyMaxLevelGold')
    && str_contains($worldSpeed, 'smithyUpgradeAllToMaxGold'),
    'docs/world-speed.md must document Train maximum and Smithy gold max prices.'
);

// Former NPC Balance / training Repeat shortcuts must stay gone from game code and tests.
$selfPath = realpath(__FILE__);
$scanRoots = [
    $root . '/game/include',
    $root . '/game/deployment',
    $root . '/tests',
];
// Split so this contract file does not match its own needles while scanning tests/.
$forbidden = [
    'Training' . 'Repeat',
    'balance' . 'Resources',
    'stop' . 'Repeat',
    'Balance ' . 'resources',
    'Repeat this ' . 'order',
];
foreach ($scanRoots as $scanRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $path = $fileInfo->getPathname();
        if ($selfPath !== false && realpath($path) === $selfPath) {
            continue;
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['php', 'js', 'md', 'html', 'css'], true)) {
            continue;
        }
        $contents = (string) file_get_contents($path);
        foreach ($forbidden as $needle) {
            expectSmithyGold(
                !str_contains($contents, $needle),
                "Removed speed-shortcut leftover '{$needle}' must not remain in {$path}."
            );
        }
    }
}
expectSmithyGold(
    !is_file($root . '/game/include/Game/TrainingRepeat.php'),
    'TrainingRepeat.php must stay deleted.'
);

echo "Smithy gold max contract passed.\n";
