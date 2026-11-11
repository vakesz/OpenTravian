<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
require dirname(__DIR__, 2) . '/game/include/bootstrap.php';

use Controller\HeroAuctionCtrl;
use Core\Database\DB;
use Core\Session;
use Model\AuctionModel;

set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
});
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

final class AuctionFixtureSession extends Session
{
    public int $bidder = 0;

    public function __construct() {}
    public function getPlayerId(): int { return $this->bidder; }
    public function getAvailableSilver(): int
    {
        return 1000 - (int)(new AuctionModel())->getInBidSilver($this->bidder);
    }
    public function banned(): bool { return false; }
    public function isInVacationMode(): bool { return false; }
}

$db = DB::getInstance();
$uid = (int)$db->fetchScalar('SELECT id FROM users WHERE id>2 ORDER BY id LIMIT 1');
$otherUid = (int)$db->fetchScalar("SELECT id FROM users WHERE id>1 AND id<>$uid ORDER BY id LIMIT 1");
if (!$uid || !$otherUid) {
    throw new RuntimeException('Auction bidding runtime check requires two installed accounts.');
}
$session = new AuctionFixtureSession();
$session->bidder = $uid;
$sessionProperty = new ReflectionProperty(Session::class, '_self');
$originalSession = $sessionProperty->getValue();
$sessionProperty->setValue(null, $session);
$controller = (new ReflectionClass(HeroAuctionCtrl::class))->newInstanceWithoutConstructor();
$controller->view = (object)['vars' => ['content' => '']];
$submit = new ReflectionMethod(HeroAuctionCtrl::class, 'processBuy');
$_SERVER['REQUEST_METHOD'] = 'POST';

$db->begin_transaction();
try {
    $model = new AuctionModel();
    $db->query("UPDATE auction SET finish=1 WHERE activeUid IN ($uid, $otherUid) AND finish=0");
    $model->addAuction(true, 0, 1, 1, 1, time() + 60, 100);
    $id = (int)$db->lastInsertId();
    $secureId = (string)$db->fetchScalar("SELECT secure_id FROM auction WHERE id=$id");
    $bid = static function (int $amount) use ($secureId, $controller, $submit): void {
        $_REQUEST = ['a' => $secureId, 'action' => 'bids', 'z' => Session::getInstance()->getChecker(), 'bidAmount' => $amount];
        $submit->invoke($controller, true);
    };
    $bid(100);
    $opening = $model->getAuction($id);
    if ((int)$opening['activeUid'] !== $uid || (int)$opening['maxSilver'] !== 100 || (int)$opening['silver'] !== 100) {
        throw new RuntimeException('FAIL: a first bid equal to the starting price must create the initial winner.');
    }
    $bid(200);
    $first = $model->getAuction($id);
    if ((int)$first['activeUid'] !== $uid || (int)$first['silver'] !== 100 || (int)$first['maxSilver'] !== 200
        || (int)$first['time'] < time() + 299) {
        throw new RuntimeException('FAIL: first bid must set the winner, keep the starting price, and extend to five minutes.');
    }

    $session->bidder = $otherUid;
    $db->query("UPDATE auction SET time=" . (time() + 60) . " WHERE id=$id");
    $bid(150);
    $losing = $model->getAuction($id);
    if ((int)$losing['activeUid'] !== $uid || (int)$losing['silver'] !== 150
        || (int)$losing['time'] < time() + 299) {
        throw new RuntimeException('FAIL: a losing bid must raise the visible price and also extend the timer.');
    }

    $bid(200);
    $tie = $model->getAuction($id);
    if ((int)$tie['activeUid'] !== $uid || (int)$tie['silver'] !== 200 || (int)$tie['maxSilver'] !== 200) {
        throw new RuntimeException('FAIL: equal maximum bids must keep the earlier bidder as winner.');
    }

    $bid(250);
    $winning = $model->getAuction($id);
    if ((int)$winning['activeUid'] !== $session->bidder || (int)$winning['silver'] !== 200
        || (int)$winning['maxSilver'] !== 250) {
        throw new RuntimeException('FAIL: a higher maximum bid must replace the winner at the previous maximum.');
    }
    $db->query("UPDATE auction SET time=" . (time() + 60) . " WHERE id=$id");
    $bid(300);
    $own = $model->getAuction($id);
    if ((int)$own['silver'] !== 200 || (int)$own['maxSilver'] !== 300 || (int)$own['time'] < time() + 299) {
        throw new RuntimeException('FAIL: raising your own maximum must extend the timer without raising the visible price.');
    }
    $bid(1000);
    $fullBalance = $model->getAuction($id);
    if ((int)$fullBalance['maxSilver'] !== 1000 || (int)$fullBalance['silver'] !== 200) {
        throw new RuntimeException('FAIL: raising your own maximum must reuse all of your already reserved silver.');
    }
    $laterEnd = time() + 600;
    $db->query("UPDATE auction SET time=$laterEnd WHERE id=$id");
    $bid(1000);
    if ((int)$model->getAuction($id)['time'] !== $laterEnd) {
        throw new RuntimeException('FAIL: bidding before the final five minutes must not shorten the auction.');
    }
    echo "Real auction controller first, losing, tied, winning, own-maximum, and reserved-silver bids passed (rolled back).\n";
} finally {
    $db->rollback();
    $sessionProperty->setValue(null, $originalSession);
}
