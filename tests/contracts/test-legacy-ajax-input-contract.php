<?php

declare(strict_types=1);

function failLegacyAjaxInputContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'village rename' => $root . '/game/include/Controller/Ajax/village/changeNames.php',
    'player autocomplete' => $root . '/game/include/Controller/Ajax/autocomplete/playername.php',
    'address book' => $root . '/game/include/Controller/Ajax/addressbook/add.php',
    'recipient languages' => $root . '/game/include/Controller/Ajax/profileLanguages/recipients.php',
    'layout button tooltip' => $root . '/game/include/Controller/Ajax/tooltip/layoutButton.php',
    'premium account rename' => $root . '/game/include/Controller/Ajax/premium.php',
    'account-name model' => $root . '/game/include/Model/OptionModel.php',
    'options' => $root . '/game/include/Controller/OptionCtrl.php',
    'rally point' => $root . '/game/include/Controller/RallyPoint/SendTroops.php',
    'rally point base' => $root . '/game/include/Controller/RallyPoint/RallyPoint.php',
];

foreach ($sources as $name => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        failLegacyAjaxInputContract("Could not read the $name source.");
    }
    if (str_contains($source, 'FILTER_SANITIZE_STRING')) {
        failLegacyAjaxInputContract("The $name endpoint must not use the deprecated string filter.");
    }
    $sources[$name] = $source;
}

if (!str_contains($sources['village rename'], 'is_scalar($entry[\'villageId\'] ?? null)')
    || !str_contains($sources['village rename'], 'is_string($entry[\'name\'] ?? null)')
    || !str_contains($sources['village rename'], 'clean_string_from_white(strip_tags($entry[\'name\']))')) {
    failLegacyAjaxInputContract('Village renaming must accept only scalar IDs and string names.');
}

if (!str_contains($sources['player autocomplete'], 'function likePattern')
    || !str_contains($sources['player autocomplete'], "htmlspecialchars(is_string(\$term) ? trim(\$term) : '', ENT_QUOTES, 'UTF-8')")) {
    failLegacyAjaxInputContract('Player autocomplete must escape only scalar search terms.');
}

if (!str_contains($sources['address book'], 'is_string($friend)')
    || !str_contains($sources['address book'], 'strip_tags($friend)')) {
    failLegacyAjaxInputContract('Address-book additions must accept only string player names.');
}

if (!str_contains($sources['recipient languages'], 'is_array($recipients) ? $recipients : []')
    || !str_contains($sources['recipient languages'], "htmlspecialchars(is_string(\$search) ? \$search : '', ENT_QUOTES, 'UTF-8')")) {
    failLegacyAjaxInputContract('Recipient-language lookup must tolerate malformed request payloads.');
}

if (!str_contains($sources['layout button tooltip'], "is_string(\$_POST['boxId'] ?? null)")
    || !str_contains($sources['layout button tooltip'], "is_string(\$_POST['buttonId'] ?? null)")) {
    failLegacyAjaxInputContract('Layout-button tooltips must accept only scalar control identifiers.');
}

if (!str_contains($sources['premium account rename'], "is_string(\$_POST['accountNewName'] ?? null)")
    || !str_contains($sources['premium account rename'], "strip_tags(\$_POST['accountNewName'])")
    || !str_contains($sources['account-name model'], "strip_tags(is_string(\$name) ? \$name : '')")
    || !str_contains($sources['account-name model'], 'strtolower($newName)')) {
    failLegacyAjaxInputContract('Premium account renaming must only sanitize scalar names.');
}

if (!str_contains($sources['options'], "is_string(\$_POST['account_rename_new_name'] ?? null)")
    || !str_contains($sources['options'], "strip_tags(\$_POST['account_rename_new_name'])")) {
    failLegacyAjaxInputContract('Free account renaming must only sanitize scalar names.');
}

if (!str_contains($sources['rally point'], "\$villageName = \$_REQUEST['villagename'] ?? \$_REQUEST['dname'] ?? null;")
    || !str_contains($sources['rally point'], 'is_string($villageName)')
    || !str_contains($sources['rally point'], "is_string(\$_POST['timestamp_checksum'])")
    || !str_contains($sources['rally point'], "real_escape_string(is_string(\$timestampChecksum) ? \$timestampChecksum : '')")
    || !str_contains($sources['rally point base'], "real_escape_string(is_string(\$timestampChecksum) ? \$timestampChecksum : '')")) {
    failLegacyAjaxInputContract('Rally-point destination names and checksums must be scalar-safe and escaped at the database boundary.');
}

echo "Legacy AJAX input contract passed.\n";
