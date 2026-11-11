<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Game\Formulas;
use Game\ResourcesHelper;
use mysqli_result;

/**
 * Marketplace reads and writes. Only ints are interpolated into SQL (everything else is bound), and rows are read
 * over the text protocol so columns stay strings for the callers and templates.
 */
class MarketModel
{
    public function getOnTheWayMerchantsCount(int $kid, int|float $merchant_cap): int
    {
        $db = DB::getInstance();

        return (int)ceil((float)$db->fetchScalar("SELECT COALESCE(SUM(CEIL((wood+clay+iron+crop) / $merchant_cap)), 0) FROM send WHERE ships=0 AND ((kid=$kid AND mode=0) OR (to_kid=$kid AND mode=1))"));
    }

    public function deleteOffer(int $id): bool
    {
        $db = DB::getInstance();
        $q = $db->query("DELETE FROM market WHERE id=$id");

        return $q && $db->affectedRows() > 0;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getOffer(int $id): array|false
    {
        return $this->fetchRow("SELECT * FROM market WHERE id=$id") ?? false;
    }

    public function getOfferingMerchantsCount(int $kid, int|float $merchant_cap): int
    {
        $db = DB::getInstance();

        return (int)ceil((float)$db->fetchScalar("SELECT COALESCE(SUM(CEIL(giveValue / $merchant_cap)), 0) FROM market WHERE kid=$kid"));
    }

    /**
     * Returns the active merchant movements visible from one village in the
     * shape consumed by the V2 marketplace. Returning merchants belong to the
     * outgoing group: they still occupy this village's merchants, but their
     * destination label is their original source village.
     *
     * @return array{
     *     incoming: list<array{id: int, villageKid: int, villageName: string, resources: array{0: int, 1: int, 2: int, 3: int}, repeat: int, endTime: int, returning: bool}>,
     *     outgoing: list<array{id: int, villageKid: int, villageName: string, resources: array{0: int, 1: int, 2: int, 3: int}, repeat: int, endTime: int, returning: bool}>
     * }
     */
    public function getDeliveryGroups(int $kid): array
    {
        $groups = ['incoming' => [], 'outgoing' => []];
        $statement = DB::getInstance()->run(
            'SELECT s.id, s.kid, s.to_kid, s.wood, s.clay, s.iron, s.crop, s.x, s.mode, s.end_time, '
            . 'source.name AS source_name, target.name AS target_name '
            . 'FROM send s '
            . 'LEFT JOIN vdata source ON source.kid=s.kid '
            . 'LEFT JOIN vdata target ON target.kid=s.to_kid '
            . 'WHERE (s.mode=0 AND (s.kid=? OR s.to_kid=?)) OR (s.mode=1 AND s.to_kid=?) '
            . 'ORDER BY s.end_time ASC, s.id ASC',
            [$kid, $kid, $kid]
        );
        $result = $statement ? $statement->get_result() : false;
        if (!$result) {
            return $groups;
        }

        while ($row = $result->fetch_assoc()) {
            $returning = (int)$row['mode'] === 1;
            $incoming = !$returning && (int)$row['to_kid'] === $kid;
            $villageKid = $incoming || $returning ? (int)$row['kid'] : (int)$row['to_kid'];
            $villageName = $incoming || $returning ? $row['source_name'] : $row['target_name'];
            $groups[$incoming ? 'incoming' : 'outgoing'][] = [
                'id' => (int)$row['id'],
                'villageKid' => $villageKid,
                'villageName' => is_string($villageName) && $villageName !== '' ? $villageName : '[?]',
                'resources' => [(int)$row['wood'], (int)$row['clay'], (int)$row['iron'], (int)$row['crop']],
                'repeat' => max(1, (int)$row['x']),
                'endTime' => (int)$row['end_time'],
                'returning' => $returning,
            ];
        }

        return $groups;
    }

    public function getTradeRoutes(int $kid, int $sortId, bool $reverse): mysqli_result|bool
    {
        $sortKey = $sortId === 1 ? 'v.name' : ($sortId === 2 ? 't.start_hour' : 't.enabled');
        $sortBy = $reverse ? 'DESC' : 'ASC';

        return DB::getInstance()->query("SELECT t.*, v.name  FROM traderoutes t, vdata v WHERE t.kid=$kid AND v.kid=t.to_kid ORDER BY $sortKey $sortBy");
    }

    /**
     * Alliance ids whose villages count as "mine" for a trade destination: the own alliance and its
     * confederates/NAPs (never for "only-mine"). -1 keeps the IN() list non-empty.
     *
     * @return list<int>
     */
    private function tradeAllianceIds(int $aid, string $destination): array
    {
        $al = [-1];
        if ($aid && $destination !== 'only-mine') {
            $al[] = $aid;
            $diplos = DB::getInstance()->query("SELECT aid1, aid2 FROM diplomacy WHERE (aid1=$aid OR aid2=$aid) AND accepted<>0");
            while ($diplos && ($row = $diplos->fetch_assoc())) {
                $al[] = (int) $row['aid1'];
                $al[] = (int) $row['aid2'];
            }
        }

        return array_values(array_unique($al));
    }

    /**
     * Villages a trade route can target from $kid, null for an unknown destination type.
     */
    public function getTradeRouteVillage(int $aid, int $owner, int $kid, string $destination): mysqli_result|bool|null
    {
        $aidArray = implode(',', $this->tradeAllianceIds($aid, $destination));
        $db = DB::getInstance();

        return match ($destination) {
            'all' => $db->query("SELECT kid, name FROM vdata v WHERE (owner=$owner OR ((isWW=1 OR (SELECT COUNT(id) FROM artefacts WHERE kid=v.kid) > 0) AND (SELECT aid FROM users WHERE id=v.owner) IN ($aidArray))) AND kid!=$kid"),
            'only-mine' => $db->query("SELECT kid, name FROM vdata v WHERE owner=$owner AND kid != $kid"),
            'others' => $db->query("SELECT kid, name FROM vdata v WHERE (owner!=$owner AND ((isWW=1 OR (SELECT COUNT(id) FROM artefacts WHERE kid=v.kid) > 0) AND (SELECT aid FROM users WHERE id=v.owner) IN ($aidArray))) AND kid!=$kid"),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getIncomingTradeRoute(int $kid): array|false
    {
        return $this->fetchRow("SELECT start_hour, r1, r2, r3, r4, time FROM traderoutes WHERE kid=$kid AND enabled=1 ORDER BY time ASC, (r1+r2+r3+r4) DESC LIMIT 1") ?? false;
    }

    public function addOffer(int $kid, int $aid, int $hours, int $needType, int $needValue, int $giveType, int $giveValue): void
    {
        if ($giveValue <= 0) {
            return;
        }
        $rate = round($needValue / $giveValue, 1);
        $xy = Formulas::kid2xy($kid);
        DB::getInstance()->run(
            'INSERT INTO market (aid, kid, x, y, rate, needType, needValue, giveType, giveValue, maxtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$aid, $kid, (int) $xy['x'], (int) $xy['y'], $rate, $needType, $needValue, $giveType, $giveValue, $hours * 3600]
        );
    }

    public function getVillageOwner(int $kid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid");
    }

    /**
     * @return array<string, mixed> name and race, empty when the player does not exist
     */
    public function getPlayerRaceAndName(int $uid): array
    {
        return $this->fetchRow("SELECT name, race FROM users WHERE id=$uid") ?? [];
    }

    /**
     * The village name as a map link, or as plain text when $HTML is false; "[?]" when the village is gone.
     */
    public function getVillageName(int $kid, bool $HTML = true): string
    {
        $name = (string) DB::getInstance()->fetchScalar("SELECT name FROM vdata WHERE kid=$kid");
        if ($name !== '') {
            return $HTML ? '<a href="/karte.php?d=' . $kid . '">' . $name . '</a>' : $name;
        }

        return $HTML ? '<span class="errorMessage">[?]</span>' : '';
    }

    /**
     * Merchants of the village not tied up in offers or shipments, counted with FOR UPDATE: call it inside the
     * transaction that then reserves merchants, so a concurrent order cannot spend the same ones.
     */
    public function availableMerchantsLocked(int $kid, int $merchantCapacity, int $merchantCount): int
    {
        $db = DB::getInstance();
        $reserved = 0;
        $shipments = $db->run(
            'SELECT wood, clay, iron, crop FROM send WHERE ships=0 AND ((kid=? AND mode=0) OR (to_kid=? AND mode=1)) FOR UPDATE',
            [$kid, $kid]
        );
        $shipmentRows = $shipments ? $shipments->get_result() : false;
        if (!$shipmentRows) {
            throw new \RuntimeException('enterVillageNameOrCoordinate');
        }
        while ($row = $shipmentRows->fetch_assoc()) {
            $reserved += (int)ceil(
                ((float)$row['wood'] + (float)$row['clay'] + (float)$row['iron'] + (float)$row['crop']) / $merchantCapacity
            );
        }

        $offers = $db->run('SELECT giveValue FROM market WHERE kid=? FOR UPDATE', [$kid]);
        $offerRows = $offers ? $offers->get_result() : false;
        if (!$offerRows) {
            throw new \RuntimeException('enterVillageNameOrCoordinate');
        }
        while ($row = $offerRows->fetch_assoc()) {
            $reserved += (int)ceil((float)$row['giveValue'] / $merchantCapacity);
        }

        return max(0, $merchantCount - $reserved);
    }

    public function cancelAllOffers(int $kid): void
    {
        $offers = DB::getInstance()->query("SELECT id FROM market WHERE kid=$kid");
        while ($offers && ($row = $offers->fetch_assoc())) {
            $this->refund($kid, $this->cancelOffer($kid, (int) $row['id']));
        }
    }

    public function cancelAllOffersForAlliance(int $uid): void
    {
        $offers = DB::getInstance()->query("SELECT m.id, m.kid FROM market m, vdata v WHERE v.owner=$uid AND v.kid=m.kid AND m.aid>0");
        while ($offers && ($row = $offers->fetch_assoc())) {
            $this->refund((int) $row['kid'], $this->cancelOffer((int) $row['kid'], (int) $row['id']));
        }
    }

    /**
     * Gives the resources of cancelled offers back to the village.
     *
     * @param array<int, int> $res wood, clay, iron, crop
     */
    private function refund(int $kid, array $res): void
    {
        DB::getInstance()->query("UPDATE vdata SET wood=wood+{$res[0]}, clay=clay+{$res[1]}, iron=iron+{$res[2]}, crop=crop+{$res[3]} WHERE kid=$kid");
    }

    /**
     * Cancels one offer of the village and returns the resources to refund (all zero when nothing was cancelled).
     *
     * @return array<int, int> wood, clay, iron, crop
     */
    public function cancelOffer(int $kid, int $id): array
    {
        $db = DB::getInstance();
        $res = array_fill(0, 4, 0);
        $offer = $this->fetchRow("SELECT giveType, giveValue FROM market WHERE kid=$kid AND id=$id");
        if ($offer !== null) {
            $query = $db->query("DELETE FROM market WHERE id=$id");
            if ($query && $db->affectedRows()) {
                $res[(int) $offer['giveType'] - 1] += (int) $offer['giveValue'];
            }
        }

        return $res;
    }

    public function getOwnOffers(int $kid): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM market WHERE kid=$kid");
    }

    /**
     * @return array<int, mixed> stock per resource (1-4, crop never negative), empty when the village does not exist
     */
    public function getVillageResources(int $kid): array
    {
        ResourcesHelper::updateVillageResources($kid);
        $row = $this->fetchRow("SELECT wood, clay, iron, crop FROM vdata WHERE kid=$kid");
        if ($row === null) {
            return [];
        }

        return [
            1 => $row['wood'],
            2 => $row['clay'],
            3 => $row['iron'],
            4 => max($row['crop'], 0),
        ];
    }

    public function toggleTradeRoute(int $kid, int $trid, int $enabled): void
    {
        $enabled = $enabled === 1 ? 1 : 0;
        $result = $this->fetchRow("SELECT * FROM traderoutes WHERE kid=$kid AND id=$trid");
        if ($result === null) {
            return;
        }
        $time = $this->nextTradeRouteTime((int) $result['start_hour']);
        DB::getInstance()->query("UPDATE traderoutes SET enabled=$enabled, time=$time WHERE kid=$kid AND id=$trid");
    }

    public function sendResources(int $kid, int $to_kid, int $race, int $r1, int $r2, int $r3, int $r4, int $repeat, int|float $time = -1): void
    {
        if ($time == -1) {
            $time = time();
        }
        $speed = Formulas::merchantSpeed($race);
        $time = (int) ($time + Formulas::travelTimeSeconds(Formulas::getDistance($kid, $to_kid) / $speed * 3600));
        DB::getInstance()->query("INSERT INTO send (kid, to_kid, wood, clay, iron, crop, x, mode, end_time) VALUES ($kid, $to_kid,$r1, $r2, $r3, $r4, $repeat,0,$time)");
    }

    public function getPlayerRace(int $uid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT race FROM users WHERE id=$uid");
    }

    public function isThereAnArtifact(int $kid): bool
    {
        if (!Config::getInstance()->dynamic->ArtifactsReleased) {
            return false;
        }

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM artefacts WHERE kid=$kid") > 0;
    }

    public function isVillageMine(int $kid, int $aid, int $uid, string $destination): bool
    {
        $al = $this->tradeAllianceIds($aid, $destination);
        $details = $this->fetchRow("SELECT owner, isWW, (SELECT aid FROM users WHERE id=v.owner) as aid FROM vdata v WHERE kid=$kid");
        if ($details === null) {
            return false;
        }
        if ((int) $details['owner'] === $uid) {
            return true;
        }

        return in_array((int) $details['aid'], $al, true) && ($details['isWW'] || $this->isThereAnArtifact($kid));
    }

    public function addTradeRoute(int $kid, int $to_kid, int $r1, int $r2, int $r3, int $r4, int $hour, int $repeat, bool $useTimezoneHelper = false): void
    {
        $time = $this->nextTradeRouteTime($hour, $useTimezoneHelper);
        DB::getInstance()->query("INSERT INTO traderoutes (kid, to_kid, r1, r2, r3, r4, enabled, start_hour, times, time) VALUES ($kid, $to_kid, $r1, $r2, $r3, $r4, 1, $hour, $repeat, $time)");
    }

    /**
     * @return array{17: int, 28: int} Marketplace and Trade Office levels of the village
     */
    public function getMarketAndTradeOfficeLevel(int $kid): array
    {
        $return = [17 => 0, 28 => 0];
        $buildings = $this->fetchRow("SELECT * FROM fdata WHERE kid=$kid");
        if ($buildings !== null) {
            for ($i = 19; $i <= 38; ++$i) {
                $type = (int) $buildings['f' . $i . 't'];
                if ($type === 17 || $type === 28) {
                    $return[$type] = (int) $buildings['f' . $i];
                }
            }
        }

        return $return;
    }

    public function updateTradeRoute(int $id, int $to_kid, int $r1, int $r2, int $r3, int $r4, int $hour, int $repeat, bool $useTimezoneHelper = false): void
    {
        $time = $this->nextTradeRouteTime($hour, $useTimezoneHelper);
        DB::getInstance()->query("UPDATE traderoutes SET to_kid=$to_kid, r1=$r1, r2=$r2, r3=$r3, r4=$r4, start_hour=$hour, times=$repeat, time=$time WHERE id=$id");
    }

    /**
     * When a trade route starting at $hour first runs: one period from now on periodic worlds, otherwise the next
     * occurrence of that hour (in the player's timezone when requested).
     */
    private function nextTradeRouteTime(int $hour, bool $useTimezoneHelper = false): int
    {
        if (Config::getInstance()->game->usePeriodicTradeRoutes) {
            return time() + $hour;
        }
        if ($useTimezoneHelper) {
            $day = (int) TimezoneHelper::date('H') < $hour ? 'today' : 'tomorrow';

            return (int) TimezoneHelper::real_strtotime("$day $hour:00");
        }
        $day = (int) date('H') < $hour ? 'today' : 'tomorrow';

        return (int) strtotime("$day $hour:00");
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getTradeRoute(int $kid, int $trid): array|false
    {
        return $this->fetchRow("SELECT * FROM traderoutes WHERE kid=$kid AND id=$trid") ?? false;
    }

    public function deleteTradeRoute(int $kid, int $trid): void
    {
        DB::getInstance()->query("DELETE FROM traderoutes WHERE kid=$kid AND id=$trid");
    }

    public function getOffersCount(int $aid, int $kid, int $race, int $rate, int $needType, int $wantType, bool $onlyAlliance = false): int
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM market WHERE ' . $this->offerFilter($aid, $kid, $race, $rate, $needType, $wantType, $onlyAlliance));
    }

    public function getOffers(int $page, int $pageSize, int $aid, int $kid, int $race, int $rate, int $needType, int $wantType, bool $onlyAlliance = false): mysqli_result|bool
    {
        $limit = ($page - 1) * $pageSize . ', ' . $pageSize;

        return DB::getInstance()->query(
            'SELECT * FROM market WHERE ' . $this->offerFilter($aid, $kid, $race, $rate, $needType, $wantType, $onlyAlliance)
            . ' ORDER BY ' . $this->distanceTo($kid) . " LIMIT $limit"
        );
    }

    /** Distance between an offer's village and $kid, as SQL. */
    private function distanceTo(int $kid): string
    {
        $xy = Formulas::kid2xy($kid);

        return 'SQRT(POW(x-' . (int) $xy['x'] . ', 2) + POW(y-' . (int) $xy['y'] . ', 2))';
    }

    /** WHERE conditions of the offers visible to a village: in merchant range, open to its alliance, and matching the filters. */
    private function offerFilter(int $aid, int $kid, int $race, int $rate, int $needType, int $wantType, bool $onlyAlliance): string
    {
        $speed = Formulas::merchantSpeed($race);
        $dist = $this->distanceTo($kid);
        $cond = [];
        if ($needType > 0) {
            $cond[] = 'giveType=' . $needType;
        }
        if ($wantType > 0) {
            $cond[] = 'needType=' . $wantType;
        }
        if ($rate) {
            $cond[] = 'rate=1';
        }
        if ($onlyAlliance) {
            // "Only alliance": offers made from villages of members of the viewer's alliance.
            $cond[] = $aid > 0
                ? 'kid IN (SELECT vdata.kid FROM vdata INNER JOIN users ON users.id=vdata.owner WHERE users.aid=' . $aid . ')'
                : '1=0';
        }

        return "kid!=$kid AND IF(maxtime>0, IF(ROUND({$dist}/{$speed}) <= maxtime, TRUE, FALSE), TRUE) AND IF(aid>0, IF(aid=$aid, TRUE, FALSE), TRUE)"
            . ($cond === [] ? '' : ' AND ' . implode(' AND ', $cond));
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
