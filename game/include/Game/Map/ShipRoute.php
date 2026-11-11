<?php

declare(strict_types=1);

namespace Game\Map;

use Core\Database\DB;
use Game\Formulas;

/**
 * How much of the straight line between two villages crosses deep water.
 *
 * Ships travel the shortest straight path (Knowledge Base article 25: "Ships
 * always travel in a straight, shortest path"); on a deep-water world the map is
 * flat, so that path never crosses the world edge (the vendor's ship movement
 * guide). The line is sampled twice per tile; the fraction of samples on a
 * deep-water tile is the fraction of the distance sailed at ship speed.
 */
final class ShipRoute
{
    /**
     * @param int|array{x: int, y: int} $from kid or coordinates
     * @param int|array{x: int, y: int} $to
     */
    public static function deepFraction($from, $to): float
    {
        $a = is_array($from) ? $from : Formulas::kid2xy($from);
        $b = is_array($to) ? $to : Formulas::kid2xy($to);
        $dx = $b['x'] - $a['x'];
        $dy = $b['y'] - $a['y'];
        $samples = max(1, (int)ceil(hypot($dx, $dy) * 2));
        $kids = [];
        for ($i = 0; $i < $samples; ++$i) {
            $t = ($i + 0.5) / $samples;
            $kids[] = Formulas::xy2kid((int)round($a['x'] + $dx * $t), (int)round($a['y'] + $dy * $t));
        }
        $deep = [];
        $result = DB::getInstance()->query('SELECT id FROM wdata WHERE deepwater=1 AND id IN (' . implode(',', array_unique($kids)) . ')');
        while ($row = $result->fetch_assoc()) {
            $deep[(int)$row['id']] = true;
        }
        $wet = 0;
        foreach ($kids as $kid) {
            $wet += isset($deep[$kid]) ? 1 : 0;
        }

        return $wet / $samples;
    }
}
