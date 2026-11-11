<?php

declare(strict_types=1);

/**
 * Rally Point's kill/free controls mutate troops and traps. Their rendered
 * links already carry the current checker, so the controller must consume it
 * before changing state. They must also keep a root-absolute target under the
 * shared <base href="/"> player layout.
 */
function failRallyPointMutationChecker(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPointCtrl.php');
$html = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPointHTML.php');

if ($controller === false || $html === false) {
    failRallyPointMutationChecker('Unable to read the Rally Point mutation sources.');
}

foreach ([
    "if (isset(\$_GET['kill']) && Session::claimRequest())",
    "if (isset(\$_GET['free']) && Session::claimRequest())",
] as $guard) {
    if (!str_contains($controller, $guard)) {
        failRallyPointMutationChecker("Rally Point mutation guard missing: {$guard}");
    }
}

foreach ([
    "\$query = \"/build.php?\" . http_build_query([",
    '<a class="arrow" href="/build.php?id=39&amp;tt=2&amp;free=',
    'Session::getCheckerName() => Session::getInstance()->getChecker()',
    'Session::getCheckerForUrl()',
] as $needle) {
    if (!str_contains($html, $needle)) {
        failRallyPointMutationChecker("Rally Point mutation link missing: {$needle}");
    }
}

if (str_contains($controller, "isset(\$_GET['kill'])/* && Session::claimRequest()*/")
    || str_contains($controller, "isset(\$_GET['free'])/* && Session::claimRequest()*/")) {
    failRallyPointMutationChecker('Rally Point must not leave mutation checker validation commented out.');
}

echo "Rally Point mutation checker contract passed.\n";
