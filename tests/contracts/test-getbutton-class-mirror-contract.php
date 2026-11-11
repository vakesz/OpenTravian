<?php

declare(strict_types=1);

/**
 * Contract: getButton/getAButton mirror rendered class into buttonClicked payloads
 * when callers omit class or pass bare green/gold.
 */

function failGetButtonClassMirrorContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/game/include/functions.general.php');
if ($source === false) {
    failGetButtonClassMirrorContract('Unable to read functions.general.php.');
}

$needle = <<<'PHP'
$payloadClass = trim((string) ($js['data']['class'] ?? ''));
    if ($payloadClass === '' || $payloadClass === 'green' || $payloadClass === 'gold') {
PHP;

if (substr_count($source, $needle) < 2) {
    failGetButtonClassMirrorContract(
        'getButton/getAButton must both mirror rendered class into buttonClicked payloads.'
    );
}

echo "getButton class mirror contract passed.\n";
