#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: the active Account preferences validation messages match the
 * current English player bundle.
 */

function failOptionsAccountReferenceCopy(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$controller = file_get_contents($root . '/game/include/Controller/OptionCtrl.php');

if ($translation === false || $controller === false) {
    failOptionsAccountReferenceCopy('Unable to read Account preference sources.');
}

foreach ([
    "\$Definition['Options']['Name black listed'] = 'The new account name does either already exist or is not allowed. Please try another name.';",
    "\$Definition['Options']['Name exists'] = 'The new account name does either already exist or is not allowed. Please try another name.';",
] as $expected) {
    if (!str_contains($translation, $expected)) {
        failOptionsAccountReferenceCopy('Missing Account copy: ' . $expected);
    }
}

foreach ([
    'T("Options", "Name black listed")',
    'T("Options", "Name exists")',
] as $expected) {
    if (!str_contains($controller, $expected)) {
        failOptionsAccountReferenceCopy('Account preferences no longer render covered copy: ' . $expected);
    }
}

echo "Options Account reference copy contract passed.\n";
