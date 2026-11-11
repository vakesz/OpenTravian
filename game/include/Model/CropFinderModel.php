<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use function allianceUrl;

class CropFinderModel
{
    private const PAGE_SIZE = 50;

    /**
     * @param array<string, mixed> $row a wdata row
     */
    public function getCroplandOccupiedBy(array $row): string
    {
        if (!$row['occupied']) {
            return '----';
        }
        $owner = $this->getOwner((int) $row['id']);
        if ($owner === 0) {
            return '----';
        }

        return (string) DB::getInstance()->fetchScalar("SELECT name FROM users WHERE id=$owner");
    }

    private function getOwner(int $kid): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid");
    }

    /**
     * @param array<string, mixed> $row a wdata row
     */
    public function getAllianceTag(array $row): string
    {
        if (!$row['occupied']) {
            return 'N/A';
        }
        $db = DB::getInstance();
        $aid = (int) $db->fetchScalar('SELECT aid FROM users WHERE id=' . $this->getOwner((int) $row['id']));
        if ($aid === 0) {
            return 'N/A';
        }
        $tag = $db->fetchScalar("SELECT tag FROM alidata WHERE id=$aid");
        if (!$tag) {
            return 'N/A';
        }

        return '<a href="' . allianceUrl($aid) . '">' . $tag . '</a>';
    }

    public function getTotalSize(string $typ, string|int $bonus_getreide, bool $only_free): int
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM wdata w  ' . $this->conditions($typ, $bonus_getreide, $only_free));
    }

    public function getCroppers(int $page, int $x, int $y, string $typ, string|int $bonus_getreide, bool $only_free): \mysqli_result
    {
        $from = ($page - 1) * self::PAGE_SIZE;

        return DB::getInstance()->query(
            "SELECT *, SQRT(POW(w.x-$x, 2)+POW(w.y-$y, 2)) as `distance` FROM wdata w  "
            . $this->conditions($typ, $bonus_getreide, $only_free)
            . ' ORDER BY distance ASC LIMIT ' . $from . ', ' . self::PAGE_SIZE
        );
    }

    private function conditions(string $typ, string|int $bonus_getreide, bool $only_free): string
    {
        $cond = [match ($typ) {
            'all' => '(fieldtype=1 OR fieldtype=6)',
            '9' => 'fieldtype=1',
            default => 'fieldtype=6',
        }];
        if ($bonus_getreide !== 'all') {
            $cond[] = 'crop_percent>=' . (int) $bonus_getreide;
        }
        if ($only_free) {
            $cond[] = 'occupied=0';
        }

        return 'WHERE ' . implode(' AND ', $cond);
    }
}
