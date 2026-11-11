<?php

declare(strict_types=1);

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/PageNotFoundCtrl.php');

if ($controller === false) {
    fwrite(STDERR, "Could not read the not-found controller.\n");
    exit(1);
}

foreach ([
    'http_response_code(404)',
    "'perspectiveBuildings login fatal_error'",
    "'login fatal_error'",
    "['containerCssClass'] = 'contentPage'",
    "htmlspecialchars((string)T(\"notFound\", \"desc\"), ENT_QUOTES, 'UTF-8')",
] as $contract) {
    if (!str_contains($controller, $contract)) {
        fwrite(STDERR, "The public not-found page must render the reference fatal-error shell.\n");
        exit(1);
    }
}

if (str_contains($controller, 'notFoundCard') || str_contains($controller, 'showHeaderBar') || str_contains($controller, 'showTime')) {
    fwrite(STDERR, "The public not-found page must use the reference fatal-error shell without legacy card chrome.\n");
    exit(1);
}

echo "Page-not-found contract passed.\n";
