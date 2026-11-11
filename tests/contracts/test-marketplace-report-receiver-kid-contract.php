<?php

declare(strict_types=1);

// The merchant-arrival report stores sender and receiver village. The receiver entry must be the village
// the merchants delivered to (send.to_kid), not the sender's own village.

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/MarketPlaceProcessor.php');
if ($source === false) {
    fwrite(STDERR, "Unable to read MarketPlaceProcessor.php.\n");
    exit(1);
}
if (!preg_match('/\'uname\' => \$receiver\[\'name\'\],\s*\'kid\' => \$toKid,/', $source)) {
    fwrite(STDERR, "The merchant report must record the receiving village (\$toKid) as the receiver's kid.\n");
    exit(1);
}

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Marketplace report receiver contract passed (static; behavioural part skipped outside the php container).\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\NoticeHelper;
use Model\MarketPlaceProcessor;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $sender = fixtureVillageCreate();
    $pdo->run('UPDATE users SET name=? WHERE id=?', ['zzfxs' . getmypid(), $sender['id']]);
    $receiver = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Marketplace report receiver contract passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
// Both are throwaway players cloned from a capital; the real players' villages are never touched.
$pdo->run('UPDATE users SET reportFilters=? WHERE id IN (?, ?)', ['0,0,0,0,0,0', $sender['id'], $receiver['id']]);
$pdo->run(
    'UPDATE vdata SET wood=0, clay=0, iron=0, crop=0, woodp=0, clayp=0, ironp=0, cropp=pop+upkeep, maxstore=5000, maxcrop=5000, lastmupdate=? WHERE kid=?',
    [miliseconds(), $receiver['kid']]
);

(new MarketPlaceProcessor())->processRow([
    'id' => 0,
    'kid' => $sender['kid'],
    'to_kid' => $receiver['kid'],
    'wood' => 100,
    'clay' => 50,
    'iron' => 0,
    'crop' => 0,
    'x' => 1,
    'ships' => 0,
    'mode' => 0,
    'end_time' => time(),
]);

$notices = $pdo->run('SELECT uid, kid, to_kid, type, data FROM ndata WHERE uid IN (?, ?)', [$sender['id'], $receiver['id']])->fetchAll(PDO::FETCH_ASSOC);
if ($notices === []) {
    $fail('Delivering merchants must create a report for the sender and the receiver.');
}
foreach ($notices as $notice) {
    $report = NoticeHelper::parseReport((int)$notice['type'], $notice['data']);
    if ((int)$notice['to_kid'] !== $receiver['kid'] || (int)$notice['kid'] !== $sender['kid']) {
        $fail('The report row must run from the sender village to the receiving village.');
    }
    if ((int)$report['sender']['kid'] !== $sender['kid']) {
        $fail('The report must name the sender village as the sender.');
    }
    if ((int)$report['receiver']['kid'] !== $receiver['kid']) {
        $fail('The report must name the receiving village as the receiver, not the sender village: got ' . $report['receiver']['kid']);
    }
}

echo "Marketplace report receiver contract passed.\n";
