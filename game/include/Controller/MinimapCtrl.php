<?php

namespace Controller;

use Core\Caching\Caching;
use Core\Database\DB;
use Game\Formulas;
use Game\Map\ImageMapCache;

/**
 * The world minimap: one pixel per map tile, like the reference's 2n+1 square JPEG. Valleys are
 * green, every tile that cannot be settled (oases, forest, mountain, clay, lake) is yellow, the
 * starting area is dark, and occupied villages take their tribe colour.
 */
class MinimapCtrl extends AnyCtrl
{
    private const VALLEY = [0x6D, 0xAA, 0x00];
    private const UNSETTLEABLE = [0xD0, 0xFC, 0x58];
    private const STARTING_AREA = [0x66, 0x55, 0x00];
    private const TRIBES = [
        1 => [0xEA, 0x32, 0x04],
        2 => [0xBC, 0x4B, 0x0C],
        3 => [0xE6, 0x62, 0x04],
        5 => [0x63, 0x5C, 0x42],
        6 => [0xF4, 0x6C, 0x02],
        7 => [0x6A, 0x37, 0x16],
    ];

    public function __construct()
    {
        // The minimap is the same for every player and never touches the session.
        session_write_close();
        $this->cache();
        ImageMapCache::checkCache('minimap.jpg', 'jpeg', null);
    }

    public function cache()
    {
        $memcache = Caching::getInstance();
        if ($memcache->exists("minimap.jpg") && !isset($_GET['noCache'])) return false;
        $size = 2 * MAP_SIZE + 1;
        $img = imagecreatetruecolor($size, $size);
        $db = DB::getInstance();
        $max = (int)$db->fetchScalar("SELECT MAX(r) FROM available_villages a WHERE occupied=1 AND (SELECT owner FROM vdata WHERE kid=a.kid)>1");
        $map = $db->query(
            "SELECT id, x, y, fieldtype, IF(occupied=1, (SELECT race FROM units WHERE kid=wdata.id LIMIT 1), 0) AS race FROM wdata"
        );
        $colors = [];
        $color = static function (array $rgb, int $alpha = 0) use ($img, &$colors): int {
            return $colors[$rgb[0] << 16 | $rgb[1] << 8 | $rgb[2]][$alpha]
                ??= imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
        };
        imagefill($img, 0, 0, $color(self::VALLEY));
        while ($row = $map->fetch_assoc()) {
            $x = MAP_SIZE + (int)$row['x'];
            $y = MAP_SIZE - (int)$row['y'];
            if (Formulas::isGrayArea($row)) {
                imagesetpixel($img, $x, $y, $color(self::STARTING_AREA, mt_rand(0, 30)));
            } elseif (!$row['fieldtype']) {
                imagesetpixel($img, $x, $y, $color(self::UNSETTLEABLE));
            }
            if ($row['race'] && isset(self::TRIBES[(int)$row['race']])) {
                $alpha = hypot($row['x'], $row['y']) >= $max - 3 ? 0 : mt_rand(50, 80);
                imagesetpixel($img, $x, $y, $color(self::TRIBES[(int)$row['race']], $alpha));
            }
        }
        ob_start();
        imagejpeg($img, null, 75);
        $content = ob_get_clean();
        $memcache->set("minimap.jpg", ['content' => $content, 'time' => time(), 'length' => strlen($content)], 3600);
        return true;
    }
}
