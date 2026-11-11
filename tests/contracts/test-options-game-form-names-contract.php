<?php

declare(strict_types=1);

/**
 * Contract: Options Game tab form field names match reference DOM.
 */

function failOptionsGameFormNamesContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$game = file_get_contents($root . '/game/include/resources/Templates/options/Game.php');
$ctrl = file_get_contents($root . '/game/include/Controller/OptionCtrl.php');
if ($game === false || $ctrl === false) {
    failOptionsGameFormNamesContract('Unable to read Options Game sources.');
}

$requiredNames = [
    'name="hideContextualHelp"',
    'name="option_night_mode"',
    'name="showDeleteReportsConfirmation"',
    'name="c4"',
    'name="c5"',
    'name="c6"',
    'name="receiveAllianceInvitations"',
    'name="c7"',
    'name="c8"',
    'name="c9"',
    'name="c10"',
    'name="c11"',
    'name="c1"',
    'name="c2"',
    'name="c3"',
    'name="marketplaceOffersPerPage"',
    'name="language"',
    'name="flag_local_time_instead_server"',
];

foreach ($requiredNames as $fragment) {
    if (!str_contains($game, $fragment)) {
        failOptionsGameFormNamesContract("Options Game template missing field name: {$fragment}");
    }
}

$legacyNames = [
    'name="hide_contextual_help"',
    'name="night_mood_images"',
    'name="show_delete_multiple_reports_confirmation"',
    'name="v4"',
    'name="v5"',
    'name="v6"',
    'name="v12"',
    'name="v7"',
    'name="v8"',
    'name="v9"',
    'name="v10"',
    'name="v11"',
    'name="v1"',
    'name="v2"',
    'name="v3"',
    'name="offersPerPage"',
    'name="lang"',
    '$tzPref',
];

foreach ($legacyNames as $fragment) {
    if (str_contains($game, $fragment)) {
        failOptionsGameFormNamesContract("Options Game template still uses legacy fragment: {$fragment}");
    }
}

$requiredPostKeys = [
    "\$_POST['hideContextualHelp']",
    "\$_POST['option_night_mode']",
    "\$_POST['showDeleteReportsConfirmation']",
    "\$_POST['c4']",
    "\$_POST['c5']",
    "\$_POST['c6']",
    "\$_POST['receiveAllianceInvitations']",
    "\$_POST['c1']",
    "\$_POST['c2']",
    "\$_POST['c3']",
    "\$_POST['c7']",
    "\$_POST['c8']",
    "\$_POST['c9']",
    "\$_POST['c10']",
    "\$_POST['c11']",
    "\$_POST['marketplaceOffersPerPage']",
    "\$_POST['flag_local_time_instead_server']",
];

foreach ($requiredPostKeys as $fragment) {
    if (!str_contains($ctrl, $fragment)) {
        failOptionsGameFormNamesContract("OptionCtrl missing POST key: {$fragment}");
    }
}

$legacyPostKeys = [
    "\$_POST['hide_contextual_help']",
    "\$_POST['night_mood_images']",
    "\$_POST['show_delete_multiple_reports_confirmation']",
    "\$_POST['v4']",
    "\$_POST['v5']",
    "\$_POST['v6']",
    "\$_POST['v12']",
    "\$_POST['v1']",
    "\$_POST['v2']",
    "\$_POST['v3']",
    "\$_POST['v7']",
    "\$_POST['v8']",
    "\$_POST['v9']",
    "\$_POST['v10']",
    "\$_POST['v11']",
    "\$_POST['offersPerPage']",
];

foreach ($legacyPostKeys as $fragment) {
    if (str_contains($ctrl, $fragment)) {
        failOptionsGameFormNamesContract("OptionCtrl still reads legacy POST key: {$fragment}");
    }
}

echo "Options Game form names contract passed.\n";
