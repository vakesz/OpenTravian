<?php

declare(strict_types=1);

namespace Game\Map;

use Closure;
use Game\Formulas;

/**
 * Generates deep water on a wrapping square map and finds the shore tiles beside it: a few solid seas, some rivers
 * and large lakes (the small decorative lakes of the map art are not deep water).
 *
 * Used by the world installer (over its in-memory map, before landscape and oases are placed so they flow around
 * the water) and by tools/regenerate-waters.php (over an installed world), so both produce the same kind of map. The
 * caller decides which tiles may become water through $eligible; the generator never asks for anything else, so it
 * can never touch a tile the caller has not released.
 *
 * Size: the water covers 6 to 8 percent of the map. On the 201x201 map (MAP_SIZE 100) that is 3 to 5 seas of 300 to
 * 900 tiles, 2 to 3 rivers 2 to 4 tiles wide and a few large lakes; smaller or larger maps scale the areas with the
 * map. No Travian article gives a size (they only say seas, major rivers and some large lakes are deep water and
 * small random lakes are not), so the shares are a local choice, large enough to hold a Harbor fleet.
 *
 * Shore rule: a settleable land tile is an abandoned shore when any of its eight neighbours is deep water (Knowledge
 * Base articles 24 and 198: "directly next to a deep water tile"). Water bodies smaller than minBody() tiles are
 * dropped.
 */
final class WaterGenerator
{
    /** Water stays this far from the map centre (the grey start area plus the Natar village). */
    public const PROTECTED_RADIUS = 30;

    /** Floor of minBody(): the smallest body any map keeps. */
    public const MIN_BODY = 12;

    /** Share of the map under deep water, lower and upper bound. */
    public const COVERAGE_MIN = 0.06;
    public const COVERAGE_MAX = 0.08;

    /** Sea area at the reference size (201x201 tiles), lower and upper bound. */
    public const SEA_MIN = 300;
    public const SEA_MAX = 900;

    /** Cells of the reference map (MAP_SIZE 100) the area figures above belong to. */
    private const REFERENCE_CELLS = 40401;

    private int $span;

    /**
     * Fixed special village locations (Wonder of the World sites and the start
     * area). Water keeps two tiles clear of each, see tileIsReserved().
     *
     * @return int[] kids
     */
    public static function reservedLocations(int $mapSize): array
    {
        $max = (int)ceil($mapSize / ($mapSize > 100 ? 4 : 2));

        return [
            Formulas::xy2kid($max, -$max),
            Formulas::xy2kid(-5, -11),
            Formulas::xy2kid($max, 0),
            Formulas::xy2kid(0, -$max),
            Formulas::xy2kid(-$max, $max),
            Formulas::xy2kid(9, -8),
            Formulas::xy2kid(-12, 2),
            Formulas::xy2kid(11, 6),
            Formulas::xy2kid(0, $max),
            Formulas::xy2kid(-$max, -$max),
            Formulas::xy2kid($max, $max),
            Formulas::xy2kid(-$max, 0),
            Formulas::xy2kid(-2, 12),
            Formulas::xy2kid(1, 0),
        ];
    }

    /** @return array<int, true> every kid within two tiles of a reserved location */
    public static function reservedZone(int $mapSize): array
    {
        $zone = [];
        foreach (self::reservedLocations($mapSize) as $kid) {
            $xy = Formulas::kid2xy($kid);
            for ($dy = -2; $dy <= 2; ++$dy) {
                for ($dx = -2; $dx <= 2; ++$dx) {
                    $zone[Formulas::xy2kid($xy['x'] + $dx, $xy['y'] + $dy)] = true;
                }
            }
        }

        return $zone;
    }

    /**
     * @param int     $mapSize  MAP_SIZE: coordinates run from -mapSize to mapSize and wrap
     * @param Closure $eligible fn(int $x, int $y): bool, true when the tile may become water
     */
    public function __construct(private int $mapSize, private Closure $eligible)
    {
        $this->span = 2 * $mapSize + 1;
    }

    /** The smallest body this map keeps, scaled with the map. */
    public function minBody(): int
    {
        return max(self::MIN_BODY, (int)round(60 * $this->areaScale()));
    }

    /** 1 on the reference map, proportionally less or more on smaller or larger ones. */
    private function areaScale(): float
    {
        return max(0.1, $this->span * $this->span / self::REFERENCE_CELLS);
    }

    /**
     * Tries up to eight times to produce water that holds its promise (coverage inside the bounds, at least three seas
     * of near SEA_MIN size); the best attempt stands when the caller's rules leave too little room.
     *
     * $required are tiles that must be water (the water an existing harbor village sits beside): each becomes the
     * rim of a sea of its own.
     *
     * @param list<array{0: int, 1: int}> $required
     * @return array<string, array{0: int, 1: int}> water tiles keyed "x|y"
     */
    public function generate(?int $seed = null, array $required = []): array
    {
        if ($seed !== null) {
            mt_srand($seed);
        }
        $best = [];
        $bestScore = -1;
        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $water = $this->attempt($required);
            [$score, $keeps] = $this->judge($water);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$water, $score];
            }
            if ($keeps) {
                break;
            }
        }

        return $best;
    }

    /**
     * @param list<array{0: int, 1: int}> $required
     * @return array<string, array{0: int, 1: int}>
     */
    private function attempt(array $required): array
    {
        $scale = $this->areaScale();
        // Aim a little inside the bounds: overlaps and dropped fragments cost a few tiles.
        $target = mt_rand((int)((self::COVERAGE_MIN + 0.004) * 10000), (int)((self::COVERAGE_MAX - 0.004) * 10000)) / 10000 * $this->span * $this->span;
        $water = [];
        foreach ($required as $tile) {
            $this->addBlob($water, mt_rand((int)(self::SEA_MIN * $scale), (int)(self::SEA_MAX * $scale * 0.6)), 1.0, true, $tile);
        }
        $this->addSeas($water, $target * 0.55, $scale);
        $this->addRivers($water, $target * 0.2, $scale);
        $this->addLakes($water, $target, $scale);

        return $this->dropSmallBodies($water);
    }

    /**
     * @param array<string, array{0: int, 1: int}> $water
     * @return array{0: int, 1: bool} a score (the larger the closer to the promise) and whether the promise is kept
     */
    private function judge(array $water): array
    {
        $coverage = count($water) / ($this->span * $this->span);
        $seas = count(array_filter($this->bodySizes($water), fn (int $size): bool => $size >= self::SEA_MIN * $this->areaScale() * 0.85));
        $inside = $coverage >= self::COVERAGE_MIN && $coverage <= self::COVERAGE_MAX;

        return [min($seas, 3) * 2 + ($inside ? 3 : 0), $inside && $seas >= 3];
    }

    /**
     * Tile counts of the orthogonally connected bodies.
     *
     * @param array<string, array{0: int, 1: int}> $water
     * @return list<int>
     */
    public function bodySizes(array $water): array
    {
        $seen = [];
        $sizes = [];
        foreach ($water as $key => $start) {
            if (isset($seen[$key])) {
                continue;
            }
            $count = 0;
            $queue = [$start];
            $seen[$key] = true;
            while ($queue) {
                [$x, $y] = array_pop($queue);
                ++$count;
                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                    $nkey = $this->wrap($x + $dx) . '|' . $this->wrap($y + $dy);
                    if (isset($water[$nkey]) && !isset($seen[$nkey])) {
                        $seen[$nkey] = true;
                        $queue[] = [$this->wrap($x + $dx), $this->wrap($y + $dy)];
                    }
                }
            }
            $sizes[] = $count;
        }
        rsort($sizes);

        return $sizes;
    }

    /**
     * Land tiles touching water, keyed "x|y".
     *
     * @param array<string, array{0: int, 1: int}> $water
     * @return array<string, array{0: int, 1: int}>
     */
    public function shoreOf(array $water): array
    {
        $shore = [];
        foreach ($water as [$x, $y]) {
            foreach ($this->neighbours($x, $y) as [$nx, $ny]) {
                $key = $nx . '|' . $ny;
                if (!isset($water[$key])) {
                    $shore[$key] = [$nx, $ny];
                }
            }
        }

        return $shore;
    }

    /**
     * The eight tiles around a coordinate, wrapped at the map edge.
     *
     * @return list<list<int>>
     */
    public function neighbours(int $x, int $y): array
    {
        $result = [];
        foreach ([-1, 0, 1] as $dy) {
            foreach ([-1, 0, 1] as $dx) {
                if ($dx || $dy) {
                    $result[] = [$this->wrap($x + $dx), $this->wrap($y + $dy)];
                }
            }
        }

        return $result;
    }

    public function wrap(int $value): int
    {
        return (($value + $this->mapSize) % $this->span + $this->span) % $this->span - $this->mapSize;
    }

    private function allowed(int $x, int $y): bool
    {
        return hypot($x, $y) > self::PROTECTED_RADIUS && ($this->eligible)($x, $y);
    }

    /**
     * Three to five solid seas whose areas share the budget, each between SEA_MIN and SEA_MAX (scaled).
     *
     * @param array<mixed> $water
     */
    private function addSeas(array &$water, float $budget, float $scale): void
    {
        $min = self::SEA_MIN * $scale;
        $max = self::SEA_MAX * $scale;
        $count = max(3, min(5, (int)round($budget / (($min + $max) / 2))));
        for ($i = 0; $i < $count; ++$i) {
            $area = max($min, min($max, $budget / $count * mt_rand(70, 130) / 100));
            $this->addBlob($water, $area, 1.0, true);
        }
    }

    /**
     * Two to three rivers, two to four tiles wide, that share the budget.
     *
     * @param array<mixed> $water
     */
    private function addRivers(array &$water, float $budget, float $scale): void
    {
        $count = $budget >= 400 * $scale ? mt_rand(2, 3) : 2;
        for ($i = 0; $i < $count; ++$i) {
            $width = mt_rand(2, 4);
            $steps = max(20, (int)round($budget / $count / $width));
            $x = (float)mt_rand(-$this->mapSize, $this->mapSize);
            $y = (float)mt_rand(-$this->mapSize, $this->mapSize);
            $heading = mt_rand(0, 628) / 100;
            $half = $width / 2;
            for ($step = 0; $step < $steps; ++$step) {
                $heading += (mt_rand(-100, 100) / 100) * 0.2;
                $x += cos($heading);
                $y += sin($heading);
                for ($dy = -(int)ceil($half); $dy <= (int)ceil($half); ++$dy) {
                    for ($dx = -(int)ceil($half); $dx <= (int)ceil($half); ++$dx) {
                        if (hypot($dx, $dy) <= $half + 0.2) {
                            $this->add($water, (int)round($x) + $dx, (int)round($y) + $dy);
                        }
                    }
                }
            }
        }
    }

    /**
     * Large lakes of 80 to 150 tiles (scaled), at least two and as many more as it takes to reach the target: deep
     * water, unlike the decorative lakes of the map art.
     *
     * @param array<mixed> $water
     */
    private function addLakes(array &$water, float $target, float $scale): void
    {
        for ($lakes = 0; ($lakes < 2 || count($water) < $target) && $lakes < 60; ++$lakes) {
            $this->addBlob($water, mt_rand(80, 150) * $scale, 0.45, false);
        }
    }

    /**
     * A solid blob of about $area tiles with a wobbling shore, retried at other places while the caller's rules and
     * the protected areas cut it short. $wobble scales the shore's irregularity. With $rim the blob must contain that
     * tile and is centred near it.
     *
     * @param array<mixed> $water
     * @param array{0: int, 1: int}|null $rim
     */
    private function addBlob(array &$water, float $area, float $wobble, bool $mustFill, ?array $rim = null): void
    {
        $radius = sqrt($area / (M_PI * (1 + 0.5 * $wobble ** 2 * (0.32 ** 2 + 0.2 ** 2 + 0.1 ** 2))));
        $reach = (int)ceil($radius * 1.7);
        $best = [];
        for ($try = 0; $try < 12; ++$try) {
            if ($rim !== null) {
                // The sea's centre lies a little inland of the rim tile, so the rim tile is well inside it.
                $heading = mt_rand(0, 628) / 100;
                $centreX = $this->wrap((int)round($rim[0] + cos($heading) * $radius * 0.5));
                $centreY = $this->wrap((int)round($rim[1] + sin($heading) * $radius * 0.5));
            } else {
                $centreX = mt_rand(-$this->mapSize, $this->mapSize);
                $centreY = mt_rand(-$this->mapSize, $this->mapSize);
            }
            $phase = [mt_rand(0, 628) / 100, mt_rand(0, 628) / 100, mt_rand(0, 628) / 100];
            $blob = [];
            for ($dy = -$reach; $dy <= $reach; ++$dy) {
                for ($dx = -$reach; $dx <= $reach; ++$dx) {
                    $angle = atan2($dy, $dx);
                    $edge = $radius * (1 + $wobble * (0.32 * sin(2 * $angle + $phase[0]) + 0.2 * sin(3 * $angle + $phase[1]) + 0.1 * sin(5 * $angle + $phase[2])));
                    if (hypot($dx, $dy) < $edge) {
                        $x = $this->wrap($centreX + $dx);
                        $y = $this->wrap($centreY + $dy);
                        if ($this->allowed($x, $y)) {
                            $blob[$x . '|' . $y] = [$x, $y];
                        }
                    }
                }
            }
            if ($rim !== null) {
                if (!isset($blob[$rim[0] . '|' . $rim[1]])) {
                    continue;
                }
            }
            if (count($blob) > count($best)) {
                $best = $blob;
            }
            if (count($blob) >= ($mustFill ? 0.85 : 0.6) * $area) {
                break;
            }
        }
        foreach ($best as $key => $tile) {
            $water[$key] = $tile;
        }
    }

    /**
     * @param array<mixed> $water
     */
    private function add(array &$water, int $x, int $y): void
    {
        $x = $this->wrap($x);
        $y = $this->wrap($y);
        if ($this->allowed($x, $y)) {
            $water[$x . '|' . $y] = [$x, $y];
        }
    }

    /**
     * @param array<mixed> $water
     * @return array<string, list<mixed>>
     */
    private function dropSmallBodies(array $water): array
    {
        $minBody = $this->minBody();
        $seen = [];
        $kept = [];
        foreach ($water as $key => $start) {
            if (isset($seen[$key])) {
                continue;
            }
            $body = [];
            $queue = [$start];
            $seen[$key] = true;
            while ($queue) {
                [$x, $y] = array_pop($queue);
                $body[$x . '|' . $y] = [$x, $y];
                foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                    $nx = $this->wrap($x + $dx);
                    $ny = $this->wrap($y + $dy);
                    $nkey = $nx . '|' . $ny;
                    if (isset($water[$nkey]) && !isset($seen[$nkey])) {
                        $seen[$nkey] = true;
                        $queue[] = [$nx, $ny];
                    }
                }
            }
            if (count($body) >= $minBody) {
                $kept += $body;
            }
        }

        return $kept;
    }
}
