<?php

declare(strict_types=1);

function failLayoutRuntimeDefaultsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'buttons' => $root . '/game/include/functions.general.php',
    'infoBox' => $root . '/game/include/Model/InfoBoxModel.php',
    'gameView' => $root . '/game/include/resources/View/GameView.php',
    'layout' => $root . '/game/include/resources/Templates/layout/Layout.php',
    'head' => $root . '/game/include/resources/Templates/layout/head.php',
    'cache' => $root . '/game/include/Core/Caching/Caching.php',
];

foreach ($sources as $name => $path) {
    $sources[$name] = file_get_contents($path);
    if ($sources[$name] === false) {
        failLayoutRuntimeDefaultsContract("Could not read the $name source.");
    }
}

if (!str_contains($sources['buttons'], "isset(\$js['data']['boxId'], \$js['data']['buttonId'])")) {
    failLayoutRuntimeDefaultsContract('Layout-button tooltips must require both optional metadata fields.');
}
if (!str_contains($sources['infoBox'], 'trim((string)$db->fetchScalar')) {
    failLayoutRuntimeDefaultsContract('Unread infobox IDs must accept an empty aggregate.');
}
if (!str_contains($sources['gameView'], "\$view->vars['content'] = '';")) {
    failLayoutRuntimeDefaultsContract('The infobox view must initialize empty content.');
}
if (!str_contains($sources['gameView'], "'playerName' => htmlspecialchars((string) \$this->session->getName(), ENT_QUOTES, 'UTF-8')")) {
    failLayoutRuntimeDefaultsContract('The active-village sidebar must receive a player name.');
}
if (!str_contains($sources['layout'], 'strpos((string) ($vars["containerCssClass"] ?? ""), "contentPage")')) {
    failLayoutRuntimeDefaultsContract('The page shell must accept an absent container class.');
}
if (str_contains($sources['head'], 'Travian.Game.eventJamHtml =')) {
    failLayoutRuntimeDefaultsContract('The header must not emit the retired event-jam variable.');
}
if (str_contains($sources['head'], 'Travian.Game.Layout.MobileOptimizations.init();')
    || !str_contains($sources['layout'], 'mobile controls need the rendered sidebars and content')) {
    failLayoutRuntimeDefaultsContract('Mobile controls must initialize after the rendered layout, not from the document head.');
}
if (!str_contains($sources['head'], "\$viewportContent = (string) (\$vars['viewportContent'] ?? 'width=device-width')")
    || !str_contains($sources['head'], '<meta name="viewport" content="<?= htmlspecialchars($viewportContent, ENT_QUOTES, \'UTF-8\') ?>"/>')) {
    failLayoutRuntimeDefaultsContract('The shared head must retain the game viewport default while allowing a scoped public override.');
}
if (!str_contains($sources['head'], 'get_gpack_link_and_hash("imports.css", false, $documentDirection)')
    || str_contains($sources['head'], 'get_gpack_link_and_hash("default_compressed.css")')) {
    failLayoutRuntimeDefaultsContract('The header must load the maintained imports Gpack without the retired default stylesheet.');
}
if (!str_contains($sources['cache'], "\$indexUrlParts[1] ?? ''")) {
    failLayoutRuntimeDefaultsContract('The cache namespace must allow a localhost index URL.');
}

echo "Layout runtime defaults contract passed.\n";
