<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Game\NoticeHelper;
use mysqli_result;

/**
 * Row and result-set readers interpolate only int-typed values and use the text protocol on purpose: callers rely on
 * string column values (strict comparisons, JSON output), which prepared statements would turn into native ints.
 */
class ReportsModel
{
    public function deleteReport(int $reportId, int $uid): bool
    {
        $statement = DB::getInstance()->run(
            'UPDATE ndata SET deleted=1, viewed=1 WHERE id=? AND uid=? AND deleted=0',
            [$reportId, $uid]
        );

        return $statement !== false && $statement->affected_rows === 1;
    }

    public function setReportAsViewed(int $reportId, int $uid): void
    {
        DB::getInstance()->run('UPDATE ndata SET viewed=1 WHERE id=? AND uid=? AND deleted=0', [$reportId, $uid]);
    }

    public function setReportAsUnViewed(int $reportId, int $uid): void
    {
        DB::getInstance()->run('UPDATE ndata SET viewed=0 WHERE id=? AND uid=? AND deleted=0', [$reportId, $uid]);
    }

    public function archiveReport(int $reportId, int $uid): void
    {
        DB::getInstance()->run('UPDATE ndata SET archive=1, viewed=1 WHERE id=? AND uid=? AND deleted=0', [$reportId, $uid]);
    }

    public function recoverReport(int $reportId, int $uid): void
    {
        DB::getInstance()->run(
            'UPDATE ndata SET archive=0 WHERE id=? AND uid=? AND deleted=0 AND archive=1',
            [$reportId, $uid]
        );
    }

    /**
     * Reads, unreads, archives or restores the reports of a player; other players' ids are ignored.
     *
     * @param list<int> $ids
     */
    public function setStatus(int $uid, array $ids, string $status): void
    {
        $assignment = [
            'read' => 'viewed=1',
            'unread' => 'viewed=0',
            'archived' => 'archive=1, viewed=1',
            'unarchived' => 'archive=0',
        ][$status] ?? null;
        if ($assignment === null || $ids === []) {
            return;
        }
        DB::getInstance()->run(
            "UPDATE ndata SET $assignment WHERE uid=? AND deleted=0 AND id IN (" . $this->placeholders($ids) . ')',
            [$uid, ...$ids]
        );
    }

    /**
     * Marks the unarchived reports of the given types (all types when the list is empty) as read.
     *
     * @param list<int> $types
     */
    public function markTypesAsRead(int $uid, array $types): void
    {
        DB::getInstance()->run(
            'UPDATE ndata SET viewed=1 WHERE uid=? AND archive=0 AND deleted=0 AND viewed=0' . $this->typesIn($types),
            [$uid, ...$types]
        );
    }

    /**
     * Deletes the unarchived reports of the given types (all types when the list is empty).
     *
     * @param list<int> $types
     */
    public function deleteTypes(int $uid, array $types): void
    {
        DB::getInstance()->run(
            'UPDATE ndata SET deleted=1, viewed=1 WHERE uid=? AND archive=0 AND deleted=0' . $this->typesIn($types),
            [$uid, ...$types]
        );
    }

    /** @param list<int> $ids */
    public function deleteReports(int $uid, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        DB::getInstance()->run(
            'UPDATE ndata SET deleted=1, viewed=1 WHERE uid=? AND deleted=0 AND id IN (' . $this->placeholders($ids) . ')',
            [$uid, ...$ids]
        );
    }

    public function cleanArchive(int $uid): void
    {
        DB::getInstance()->run('UPDATE ndata SET deleted=1, viewed=1 WHERE uid=? AND archive=1 AND deleted=0', [$uid]);
    }

    /** @param list<int> $values */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** @param list<int> $types */
    private function typesIn(array $types): string
    {
        return $types === [] ? '' : ' AND type IN (' . $this->placeholders($types) . ')';
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getReport(int $id): array|false
    {
        return $this->fetchRow("SELECT * FROM ndata WHERE id=$id") ?? false;
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getOwnedReport(int $reportId, int $uid): array|false
    {
        return $this->fetchRow("SELECT * FROM ndata WHERE id=$reportId AND uid=$uid AND deleted=0") ?? false;
    }

    public function updateOwnedReportPermissions(int $reportId, int $uid, int $permissionMask, string $description): bool
    {
        $report = $this->getOwnedReport($reportId, $uid);
        if ($report === false) {
            return false;
        }

        $data = NoticeHelper::parseReport((int) $report['type'], $report['data']);
        if (!is_array($data)) {
            return false;
        }

        $data['permissions'] = ['prem' => $permissionMask, 'desc' => $description];
        DB::getInstance()->run(
            'UPDATE ndata SET data=? WHERE id=? AND uid=? AND deleted=0',
            [NoticeHelper::convertReport((int) $report['type'], $data), $reportId, $uid]
        );

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJustOnReportForPositionData(int $kid, int $uid): ?array
    {
        return $this->fetchRow("SELECT * FROM ndata WHERE uid=$uid AND to_kid=$kid AND type <= 3 ORDER BY id DESC LIMIT 1");
    }

    public function getLast5Reports(int $aid, int $uid, int $kid): mysqli_result|bool
    {
        return DB::getInstance()->query(
            "SELECT * FROM ndata WHERE (uid=$uid OR (aid>0 AND aid=$aid)) AND to_kid=$kid AND ((type <= 7) OR (type>=15 AND type<=19)) ORDER BY id DESC LIMIT 5"
        );
    }

    /**
     * @param array<int, list<int>> $customReports report types per tab (1-4)
     * @param array{villageId?: int, playerId?: int} $entity optional report-list filter (reference `villageId`/`playerId`)
     */
    public function getAllReports(int $uid, int $page, bool $recursive, int $tab, array $customReports, array $entity = []): mysqli_result|bool
    {
        $perPage = (int) Session::getInstance()->getReportsRecordsPerPage();
        $offset = ($page - 1) * $perPage;
        $archive = $tab === 5 ? 1 : 0;

        return DB::getInstance()->query(
            "SELECT * FROM ndata WHERE uid=$uid AND archive=$archive AND deleted=0" . $this->typeCondition($tab, $customReports)
            . $this->entityCondition($entity)
            . ' ORDER BY id ' . ($recursive ? 'ASC' : 'DESC') . " LIMIT $offset, $perPage"
        );
    }

    /**
     * @param array<int, list<int>> $customReports report types per tab (1-4)
     * @param array{villageId?: int, playerId?: int} $entity
     */
    public function getAllReportsCount(int $uid, int $tab, array $customReports, array $entity = []): int
    {
        return (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM ndata WHERE uid=? AND archive=? AND deleted=0' . $this->typeCondition($tab, $customReports)
            . $this->entityCondition($entity),
            [$uid, $tab === 5 ? 1 : 0]
        );
    }

    /**
     * A report matches a village when it is the origin or the target, and a player when either of those villages
     * belongs to the player. Only integers are interpolated.
     *
     * @param array{villageId?: int, playerId?: int} $entity
     */
    private function entityCondition(array $entity): string
    {
        if (!empty($entity['villageId'])) {
            $kid = (int) $entity['villageId'];

            return " AND (kid=$kid OR to_kid=$kid)";
        }
        if (!empty($entity['playerId'])) {
            $owned = '(SELECT kid FROM vdata WHERE owner=' . (int) $entity['playerId'] . ')';

            return " AND (kid IN $owned OR to_kid IN $owned)";
        }

        return '';
    }

    public function getSurroundingCount(int $kid): int
    {
        [$x, $y] = $this->coordinates($kid);

        return (int) DB::getInstance()->fetchScalar(
            'SELECT COUNT(id) FROM surrounding WHERE (SQRT(POW(x-?, 2)+POW(y-?, 2)) BETWEEN 1 AND 20)',
            [$x, $y]
        );
    }

    public function getSurrounding(int $kid, int $page): mysqli_result|bool
    {
        [$x, $y] = $this->coordinates($kid);
        $pageSize = (int) Session::getInstance()->getReportsRecordsPerPage();
        $offset = ($page - 1) * $pageSize;
        $distance = "SQRT(POW(x-$x, 2)+POW(y-$y, 2))";

        return DB::getInstance()->query(
            "SELECT *, $distance AS `distance` FROM surrounding WHERE ($distance BETWEEN 1 AND 20) ORDER BY id DESC LIMIT $offset, $pageSize"
        );
    }

    /**
     * @param array<int, list<int>> $customReports
     */
    private function typeCondition(int $tab, array $customReports): string
    {
        if ($tab < 1 || $tab > 4) {
            return '';
        }

        $types = array_map('intval', $customReports[$tab] ?? []);

        return ' AND type IN(' . implode(',', $types === [] ? [0] : $types) . ')';
    }

    /**
     * @return array{int, int}
     */
    private function coordinates(int $kid): array
    {
        $xy = Formulas::kid2xy($kid);

        return [(int) $xy['x'], (int) $xy['y']];
    }

    /**
     * Rows are read over the text protocol so every column stays a string, as the report renderer expects
     * (it compares e.g. the bounty column with '-1'). Only integers are ever interpolated into $query.
     *
     * @return array<string, mixed>|null
     */
    private function fetchRow(string $query): ?array
    {
        $result = DB::getInstance()->query($query);
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }
}
