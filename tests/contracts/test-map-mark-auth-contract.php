<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$endpoints = [
    'flag' => $root . '/game/include/Controller/Ajax/map/flag.php',
    'multi-mark' => $root . '/game/include/Controller/Ajax/map/multiMark.php',
];

foreach ($endpoints as $endpoint => $path) {
    $source = file_get_contents($path);
    if (!is_string($source) || !str_contains($source, '!$this->session->isValid()')) {
        fwrite(STDERR, "Map {$endpoint} writes must require an authenticated session.\n");
        exit(1);
    }
    if (!str_contains($source, '403')) {
        fwrite(STDERR, "Map {$endpoint} authentication failures must be forbidden.\n");
        exit(1);
    }
}

$client = file_get_contents($root . '/game/deployment/public/game-client.js');
$legacyErrorPath = ".toElement()\n                            .down(\".errorMessage\")";
$jqueryErrorPath = ".toElement()\n                            .find(\".errorMessage\")";
if (!is_string($client)
    || str_contains($client, $legacyErrorPath)
    || !str_contains($client, $jqueryErrorPath)) {
    fwrite(STDERR, "Rejected map-flag edits must render their error through the jQuery dialog wrapper.\n");
    exit(1);
}

$deleteErrorRecovery = "error: function (t) {\n"
    . "                      new Travian.Dialog.Dialog({ preventFormSubmit: !0 })\n"
    . "                        .setContent(t.message)\n"
    . "                        .show();\n"
    . "                    },";
if (substr_count($client, $deleteErrorRecovery) < 2) {
    fwrite(STDERR, "Rejected map marker and flag deletes must show an error to the player.\n");
    exit(1);
}

// Wire format: the reference client sends flat bodies, `variant` for the colour or flag
// index, `id` plus type "flag" or "marker" to map/flag for deletes, and no owner on edits.
foreach ([
    'data: { id: this.dataId, type: "flag" },',
    'data: { id: this.dataId, type: "marker" },',
    'data: { variant: t, id: this.dataId },',
    'data: { id: this.dataId, variant: t, text: i },',
    'variant: e.color || 0,',
    'variant: e.index || this.indexes.min,',
] as $needle) {
    if (!is_string($client) || !str_contains($client, $needle)) {
        fwrite(STDERR, "The map mark client must send the reference request shape: {$needle}\n");
        exit(1);
    }
}
foreach (['type: "mark"', 'color: t,', 'color: e.index', 'dataId: this.dataId,'] as $legacy) {
    if (str_contains($client, $legacy)) {
        fwrite(STDERR, "The old map mark request shape ({$legacy}) must stay removed.\n");
        exit(1);
    }
}
$flag = (string)file_get_contents($endpoints['flag']);
$multiMark = (string)file_get_contents($endpoints['multi-mark']);
foreach ([$flag, $multiMark] as $source) {
    if (str_contains($source, "\$_POST['data']") || str_contains($source, "\$data['color']") || str_contains($source, "\$data['dataId']")) {
        fwrite(STDERR, "Map mark handlers must read the flat reference body (variant, id), not the old shape.\n");
        exit(1);
    }
}
if (!str_contains($flag, "['variant']") || !str_contains($flag, "['id']") || str_contains($multiMark, 'performDelete')) {
    fwrite(STDERR, "Marks are deleted through map/flag with type marker; multiMark has no delete.\n");
    exit(1);
}

echo "Map mark authentication contract passed.\n";
