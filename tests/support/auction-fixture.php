<?php

declare(strict_types=1);

// Auction data for a throwaway fixture player (see village-fixture.php): the player can take part in auctions
// (ten adventures, silver), has items to sell, five running and twelve finished auctions of their own and fourteen
// bids, and the world has at least 25 item types for sale, so every auction list has a second page. The extra
// system auctions are removed again at shutdown, and the player's own rows go with the player.

use Core\Database\DBI;

function auctionFixtureSeed(int $uid): void
{
    $pdo = DBI::getInstance();
    $pdo->run('UPDATE users SET success_adventures_count=10, silver=20000 WHERE id=?', [$uid]);
    $now = time();
    $systemAuctions = [];
    $addAuction = static function (int $owner, int $btype, int $type, int $time, int $finish, int $silver = 50) use ($pdo, $now, &$systemAuctions): int {
        $pdo->run(
            'INSERT INTO auction (uid, btype, type, num, bids, silver, secure_id, time, created, finish) VALUES (?, ?, ?, 1, 1, ?, ?, ?, ?, ?)',
            [$owner, $btype, $type, $silver, sha1("zzfx-auction-$owner-$btype-$type-$time"), $time, $now - 60, $finish]
        );
        $id = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');
        if ($owner === 0) {
            $systemAuctions[] = $id;
        }

        return $id;
    };
    // Enough distinct item types on sale for a second page even in the compact layout (20 per page).
    $types = [];
    foreach (\Game\Hero\HeroItems::getAvailableItemTypes() as $btype => $typeIds) {
        foreach ($typeIds as $type) {
            $types[] = [$btype, $type];
        }
    }
    $onSale = (int)$pdo->fetchScalar('SELECT COUNT(DISTINCT btype, type) FROM auction WHERE finish=0 AND uid<>?', [$uid]);
    foreach ($types as $index => [$btype, $type]) {
        if ($onSale >= 25) {
            break;
        }
        if ((int)$pdo->fetchScalar('SELECT COUNT(*) FROM auction WHERE finish=0 AND btype=? AND type=?', [$btype, $type]) === 0) {
            $addAuction(0, $btype, $type, $now + 7200 + $index * 60, 0);
            ++$onSale;
        }
    }
    // Five running auctions of the player, and twelve finished and sold ones.
    for ($i = 0; $i < 5; ++$i) {
        $addAuction($uid, $types[$i][0], $types[$i][1], $now + 3600 + $i * 60, 0, 100 + $i);
    }
    for ($i = 0; $i < 12; ++$i) {
        $addAuction($uid, $types[20 + $i][0], $types[20 + $i][1], $now - 3600 * ($i + 1), 1, 200 + $i);
    }
    // Fourteen bids: five on running auctions, nine on finished ones.
    for ($i = 0; $i < 14; ++$i) {
        $finished = $i >= 5 ? 1 : 0;
        $auctionId = $addAuction(0, $types[60 + $i][0], $types[60 + $i][1], $finished ? $now - 600 * ($i + 1) : $now + 5400 + $i * 60, $finished, 300 + $i);
        $pdo->run('INSERT INTO bids (uid, auctionId, outbid, del) VALUES (?, ?, 0, 0)', [$uid, $auctionId]);
    }
    // Items to sell, on the first inventory places.
    foreach ([[1, 1], [1, 2], [4, 16], [7, 112]] as $place => [$btype, $type]) {
        $pdo->run('INSERT INTO items (uid, btype, type, num, placeId, proc) VALUES (?, ?, ?, 10, ?, 0)', [$uid, $btype, $type, $place + 1]);
    }
    register_shutdown_function(static function () use ($pdo, &$systemAuctions, $uid): void {
        $pdo->run('DELETE FROM bids WHERE uid=?', [$uid]);
        foreach ($systemAuctions as $id) {
            $pdo->run('DELETE FROM auction WHERE id=?', [$id]);
        }
        $pdo->run('DELETE FROM auction WHERE uid=?', [$uid]);
        $pdo->run('DELETE FROM items WHERE uid=?', [$uid]);
    });
}
