<?php

declare(strict_types=1);

function failScalarControllerInputContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'email verification' => $root . '/game/include/Controller/EmailVerificationUrlCtrl.php',
    'reports' => $root . '/game/include/Controller/ReportsCtrl.php',
    'world wonder naming' => $root . '/game/include/Controller/BuildCtrl.php',
    'alliance founding' => $root . '/game/include/Controller/Build/EmbassyCtrl.php',
];

foreach ($sources as $name => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        failScalarControllerInputContract("Could not read the $name controller.");
    }
    if (str_contains($source, 'FILTER_SANITIZE_STRING')) {
        failScalarControllerInputContract("The $name controller must not use the deprecated string filter.");
    }
    $sources[$name] = $source;
}

if (!str_contains($sources['email verification'], "is_string(\$code)")
    || !str_contains($sources['email verification'], 'explode("-", $code)')) {
    failScalarControllerInputContract('Email verification must accept only a scalar, two-part verification code.');
}

if (!str_contains($sources['reports'], "strip_tags(is_string(\$id) ? \$id : '')")
    || !str_contains($sources['reports'], "strip_tags(is_string(\$_GET['id']) ? \$_GET['id'] : '')")) {
    failScalarControllerInputContract('Report identifiers must accept only strings.');
}

if (!str_contains($sources['world wonder naming'], "\$wwNameInput = \$_POST['ww_name'] ?? null")
    || !str_contains($sources['world wonder naming'], 'is_string($wwNameInput)')) {
    failScalarControllerInputContract('World Wonder naming must accept only string names.');
}

if (!str_contains($sources['alliance founding'], "is_string(\$_POST['newTag'])")
    || !str_contains($sources['alliance founding'], "is_string(\$_POST['newName'])")) {
    failScalarControllerInputContract('Alliance founding must accept only string names and tags.');
}

echo "Scalar controller input contract passed.\n";
