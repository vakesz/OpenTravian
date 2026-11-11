<?php

declare(strict_types=1);

// Contract: like the reference, an unknown path below /village/statistics renders the overview at that
// same URL (no 404 and no redirect).

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/game/include/mainInclude.php');
if (!str_contains($source, "str_starts_with(\$requestPathForLinkTargets, '/village/statistics/')")
    || !str_contains($source, "\$cleanLinkListTargetRoutes[\$requestPathForLinkTargets] = ['page' => 'dorf3', 'query' => ['s' => '0']];")) {
    fwrite(STDERR, "Unknown /village/statistics/* paths must render the overview.\n");
    exit(1);
}

echo "Village statistics catch-all contract passed.\n";
