<?php

declare(strict_types=1);

namespace Model;

use Core\Caching\Caching;
use Core\Database\DB;
use Game\Hero\HeroImage;

class HeroFaceModel
{
    /**
     * @param array<string, int|string> $attributes
     */
    public function modifyWholeHeroFace(int $uid, array $attributes): bool
    {
        $db = DB::getInstance();
        $now = time();
        // Keep the body URL version unique even when a player saves two
        // appearance changes in the same second. The browser otherwise has no
        // reason to replace its cached portrait.
        $inventory = $db->run(
            'UPDATE inventory SET lastupdate=GREATEST(lastupdate + 1, ?) WHERE uid=?',
            [$now, $uid]
        );
        $face = $db->run(
            'UPDATE face SET headProfile=?, hairColor=?, hairStyle=?, ears=?, eyebrow=?, eyes=?, nose=?, mouth=?, beard=?, scar=?, tattoo=?, skinColor=?, eyeColor=?, gender=?, lastupdate=? WHERE uid=?',
            [
                (int)$attributes['headProfile'],
                (int)$attributes['hairColor'],
                (int)$attributes['hairStyle'],
                (int)$attributes['ears'],
                (int)$attributes['eyebrow'],
                (int)$attributes['eyes'],
                (int)$attributes['nose'],
                (int)$attributes['mouth'],
                (int)$attributes['beard'],
                (int)$attributes['scar'],
                (int)$attributes['tattoo'],
                (string)$attributes['skinColor'],
                (string)$attributes['eyeColor'],
                (string)$attributes['gender'],
                $now,
                $uid,
            ]
        );
        if (!$inventory || !$face) {
            return false;
        }

        $this->invalidateCache($uid);
        return true;
    }

    /**
     * Return the normalized ownership map used by the appearance picker.
     * Keys are deliberately the exact attribute/value pair accepted by the
     * server, so a request cannot unlock one option and save another.
     *
     * @return array<string, true>
     */
    public function getUnlockedOptions(int $uid, bool $forUpdate = false): array
    {
        $statement = DB::getInstance()->run(
            'SELECT attribute_name, option_value FROM hero_appearance_unlocks WHERE uid=?' . ($forUpdate ? ' FOR UPDATE' : ''),
            [$uid]
        );
        if ($statement === false) {
            return [];
        }

        $result = $statement->get_result();
        $unlocked = [];
        while ($row = $result->fetch_assoc()) {
            $unlocked[$row['attribute_name'] . ':' . (int)$row['option_value']] = true;
        }

        return $unlocked;
    }

    /**
     * @param list<array{0: string, 1: int}> $options attribute/value pairs
     */
    public function unlockOptions(int $uid, array $options): bool
    {
        if ($options === []) {
            return true;
        }

        foreach ($options as [$attribute, $option]) {
            if (DB::getInstance()->run(
                'INSERT IGNORE INTO hero_appearance_unlocks (uid, attribute_name, option_value, unlocked_at) VALUES (?, ?, ?, ?)',
                [$uid, $attribute, $option, time()]
            ) === false) {
                return false;
            }
        }

        return true;
    }

    private function invalidateCache(int $uid): void
    {
        HeroImage::forget($uid);
        Caching::getInstance()->delete("hero_sidebar_{$uid}");
    }
}
