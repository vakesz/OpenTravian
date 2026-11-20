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
 * Egyptians, Huns, Spartans and Vikings start ticked.
 */
final class Tribes
{
    public const ROMANS = 1;
    public const TEUTONS = 2;
    public const GAULS = 3;
    public const NATURE = 4;
    public const NATARS = 5;
    public const EGYPTIANS = 6;
    public const HUNS = 7;
    public const SPARTANS = 8;
    public const VIKINGS = 9;

    /** Tribes a world may offer to players. */
    public const SELECTABLE = [self::ROMANS, self::TEUTONS, self::GAULS, self::EGYPTIANS, self::HUNS, self::SPARTANS, self::VIKINGS];

    /** Default playable tribes, including Vikings. */
    public const DEFAULT_PLAYABLE = [self::ROMANS, self::TEUTONS, self::GAULS, self::EGYPTIANS, self::HUNS, self::SPARTANS, self::VIKINGS];

    /** Display names for the tribes a world may offer. */
    public const NAMES = [self::ROMANS => 'Romans', self::TEUTONS => 'Teutons', self::GAULS => 'Gauls', self::EGYPTIANS => 'Egyptians', self::HUNS => 'Huns', self::SPARTANS => 'Spartans', self::VIKINGS => 'Vikings'];

    /** Order of the activation tribe picker (Gauls are the recommended first card). */
    public const PICKER_ORDER = [self::GAULS, self::ROMANS, self::TEUTONS, self::EGYPTIANS, self::HUNS, self::SPARTANS, self::VIKINGS];

    /** Slug used by Gpack classes and assets. */
    private const SLUGS = [
        self::ROMANS => 'roman',
        self::TEUTONS => 'teuton',
        self::GAULS => 'gaul',
        self::NATURE => 'nature',
        self::NATARS => 'natar',
        self::EGYPTIANS => 'egyptian',
        self::HUNS => 'hun',
        self::SPARTANS => 'spartan',
        self::VIKINGS => 'viking',
    ];

    /**
     * Zero-based position of the first stable-trained unit among a tribe's six
     * barracks/stable units (-1: Nature has no stable). Hun Spotters, Gaul
     * Pathfinders, Roman Equites Legati and Egyptian Sopdu Explorers ride; Teuton,
     * Spartan and Viking scouts walk.
     */
    public const CAVALRY_START = [
        self::ROMANS => 3,
        self::TEUTONS => 4,
        self::GAULS => 2,
        self::NATURE => -1,
        self::NATARS => 4,
        self::EGYPTIANS => 3,
        self::HUNS => 2,
        self::SPARTANS => 4,
        self::VIKINGS => 4,
    ];

    /** summary table column prefix per selectable tribe. */
    private const SUMMARY_COLUMNS = [
        self::ROMANS => 'roman',
        self::TEUTONS => 'teuton',
        self::GAULS => 'gaul',
        self::EGYPTIANS => 'egyptians',
        self::HUNS => 'huns',
        self::SPARTANS => 'spartans',
        self::VIKINGS => 'vikings',
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
        return self::isPlayable(self::VIKINGS);
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
        if ($start === null || $tribe === self::NATURE) {
            return [];
        }
        $numbers = match ($buildingId) {
            Gid::BARRACKS, Gid::GREAT_BARRACKS => range(1, $start),
            Gid::STABLE, Gid::GREAT_STABLE => range($start + 1, 6),
            Gid::WORKSHOP => [Unit::RAM, Unit::CATAPULT],
            Gid::RESIDENCE, Gid::PALACE, Gid::COMMAND_CENTER => [Unit::CHIEF, Unit::SETTLER],
            default => [],
        };

        return array_map(static fn (int $nr): int => ($tribe - 1) * 10 + $nr, $numbers);
    }
}
