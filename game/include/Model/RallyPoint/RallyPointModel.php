<?php

declare(strict_types=1);

namespace Model\RallyPoint;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use InvalidArgumentException;
use Model\MovementsModel;
use mysqli_result;

use function getCustom;
use function miliseconds;

class RallyPointStaticData
{
    private static ?RallyPointStaticData $instance = null;
    /** @var list<int>|null kids of the active village's oases, loaded once per request */
    public ?array $oases = null;

    public static function Singleton(): self
    {
        return self::$instance ??= new self();
    }
}

/**
 * Movement and troop-placement readers for the rally point. Only ints are interpolated into SQL and rows are read
 * over the text protocol, so columns stay strings for the callers (and the JSON they build).
 */
class RallyPointModel
{
    /** Orders of the outgoing list, as the reference names them in its `sortOrder` query value. */
    public const SORT_EARLIEST_ARRIVING_FIRST = 'earliestArrivingFirst';
    public const SORT_LATEST_SENT_FIRST = 'latestSentFirst';

    /** @var list<int> */
    private array $oases;

    public function __construct()
    {
        $static = RallyPointStaticData::Singleton();
        if (!is_array($static->oases)) {
            $static->oases = [];
            $kid = (int) Session::getInstance()->getKid();
            $oases = DB::getInstance()->query("SELECT kid FROM odata WHERE did=$kid");
            while ($oases && ($row = $oases->fetch_assoc())) {
                $static->oases[] = (int) $row['kid'];
            }
        }
        $this->oases = $static->oases;
    }

    ## Filters :
    # 1 => incoming
    # 2 => out going
    # 3 => in village
    # 4 => in other villages
    ## SubFilters
    ## Incoming
    # 1 => incoming attacks/raids
    # 2 => returning troops
    # 3 => incoming reinforcements
    ## OutGoing
    # 4 => Outgoing attacks/raids
    # 5 => Out going reinforcements

    /**
     * @param array<int, int|bool> $subFilters
     */
    public function getOutgoingMovementsCount(array $subFilters = [4 => 1, 5 => 1]): int
    {
        return (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM movement WHERE kid=' . $this->kid() . ' ' . $this->outgoingCondition($subFilters)
        );
    }

    public function cancelTask(int $taskId): void
    {
        $miliseconds = (int) miliseconds();
        $db = DB::getInstance();
        $kid = (int) Village::getInstance()->getKid();
        $result = $db->query("SELECT kid, to_kid, attack_type, mode, start_time, end_time FROM movement WHERE kid=$kid AND id=$taskId");
        $task = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        if (!is_array($task)) {
            return;
        }
        $taskKid = (int) $task['kid'];
        $toKid = (int) $task['to_kid'];
        $attackType = (int) $task['attack_type'];

        if (getCustom('realCancelAttack')) {
            $abort = ($miliseconds - (int) $task['start_time']) <= 90 * 1000;
        } else {
            $abort = ($miliseconds - (int) $task['start_time']) <= (round((int) $task['end_time'] - (int) $task['start_time'])) / 3;
        }
        if ($abort || $attackType === MovementsModel::ATTACKTYPE_EVASION) {
            $db->query("UPDATE movement SET kid=$toKid, to_kid=$taskKid, mode=1, end_time=(" . (2 * $miliseconds) . "-start_time), start_time=$miliseconds WHERE id=$taskId");
            if ($attackType === MovementsModel::ATTACKTYPE_RAID || $attackType === MovementsModel::ATTACKTYPE_NORMAL) {
                Caching::getInstance()->delete('attacks' . $toKid);
            }
        }

        if ($attackType === MovementsModel::ATTACKTYPE_SETTLERS && (int) $task['mode'] === 0) {
            // Add resources
            $db->query("UPDATE vdata SET wood=wood+750, clay=clay+750, iron=iron+750, crop=crop+750 WHERE kid=$taskKid");
        }
    }

    /**
     * @param array<int, int|bool> $subFilters
     */
    public function getOutgoingMovementsByLimit(int $page, array $subFilters = [4 => 1, 5 => 1], string $sortOrder = self::SORT_EARLIEST_ARRIVING_FIRST): mysqli_result|bool
    {
        $perPage = $this->perPage();
        $from = ($page - 1) * $perPage;
        $order = $sortOrder === self::SORT_LATEST_SENT_FIRST ? 'start_time DESC, id DESC' : 'end_time ASC, id ASC';

        return DB::getInstance()->query(
            'SELECT * FROM movement WHERE kid=' . $this->kid() . ' ' . $this->outgoingCondition($subFilters) . " ORDER BY $order LIMIT $from, $perPage"
        );
    }

    /**
     * @param array<int, int|bool> $subFilters
     */
    public function getIncomingMovementsCount(array $subFilters = [1 => 1, 2 => 2, 3 => 1]): int
    {
        return (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM movement WHERE to_kid IN(' . $this->villageAndOasisKids() . ') ' . $this->incomingCondition($subFilters)
        );
    }

    /**
     * @param array<int, int|bool> $subFilters
     */
    public function getIncomingMovementsByLimit(int $page, array $subFilters = [1 => 1, 2 => 2, 3 => 1]): mysqli_result|bool
    {
        $perPage = $this->perPage();
        $from = ($page - 1) * $perPage;

        return DB::getInstance()->query(
            'SELECT * FROM movement WHERE to_kid IN(' . $this->villageAndOasisKids() . ') ' . $this->incomingCondition($subFilters)
            . " ORDER BY end_time ASC, id ASC LIMIT $from, $perPage"
        );
    }

    public function getOasisEnforcesCount(int $kid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM enforcement WHERE to_kid=$kid");
    }

    public function getMyOasisEnforce(int $fromKid, int $kid): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM enforcement WHERE kid=$fromKid AND to_kid=$kid");
    }

    public function getOasisEnforcesByLimit(int $myKid, int $kid, int $from, int $free): mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT * FROM enforcement WHERE kid!=$myKid AND to_kid=$kid LIMIT $from, $free");
    }

    public function getEnforcesCount(int $mode): int
    {
        $kid = $this->kid();
        if ($mode === 1) {
            return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM enforcement WHERE kid=$kid AND to_kid NOT IN($kid)");
        }

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM enforcement WHERE to_kid IN($kid)");
    }

    public function getEnforcesByLimit(int $from, int $free, int $mode): mysqli_result|bool
    {
        $kid = $this->kid();
        if ($mode === 1) {
            return DB::getInstance()->query("SELECT * FROM enforcement WHERE kid=$kid AND to_kid NOT IN($kid) LIMIT $from, $free");
        }

        return DB::getInstance()->query("SELECT * FROM enforcement WHERE to_kid IN($kid) LIMIT $from, $free");
    }

    public function getTrappedCount(int $mode): int
    {
        $column = $mode === 1 ? 'kid' : 'to_kid';

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM trapped WHERE $column=" . $this->kid());
    }

    public function getTrappedByLimit(int $from, int $size, int $mode): mysqli_result|bool
    {
        $column = $mode === 1 ? 'kid' : 'to_kid';

        return DB::getInstance()->query("SELECT * FROM trapped WHERE $column=" . $this->kid() . " LIMIT $from, $size");
    }

    public function enforcementExists(int $id): bool
    {
        return 0 < (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM enforcement WHERE (kid=' . $this->kid() . ' OR to_kid IN(' . $this->villageAndOasisKids() . "))
             AND id=$id"
        );
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getEnforcement(int $id): array|false
    {
        $find = DB::getInstance()->query(
            'SELECT * FROM enforcement WHERE (kid=' . $this->kid() . ' OR to_kid IN(' . $this->villageAndOasisKids() . ")) AND id=$id LIMIT 1"
        );
        $row = $find instanceof mysqli_result ? $find->fetch_assoc() : null;

        return is_array($row) ? $row : false;
    }

    public function deleteEnforce(int $id): bool
    {
        $db = DB::getInstance();
        $db->query("DELETE FROM enforcement WHERE id=$id");

        return $db->affectedRows() > 0;
    }

    /**
     * Subtracts troops from a reinforcement.
     *
     * @param list<string> $modify assignments of the form "u7=u7-3"
     */
    public function modifyEnforce(array $modify, int $id): bool
    {
        foreach ($modify as $assignment) {
            if (!preg_match('/^u(\d+)=u\1-\d+$/', $assignment)) {
                throw new InvalidArgumentException('Invalid enforcement change.');
            }
        }
        if ($modify === []) {
            return false;
        }
        $db = DB::getInstance();
        $db->query('UPDATE enforcement SET ' . implode(',', $modify) . " WHERE id=$id");

        return $db->affectedRows() > 0;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getUnits(int $kid): array|false
    {
        $stmt = DB::getInstance()->query("SELECT * FROM units WHERE kid=$kid");
        $row = $stmt instanceof mysqli_result ? $stmt->fetch_assoc() : null;

        return is_array($row) ? $row : false;
    }

    public function checkMaxAttacks(int $kid): bool
    {
        $result = DB::getInstance()->query(
            "SELECT (SELECT COUNT(id) FROM movement WHERE mode=0 AND kid=$kid) as `outGoing`, (SELECT COUNT(id) FROM movement WHERE mode=1 AND to_kid=$kid) as `inComing`"
        );
        $counts = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        return is_array($counts) && ((int) $counts['inComing'] + (int) $counts['outGoing']) <= 10000;
    }

    private function kid(): int
    {
        return (int) Session::getInstance()->getKid();
    }

    private function perPage(): int
    {
        return (int) Session::getInstance()->getRallyPointRecordsPerPage();
    }

    /** Comma-separated kids of the active village and its oases. */
    private function villageAndOasisKids(): string
    {
        return implode(',', [...$this->oases, $this->kid()]);
    }

    /**
     * @param array<int, int|bool> $subFilters
     */
    private function outgoingCondition(array $subFilters): string
    {
        $other = [];
        if ($subFilters[4]) {
            $other[] = '(mode=0 AND attack_type!=' . MovementsModel::ATTACKTYPE_REINFORCEMENT . ')';
        }
        if ($subFilters[5]) {
            $other[] = '(mode=0 AND attack_type=' . MovementsModel::ATTACKTYPE_REINFORCEMENT . ')';
        }

        return $other === [] ? '' : 'AND (' . implode(' OR ', $other) . ')';
    }

    /**
     * @param array<int, int|bool> $subFilters
     */
    private function incomingCondition(array $subFilters): string
    {
        $other = [];
        if ($subFilters[1]) {
            $other[] = '(mode=0 AND (attack_type=' . MovementsModel::ATTACKTYPE_NORMAL . ' OR attack_type=' . MovementsModel::ATTACKTYPE_RAID . '))';
        }
        if ($subFilters[2]) {
            $other[] = '(mode=1)';
        }
        if ($subFilters[3]) {
            $other[] = '(mode=0 AND attack_type=' . MovementsModel::ATTACKTYPE_REINFORCEMENT . ')';
        }

        return $other === [] ? '' : 'AND (' . implode(' OR ', $other) . ')';
    }
}
