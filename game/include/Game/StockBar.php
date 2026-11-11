<?php

declare(strict_types=1);

namespace Game;

use Core\Village;

use function secondsToString;
use function T;

/**
 * What the resource bar shows for a village: the page renders it and village/resources
 * returns it, in the shape the reference client reads (maxStorage, production, storage, tooltips).
 */
final class StockBar
{
    /**
     * @return array{maxStorage: array<string, int>, production: array<string, int|float>, storage: array<string, int|float>, tooltips: array<string, string>}
     */
    public static function snapshot(Village $village): array
    {
        $production = $village->getProduction(-1);
        $storage = array_map('round', $village->getCurrentResources(-1, false));
        $maxStore = $village->get('maxstore');
        $maxCrop = $village->get('maxcrop');
        $tooltips = self::tooltips($village);

        return [
            'maxStorage' => ['l1' => $maxStore, 'l2' => $maxStore, 'l3' => $maxStore, 'l4' => $maxCrop],
            'production' => [
                'l1' => $production[0], 'l2' => $production[1], 'l3' => $production[2],
                'l4' => $production[3], 'l5' => $production[4],
            ],
            'storage' => ['l1' => $storage[0], 'l2' => $storage[1], 'l3' => $storage[2], 'l4' => $storage[3]],
            'tooltips' => array_combine(['l1', 'l2', 'l3', 'l4', 'l5'], $tooltips),
        ];
    }

    /**
     * The five tooltips: lumber, clay, iron, crop and free crop.
     *
     * @return list<string>
     */
    public static function tooltips(Village $village): array
    {
        $cropProduction = $village->getProduction(3);

        return [
            sprintf(T('inGame', 'stockBar.production.r1'), (int)$village->getProduction(0), self::fullTime($village, 0)),
            sprintf(T('inGame', 'stockBar.production.r2'), (int)$village->getProduction(1), self::fullTime($village, 1)),
            sprintf(T('inGame', 'stockBar.production.r3'), (int)$village->getProduction(2), self::fullTime($village, 2)),
            sprintf(
                T('inGame', 'stockBar.production.r4' . ($cropProduction < 0 ? 'Empty' : '')),
                (int)$village->getProduction(4),
                $cropProduction < 0 ? self::cropEmptyTime($village) : self::fullTime($village, 3)
            ),
            sprintf(T('inGame', 'stockBar.production.r5'), "\u{202D}" . (int)$cropProduction . "\u{202C}"),
        ];
    }

    private static function fullTime(Village $village, int $n): string
    {
        $maxStore = $village->get($n === 3 ? 'maxcrop' : 'maxstore');
        $production = $village->getProduction($n);
        $current = $village->getCurrentResources($n);
        if ($production == 0 || $current >= $maxStore) {
            return $current >= $maxStore ? T('inGame', 'stockBar.fullNow') : secondsToString(0);
        }

        return secondsToString(round((($maxStore - $current) / $production) * 3600));
    }

    private static function cropEmptyTime(Village $village): string
    {
        $cropProduction = $village->getProduction(3);

        return secondsToString(round(($village->village['crop'] / abs($cropProduction == 0 ? 0.01 : $cropProduction)) * 3600));
    }
}
