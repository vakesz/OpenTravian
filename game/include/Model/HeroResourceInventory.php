<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Game\ResourcesHelper;

/**
 * Holds resource rewards until the player deliberately collects them from the
 * hero inventory. Reward producers can call add() inside their own database
 * transaction; collection owns its transaction because it moves the balance
 * to a village.
 *
 * renders carried resources as inventory tiles (item types 145–148).
 * Those rows are kept in sync with this table so the V2 inventory chrome and
 * use/claim path stay aligned.
 */
final class HeroResourceInventory
{
    private const RESOURCE_FIELDS = ['wood', 'clay', 'iron', 'crop'];

    /** Inventory item type ids matching Gpack `.item.item145`–`.item.item148`. */
    public const ITEM_TYPES = [
        'wood' => 145,
        'clay' => 146,
        'iron' => 147,
        'crop' => 148,
    ];

    /** Dedicated btype so use/move treat stacks as bag consumables (btype ≥ 10). */
    public const ITEM_BTYPE = 16;

    /**
     * @param array<string, int|float|string> $resources
     */
    public function add(int $uid, array $resources): bool
    {
        if ($uid < 1) {
            throw new \InvalidArgumentException('A resource inventory needs a player.');
        }

        $amounts = $this->normalize($resources);
        if (array_sum($amounts) === 0) {
            return true;
        }

        $statement = DB::getInstance()->run(
            'INSERT INTO hero_resources (uid, wood, clay, iron, crop, updated_at) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 wood=wood+VALUES(wood),
                 clay=clay+VALUES(clay),
                 iron=iron+VALUES(iron),
                 crop=crop+VALUES(crop),
                 updated_at=VALUES(updated_at)',
            [$uid, $amounts['wood'], $amounts['clay'], $amounts['iron'], $amounts['crop'], time()]
        );

        if ($statement === false) {
            return false;
        }

        $this->syncInventoryItems($uid);

        return true;
    }

    /**
     * @return array{wood:int, clay:int, iron:int, crop:int}
     */
    public function get(int $uid): array
    {
        if ($uid < 1) {
            return $this->empty();
        }

        $statement = DB::getInstance()->run(
            'SELECT wood, clay, iron, crop FROM hero_resources WHERE uid=?',
            [$uid]
        );
        if ($statement === false) {
            throw new \RuntimeException('Could not read the hero resource inventory.');
        }

        $row = $statement->get_result()->fetch_assoc();
        return $row === null ? $this->empty() : $this->normalize($row);
    }

    /**
     * Ensure inventory tiles exist for every non-zero carried resource.
     */
    public function syncInventoryItems(int $uid): void
    {
        if ($uid < 1) {
            return;
        }

        $amounts = $this->get($uid);
        $db = DB::getInstance();
        $auction = new AuctionModel();

        foreach (self::ITEM_TYPES as $field => $type) {
            $amount = $amounts[$field];
            $existing = $db->run(
                'SELECT id, num, placeId FROM items WHERE uid=? AND btype=? AND type=? AND proc=0 LIMIT 1',
                [$uid, self::ITEM_BTYPE, $type]
            );
            if ($existing === false) {
                throw new \RuntimeException('Could not read hero resource inventory items.');
            }
            $row = $existing->get_result()->fetch_assoc();

            if ($amount < 1) {
                if ($row !== null) {
                    $db->run('DELETE FROM items WHERE id=? AND uid=?', [(int)$row['id'], $uid]);
                }
                continue;
            }

            if ($row !== null) {
                if ((int)$row['num'] !== $amount) {
                    $db->run(
                        'UPDATE items SET num=? WHERE id=? AND uid=?',
                        [$amount, (int)$row['id'], $uid]
                    );
                }
                continue;
            }

            $placeId = $auction->getNewPlaceId($uid);
            $inserted = $db->run(
                'INSERT INTO items (uid, btype, type, num, proc, placeId) VALUES (?, ?, ?, ?, 0, ?)',
                [$uid, self::ITEM_BTYPE, $type, $amount, $placeId]
            );
            if ($inserted === false) {
                throw new \RuntimeException('Could not create a hero resource inventory item.');
            }
        }
    }

    /**
     * Moves every carried resource into one owned village.
     *
     * @return array{wood:int, clay:int, iron:int, crop:int}
     */
    public function claim(int $uid, int $kid): array
    {
        return $this->claimAmounts($uid, $kid, $this->get($uid));
    }

    /**
     * Moves one resource type (partial or full stack) into an owned village.
     *
     * @return array{wood:int, clay:int, iron:int, crop:int}
     */
    public function claimField(int $uid, int $kid, string $field, int $amount): array
    {
        if (!isset(self::ITEM_TYPES[$field])) {
            throw new \InvalidArgumentException('Unknown hero resource field.');
        }
        if ($amount < 1) {
            return $this->empty();
        }

        $current = $this->get($uid);
        $take = min($amount, $current[$field]);
        if ($take < 1) {
            return $this->empty();
        }

        $payload = $this->empty();
        $payload[$field] = $take;

        return $this->claimAmounts($uid, $kid, $payload);
    }

    /**
     * @param array{wood:int, clay:int, iron:int, crop:int} $amounts
     * @return array{wood:int, clay:int, iron:int, crop:int}
     */
    private function claimAmounts(int $uid, int $kid, array $amounts): array
    {
        if ($uid < 1 || $kid < 1) {
            throw new \InvalidArgumentException('Choose a valid village for the hero resources.');
        }

        $amounts = $this->normalize($amounts);
        if (array_sum($amounts) === 0) {
            return $amounts;
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            throw new \RuntimeException('Could not lock the hero resource inventory.');
        }

        try {
            $statement = $db->run(
                'SELECT wood, clay, iron, crop FROM hero_resources WHERE uid=? FOR UPDATE',
                [$uid]
            );
            if ($statement === false) {
                throw new \RuntimeException('Could not lock the hero resource inventory.');
            }

            $row = $statement->get_result()->fetch_assoc();
            $available = $row === null ? $this->empty() : $this->normalize($row);
            $taken = $this->empty();
            foreach (self::RESOURCE_FIELDS as $field) {
                $taken[$field] = min($amounts[$field], $available[$field]);
            }
            if (array_sum($taken) === 0) {
                if (!$db->commit()) {
                    throw new \RuntimeException('Could not release the hero resource inventory.');
                }
                return $taken;
            }

            ResourcesHelper::updateVillageResources($kid, true);
            $credited = $db->run(
                'UPDATE vdata SET wood=wood+?, clay=clay+?, iron=iron+?, crop=crop+? WHERE kid=? AND owner=?',
                [$taken['wood'], $taken['clay'], $taken['iron'], $taken['crop'], $kid, $uid]
            );
            if ($credited === false || $credited->affected_rows !== 1) {
                throw new \RuntimeException('The selected village is no longer available.');
            }

            $cleared = $db->run(
                'UPDATE hero_resources SET wood=wood-?, clay=clay-?, iron=iron-?, crop=crop-?, updated_at=? WHERE uid=? AND wood>=? AND clay>=? AND iron>=? AND crop>=?',
                [
                    $taken['wood'],
                    $taken['clay'],
                    $taken['iron'],
                    $taken['crop'],
                    time(),
                    $uid,
                    $taken['wood'],
                    $taken['clay'],
                    $taken['iron'],
                    $taken['crop'],
                ]
            );
            if ($cleared === false || $cleared->affected_rows !== 1) {
                throw new \RuntimeException('Could not collect the hero resources.');
            }
            if (!$db->commit()) {
                throw new \RuntimeException('Could not collect the hero resources.');
            }

            $this->syncInventoryItems($uid);

            return $taken;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $exception;
        }
    }

    /**
     * @param array<string, int|float|string> $resources
     * @return array{wood:int, clay:int, iron:int, crop:int}
     */
    private function normalize(array $resources): array
    {
        $normalized = [];
        foreach (self::RESOURCE_FIELDS as $field) {
            $value = $resources[$field] ?? 0;
            if (!is_numeric($value) || (float)$value < 0 || floor((float)$value) !== (float)$value) {
                throw new \InvalidArgumentException('Hero resource amounts must be whole, non-negative numbers.');
            }
            $normalized[$field] = (int)$value;
        }

        return $normalized;
    }

    /** @return array{wood:int, clay:int, iron:int, crop:int} */
    private function empty(): array
    {
        return ['wood' => 0, 'clay' => 0, 'iron' => 0, 'crop' => 0];
    }
}
