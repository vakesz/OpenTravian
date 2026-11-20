<?php

declare(strict_types=1);

// A finished auction settles exactly once: running the settlement twice pays the seller and delivers the item once.
// A winner who can no longer pay is not charged into negative silver; the item goes back to the seller as if
// nobody had bid. Two throwaway fixture players act as seller and buyer.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Auction settle once runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\Hero\ItemBtype;
use Game\Hero\ItemType;
use Model\AuctionModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $seller = fixtureVillageCreate();
    $buyer = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Auction settle once runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$prefix = 'zzsettle-' . getmypid() . '-';
register_shutdown_function(static function () use ($prefix, $seller, $buyer): void {
    $pdo = DBI::getInstance();
    $pdo->run('DELETE FROM bids WHERE auctionId IN (SELECT id FROM auction WHERE secure_id LIKE ?)', [$prefix . '%']);
    $pdo->run('DELETE FROM auction WHERE secure_id LIKE ?', [$prefix . '%']);
    $pdo->run('DELETE FROM items WHERE uid IN (?, ?)', [$seller['id'], $buyer['id']]);
});
$silver = static fn (int $uid): int => (int)$pdo->fetchScalar('SELECT silver FROM users WHERE id=?', [$uid]);
$items = static fn (int $uid): int => (int)$pdo->fetchScalar('SELECT COALESCE(SUM(num), 0) FROM items WHERE uid=? AND btype=? AND type=?', [$uid, ItemBtype::SMALL_BANDAGE, ItemType::SMALL_BANDAGE]);
/** A due auction of 5 small bandages won by the buyer at 100 silver. */
$auction = static function (string $name) use ($pdo, $prefix, $seller, $buyer): int {
    $pdo->run(
        'INSERT INTO auction (uid, btype, type, num, bids, silver, maxSilver, activeUid, secure_id, time, created, finish) VALUES (?, ?, ?, 5, 1, 100, 100, ?, ?, ?, ?, 0)',
        [$seller['id'], ItemBtype::SMALL_BANDAGE, ItemType::SMALL_BANDAGE, $buyer['id'], $prefix . $name, time() - 60, time() - 7200]
    );
    $id = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
    $pdo->run('INSERT INTO bids (uid, auctionId, outbid, locked) VALUES (?, ?, 0, 0)', [$buyer['id'], $id]);
    $pdo->run('UPDATE auction SET activeId=? WHERE id=?', [(int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()'), $id]);

    return $id;
};

$pdo->run('UPDATE users SET silver=1000 WHERE id IN (?, ?)', [$seller['id'], $buyer['id']]);
$auction('paid');
$model = new AuctionModel();
$model->doAuction();
$model->doAuction();
if ($silver($buyer['id']) !== 900) {
    $fail('The buyer must be charged 100 once, has ' . $silver($buyer['id']) . '.');
}
if ($silver($seller['id']) !== 1098) {
    $fail('The seller must be paid 98 once, has ' . $silver($seller['id']) . '.');
}
if ($items($buyer['id']) !== 5) {
    $fail('The buyer must receive the 5 bandages once, has ' . $items($buyer['id']) . '.');
}

$pdo->run('UPDATE users SET silver=10 WHERE id=?', [$buyer['id']]);
$pdo->run('UPDATE users SET silver=1000 WHERE id=?', [$seller['id']]);
$unpaid = $auction('unpaid');
$model->doAuction();
if ($silver($buyer['id']) !== 10) {
    $fail('A winner who cannot pay must not be charged, has ' . $silver($buyer['id']) . '.');
}
if ($silver($seller['id']) !== 1000 || $items($seller['id']) !== 5) {
    $fail('An unpaid item must go back to the seller unpaid (silver ' . $silver($seller['id']) . ', items ' . $items($seller['id']) . ').');
}
if ($items($buyer['id']) !== 5) {
    $fail('A winner who cannot pay must not receive the item.');
}
if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM auction WHERE id=?', [$unpaid]) !== 0) {
    $fail('An unpaid auction is removed like an unsold one.');
}

echo "Auction settle once runtime passed.\n";
