<?php

declare(strict_types=1);

namespace Game;

use Core\Config;

/**
 * Single source for tribe identity and the world's playable-tribe setting.
 *
 * Ids are 1 Romans, 2 Teutons, 3 Gauls, 4 Nature, 5 Natars, 6 Egyptians,
 * 7 Huns, 8 Spartans, 9 Vikings. Nature and Natars are never selectable.
 * Which of the others a player may pick is a world setting (config.playableTribes),
 * chosen at install and editable in the admin panel. Romans, Teutons, Gauls,
 * Egyptians, Huns and Spartans start ticked; Vikings start off.
 */
final class Tribes
{
    /** Tribes a world may offer to players. */
    public const SELECTABLE = [1, 2, 3, 6, 7, 8, 9];

    /** Default playable tribes; Vikings start off. */
    public const DEFAULT_PLAYABLE = [1, 2, 3, 6, 7, 8];

    /** Display names for the tribes a world may offer. */
    public const NAMES = [1 => 'Romans', 2 => 'Teutons', 3 => 'Gauls', 6 => 'Egyptians', 7 => 'Huns', 8 => 'Spartans', 9 => 'Vikings'];

    /** Order of the activation tribe picker (Gauls are the recommended first card). */
    public const PICKER_ORDER = [3, 1, 2, 6, 7, 8, 9];

    /** Slug used by Gpack classes and assets. */
    private const SLUGS = [
        1 => 'roman',
        2 => 'teuton',
        3 => 'gaul',
        4 => 'nature',
        5 => 'natar',
        6 => 'egyptian',
        7 => 'hun',
        8 => 'spartan',
        9 => 'viking',
    ];

    /**
     * Zero-based position of the first stable-trained unit among a tribe's six
     * barracks/stable units (-1: Nature has no stable). Hun Spotters, Gaul
     * Pathfinders, Roman Equites Legati and Egyptian Sopdu Explorers ride; Teuton,
     * Spartan and Viking scouts walk.
     */
    public const CAVALRY_START = [1 => 3, 2 => 4, 3 => 2, 4 => -1, 5 => 4, 6 => 3, 7 => 2, 8 => 4, 9 => 4];

    /** summary table column prefix per selectable tribe. */
    private const SUMMARY_COLUMNS = [
        1 => 'roman',
        2 => 'teuton',
        3 => 'gaul',
        6 => 'egyptians',
        7 => 'huns',
        8 => 'spartans',
        9 => 'vikings',
    ];

    /** @return int[] tribe ids players may currently pick, in picker order */
    public static function playable(): array
    {
        $raw = Config::getInstance()->dynamic->playableTribes ?? null;

        return self::order(self::parse($raw) ?: self::DEFAULT_PLAYABLE);
    }

    public static function isPlayable(int $tribe): bool
    {
        return in_array($tribe, self::playable(), true);
    }

    /** True when Vikings are among the playable tribes. */
    public static function vikingsEnabled(): bool
    {
        return self::isPlayable(9);
    }

    /**
     * Parse a stored or submitted list into valid, unique selectable ids.
     *
     * @param array<int|string>|string|null $value comma separated string or list
     * @return int[]
     */
    public static function parse($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }
        $tribes = [];
        foreach ($value as $item) {
            $id = filter_var($item, FILTER_VALIDATE_INT);
            if ($id !== false && in_array($id, self::SELECTABLE, true)) {
                $tribes[$id] = $id;
            }
        }

        return array_values($tribes);
    }

    /**
     * Canonical storage form.
     *
     * @param array<int> $tribes
     */
    public static function serialize(array $tribes): string
    {
        $tribes = self::parse($tribes);
        sort($tribes);

        return implode(',', $tribes);
    }

    /**
     * @param int[] $tribes
     *
     * @return list<int>
     */
    private static function order(array $tribes): array
    {
        return array_values(array_filter(self::PICKER_ORDER, static fn (int $id): bool => in_array($id, $tribes, true)));
    }

    public static function slug(int $tribe): string
    {
        return self::SLUGS[$tribe] ?? 'roman';
    }

    public static function summaryColumn(int $tribe): ?string
    {
        return isset(self::SUMMARY_COLUMNS[$tribe]) ? self::SUMMARY_COLUMNS[$tribe] . '_players_count' : null;
    }

    /** @return array<int, string> summary column prefix keyed by tribe */
    public static function summaryColumns(): array
    {
        return self::SUMMARY_COLUMNS;
    }

    /** Whether unit number $nr (1-10) of $tribe is trained in the stable and fights as cavalry. */
    public static function isCavalry(int $tribe, int $nr): bool
    {
        $start = self::CAVALRY_START[$tribe] ?? -1;

        return $start >= 0 && $nr >= $start + 1 && $nr <= 6;
    }

    /**
     * Unit ids a tribe trains in a building: barracks (19, 29), stable (20, 30),
     * workshop (21) and the expansion buildings (25, 26, 44).
     *
     * @return int[]
     */
    public static function trainingUnitIds(int $tribe, int $buildingId): array
    {
        $start = self::CAVALRY_START[$tribe] ?? null;
        if ($start === null || $tribe === 4) {
            return [];
        }
        $numbers = match ($buildingId) {
            19, 29 => range(1, $start),
            20, 30 => range($start + 1, 6),
            21 => [7, 8],
            25, 26, 44 => [9, 10],
            default => [],
        };

        return array_map(static fn (int $nr): int => ($tribe - 1) * 10 + $nr, $numbers);
    }
}
