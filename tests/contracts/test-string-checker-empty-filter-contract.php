#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: empty bad-word / URL filter files must not reject every name.
 *
 * explode(",", "") yields ['']; strpos($name, '') === 0 always "matches".
 */

use Core\Helper\StringChecker;

function assertFilter(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

assertFilter(StringChecker::isValidName('AlphaBase'), 'AlphaBase must be a valid village name with empty/normal filters.');
assertFilter(StringChecker::isValidName('TestVillage'), 'TestVillage must be a valid village name with empty/normal filters.');
assertFilter(StringChecker::isValidName('player'), 'Existing account-style names must remain valid.');

$badWords = (string) file_get_contents($root . '/game/include/Core/Helper/BadWordsFilter.php');
$checker = (string) file_get_contents($root . '/game/include/Core/Helper/StringChecker.php');
assertFilter(str_contains($badWords, "static fn (string \$token): bool => \$token !== ''"), 'BadWordsFilter must drop empty tokens.');
assertFilter(str_contains($checker, "static fn (string \$token): bool => \$token !== ''"), 'StringChecker::checkUrls must drop empty tokens.');

echo "String checker empty-filter contract passed.\n";
