<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$alliance = file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxAlliance.php');
$gameView = file_get_contents($root . '/game/include/resources/View/GameView.php');
$bundles = [
    $root . '/game/deployment/public/game-client.js',
];

if (!is_string($alliance) || !is_string($gameView)) {
    fwrite(STDERR, "Unable to read the alliance sidebar sources.\n");
    exit(1);
}

if (!str_contains($gameView, "if (\$this->session->getAllianceId() <= 0) {\n            return;\n        }")) {
    fwrite(STDERR, "Players outside an alliance must not receive the legacy empty alliance sidebar card.\n");
    exit(1);
}

foreach ([
    "Travian.Game.Layout.toggleBox(box, 'travian_toggle', 'alliance')",
    "'alliance_collapsed'",
    "'alliance_expanded'",
] as $contract) {
    if (!str_contains($alliance, $contract)) {
        fwrite(STDERR, "Alliance sidebar expansion must use its own preference and translations.\n");
        exit(1);
    }
}

if (substr_count($alliance, '"disabled"     => $vars[\'aid\'] <= 0,') !== 2) {
    fwrite(STDERR, "Alliance and forum button metadata must match their rendered disabled state.\n");
    exit(1);
}

foreach ($bundles as $path) {
    $bundle = file_get_contents($path);
    if (!is_string($bundle)) {
        fwrite(STDERR, "Unable to read a client bundle.\n");
        exit(1);
    }

    if (!str_contains($bundle, 'e.find("button.toggleBox").prop("title", d)')
        || !str_contains($bundle, '"infobox" === i && this.setInfoboxItemsRead()')) {
        fwrite(STDERR, "Sidebar toggles must update their real control and only mark infobox messages as read.\n");
        exit(1);
    }
}

echo "Sidebar toggle client contract passed.\n";
