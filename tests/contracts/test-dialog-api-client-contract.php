<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$clientFiles = [
    $root . '/game/deployment/public/game-client.js',
];
$ajaxRequestErrorHandler = "error: function (t) {\n"
    . "          e.setContent(t.message).show();\n"
    . "        },";
$apiRequestErrorHandler = "error: function (t) {\n"
    . "            e.setContent(t.message).show();\n"
    . "          },";
$dialogNavigationErrorHandler = "'<div class=\"error\">' +\n"
    . "                        (e.message || \"Request failed.\") +\n"
    . "                        \"</div>\",";
foreach ($clientFiles as $clientFile) {
    $source = file_get_contents($clientFile);
    if (!is_string($source)
        || !str_contains($source, $ajaxRequestErrorHandler)
        || !str_contains($source, $apiRequestErrorHandler)
        || !str_contains($source, $dialogNavigationErrorHandler)) {
        fwrite(STDERR, "AJAX dialogs must render rejected requests instead of silently staying blank.\n");
        exit(1);
    }
}

echo "Dialog API client contract passed.\n";
