<?php

declare(strict_types=1);

// An offer's maximum transport time (market.maxtime, in seconds) must mean the same thing in the offer list and in
// the accept check: a buyer whose merchants arrive in time sees the offer and may accept it, a buyer whose merchants
// would be too slow neither sees it nor may accept it. Both use the wrap-around map distance.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Market max time runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\Formulas;
use Game\Tribes;
use Model\MarketModel;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Market max time runtime failed: ' . $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $seller = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Market max time runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$sellerKid = $seller['kid'];
$market = new MarketModel();
$race = Tribes::ROMANS;
$speed = (float)Formulas::merchantSpeed($race);   // fields per hour

// A limit of about ten fields of merchant travel (at least 10 s), then buyers at half and at twice that distance.
$maxTime = max(10, (int)round(10 / $speed * 3600));
$reach = $maxTime * $speed / 3600;
$market->addOffer($sellerKid, 0, 1, 1, 100, 2, 100);
$offerId = (int)$pdo->fetchScalar('SELECT id FROM market WHERE kid=? ORDER BY id DESC LIMIT 1', [$sellerKid]);
$pdo->run('UPDATE market SET maxtime=? WHERE id=?', [$maxTime, $offerId]);
$offer = $market->getOffer($offerId);
if ($offer === false) {
    $fail('the offer was not stored.');
}

$origin = Formulas::kid2xy($sellerKid);
$buyerAt = static fn (float $dx, float $dy): int => (int)Formulas::xy2kid((int)$origin['x'] + (int)round($dx), (int)$origin['y'] + (int)round($dy));
$listed = static function (int $buyerKid) use ($market, $race, $offerId): bool {
    $result = $market->getOffers(1, 1000, 0, $buyerKid, $race, 0, 0, 0);
    while ($result && ($row = $result->fetch_assoc())) {
        if ((int)$row['id'] === $offerId) {
            return true;
        }
    }

    return false;
};
$acceptable = static function (int $buyerKid) use ($offer, $sellerKid, $speed): bool {
    // The same duration MarketPlaceCtrl computes before it calls withinMaxTime().
    $duration = Formulas::travelTimeSeconds(Formulas::getDistance($sellerKid, $buyerKid) / $speed * 3600);

    return MarketModel::withinMaxTime($offer, $duration);
};

if (!MarketModel::withinMaxTime(['maxtime' => 0], 1e9)) {
    $fail('an offer without a maximum time must accept any trip.');
}

// The fixture takes the last free map slot, so +x usually crosses the map edge: this also checks the wrap-around.
$near = $buyerAt(max(1.0, floor($reach / 2)), 0);
if (!$listed($near)) {
    $fail("a buyer at half the merchants' reach ({$reach} fields) must see the offer.");
}
if (!$acceptable($near)) {
    $fail('a buyer whose merchants arrive in time must be allowed to accept the offer.');
}

$farDistance = 2 * $reach;
if ($farDistance > MAP_SIZE * 1.4) {
    echo "Market max time runtime passed (the map is too small for a far buyer at this speed).\n";
    exit(0);
}
$dx = min($farDistance, (float)MAP_SIZE);
$dy = sqrt(max(0.0, $farDistance ** 2 - $dx ** 2));
$far = $buyerAt($dx, $dy);
if (Formulas::getDistance($sellerKid, $far) <= $reach + 1) {
    echo "Market max time runtime passed (no far buyer position at this speed).\n";
    exit(0);
}
if ($listed($far)) {
    $fail('a buyer whose merchants are too slow must not see the offer.');
}
if ($acceptable($far)) {
    $fail('a buyer whose merchants are too slow must not be allowed to accept the offer.');
}

echo "Market max time runtime passed.\n";
