<?php

declare(strict_types=1);

function failAdminInputContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'fake users' => $root . '/game/include/admin/include/Controllers/FakeUserCtrl.php',
];

foreach ($sources as $name => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        failAdminInputContract("Could not read the $name controller.");
    }
    if (str_contains($source, 'FILTER_SANITIZE_STRING')) {
        failAdminInputContract("The $name controller must not use the deprecated string filter.");
    }
    $sources[$name] = $source;
}

if (!str_contains($sources['fake users'], "is_string(\$names) ? strip_tags(\$names) : ''")) {
    failAdminInputContract('Admin names must be normalized only after confirming they are strings.');
}

echo "Admin input contract passed.\n";
