<?php

declare(strict_types=1);

namespace Game\FarmList;

use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Model\MovementsModel;

use function nrToUnitId;

/**
 * The one read model of the farm list page: it builds the `viewData` the page client renders from, and the
 * same list shape is returned by every mutation, so the page never has to reload. Every slot of every list is
 * embedded; the client expands and collapses without a request.
 */
final class FarmListData
{
    /** The reference page's Travian.Constants.VILLAGE_TYPE_* values. */
    public const TYPE_VILLAGE = 0;
    public const TYPE_CAPITAL = 1;
    public const TYPE_OASIS_OCCUPIED = 2;
    public const TYPE_OASIS_FREE = 3;

    public const LIST_LIMIT = 100;
    public const SLOT_LIMIT = 100;
    public const MAX_UNITS = 9999999;

    /** Sort columns a list accepts, as the client's headers name them. */
    public const SORT_FIELDS = ['village', 'ew', 'distance', 'troops', 'lastRaid', 'combatResult', 'bounty', 'totalBounty', 'nextAttack'];

    /** Report types a last raid can have, best result first; the client turns a type into its icon. */
    private const COMBAT_RESULTS = [1, 2, 3, 15, 16, 17];

    private DB $db;
    private Session $session;
    private int $uid;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->session = Session::getInstance();
        $this->uid = $this->session->getPlayerId();
    }

    /** Village names are stored HTML-encoded; the page gets plain text and encodes it itself. */
    public static function plain(string $name): string
    {
        return htmlspecialchars_decode($name, ENT_QUOTES);
    }

    /** @return array<string, mixed> */
    public function viewData(): array
    {
        $sitter = $this->session->isSitter();
        $protectionEnd = $this->session->hasProtection() ? (int)$this->session->protectionTill() : null;

        return [
            'bootstrapData' => ['timestamp' => time()],
            'weekendWarrior' => ['isNightTruce' => false],
            'ownPlayer' => [
                'isSitter' => $sitter,
                'isInVacationMode' => $this->session->isInVacationMode(),
                'beginnersProtection' => $protectionEnd,
                'accessRights' => ['sendRaids' => !$sitter || (bool)$this->session->checkSitterPermission(Session::SITTER_CAN_RAID)],
                'banInfo' => $this->session->banned() ? ['lockStatus' => 1] : null,
                'village' => ['id' => (int)$this->session->getKid(), 'tribeId' => (int)$this->session->getRace()],
                'villages' => $this->villages(),
                'abandonedFarmLists' => $this->listsOf(true),
                'farmLists' => $this->listsOf(false),
                'deactivatedFarmListTargets' => $this->deactivatedTargets(),
            ],
        ];
    }

    /**
     * The given lists in the page's shape, in the page's order; lists that are not the player's are left out.
     *
     * @param list<int> $listIds
     * @return list<array<string, mixed>>
     */
    public function lists(array $listIds): array
    {
        return array_values(array_filter(
            array_merge($this->listsOf(false), $this->listsOf(true)),
            static fn (array $list): bool => in_array($list['id'], $listIds, true)
        ));
    }

    /** @return list<array<string, mixed>> */
    private function villages(): array
    {
        $race = (int)$this->session->getRace();
        $villages = [];
        foreach ($this->rows('SELECT v.kid, v.name, f.* FROM vdata v LEFT JOIN fdata f ON f.kid = v.kid WHERE v.owner = ? ORDER BY v.kid', [$this->uid]) as $row) {
            $harbour = false;
            for ($slot = 19; $slot <= 40; $slot++) {
                $harbour = $harbour || ((int)($row['f' . $slot . 't'] ?? 0) === 49 && (int)$row['f' . $slot] > 0);
            }
            $villages[] = [
                'id' => (int)$row['kid'],
                'name' => self::plain((string)$row['name']),
                'hasRallyPoint' => (int)($row['f39t'] ?? 0) === 16 && (int)$row['f39'] > 0,
                'hasHarbour' => $harbour,
                'tribeId' => $race,
            ];
        }

        return $villages;
    }

    /**
     * Lists of the player's villages, or (abandoned) of villages the player no longer owns.
     *
     * @return list<array<string, mixed>>
     */
    private function listsOf(bool $abandoned): array
    {
        $rows = $this->rows(
            'SELECT l.* FROM farmlist l LEFT JOIN vdata v ON v.kid = l.kid AND v.owner = l.owner WHERE l.owner = ? AND v.kid IS '
            . ($abandoned ? '' : 'NOT ') . 'NULL ORDER BY l.kid, l.sortIndex, l.id',
            [$this->uid]
        );
        $lists = [];
        foreach ($rows as $row) {
            $lists[] = $this->shape($row, $abandoned);
        }

        return $lists;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function shape(array $row, bool $abandoned): array
    {
        $listId = (int)$row['id'];
        $kid = (int)$row['kid'];
        $running = $this->runningAttacks($kid, $listId);
        $slots = $this->slots($listId, $running, (string)$row['sortField'], (string)$row['sortDirection']);
        $list = [
            'id' => $listId,
            'name' => (string)$row['name'],
            'slotsAmount' => count($slots),
            'runningRaidsAmount' => count($running),
            'isExpanded' => (bool)$row['isExpanded'],
            'sortIndex' => (int)$row['sortIndex'],
            'lastStartedTime' => (int)$row['lastStartedTime'],
            'sortField' => (string)$row['sortField'],
            'sortDirection' => (string)$row['sortDirection'],
            'useShip' => (bool)$row['useShip'],
            'onlyLosses' => (bool)$row['onlyLosses'],
            'ownerVillage' => ['id' => $kid],
            'defaultTroop' => $this->troops($row),
            'slotsStates' => array_map(static fn (array $slot): array => ['id' => $slot['id'], 'isActive' => $slot['isActive']], $slots),
            'slots' => $slots,
        ];
        if (!$abandoned) {
            $list['ownerVillage']['troops'] = ['ownTroopsAtTown' => ['units' => $this->troops($this->rows('SELECT * FROM units WHERE kid = ?', [$kid])[0] ?? [])]];
        }

        return $list;
    }

    /**
     * @param array<int, array{count: int, next: int, spy: bool}> $running running movements by target
     * @return list<array<string, mixed>>
     */
    private function slots(int $listId, array $running, string $sortField, string $sortDirection): array
    {
        $slots = [];
        foreach ($this->rows(
            'SELECT r.*, w.x, w.y, w.oasistype, w.occupied, v.name, v.pop, v.capital FROM raidlist r INNER JOIN wdata w ON w.id = r.kid LEFT JOIN vdata v ON v.kid = r.kid WHERE r.lid = ? ORDER BY r.id',
            [$listId]
        ) as $row) {
            $kid = (int)$row['kid'];
            $troop = $this->troops($row);
            $slots[] = [
                'id' => (int)$row['id'],
                'target' => $this->target($row),
                'troop' => $troop,
                'distance' => round((float)$row['distance'], 1),
                'isActive' => (bool)$row['isActive'],
                'isRunning' => isset($running[$kid]),
                'isSpying' => ($running[$kid]['spy'] ?? false),
                'runningAttacks' => $running[$kid]['count'] ?? 0,
                'nextAttackAt' => $running[$kid]['next'] ?? null,
                'lastRaid' => $this->lastRaid($kid, $troop),
                'totalBooty' => $this->totalBooty($kid),
            ];
        }

        return $this->sorted($slots, $sortField, $sortDirection);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function target(array $row): array
    {
        $oasis = (int)$row['oasistype'] > 0;
        $x = (int)$row['x'];
        $y = (int)$row['y'];

        return [
            'id' => (int)$row['kid'],
            'mapId' => (int)$row['kid'],
            'x' => $x,
            'y' => $y,
            'name' => $oasis ? '' : self::plain((string)($row['name'] ?? '')),
            'type' => match (true) {
                !$oasis => (int)($row['capital'] ?? 0) === 1 ? self::TYPE_CAPITAL : self::TYPE_VILLAGE,
                (int)$row['occupied'] === 1 => self::TYPE_OASIS_OCCUPIED,
                default => self::TYPE_OASIS_FREE,
            },
            'population' => $oasis ? null : (int)($row['pop'] ?? 0),
        ];
    }

    /**
     * @param array<string, int> $troop t1..t10
     * @return array<string, mixed>|null
     */
    private function lastRaid(int $targetKid, array $troop): ?array
    {
        $report = $this->rows(
            'SELECT n.id, n.type, n.bounty, n.private_key, n.time FROM ndata n WHERE n.id = (SELECT report_id FROM farmlist_last_reports WHERE uid = ? AND kid = ? ORDER BY id DESC LIMIT 1)',
            [$this->uid, $targetKid]
        )[0] ?? null;
        if ($report === null) {
            return null;
        }
        $type = (int)$report['type'];
        $bounty = array_map('intval', explode(',', (string)$report['bounty']));
        $carry = 0;
        $race = (int)$this->session->getRace();
        foreach ($troop as $key => $amount) {
            $carry += $amount * (int)Formulas::uCarry(nrToUnitId((int)substr($key, 1), $race));
        }

        return [
            'reportObjectId' => (int)$report['id'],
            'authKey' => '|' . $report['private_key'],
            'time' => (int)$report['time'],
            'raidedResources' => ['lumber' => $bounty[0] ?? 0, 'clay' => $bounty[1] ?? 0, 'iron' => $bounty[2] ?? 0, 'crop' => $bounty[3] ?? 0],
            'bootyMax' => $carry,
            'icon' => $type,
        ];
    }

    /** @return array{booty: int, raids: int} */
    private function totalBooty(int $targetKid): array
    {
        $booty = 0;
        $raids = 0;
        foreach ($this->rows('SELECT bounty FROM ndata WHERE uid = ? AND to_kid = ? AND type IN (1, 2, 3) AND deleted = 0', [$this->uid, $targetKid]) as $row) {
            $booty += array_sum(array_map('intval', explode(',', (string)$row['bounty'])));
            $raids++;
        }

        return ['booty' => $booty, 'raids' => $raids];
    }

    /** @return list<array<string, int|string>> */
    private function deactivatedTargets(): array
    {
        $targets = [];
        foreach ($this->rows(
            'SELECT d.kid, w.x, w.y, w.oasistype, w.occupied, v.name, v.capital FROM farmlist_deactivated_targets d INNER JOIN wdata w ON w.id = d.kid LEFT JOIN vdata v ON v.kid = d.kid WHERE d.uid = ? ORDER BY d.created, d.kid',
            [$this->uid]
        ) as $row) {
            $target = $this->target($row);
            unset($target['population']);
            $targets[] = $target;
        }

        return $targets;
    }

    /**
     * Running raids and spy missions of one village against the targets of one list, keyed by target.
     *
     * @return array<int, array{count: int, next: int, spy: bool}>
     */
    private function runningAttacks(int $kid, int $listId): array
    {
        $targets = [];
        foreach ($this->rows(
            'SELECT m.to_kid, COUNT(m.id) AS amount, MIN(m.end_time) AS nextAt, MAX(m.attack_type = ?) AS spy FROM movement m'
            . ' INNER JOIN raidlist r ON r.kid = m.to_kid AND r.lid = ? WHERE m.kid = ? AND m.mode = 0 AND m.attack_type IN (?, ?, ?) GROUP BY m.to_kid',
            [MovementsModel::ATTACKTYPE_SPY, $listId, $kid, MovementsModel::ATTACKTYPE_SPY, MovementsModel::ATTACKTYPE_NORMAL, MovementsModel::ATTACKTYPE_RAID]
        ) as $row) {
            $targets[(int)$row['to_kid']] = ['count' => (int)$row['amount'], 'next' => intdiv((int)$row['nextAt'], 1000), 'spy' => (int)$row['spy'] === 1];
        }

        return $targets;
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return list<array<string, mixed>>
     */
    private function sorted(array $slots, string $field, string $direction): array
    {
        if (!in_array($field, self::SORT_FIELDS, true)) {
            return $slots;
        }
        $key = static fn (array $slot): int|float|string => match ($field) {
            'village' => mb_strtolower($slot['target']['name'] !== '' ? $slot['target']['name'] : $slot['target']['x'] . '|' . $slot['target']['y']),
            'ew' => (int)$slot['target']['population'],
            'distance' => $slot['distance'],
            'troops' => array_sum($slot['troop']),
            'lastRaid' => $slot['lastRaid']['time'] ?? 0,
            'combatResult' => isset($slot['lastRaid']) ? 1 + (int)array_search($slot['lastRaid']['icon'], self::COMBAT_RESULTS, true) : 0,
            'bounty' => array_sum($slot['lastRaid']['raidedResources'] ?? []),
            'totalBounty' => $slot['totalBooty']['booty'],
            'nextAttack' => $slot['nextAttackAt'] ?? PHP_INT_MAX,
        };
        usort($slots, static fn (array $a, array $b): int => $direction === 'desc' ? $key($b) <=> $key($a) : $key($a) <=> $key($b));

        return $slots;
    }

    /**
     * @param array<string, mixed> $row any row with u1..u10
     * @return array<string, int>
     */
    private function troops(array $row): array
    {
        $troops = [];
        for ($unit = 1; $unit <= 10; $unit++) {
            $troops['t' . $unit] = (int)($row['u' . $unit] ?? 0);
        }

        return $troops;
    }

    /**
     * @param list<mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function rows(string $query, array $parameters): array
    {
        $statement = $this->db->run($query, $parameters);

        return $statement === false ? [] : $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
