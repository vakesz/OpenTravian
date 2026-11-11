<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Game\Formulas;
use InvalidArgumentException;
use mysqli_result;

/**
 * Map reads. Ids are typed ints and are the only values interpolated into SQL, and everything is read over the
 * text protocol on purpose: the map responses are JSON and templates compare columns as strings, which prepared
 * statements would turn into native ints.
 */
class MapPageModel
{
    public function getAdventures(?int $uid): mysqli_result|bool
    {
        $uid = (int) $uid;

        return DB::getInstance()->query("SELECT * FROM adventure WHERE uid=$uid AND end=0");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMapTileByCoordinates(int $x, int $y): ?array
    {
        $kid = (int) Formulas::xy2kid($x, $y);

        return $this->fetchRow("SELECT * FROM wdata WHERE id=$kid LIMIT 1");
    }

    /**
     * @return list<string>
     */
    public function getVillageOasesIds(?int $kid): array
    {
        $kid = (int) $kid;
        $result = DB::getInstance()->query("SELECT kid FROM odata WHERE did=$kid");
        $return = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $return[] = (string) $row['kid'];
        }

        return $return;
    }

    public function getPlayerAccesses(?int $uid): string|false
    {
        $uid = (int) $uid;

        return $this->scalar("SELECT access FROM users WHERE id=$uid");
    }

    public function hasBeginnerProtection(?int $kid): int
    {
        $kid = (int) $kid;
        $protectionUntil = (int) DB::getInstance()->fetchScalar(
            "SELECT users.protection FROM users, vdata WHERE users.id=vdata.owner AND vdata.kid=$kid LIMIT 1"
        );

        return $protectionUntil < time() ? 0 : $protectionUntil;
    }

    public function isInVacation(?int $kid): int|false
    {
        $kid = (int) $kid;
        $protect = (int) DB::getInstance()->fetchScalar(
            "SELECT users.vacationActiveTil FROM users, vdata WHERE users.id=vdata.owner AND vdata.kid=$kid LIMIT 1"
        );

        return $protect >= time() ? $protect : false;
    }

    public function checkForAdventure(?int $uid, ?int $kid): int
    {
        $uid = (int) $uid;
        $kid = (int) $kid;

        return (int) DB::getInstance()->fetchScalar(
            "SELECT COUNT(id) FROM adventure WHERE end=0 AND uid=$uid AND kid=$kid"
        );
    }

    /**
     * @return array<string, mixed> empty when the oasis does not exist
     */
    public function getOasisInfo(?int $kid, string $columns = '*'): array
    {
        $kid = (int) $kid;

        return $this->fetchRow('SELECT ' . $this->columns($columns) . " FROM odata WHERE kid=$kid") ?? [];
    }

    /**
     * @return array<string, mixed> empty when the village does not exist
     */
    public function getVillageInfo(?int $kid, string $columns = '*'): array
    {
        $kid = (int) $kid;

        return $this->fetchRow('SELECT ' . $this->columns($columns) . " FROM vdata WHERE kid=$kid") ?? [];
    }

    public function getDoneAdventuresCount(?int $uid): int
    {
        $uid = (int) $uid;

        return (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM adventure WHERE uid=$uid AND end=1");
    }

    public function getWholeMapMarks(?int $uid, ?int $aid, int $mode = -1, bool $noFlag = false): mysqli_result|bool
    {
        $uid = (int) $uid;
        $aid = (int) $aid;
        $filter = $noFlag ? 'AND type!=2' : '';
        $db = DB::getInstance();
        if ($mode === 0) {
            return $db->query("SELECT * FROM mapflag WHERE uid=$uid $filter");
        }
        if ($mode === 1) {
            return $db->query("SELECT * FROM mapflag WHERE uid=0 AND aid=$aid $filter");
        }

        return $db->query("SELECT * FROM mapflag WHERE (uid=$uid OR (aid=$aid AND $aid>0)) $filter");
    }

    public function getWholeMapMarksForKid(?int $kid, ?int $uid, ?int $aid, int $mode = -1, bool $noFlag = false): mysqli_result|bool
    {
        $kid = (int) $kid;
        $uid = (int) $uid;
        $aid = (int) $aid;
        $filter = $noFlag ? 'AND type!=2' : '';
        $db = DB::getInstance();
        if ($mode === 0) {
            return $db->query("SELECT * FROM mapflag WHERE kid=$kid AND uid=$uid $filter");
        }
        if ($mode === 1) {
            return $db->query("SELECT * FROM mapflag WHERE kid=$kid AND uid=0 AND aid=$aid AND aid>0 $filter");
        }

        return $db->query("SELECT * FROM mapflag WHERE kid=$kid AND (uid=$uid OR (uid=0 AND aid=$aid AND aid>0)) $filter");
    }

    public function getPlayerName(?int $uid): string|false
    {
        $uid = (int) $uid;

        return $this->scalar("SELECT name FROM users WHERE id=$uid");
    }

    public function getAllianceTag(?int $aid): string|false
    {
        $aid = (int) $aid;

        return $this->scalar("SELECT tag FROM alidata WHERE id=$aid");
    }

    public function getVillageName(?int $kid): string|false
    {
        $kid = (int) $kid;

        return $this->scalar("SELECT name FROM vdata WHERE kid=$kid");
    }

    public function getVillagePop(?int $kid): string|false
    {
        $kid = (int) $kid;

        return $this->scalar("SELECT pop FROM vdata WHERE kid=$kid");
    }

    public function getPlayerTribe(?int $uid): string|false
    {
        $uid = (int) $uid;

        return $this->scalar("SELECT race FROM users WHERE id=$uid");
    }

    public function getPlayerAllianceId(?int $uid): string|false
    {
        $uid = (int) $uid;

        return $this->scalar("SELECT aid FROM users WHERE id=$uid");
    }

    public function getVillageOwner(?int $kid): string|false
    {
        $kid = (int) $kid;

        return $this->scalar("SELECT owner FROM vdata WHERE kid=$kid");
    }

    public function getOasisOwner(?int $kid): string|false
    {
        $kid = (int) $kid;

        return $this->scalar("SELECT owner FROM odata WHERE kid=$kid");
    }

    /** First column of the first row as a string, false when there is none. */
    private function scalar(string $query): string|false
    {
        $value = DB::getInstance()->fetchScalar($query);

        return $value === false || $value === null ? false : (string) $value;
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

    /** Callers pass literal column lists; anything but plain identifiers is refused. */
    private function columns(string $columns): string
    {
        if (!preg_match('/^\*$|^[A-Za-z_][A-Za-z0-9_]*(\s*,\s*[A-Za-z_][A-Za-z0-9_]*)*$/', $columns)) {
            throw new InvalidArgumentException('Invalid column list.');
        }

        return $columns;
    }
}
