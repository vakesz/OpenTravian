<?php

namespace Game\Hero;

/** Resolves the Gpack artwork identity for a hero-adventure destination. */
final class AdventurePlace
{
    /**
     * @param array<mixed> $worldData
     */
    public static function fromWorldData(array $worldData, ?int $villageRace = null): string
    {
        $fieldType = (int)($worldData['fieldtype'] ?? 0);
        $occupied = (int)($worldData['occupied'] ?? 0);

        if ($fieldType > 0 && $occupied > 0) {
            return $villageRace === 5 ? 'village-natars' : 'village-normal';
        }

        $landscapes = [
            1 => 'forest',
            2 => 'clay',
            3 => 'hill',
            4 => 'lake',
            5 => 'vulcano',
        ];
        $landscape = (int)($worldData['landscape'] ?? 0);
        if ($landscape > 0) {
            return $landscapes[$landscape] ?? 'grassland';
        }

        if ($fieldType > 0) {
            return 'grassland';
        }

        $oases = [
            2 => 'forest',
            3 => 'forest',
            4 => 'forest',
            6 => 'clay',
            7 => 'clay',
            8 => 'clay',
            10 => 'hill',
            11 => 'hill',
            12 => 'hill',
            14 => 'lake',
            15 => 'lake',
        ];

        return $oases[(int)($worldData['oasistype'] ?? 0)] ?? 'grassland';
    }

    public static function listImageClass(string $place): string
    {
        return match ($place) {
            'village-normal' => 'normal_village',
            'village-natars' => 'natar_village',
            default => $place,
        };
    }

    public static function detailImageClass(string $place): string
    {
        return 'landscape-' . $place;
    }
}
