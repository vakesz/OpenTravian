<?php

declare(strict_types=1);

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/tooltip/layoutButton.php');

if ($controller === false
    || !str_contains($controller, 'T("embassyWhite", "embassy")')
    || !str_contains($controller, 'T("embassyWhite", "invites to you:")')
    || !str_contains($controller, 'T("embassyWhite", "max embassy level:")')
    || !str_contains($controller, 'T("embassyWhite", "construct an embassy")')
    || str_contains($controller, 'T("embassy", "embassy")')) {
    fwrite(STDERR, "Alliance layout-button tooltips must use the available embassyWhite translations.\n");
    exit(1);
}

echo "Layout-button tooltip translation contract passed.\n";
