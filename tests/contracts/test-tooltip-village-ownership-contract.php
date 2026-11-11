<?php

declare(strict_types=1);

// tooltip/layoutButton (and incomingTroops, which extends it) may only report on villages the
// session player owns.

$root = dirname(__DIR__, 2);
$source = (string)file_get_contents($root . '/game/include/Controller/Ajax/tooltip/layoutButton.php');
$start = strpos($source, 'resolveIncomingTroopsVillageId');
if ($start === false) {
    fwrite(STDERR, "The incoming-troops village resolver was not found.\n");
    exit(1);
}
if (!str_contains($source, "\$_POST['villageIds']") || !str_contains($source, "\$_POST['villageId']")) {
    fwrite(STDERR, "Incoming-troops tooltips must accept villageIds and villageId.\n");
    exit(1);
}
$branch = substr($source, $start);
foreach (['FILTER_VALIDATE_INT', 'SELECT owner FROM vdata WHERE kid=?', 'getPlayerId()', 'return;'] as $needle) {
    if (!str_contains($branch, $needle)) {
        fwrite(STDERR, "The villageId tooltip must check ownership ({$needle}).\n");
        exit(1);
    }
}
if (strpos($branch, 'return;') > strpos($branch, 'FROM movement WHERE to_kid=')) {
    fwrite(STDERR, "The ownership check must run before incoming attacks are read.\n");
    exit(1);
}
if (str_contains($source, 'FILTER_SANITIZE_INT')) {
    fwrite(STDERR, "FILTER_SANITIZE_INT does not exist; validate the id instead.\n");
    exit(1);
}

echo "Tooltip village ownership contract passed.\n";
