<?php

declare(strict_types=1);

// Cancelling every offer of a village (a destroyed Marketplace or Trade Office, a player leaving the alliance)
// refunds only offers it actually deleted, and the merchants still free are counted from locked rows.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Market cancel-all runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Model\MarketModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
try {
    $fixture = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Market cancel-all runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$kid = $fixture['kid'];
$market = new MarketModel();
$pdo->run('DELETE FROM market WHERE kid=?', [$kid]);
$pdo->run('DELETE FROM `send` WHERE kid=? OR to_kid=?', [$kid, $kid]);
$stock = static fn (): array => array_map('intval', $pdo->fetchSingleRow('SELECT wood, clay, iron, crop FROM vdata WHERE kid=?', [$kid]));
$reset = static fn () => $pdo->run('UPDATE vdata SET wood=1000, clay=1000, iron=1000, crop=1000 WHERE kid=?', [$kid]);
$offerId = static fn (int $giveType): int => (int)$pdo->fetchScalar('SELECT id FROM market WHERE kid=? AND giveType=? ORDER BY id DESC LIMIT 1', [$kid, $giveType]);

// One offer was accepted (deleted) elsewhere: only the two that remain are refunded.
$reset();
$market->addOffer($kid, 0, 0, 2, 100, 1, 100);
$market->addOffer($kid, 0, 0, 3, 200, 2, 200);
$market->addOffer($kid, 0, 0, 1, 300, 4, 300);
$market->deleteOffer($offerId(1)) || $fail('The accepted offer must delete.');
$market->cancelAllOffers($kid);
$stock() === ['wood' => 1000, 'clay' => 1200, 'iron' => 1000, 'crop' => 1300] || $fail('Only the surviving offers may be refunded: ' . json_encode($stock()));
(int)$pdo->fetchScalar('SELECT COUNT(*) FROM market WHERE kid=?', [$kid]) === 0 || $fail('Every offer must be gone after cancelling all.');
$market->cancelAllOffers($kid);
$stock() === ['wood' => 1000, 'clay' => 1200, 'iron' => 1000, 'crop' => 1300] || $fail('Cancelling with no offers must refund nothing.');

// Alliance-only offers of a player are cancelled and refunded; open offers stay.
$reset();
$market->addOffer($kid, 7, 0, 2, 100, 1, 150);
$market->addOffer($kid, 0, 0, 2, 100, 1, 50);
$market->cancelAllOffersForAlliance($fixture['id']);
$stock()['wood'] === 1150 || $fail('The alliance offer must be refunded once: ' . json_encode($stock()));
(int)$pdo->fetchScalar('SELECT COUNT(*) FROM market WHERE kid=? AND aid=0', [$kid]) === 1 || $fail('An open offer must survive.');
(int)$pdo->fetchScalar('SELECT COUNT(*) FROM market WHERE kid=? AND aid>0', [$kid]) === 0 || $fail('The alliance offer must be deleted.');
$pdo->run('DELETE FROM market WHERE kid=?', [$kid]);

// Merchants free under lock: offers and shipments in flight both reserve merchants.
$cap = 100;
$market->addOffer($kid, 0, 0, 2, 250, 1, 250);
$pdo->run('INSERT INTO `send` (kid, to_kid, wood, clay, iron, crop, x, ships, mode, end_time) VALUES (?, ?, 100, 0, 0, 0, 1, 0, 0, ?)', [$kid, $kid, time() + 3600]);
$free = $market->availableMerchantsLocked($kid, $cap, 10);
$free === 6 || $fail("Ten merchants, three on the offer and one on the way leave six, got $free.");
$market->availableMerchantsLocked($kid, $cap, 2) === 0 || $fail('The free merchants never go below zero.');

echo "Market cancel-all runtime passed.\n";
