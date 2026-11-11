<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Model/PlayerAccusationModel.php';

use Model\PlayerAccusationModel;

$root = dirname(__DIR__, 2);
function failPlayerAccusationContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

if (PlayerAccusationModel::MAX_PER_DAY !== 2 || PlayerAccusationModel::MAX_MESSAGE_LENGTH !== 300) {
    failPlayerAccusationContract('The report limits must match the reference (2 per day, 300 characters).');
}
if (PlayerAccusationModel::REASONS !== ['multiAccount', 'passwordSharing', 'botScript', 'other', 'inappropriateContent', 'privateFarm']) {
    failPlayerAccusationContract('The reasons must be the reference dialog options in its order.');
}

$schema = $read('database/world.sql');
if (!preg_match('/CREATE TABLE `player_accusation` \((.*?)\) ENGINE/s', $schema, $table)
    || array_filter(['reporter_uid', 'accused_uid', 'reason', 'message', 'time', 'handled_time'], static fn (string $column): bool => !str_contains($table[1], '`' . $column . '`')) !== []) {
    failPlayerAccusationContract('world.sql must create player_accusation with its columns.');
}

$ajax = $read('game/include/Controller/Ajax/player/accuse.php');
foreach (['isSitter()', 'banned()', 'remainingToday($reporter) === 0', '->file($reporter, $uid, $reason, $message)', '$uid <= 2', '$uid === $reporter'] as $needle) {
    if (!str_contains($ajax, $needle)) {
        failPlayerAccusationContract("The report endpoint must guard {$needle}.");
    }
}
$routes = require $root . '/game/deployment/include/api_legacy_routes.php';
if (($routes['routes']['player/accuse'] ?? null) !== ['player', 'accuse']) {
    failPlayerAccusationContract('player/accuse must be a routed browser endpoint (it reaches the ajax CSRF token check).');
}

$english = $read('game/include/resources/Translation/en-US.php');
foreach ([
    "['Profile']['reportPlayer_successMessage'] = 'Thank you for your report. We will investigate the player\\'s account and take the appropriate measures.'",
    "['Profile']['reportPlayer_reportsPerDayWarning'] = 'Available reports per day: %s'",
    "['Profile']['accusationReason.botScript'] = 'Scripts & bots'",
] as $needle) {
    if (!str_contains($english, $needle)) {
        failPlayerAccusationContract('The report copy must be the reference text: ' . $needle);
    }
}

$admin = $read('game/include/Game/Admin/AdminMenu.php') . $read('game/include/admin/include/Core/RequestGuard.php');
if (!str_contains($admin, "action=playerAccusations") || !str_contains($admin, "'playeraccusations' => ['handle' => '*']")) {
    failPlayerAccusationContract('The admin list must be linked and its mark-handled GET must go through the CSRF confirmation.');
}
if (!is_file($root . '/game/include/admin/include/Controllers/PlayerAccusationsCtrl.php')
    || !is_file($root . '/game/include/admin/include/templates/playerAccusations.php')) {
    failPlayerAccusationContract('The admin controller and template must exist.');
}

echo "Player accusation contract passed.\n";
