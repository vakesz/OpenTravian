<?php

declare(strict_types=1);

function failLogErrorLiteralPercentContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$literal = 'Missing translation key retains its literal placeholder: %s';

try {
    $logged = logError($literal);
} catch (ValueError $error) {
    failLogErrorLiteralPercentContract('logError must accept literal percent placeholders without format arguments: ' . $error->getMessage());
}

if ($logged !== $literal) {
    failLogErrorLiteralPercentContract('logError must preserve a non-formatted literal error message.');
}

$formatted = logError('Formatted log message for %s', ['a supplied value']);
if ($formatted !== 'Formatted log message for a supplied value') {
    failLogErrorLiteralPercentContract('logError must retain formatting when arguments are supplied.');
}

echo "Literal-percent log-error contract passed.\n";
