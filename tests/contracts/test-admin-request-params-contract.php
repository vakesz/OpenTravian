<?php

declare(strict_types=1);

// The admin controllers must never put request values into SQL text or file paths unvalidated,
// and templates must escape the values they echo back. Static, so it runs anywhere.

function failAdminInput(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2) . '/game/include/admin/include';
$controllers = glob($root . '/Controllers/*Ctrl.php') ?: [];
if (count($controllers) < 40) {
    failAdminInput('Expected the admin controllers to be found.');
}

foreach ($controllers as $file) {
    $name = basename($file);
    $source = (string)file_get_contents($file);
    $source = preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $source) ?? $source;

    // Integer casts are safe in SQL text; everything else must be bound.
    $source = preg_replace('/\(int\)\s*\$_(?:GET|POST|REQUEST)\[[^\]]+\]/', '0', $source) ?? $source;

    // A request value interpolated into a string, or concatenated into a query call.
    if (preg_match('/\{\$_(GET|POST|REQUEST)\b/', $source)) {
        failAdminInput("$name interpolates a request value into a string; bind it instead.");
    }
    if (preg_match('/->(?:query|multi_query)\([^;]*\$_(GET|POST|REQUEST)\b/', $source)) {
        failAdminInput("$name builds SQL from a request value; use run() with placeholders.");
    }
    if (preg_match('/\b(?:include|require)(?:_once)?\b[^;]*\$_(GET|POST|REQUEST)\b/', $source)) {
        failAdminInput("$name builds a file path from a request value.");
    }
    // explode(...) on a request value without an is_string guard blows up on arrays and missing keys.
    if (preg_match('/explode\([^;]*\$_(GET|POST|REQUEST)\[[^\]]+\]\s*\)/', $source)) {
        failAdminInput("$name explodes a raw request value; check is_string first.");
    }
    if (preg_match('/\$_(?:GET|POST|REQUEST)\[[^\]]+\]\s*(?:==|!=)\s*[\'"]/', $source)
        && !in_array($name, ['EditPlayerCtrl.php', 'ConfigurationDetailsCtrl.php', 'MinimapCtrl.php'], true)) {
        failAdminInput("$name compares a raw request value loosely; use === with an is_string guard.");
    }
}

// Numeric ids are validated, not just cast, where they select what to repair or edit.
$fixes = (string)file_get_contents($root . '/Controllers/FixesCtrl.php');
if (!str_contains($fixes, "ctype_digit(\$_GET['fixKid'])") || str_contains($fixes, 'is_numeric')) {
    failAdminInput('FixesCtrl must accept only digit strings for fixKid.');
}

// Hero auction/add-item/cleanup bounds.
$bounds = [
    'HeroAuctionCtrl.php' => 'min($int(\'repeat\'), 100)',
    'CleanupCtrl.php' => 'min((int)$_REQUEST[\'days\'], 36500)',
    'PreRegistrationCodeBatchCtrl.php' => 'min((int)$_REQUEST[\'count\'], 1000)',
    'GoldPackageCodeGeneratorCtrl.php' => 'min(abs((int)$_REQUEST[\'count\']), 1000)',
    'AddNatarsFarmCtrl.php' => 'min((int)$_REQUEST[\'village_count\'], 100)',
];
foreach ($bounds as $file => $needle) {
    if (!str_contains((string)file_get_contents($root . '/Controllers/' . $file), $needle)) {
        failAdminInput("$file must bound its repeat/count parameter.");
    }
}

// The dispatcher only loads controllers whose name is a plain identifier.
$dispatcher = (string)file_get_contents($root . '/Core/Dispatcher.php');
if (!str_contains($dispatcher, "preg_match('/^[A-Za-z][A-Za-z0-9]*\$/'") || !str_contains($dispatcher, "is_string(\$_REQUEST['action']")) {
    failAdminInput('Dispatcher must restrict the action name before resolving a controller file.');
}

// Templates escape the values they reflect. Only the generated-codes box carries markup.
foreach (glob($root . '/templates/*.php') ?: [] as $file) {
    $name = basename($file);
    $source = (string)file_get_contents($file);
    $pattern = '/<\?= \$params\[\'(error|result|email|ip|emails|words|tag|name|desc1|desc2|reason|html|message)\'\]; \?>/';
    if ($name === 'addPaymentCodeBatch.php') {
        continue;
    }
    if (preg_match($pattern, $source, $match) || preg_match('/\. \$params\[\'(error|result)\'\] \./', $source)) {
        failAdminInput("$name echoes params['" . ($match[1] ?? 'error/result') . "'] without AdminUi::esc.");
    }
}

echo "Admin request params contract passed.\n";
