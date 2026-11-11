<?php

declare(strict_types=1);

namespace Model;

use Controller\Ajax\heroAuction;
use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use Game\Hero\AuctionFee;
use Game\Hero\HeroItems;
use mysqli_result;

use function getCustom;
use function getGameElapsedSeconds;
use function getGameSpeed;

/**
 * Hero auctions and the hero inventory slots. Only ints are interpolated into SQL (everything else is bound), and
 * rows are read over the text protocol so columns stay strings for the callers.
 */
class AuctionModel
{
    public const HERO_INVENTORY_COLUMNS = 8;
    public const HERO_INVENTORY_MAX_ROWS = 6;
    public const HERO_INVENTORY_MAX_SLOTS = self::HERO_INVENTORY_COLUMNS * self::HERO_INVENTORY_MAX_ROWS;

    /** Knowledge Base article 94: a sale can be cancelled within the first five minutes. */
    public const CANCEL_WINDOW_SECONDS = 300;

    /**
     * @param array<string, mixed> $auction auction row
     */
    public static function canCancel(array $auction): bool
    {
        $created = (int)($auction['created'] ?? 0);
        return (int)($auction['bids'] ?? 0) === 0
            && (int)($auction['finish'] ?? 0) === 0
            && (int)($auction['time'] ?? 0) >= time()
            && $created > 0
            && $created >= time() - self::CANCEL_WINDOW_SECONDS;
    }

    /** @var list<int> players already given a spread-out auction item in this run */
    private static array $given_user_ids = [];

    public function doAuction(): void
    {
        $m = new AutomationModel();
        $db = DB::getInstance();
        $find = $db->query('SELECT * FROM auction WHERE finish=0 AND time <= ' . time() . ' ORDER BY id ASC LIMIT 100');
        $dailyQuest = new DailyQuestModel();
        $spread_randomly = Config::getInstance()->auction->fakeAuction->SpreadOutRandomlyBetweenPlayers;
        $spread_timeout = (int) Config::getInstance()->auction->fakeAuction->SpreadOutTimeout;
        while ($find && ($row = $find->fetch_assoc())) {
            $auctionId = (int) $row['id'];
            $activeUid = (int) $row['activeUid'];
            $owner = (int) $row['uid'];
            $silver = (int) $row['silver'];
            $item = [(int) $row['btype'], (int) $row['type'], (int) $row['num']];
            $db->query("UPDATE auction SET finish=1 WHERE id=$auctionId");
            if ($spread_randomly) {
                $uid = $this->getRandomUID($spread_timeout);
                if ($uid !== false) {
                    $dailyQuest->setQuestAsCompleted($activeUid, 4);
                    $this->addItemToUser($uid, ...$item);
                }
            } elseif ((int) $row['activeId'] !== 0) {
                $buyer = $m->getUser($activeUid, 'silver');
                if ($buyer !== false && $this->updateUserSilver($activeUid, $silver)) {
                    $dailyQuest->setQuestAsCompleted($activeUid, 4);
                    $this->addItemToUser($activeUid, ...$item);
                    $this->addBooking($activeUid, [2, ...$item], -$silver, -$silver + (int) $buyer['silver'], (int) $row['time']);
                }
            }
            if ($owner !== 0) {
                if ((int) $row['activeId'] === 0 && !Config::getProperty('auction', 'buyInactiveAuctionBySystem')) {
                    $this->addItemToUser($owner, ...$item);
                    $db->query("DELETE FROM auction WHERE id=$auctionId AND finish=1");
                } else {
                    // The seller is paid the sale price minus the auction house's fee.
                    $payout = AuctionFee::payout($silver);
                    $seller = $m->getUser($owner, 'silver');
                    if ($seller !== false && $this->updateUserSilver($owner, $payout, 1)) {
                        $this->addBooking($owner, [1, ...$item], $payout, $payout + (int) $seller['silver'], (int) $row['time']);
                    }
                }
            }
        }
    }

    public function getRandomUID(int $spread_timeout, bool $isLoop = false): int|false
    {
        $time = time() - $spread_timeout;
        $exclude = self::$given_user_ids === [] ? '' : ' AND id NOT IN(' . implode(',', self::$given_user_ids) . ')';
        $uid = (int) DB::getInstance()->fetchScalar("SELECT id FROM users WHERE id>2 AND access=1 AND last_login_time >= $time$exclude ORDER BY RAND() LIMIT 1");
        if ($uid) {
            self::$given_user_ids[] = $uid;

            return $uid;
        }
        if (!$isLoop) {
            self::$given_user_ids = [];

            return $this->getRandomUID($spread_timeout, true);
        }

        return false;
    }

    public function addItemToUser(int $uid, int $btype, int $type, int $num): bool
    {
        $db = DB::getInstance();
        $stackAble = $btype >= 7 && $btype !== 12 && $btype !== 13;
        if ($stackAble) {
            $itemId = (int) $db->fetchScalar("SELECT id FROM items WHERE uid=$uid AND btype=$btype AND type=$type AND proc=0");
            if ($itemId) {
                return (bool) $db->query("UPDATE items SET num=num+$num WHERE id=$itemId");
            }
        }
        $placeId = $this->getNewPlaceId($uid);

        return (bool) $db->query("INSERT INTO items(uid, btype, type, num, proc, placeId) VALUES ($uid, $btype, $type, $num, 0, $placeId)");
    }

    public function removeItemFromUser(int $uid, int $btype, int $type, int $num): bool
    {
        $db = DB::getInstance();
        $stmt = $db->query("SELECT id, num FROM items WHERE uid=$uid AND btype=$btype AND type=$type AND proc=0");
        while ($stmt && ($row = $stmt->fetch_assoc())) {
            if ($num <= 0) {
                break;
            }
            $rowNum = (int) $row['num'];
            if ($rowNum <= $num) {
                $db->query('DELETE FROM items WHERE id=' . (int) $row['id']);
            } else {
                $db->query("UPDATE items SET num=num-$num WHERE id=" . (int) $row['id']);
            }
            $num -= $rowNum;
        }

        return true;
    }

    public function getNewPlaceId(int $uid): int
    {
        $db = DB::getInstance();
        $occupiedPlaces = [];
        $statement = $db->run(
            'SELECT placeId FROM items WHERE uid=? AND proc=0 AND placeId BETWEEN 1 AND ?',
            [$uid, self::HERO_INVENTORY_MAX_SLOTS]
        );
        if (!$statement) {
            throw new \RuntimeException('Could not read the hero inventory.');
        }

        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $occupiedPlaces[(int)$row['placeId']] = true;
        }

        for ($placeId = 1; $placeId <= self::HERO_INVENTORY_MAX_SLOTS; ++$placeId) {
            if (!isset($occupiedPlaces[$placeId])) {
                return $placeId;
            }
        }

        throw new \RuntimeException('The hero inventory is full.');
    }

    public function getInventorySize(int $uid, bool $includeOnHeroItems = true): int
    {
        return self::HERO_INVENTORY_MAX_SLOTS;
    }

    public function getVisibleInventorySize(int $uid): int
    {
        $maxPlaceId = max(0, (int) DB::getInstance()->fetchScalar(
            "SELECT COALESCE(MAX(placeId), 0) FROM items WHERE uid=$uid AND proc=0",
            [],
            0
        ));

        return self::visibleInventorySlotsForMaxPlaceId($maxPlaceId);
    }

    /**
     * The V2 inventory starts with one visible row. When its final slot is
     * occupied, the client opens the next row so an empty destination is
     * always immediately available. It intentionally never grows beyond six
     * rows.
     */
    public static function visibleInventorySlotsForMaxPlaceId(int $maxPlaceId): int
    {
        $maxPlaceId = max(0, $maxPlaceId);
        $rows = max(1, (int)ceil($maxPlaceId / self::HERO_INVENTORY_COLUMNS));
        if ($maxPlaceId > 0
            && $maxPlaceId < self::HERO_INVENTORY_MAX_SLOTS
            && $maxPlaceId % self::HERO_INVENTORY_COLUMNS === 0) {
            ++$rows;
        }

        return min(self::HERO_INVENTORY_MAX_ROWS, $rows) * self::HERO_INVENTORY_COLUMNS;
    }

    private function updateUserSilver(int $uid, int $silver, int $mode = 0): bool
    {
        return (bool) DB::getInstance()->query("UPDATE users SET silver=silver" . ($mode === 0 ? '-' : '+') . "$silver WHERE id=$uid");
    }

    /**
     * @param array<int, int|string>|string|int $cause accounting reason; a list is stored comma-separated
     */
    public function addBooking(int $uid, array|string|int $cause, int|float $reserve, int|float $balance, int $time): void
    {
        $cause = is_array($cause) ? implode(',', $cause) : (string) $cause;
        DB::getInstance()->run(
            'INSERT INTO accounting (uid, cause, reserve, balance, time) VALUES (?, ?, ?, ?, ?)',
            [$uid, $cause, $reserve, $balance, $time]
        );
    }

    public function getPlayerOutBidCount(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(bids.id) FROM bids, auction WHERE auction.id=bids.auctionId AND auction.finish=0 AND bids.outbid=1 AND bids.uid=$uid");
    }

    public function getPlayerMaximumBidCount(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(bids.id) FROM bids, auction WHERE auction.id=bids.auctionId AND auction.finish=0 AND bids.outbid=0 AND bids.uid=$uid");
    }

    public function getAvailableItemsToSell(int $uid): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM items WHERE uid=$uid AND proc=0");
    }

    public function getMyRunningAuctions(int $uid): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM auction WHERE uid=$uid AND finish=0 ORDER BY id ASC");
    }

    public function getMyRunningAuctionsCount(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM auction WHERE uid=$uid AND finish=0");
    }

    public function getMyFinishedAuctions(int $uid, int $page, int $pageSize, int|string $filter = 'all'): mysqli_result|bool
    {
        $filterSql = $this->auctionFilterClause($filter);

        return DB::getInstance()->query("SELECT * FROM auction WHERE uid=$uid {$filterSql} AND finish=1 ORDER BY id DESC LIMIT " . (($page - 1) * $pageSize) . ", " . $pageSize);
    }

    /** SQL condition for the item-category filter of the auction screens; '' for "all". */
    public function auctionFilterClause(int|string $filter): string
    {
        if ($filter === 0 || $filter === '0' || $filter === '' || $filter === 'all') {
            return '';
        }
        if ($filter === 'bag') {
            return ' AND btype IN (7,8,9,10,11,12,13,14,15)';
        }

        $slotMap = [
            'helmet' => 1,
            'body' => 2,
            'leftHand' => 3,
            'rightHand' => 4,
            'shoes' => 5,
            'horse' => 6,
        ];
        if (is_string($filter) && isset($slotMap[$filter])) {
            return ' AND btype=' . $slotMap[$filter];
        }
        if (is_numeric($filter) && (int) $filter >= 1 && (int) $filter <= 15) {
            return ' AND btype=' . (int) $filter;
        }

        return '';
    }

    public function getMyRunningBids(int $uid, int|string $filter): mysqli_result|bool
    {
        $filterSql = $this->auctionFilterClause($filter);

        return DB::getInstance()->query("SELECT bids.*, auction.btype, auction.type, auction.activeUid, auction.maxSilver, auction.num, auction.secure_id, auction.activeId, auction.silver, auction.bids, auction.time FROM bids, auction WHERE bids.uid=$uid {$filterSql} AND bids.auctionId=auction.id AND auction.finish=0 ORDER BY auction.time ASC, bids.id DESC");
    }

    public function getMyFinishedBids(int $uid, int|string $filter, int $page, int $pageSize): mysqli_result|bool
    {
        $filterSql = $this->auctionFilterClause($filter);

        return DB::getInstance()->query("SELECT bids.*, auction.btype, auction.type, auction.activeId, auction.activeUid, auction.secure_id, auction.num, auction.silver, auction.maxSilver, auction.bids, auction.time FROM bids, auction WHERE bids.uid=$uid {$filterSql} AND auction.id=bids.auctionId AND auction.finish=1 AND del=0 ORDER BY bids.id DESC LIMIT  " . (($page - 1) * $pageSize) . ", " . $pageSize);
    }

    public function getMyFinishedBidsCount(int $uid, int|string $filter): int
    {
        $filterSql = $this->auctionFilterClause($filter);

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(bids.id) FROM bids, auction WHERE bids.uid=$uid {$filterSql} AND auction.activeId=bids.id AND auction.finish=1 AND del=0");
    }

    public function getHeroGender(int $uid): string|false
    {
        $gender = DB::getInstance()->fetchScalar("SELECT gender FROM face WHERE uid=$uid");

        return $gender === false || $gender === null ? false : (string) $gender;
    }

    /** Silver tied up in the player's winning bids. */
    public function getInBidSilver(int|string $uid): int
    {
        $uid = (int) $uid;

        return (int) DB::getInstance()->fetchScalar("SELECT SUM(maxSilver) FROM auction WHERE finish=0 AND activeUid=$uid");
    }

    public function cancelAuction(int $id): bool
    {
        $db = DB::getInstance();
        $query = $db->query("UPDATE auction SET finish=1, cancel=1 WHERE id=$id");

        return $query && $db->affectedRows() > 0;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getAuction(int $id): array|false
    {
        return $this->fetchRow("SELECT * FROM auction WHERE id=$id") ?? false;
    }

    public function decreaseCageOrientItem(int $uid, int $itemId, int $total_num, int $num): void
    {
        $this->getItemFromUser($itemId, $total_num, $num);
        if ($total_num === $num) {
            DB::getInstance()->query("UPDATE inventory SET bag=0 WHERE uid=$uid");
        }
    }

    public function getItemFromUser(int $itemId, int $total_num, int $num): bool
    {
        $db = DB::getInstance();
        $query = $total_num === $num
            ? $db->query("DELETE FROM items WHERE id=$itemId AND num >= $num")
            : $db->query("UPDATE items SET num=num-$num WHERE id=$itemId AND num >= $num");

        return $query && $db->affectedRows() > 0;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getAuctionBySecureId(mixed $secure_id): array|false
    {
        if (!is_string($secure_id)) {
            return false;
        }
        $statement = DB::getInstance()->run('SELECT * FROM auction WHERE secure_id=?', [$secure_id]);
        $row = $statement === false ? null : $statement->get_result()->fetch_assoc();
        if (!is_array($row)) {
            return false;
        }

        return array_map(static fn (mixed $value): ?string => $value === null ? null : (string) $value, $row);
    }

    public function getTotalBuyAuction(int $uid, int|string $filter): int
    {
        //TODO: ip tables!
        $where = 'finish=0 AND uid!=' . $uid . $this->auctionFilterClause($filter);

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM auction WHERE {$where}");
    }

    public function getBuyAuction(int $uid, int $page, int $pageSize, int|string $filter): mysqli_result|bool
    {
        //TODO: ip tables!
        $where = 'finish=0 AND uid!=' . $uid . $this->auctionFilterClause($filter);

        return DB::getInstance()->query('SELECT * FROM auction WHERE ' . $where . ' ORDER BY time ASC LIMIT ' . (($page - 1) * $pageSize) . ', ' . $pageSize);
    }

    /**
     * Every group of the buy list, unsorted and unpaged: the caller orders the groups (for example by localized
     * item name) and paginates them itself.
     */
    public function getAllBuyAuctionGroups(int $uid, int|string $filter): mysqli_result|bool
    {
        $where = 'finish=0 AND uid!=' . $uid . $this->auctionFilterClause($filter);

        return DB::getInstance()->query(
            'SELECT btype, type, COUNT(*) AS auction_count, MIN(time) AS next_finish, MIN(silver) AS next_price, '
            . 'SUBSTRING_INDEX(GROUP_CONCAT(secure_id ORDER BY silver ASC SEPARATOR \',\'), \',\', 1) AS secure_id '
            . 'FROM auction WHERE ' . $where . ' GROUP BY btype, type'
        );
    }

    /**
     * Active buy rows for the native item-detail screen. The listing route
     * groups these rows by item type, while the detail route exposes every
     * available auction and retains the existing bid IDs for processBuy().
     */
    public function getBuyAuctionItemRows(int $uid, int $type, int $page = 1, int $pageSize = 10): mysqli_result|bool
    {
        $offset = max(0, ($page - 1) * $pageSize);

        return DB::getInstance()->query(
            'SELECT auction.*, users.name AS active_bidder_name FROM auction '
            . 'LEFT JOIN users ON users.id=auction.activeUid '
            . 'WHERE auction.finish=0 AND auction.uid!=' . $uid
            . ' AND auction.type=' . $type
            . ' ORDER BY auction.time ASC, auction.id ASC LIMIT ' . $offset . ', ' . max(1, $pageSize)
        );
    }

    public function getTotalBuyAuctionItemRows(int $uid, int $type): int
    {
        return (int)DB::getInstance()->fetchScalar(
            'SELECT COUNT(*) FROM auction WHERE finish=0 AND uid!=' . $uid
            . ' AND type=' . $type
        );
    }

    /**
     * @return array{lowest: int, average: int, highest: int}|null
     */
    public function getBuyAuctionItemPrices(int $uid, int $type): ?array
    {
        $result = DB::getInstance()->query(
            'SELECT MIN(silver) AS lowest, AVG(silver) AS average, MAX(silver) AS highest '
            . 'FROM auction WHERE finish=0 AND uid!=' . $uid
            . ' AND type=' . $type
        );
        $prices = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        if ($prices === null || $prices['lowest'] === null) {
            return null;
        }

        return [
            'lowest' => (int)$prices['lowest'],
            'average' => (int)round((float)$prices['average']),
            'highest' => (int)$prices['highest'],
        ];
    }

    /**
     * @return array<int, array{time: int, silver: int}>
     */
    public function getBuyAuctionItemHistory(int $type, int $since): array
    {
        $result = DB::getInstance()->query(
            'SELECT time, silver FROM auction WHERE type=' . $type
            . ' AND finish=1 AND cancel=0 AND time>=' . (int)$since
            . ' ORDER BY time ASC'
        );
        $history = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $history[] = [
                'time' => (int)$row['time'],
                'silver' => (int)$row['silver'],
            ];
        }

        return $history;
    }

    /**
     * @return array<string, int>
     */
    public function getBuyAuctionSearchCounts(int $uid): array
    {
        $result = DB::getInstance()->query(
            'SELECT btype, type, COUNT(*) AS auction_count FROM auction'
            . ' WHERE finish=0 AND uid!=' . $uid
            . ' GROUP BY btype, type'
        );
        $counts = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $counts[(int)$row['btype'] . ':' . (int)$row['type']] = (int)$row['auction_count'];
        }

        return $counts;
    }

    public function modifyAuction(int $id, int $activeId, int $activeUid, int|float $silver, int|float $maxSilver, int $time, bool $add = true): bool
    {
        $db = DB::getInstance();
        $time = max($time, time() + 300);
        $query = $db->query("UPDATE auction SET activeId=$activeId, activeUid=$activeUid, silver = $silver, maxSilver=$maxSilver" . ($add ? ', bids = bids + 1' : '') . ", time = $time where id = $id");

        return $query && $db->affectedRows() > 0;
    }

    /** Records (or reopens) the player's bid on an auction; returns the bid id. */
    public function addBid(int $uid, int $auctionId): int
    {
        $db = DB::getInstance();
        $db->query("UPDATE bids SET outbid=0 WHERE uid=$uid AND auctionId=$auctionId");
        if (!$db->affectedRows()) {
            $db->query("INSERT INTO bids (uid, auctionId) VALUES ($uid, $auctionId)");

            return (int) $db->lastInsertId();
        }

        return (int) $db->fetchScalar("SELECT id FROM bids WHERE uid=$uid AND auctionId=$auctionId AND outbid=0");
    }

    public function setAsOutBid(int $activeId): void
    {
        DB::getInstance()->query("UPDATE bids SET outbid=1 WHERE id=$activeId");
    }

    public function fakeAuction(): void
    {
        $config = Config::getInstance();
        $auctionConfig = $config->auction;
        if (!$auctionConfig->fakeAuction->enabled || getGameElapsedSeconds() < 0) {
            return;
        }
        if (getCustom('delayAuctions') && getGameElapsedSeconds() < ($config->game->protection_time * 2 / 3)) {
            return;
        }
        $db = DB::getInstance();
        $lastFakeAuction = (int) $db->fetchScalar('SELECT lastFakeAuction FROM config');
        if ($lastFakeAuction !== 0 && (time() - $lastFakeAuction) < $auctionConfig->fakeAuction->interval) {
            return;
        }
        $db->query('UPDATE config SET lastFakeAuction=' . time());
        $items = new HeroItems();
        $auctionTime = time() + (int) $config->auction->auctionTime;
        $highSpeedTrainingHelmets = getGameSpeed() > 20;
        $tierNum = Formulas::getCurrentHeroItemsTier();
        for ($i = 1; $i <= 35; ++$i) {
            $rand = $items->getRandomItem();
            $btype = (int) $rand['btype'];
            $type = (int) $rand['type'];
            if ($btype === 15 && getGameSpeed() <= 100) {
                continue;
            }
            $amounts = heroAuction::getPackagesFakeAuctionForItem($btype, $type);
            if ($highSpeedTrainingHelmets && $btype === 1 && ($type >= 10 && $type <= 15)) {
                continue;
            }
            if ($btype <= 6) {
                //this has tier
                $tier = ($type % 3) === 0 ? 3 : ($type % 3 === 1 ? 1 : 2);
                if ($tierNum !== $tier) {
                    continue;
                }
            }
            $size = count($amounts);
            $num = 1;
            if ($size > 1) {
                $num = (int) $amounts[mt_rand(0, max($size - 2, 1))];
            }
            $this->addAuction(true, 0, $btype, $type, $num, $auctionTime);
        }
        if ($highSpeedTrainingHelmets) {
            for ($i = 10; $i <= 15; ++$i) {
                $this->addAuction(true, 0, 1, $i, 1, $auctionTime, 2000);
            }
        }
    }

    public function addAuction(bool $isSystem, int $uid, int $btype, int $type, int $num, int $time, int|float|null $silver = null): bool
    {
        $stackAble = heroAuction::isStackable($btype);
        if (!$stackAble) {
            $num = 1;
        }
        if (empty($silver)) {
            if (getCustom('decreaseItemsPriceInAuction')) {
                $silver = match ($btype) {
                    14, 11, 10 => 1,
                    15 => 10,
                    8 => 0.05,
                    default => $stackAble ? 0.05 : 100,
                };
            } else {
                $silver = ($stackAble && $btype !== 15) ? 1 : 100;
                if ($btype === 6 || $btype === 13) {
                    $silver = 1;
                }
            }
            if ($silver < 1 && !$isSystem && getCustom('makeAuctionsCheaperInSell')) {
                $silver /= 10;
            }
            $silver = (int) ceil($num * $silver);
            if ($silver === 0) {
                $silver = $num;
            }
        }

        return (bool) DB::getInstance()->run(
            'INSERT INTO auction (uid, btype, type, num, silver, maxSilver, secure_id, time, created) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [$uid, $btype, $type, $num, $silver, sha1(microtime()), $time, time()]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRow(string $query): ?array
    {
        $result = DB::getInstance()->query($query);
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }
}
