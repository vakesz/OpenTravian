<?php

declare(strict_types=1);

/**
 * Contract: player-facing templates/controllers must not ship legacy nested Button V1 chrome
 * or bare getButton class => green (must be textButtonV1 green).
 */

function failLegacyButtonChromeContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * Bare `'class' => 'green…'` / `"class" => "green…"` (not textButtonV1 / layoutButton / framed).
 */
function legacyButtonChromeHasBareGreenClass(string $contents): bool
{
    return (bool) preg_match(
        '/([\'"])class\1\s*=>\s*([\'"])green(\s|disabled|small|\2)/',
        $contents
    );
}

$root = dirname(__DIR__, 2);
$paths = [
    'game/include/resources/Templates/options/VacationActive.php',
    'game/include/resources/Templates/verify/newProgress.php',
    'game/include/resources/Templates/verify/inProgress.php',
    'game/include/resources/Templates/system/recaptcha.php',
    'game/include/resources/Templates/layout/head.php',
    'game/include/resources/Templates/alliance/editTopic.php',
    'game/include/resources/Templates/alliance/forum_edit.php',
    'game/include/resources/Templates/alliance/newForum.php',
    'game/include/resources/Templates/alliance/newPost.php',
    'game/include/resources/Templates/alliance/new_topic.php',
    'game/include/resources/Templates/alliance/showTopic.php',
    'game/include/resources/Templates/alliance/bonuses/donateForm.php',
];

foreach ($paths as $relative) {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false) {
        failLegacyButtonChromeContract("Unable to read {$relative}");
    }
    if (str_contains($contents, 'button-container addHoverClick')) {
        failLegacyButtonChromeContract("{$relative} still embeds legacy button-container chrome.");
    }
}

$head = (string) file_get_contents($root . '/game/include/resources/Templates/layout/head.php');
if (!str_contains($head, "\$buttonTemplate = '<button ></button>';")) {
    failLegacyButtonChromeContract('layout/head.php ButtonTemplate must be the plain ButtonV1 shell.');
}

foreach ([
    'game/include/resources/Templates/options/VacationActive.php' => 'textButtonV1 gold',
    'game/include/resources/Templates/verify/newProgress.php' => 'textButtonV1 green',
    'game/include/resources/Templates/verify/inProgress.php' => 'textButtonV1 green',
    'game/include/resources/Templates/system/recaptcha.php' => 'textButtonV1 green',
    'game/include/resources/Templates/alliance/editTopic.php' => 'textButtonV1 green',
    'game/include/resources/Templates/alliance/forum_edit.php' => 'textButtonV1 green',
    'game/include/resources/Templates/alliance/newForum.php' => 'textButtonV1 green',
    'game/include/resources/Templates/alliance/newPost.php' => 'textButtonV1 green',
    'game/include/resources/Templates/alliance/new_topic.php' => 'textButtonV1 green',
    'game/include/resources/Templates/alliance/showTopic.php' => 'textButtonV1 green',
    'game/include/resources/Templates/alliance/bonuses/donateForm.php' => 'textButtonV1 green',
] as $relative => $needle) {
    $contents = (string) file_get_contents($root . '/' . $relative);
    if (!str_contains($contents, $needle)) {
        failLegacyButtonChromeContract("{$relative} must use {$needle}.");
    }
}

$helpers = [
    'game/include/Controller/PasswordCtrl.php',
    'game/include/Controller/RallyPoint/RallyPoint.php',
    'game/include/Controller/RallyPoint/RallyPointCtrl.php',
    'game/include/Controller/Build/TownhallCtrl.php',
    'game/include/Controller/Build/SmithyCtrl.php',
    'game/include/Controller/Build/AcademyCtrl.php',
    'game/include/Controller/Build/BreweryCtrl.php',
    'game/include/Controller/MapCtrl.php',
    'game/include/Controller/BannedCtrl.php',
    'game/include/Controller/AllianceForum.php',
    'game/include/functions.general.php',
];
foreach ($helpers as $relative) {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false) {
        failLegacyButtonChromeContract("Unable to read {$relative}");
    }
    if ($relative === 'game/include/functions.general.php') {
        if (str_contains($contents, 'button-background')
            || str_contains($contents, 'button-content')) {
            failLegacyButtonChromeContract('getButton() must not emit nested Button V1 background/content chrome.');
        }
        $layoutOffset = strpos($contents, "if (str_contains(\$buttonClass, 'layoutButton'))");
        $legacyChromeOffset = strpos($contents, 'button-container addHoverClick');
        if ($layoutOffset === false || $legacyChromeOffset === false || $legacyChromeOffset < $layoutOffset) {
            failLegacyButtonChromeContract(
                'getButton() may only emit button-container addHoverClick inside the layoutButton icon shell.'
            );
        }
        if (!str_contains($contents, "trim('textButtonV1 ' . \$buttonClass)")) {
            failLegacyButtonChromeContract('getButton() must normalize bare classes to textButtonV1.');
        }
        continue;
    }
    if (str_contains($contents, 'button-container addHoverClick')) {
        failLegacyButtonChromeContract("{$relative} still embeds legacy button-container chrome.");
    }
    if (legacyButtonChromeHasBareGreenClass($contents)) {
        failLegacyButtonChromeContract("{$relative} still uses bare class => green; use textButtonV1 green.");
    }
    if (!str_contains($contents, 'textButtonV1 green')) {
        failLegacyButtonChromeContract("{$relative} must use textButtonV1 green.");
    }
}

$templateRoot = $root . '/game/include/resources/Templates';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS)
);
/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = 'game/include/resources/Templates/' . substr($file->getPathname(), strlen($templateRoot) + 1);
    $contents = file_get_contents($file->getPathname());
    if ($contents === false) {
        failLegacyButtonChromeContract("Unable to read {$relative}");
    }
    if (legacyButtonChromeHasBareGreenClass($contents)) {
        failLegacyButtonChromeContract(
            "{$relative} still uses bare 'class' => 'green' / \"class\" => \"green\"; allow textButtonV1 green only."
        );
    }
}

echo "Legacy button chrome contract passed.\n";
