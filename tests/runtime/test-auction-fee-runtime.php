<?php

declare(strict_types=1);

// The auction house keeps a fee of every sale (2%, rounded up): the seller of a finished
// auction is paid the sale price minus the fee, and the sold list shows that payout. A throwaway fixture player
// sells an item nobody bid on to the system; its rows are removed when the test ends.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Auction fee runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\Hero\AuctionFee;
use Model\AuctionModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$expect = static function (bool $condition, string $what) use ($fail): void {
    if (!$condition) {
        $fail($what);
    }
};

$expect(AuctionFee::percent() === 2, 'The auction fee must be the reference 2 percent.');
$expect(AuctionFee::of(100) === 2 && AuctionFee::payout(100) === 98, 'A sale of 100 pays out 98, as the reference dialog shows.');
$expect(AuctionFee::of(1) === 1 && AuctionFee::payout(1) === 0, 'The fee is rounded up.');
$expect(AuctionFee::of(150) === 3 && AuctionFee::payout(150) === 147, 'A sale of 150 keeps 3.');
$expect(AuctionFee::of(0) === 0 && AuctionFee::payout(0) === 0, 'Nothing is sold, nothing is charged.');

$pdo = DBI::getInstance();
try {
    $seller = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Auction fee runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$silver = static fn (): int => (int)$pdo->fetchScalar('SELECT silver FROM users WHERE id=?', [$seller['id']]);
$pdo->run('UPDATE users SET silver=1000 WHERE id=?', [$seller['id']]);
register_shutdown_function(static fn () => DBI::getInstance()->run('DELETE FROM auction WHERE uid=? AND secure_id LIKE ?', [$seller['id'], 'zzfee-%']));

// A finished sale of 150 silver: due since a minute, one bidder (the system buys it inactive: no bidder row).
$pdo->run(
    'INSERT INTO auction (uid, btype, type, num, bids, silver, secure_id, time, created, finish) VALUES (?, 7, 112, 5, 1, 150, ?, ?, ?, 0)',
    [$seller['id'], 'zzfee-' . getmypid(), time() - 60, time() - 7200]
);
$auctionId = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
(new AuctionModel())->doAuction();
$expect((int)$pdo->fetchScalar('SELECT finish FROM auction WHERE id=?', [$auctionId]) === 1, 'The due auction must be finished.');
$expect($silver() === 1000 + 147, 'The seller must be paid 147 of the 150 silver (got ' . ($silver() - 1000) . ').');
$booked = (int)$pdo->fetchScalar('SELECT reserve FROM accounting WHERE uid=? ORDER BY id DESC LIMIT 1', [$seller['id']]);
$expect($booked === 147, "The booking must show the payout (got $booked).");

echo "Auction fee runtime passed.\n";
