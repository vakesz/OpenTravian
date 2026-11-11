<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\Hero\Artwork;
use Game\Hero\HeroExperience;
use Game\Hero\HeroHelper;
use Game\Hero\HeroItems;
use Game\ResourcesHelper;

/**
 * Owns the database boundary for hero inventory movement and item use.
 * The browser only supplies an item, target and amount; ownership and item
 * effects are always resolved from locked database rows.
 */
class HeroInventoryModel
{
    private const EQUIPMENT_SLOTS = ['helmet', 'body', 'leftHand', 'rightHand', 'shoes', 'horse', 'bag'];

    /** @var array<string, int> */
    private const LEGACY_EQUIPMENT_SLOT_TYPES = [
        'helmet' => 1,
        'body' => 2,
        'leftHand' => 3,
        'rightHand' => 4,
        'shoes' => 5,
        'horse' => 6,
    ];

    /**
     * Safely handles the retired hero-page equipment form. New equipment must
     * still be moved through apply(), which keeps item and inventory state in
     * sync; this compatibility path only permits removing an equipped item.
     *
     * @param array<string, int> $equipment
     */
    public function applyLegacyEquipment(int $uid, array $equipment): void
    {
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            throw new \RuntimeException('Could not lock the hero inventory.');
        }

        try {
            $inventory = $this->fetchOne(
                'SELECT helmet, body, leftHand, rightHand, shoes, horse FROM inventory WHERE uid=? FOR UPDATE',
                [$uid]
            );
            if ($inventory === null) {
                throw new \DomainException('That hero inventory is no longer available.');
            }

            $items = $this->loadLegacyEquipmentItems($uid, $inventory, $equipment);
            $slotsToRemove = self::legacyEquipmentSlotsToRemove($inventory, $equipment, $items);
            foreach ($slotsToRemove as $slot => $itemId) {
                $item = $items[$itemId] ?? null;
                if ($item !== null
                    && (int)$item['btype'] === self::LEGACY_EQUIPMENT_SLOT_TYPES[$slot]
                    && (int)$item['proc'] === 1) {
                    $placeId = $this->findFreePlace($uid);
                    $unequipped = $db->run(
                        'UPDATE items SET proc=0, placeId=? WHERE id=? AND uid=? AND proc=1',
                        [$placeId, $itemId, $uid]
                    );
                    if (!$unequipped || $db->affectedRows() !== 1) {
                        throw new \RuntimeException('Could not remove the equipped item.');
                    }
                }

                $updated = $db->run("UPDATE inventory SET `$slot`=0, lastupdate=? WHERE uid=?", [time(), $uid]);
                if (!$updated) {
                    throw new \RuntimeException('Could not save the equipment slot.');
                }
                \Game\Hero\HeroImage::forget($uid);
            }

            if (!$db->commit()) {
                throw new \RuntimeException('Could not commit the hero inventory update.');
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $exception;
        }
    }

    /**
     * Validates the locked snapshot used by the retired full-state equipment
     * form. A nonzero value must be the same, owned equipped item already in
     * that slot. Zero is an explicit request to unequip the current item.
     *
     * @param array<string, int|string> $inventory
     * @param array<string, int> $equipment
     * @param array<int, array<string, int|string>> $items
     * @return array<string, int>
     */
    public static function legacyEquipmentSlotsToRemove(array $inventory, array $equipment, array $items): array
    {
        $slotsToRemove = [];
        foreach (self::LEGACY_EQUIPMENT_SLOT_TYPES as $slot => $expectedType) {
            $requestedItemId = $equipment[$slot] ?? null;
            if (!is_int($requestedItemId) || $requestedItemId < 0) {
                throw new \DomainException('The hero equipment request is invalid.');
            }

            $currentItemId = (int)$inventory[$slot];
            if ($requestedItemId === 0) {
                if ($currentItemId > 0) {
                    $slotsToRemove[$slot] = $currentItemId;
                }
                continue;
            }

            $item = $items[$requestedItemId] ?? null;
            if ($item === null
                || (int)$item['id'] !== $requestedItemId
                || (int)$item['proc'] !== 1
                || (int)$item['btype'] !== $expectedType
                || $currentItemId !== $requestedItemId) {
                throw new \DomainException('Hero equipment must be changed from the inventory.');
            }
        }

        return $slotsToRemove;
    }

    /** Amount a use action really applied (resource stacks are capped by storage). */
    private int $applied = 0;

    /**
     * Apply a move or use action and return the amount it actually affected.
     */
    public function apply(int $uid, int $villageId, int $itemId, string $target, int $amount, bool $clicked): int
    {
        $this->applied = $amount;
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            throw new \RuntimeException('Could not lock the hero inventory.');
        }

        try {
            $hero = $this->fetchOne(
                'SELECT uid, kid, exp, health, itemHealth, lastupdate FROM hero WHERE uid=? FOR UPDATE',
                [$uid]
            );
            $inventory = $this->fetchOne('SELECT * FROM inventory WHERE uid=? FOR UPDATE', [$uid]);
            $item = $this->fetchOne('SELECT * FROM items WHERE id=? AND uid=? FOR UPDATE', [$itemId, $uid]);
            if ($hero === null || $inventory === null || $item === null) {
                throw new \DomainException('That hero item is no longer available.');
            }
            if ($amount > (int)$item['num']) {
                throw new \DomainException('You do not have that many of this item.');
            }

            $type = (int)$item['btype'];
            $useItem = $type >= 10 && $clicked;
            if ($useItem) {
                $this->useItem($uid, $villageId, $hero, $inventory, $item, $amount);
            } else {
                $this->moveItem($uid, $inventory, $item, $target, $amount);
            }

            if (!$db->commit()) {
                throw new \RuntimeException('Could not commit the hero inventory update.');
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $exception;
        }

        return $this->applied;
    }

    /** Reference place ids: negative values are equipment slots, positive ones inventory cells. */
    private const SLOT_BY_PLACE = [-1 => 'helmet', -2 => 'leftHand', -3 => 'rightHand', -4 => 'body', -5 => 'shoes', -6 => 'bag', -7 => 'horse'];

    /**
     * Resolve a reference targetPlaceId (0 = first free inventory cell) to the
     * target name apply() understands.
     */
    public function targetForPlace(int $uid, int $targetPlaceId): ?string
    {
        if ($targetPlaceId < 0) {
            return self::SLOT_BY_PLACE[$targetPlaceId] ?? null;
        }
        if ($targetPlaceId === 0) {
            $statement = DB::getInstance()->run('SELECT placeId FROM items WHERE uid=? AND proc=0', [$uid]);
            $occupied = [];
            $result = $statement ? $statement->get_result() : false;
            while ($result && ($row = $result->fetch_assoc())) {
                $occupied[(int)$row['placeId']] = true;
            }
            $capacity = (new AuctionModel())->getVisibleInventorySize($uid);
            for ($placeId = 1; $placeId <= $capacity; ++$placeId) {
                if (!isset($occupied[$placeId])) {
                    return 'inventory_' . $placeId;
                }
            }
            return null;
        }

        return 'inventory_' . $targetPlaceId;
    }

    /**
     * @param array<string, int|string> $inventory
     * @param array<string, int> $equipment
     * @return array<int, array<string, int|string>>
     */
    private function loadLegacyEquipmentItems(int $uid, array $inventory, array $equipment): array
    {
        $itemIds = [];
        foreach (self::LEGACY_EQUIPMENT_SLOT_TYPES as $slot => $_) {
            $currentItemId = (int)$inventory[$slot];
            if ($currentItemId > 0) {
                $itemIds[$currentItemId] = $currentItemId;
            }

            $requestedItemId = $equipment[$slot] ?? 0;
            if ($requestedItemId > 0) {
                $itemIds[$requestedItemId] = $requestedItemId;
            }
        }

        $items = [];
        foreach ($itemIds as $itemId) {
            $item = $this->fetchOne(
                'SELECT id, btype, proc FROM items WHERE id=? AND uid=? FOR UPDATE',
                [$itemId, $uid]
            );
            if ($item !== null) {
                $items[$itemId] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $inventory
     * @param array<string, mixed> $item
     */
    private function moveItem(int $uid, array $inventory, array $item, string $target, int $amount): void
    {
        $db = DB::getInstance();
        $itemId = (int)$item['id'];
        $itemType = (int)$item['btype'];
        $sourceSlot = $this->findEquippedSlot($inventory, $itemId);
        $isEquipped = (int)$item['proc'] === 1;
        if ($isEquipped !== ($sourceSlot !== null)) {
            throw new \DomainException('This item is not in a valid inventory slot.');
        }

        if (str_starts_with($target, 'inventory_')) {
            $placeId = (int)substr($target, strlen('inventory_'));
            $visibleCapacity = (new AuctionModel())->getVisibleInventorySize($uid);
            if ($placeId > $visibleCapacity) {
                throw new \DomainException('That inventory slot is not available.');
            }
            if ($isEquipped) {
                $this->moveEquippedToInventory($uid, $inventory, $item, $sourceSlot, $placeId);
            } else {
                $this->moveInventoryItem($uid, $item, $placeId, $amount);
            }
            return;
        }

        $expectedSlot = $itemType <= 6 ? (new HeroItems())->getHeroItemProperties($itemType, (int)$item['type'])['slot'] : 'bag';
        // Bandages, cages and ointments go into the bag slot (an ointment there heals the hero after a battle).
        if (!in_array($itemType, [...range(1, 9), 11], true) || $target !== $expectedSlot) {
            throw new \DomainException('That item cannot be equipped in this slot.');
        }
        if ($isEquipped) {
            if ($sourceSlot === $target) {
                return;
            }
            throw new \DomainException('Move the item back to the inventory first.');
        }
        if ($amount !== 1 && $itemType <= 6) {
            throw new \DomainException('Equipment items cannot be split.');
        }

        $equippedId = (int)$inventory[$target];
        if ($equippedId > 0) {
            $oldItem = $this->fetchOne('SELECT id, btype, type FROM items WHERE id=? AND uid=? AND proc=1 FOR UPDATE', [$equippedId, $uid]);
            if ($oldItem === null) {
                throw new \DomainException('The equipped item is no longer available.');
            }
            if ($target === 'bag' && (int)$oldItem['btype'] === $itemType && (int)$oldItem['type'] === (int)$item['type']) {
                // The same kind is already in the bag: the reference adds to that stack (alreadyEquipped + amount).
                $grown = $db->run('UPDATE items SET num=num+? WHERE id=? AND uid=? AND proc=1', [$amount, $equippedId, $uid]);
                if (!$grown || !$this->consumeItem($uid, $item, $amount)) {
                    throw new \RuntimeException('Could not add to the equipped stack.');
                }
                return;
            }

            $oldPlace = $amount === (int)$item['num']
                ? (int)$item['placeId']
                : $this->findFreePlace($uid);
            $released = $db->run('UPDATE items SET proc=0, placeId=? WHERE id=? AND uid=? AND proc=1', [$oldPlace, $equippedId, $uid]);
            if (!$released || $db->affectedRows() !== 1) {
                throw new \RuntimeException('Could not unequip the current item.');
            }
        }

        if ($amount < (int)$item['num']) {
            $this->splitStack($uid, $item, $amount, (int)$item['placeId'], true);
        } else {
            $equipped = $db->run('UPDATE items SET proc=1 WHERE id=? AND uid=? AND proc=0', [$itemId, $uid]);
            if (!$equipped || $db->affectedRows() !== 1) {
                throw new \RuntimeException('Could not equip the item.');
            }
        }

        $newId = $amount < (int)$item['num'] ? $db->lastInsertId() : $itemId;
        $this->setInventorySlot($uid, $target, $newId);
        if ($target !== 'bag') {
            $this->touchHeroBody($uid);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function moveInventoryItem(int $uid, array $item, int $targetPlace, int $amount): void
    {
        $db = DB::getInstance();
        $itemId = (int)$item['id'];
        $sourcePlace = (int)$item['placeId'];
        $targetItem = $this->fetchOne(
            'SELECT id, btype, type, num, placeId FROM items WHERE uid=? AND proc=0 AND placeId=? AND id<>? FOR UPDATE',
            [$uid, $targetPlace, $itemId]
        );
        if ($targetPlace === $sourcePlace) {
            return;
        }

        if ($targetItem !== null && $amount === (int)$item['num']
            && (int)$targetItem['btype'] === (int)$item['btype']
            && (int)$targetItem['type'] === (int)$item['type']
            && (int)$item['btype'] >= 7 && !in_array((int)$item['btype'], [12, 13], true)) {
            $merged = $db->run('UPDATE items SET num=num+? WHERE id=? AND uid=? AND proc=0', [$amount, (int)$targetItem['id'], $uid]);
            $removed = $db->run('DELETE FROM items WHERE id=? AND uid=? AND proc=0 AND num=?', [$itemId, $uid, (int)$item['num']]);
            if (!$merged || !$removed || $db->affectedRows() !== 1) {
                throw new \RuntimeException('Could not combine the item stacks.');
            }
            return;
        }

        if ($amount < (int)$item['num']) {
            if ($targetItem !== null) {
                $freePlace = $this->findFreePlace($uid, [(int)$targetItem['id']]);
                $moved = $db->run('UPDATE items SET placeId=? WHERE id=? AND uid=? AND proc=0', [$freePlace, (int)$targetItem['id'], $uid]);
                if (!$moved) {
                    throw new \RuntimeException('Could not move the item in that inventory slot.');
                }
            }
            $this->splitStack($uid, $item, $amount, $targetPlace, false);
            return;
        }

        if ($targetItem !== null) {
            $swapped = $db->run('UPDATE items SET placeId=? WHERE id=? AND uid=? AND proc=0', [$sourcePlace, (int)$targetItem['id'], $uid]);
            if (!$swapped) {
                throw new \RuntimeException('Could not swap the inventory items.');
            }
        }
        $moved = $db->run('UPDATE items SET placeId=? WHERE id=? AND uid=? AND proc=0', [$targetPlace, $itemId, $uid]);
        if (!$moved) {
            throw new \RuntimeException('Could not move the inventory item.');
        }
    }

    /**
     * @param array<string, mixed> $inventory
     * @param array<string, mixed> $item
     */
    private function moveEquippedToInventory(int $uid, array $inventory, array $item, string $slot, int $targetPlace): void
    {
        $db = DB::getInstance();
        $itemId = (int)$item['id'];
        $targetItem = $this->fetchOne(
            'SELECT id FROM items WHERE uid=? AND proc=0 AND placeId=? FOR UPDATE',
            [$uid, $targetPlace]
        );
        if ($targetItem !== null) {
            $freePlace = $this->findFreePlace($uid, [$itemId, (int)$targetItem['id']]);
            $moved = $db->run('UPDATE items SET placeId=? WHERE id=? AND uid=? AND proc=0', [$freePlace, (int)$targetItem['id'], $uid]);
            if (!$moved) {
                throw new \RuntimeException('Could not move the item in that inventory slot.');
            }
        }

        $unequipped = $db->run('UPDATE items SET proc=0, placeId=? WHERE id=? AND uid=? AND proc=1', [$targetPlace, $itemId, $uid]);
        if (!$unequipped || $db->affectedRows() !== 1) {
            throw new \RuntimeException('Could not remove the equipped item.');
        }
        $this->setInventorySlot($uid, $slot, 0);
        if ($slot !== 'bag') {
            $this->touchHeroBody($uid);
        }
    }

    /**
     * @param array<string, mixed> $hero
     * @param array<string, mixed> $inventory
     * @param array<string, mixed> $item
     */
    private function useItem(int $uid, int $villageId, array $hero, array $inventory, array $item, int $amount): void
    {
        if ((int)$item['proc'] !== 0) {
            throw new \DomainException('Move this item into the inventory before using it.');
        }

        $db = DB::getInstance();
        $itemType = (int)$item['btype'];
        $properties = (new HeroItems())->getHeroItemProperties($itemType, (int)$item['type']);
        switch ($itemType) {
            case 10:
                $bonus = (new HeroHelper())->calcMoreExp((int)$inventory['helmet']);
                $experience = (int)floor((float)$properties['exp'] * (100 + $bonus) / 100 * $amount);
                if ($experience <= 0 || !HeroExperience::add($uid, $experience)) {
                    throw new \RuntimeException('Could not apply the experience scroll.');
                }
                break;

            case 11:
                $health = $this->currentHealth($hero);
                if ($health <= 0) {
                    throw new \DomainException('Ointments cannot be used while the hero is dead.');
                }
                $used = min($amount, (int)ceil(100 - $health));
                if ($used <= 0) {
                    throw new \DomainException('The hero already has full health.');
                }
                $newHealth = min(100, $health + $used);
                $updated = $db->run('UPDATE hero SET health=?, lastupdate=? WHERE uid=?', [$newHealth, time(), $uid]);
                if (!$updated || !$this->consumeItem($uid, $item, $used)) {
                    throw new \RuntimeException('Could not apply the ointment.');
                }
                return;

            case 12:
                $now = time();
                $rate = max(1, (int)Config::getInstance()->heroConfig->waterBucketsPerDay);
                $nextUse = (int)$inventory['lastWaterBucketUse'] + intdiv(86400, $rate);
                if ((float)$hero['health'] > 0) {
                    throw new \DomainException('A water bucket can only revive a dead or reviving hero.');
                }
                if ($now < $nextUse) {
                    throw new \DomainException('The water bucket is still on cooldown.');
                }
                $oldKid = (int)$hero['kid'];
                ResourcesHelper::updateVillageResources($oldKid, true);
                if (!$db->run('DELETE FROM training WHERE kid=? AND nr=11', [(int)$hero['kid']])
                    || !$db->run('UPDATE hero SET health=100, kid=?, lastupdate=? WHERE uid=?', [$villageId, $now, $uid])
                    || !$db->run('UPDATE units SET u11=1 WHERE kid=?', [$villageId])
                    || !$db->run('UPDATE inventory SET lastWaterBucketUse=? WHERE uid=?', [$now, $uid])
                    || !$this->consumeItem($uid, $item, 1)) {
                    throw new \RuntimeException('Could not use the water bucket.');
                }
                ResourcesHelper::updateVillageUpkeep($uid, $villageId);
                ResourcesHelper::updateVillageResources($villageId, false, false);
                return;

            case 13:
                if ($amount !== 1) {
                    throw new \DomainException('Only one Book of Wisdom can be used at a time.');
                }
                if (!$db->run('UPDATE hero SET power=0, offBonus=0, defBonus=0, production=0 WHERE uid=?', [$uid])
                    || !$this->consumeItem($uid, $item, 1)) {
                    throw new \RuntimeException('Could not use the Book of Wisdom.');
                }
                return;

            case 14:
                $village = $this->fetchOne('SELECT loyalty FROM vdata WHERE kid=? AND owner=? FOR UPDATE', [(int)$hero['kid'], $uid]);
                if ($village === null) {
                    throw new \DomainException('The hero’s home village could not be found.');
                }
                $used = min($amount, max(0, 125 - (int)$village['loyalty']));
                if ($used <= 0) {
                    throw new \DomainException('Village loyalty is already at its maximum.');
                }
                if (!$db->run('UPDATE vdata SET loyalty=loyalty+? WHERE kid=? AND owner=?', [$used, (int)$hero['kid'], $uid])
                    || !$this->consumeItem($uid, $item, $used)) {
                    throw new \RuntimeException('Could not apply the tablets of law.');
                }
                return;

            case 15:
                $now = time();
                if ($amount !== 1) {
                    throw new \DomainException('Only one artwork can be used at a time.');
                }
                if ($now < Artwork::availableAt((int)getGameSpeed(), (int)$inventory['lastArtworkUse'])) {
                    throw new \DomainException('The artwork is still on cooldown.');
                }
                $production = $this->fetchOne('SELECT COALESCE(SUM(cp), 0) AS total FROM vdata WHERE owner=?', [$uid]);
                $villageProduction = (int)($production['total'] ?? 0);
                $perItem = Artwork::reward((int)getGameSpeed(), $villageProduction);
                if ($perItem <= 0) {
                    throw new \DomainException('There is no culture point production to convert yet.');
                }
                if (!$db->run('UPDATE users SET cp=cp+? WHERE id=?', [$perItem, $uid])
                    || !$db->run('UPDATE inventory SET lastArtworkUse=? WHERE uid=?', [$now, $uid])
                    || !$this->consumeItem($uid, $item, 1)) {
                    throw new \RuntimeException('Could not apply the artwork.');
                }
                return;

            case 16:
                $resourceByType = array_flip(HeroResourceInventory::ITEM_TYPES);
                $field = $resourceByType[(int)$item['type']] ?? null;
                if ($field === null) {
                    throw new \DomainException('This item cannot be used here.');
                }
                $locked = $this->fetchOne(
                    'SELECT wood, clay, iron, crop FROM hero_resources WHERE uid=? FOR UPDATE',
                    [$uid]
                );
                $available = (int)($locked[$field] ?? 0);
                $used = min($amount, $available, (int)$item['num']);
                if ($used < 1) {
                    throw new \DomainException('There are no hero resources to collect.');
                }
                ResourcesHelper::updateVillageResources($villageId, true);
                $village = $this->fetchOne(
                    "SELECT `$field` AS stock, " . ($field === 'crop' ? 'maxcrop' : 'maxstore') . ' AS capacity FROM vdata WHERE kid=? AND owner=? FOR UPDATE',
                    [$villageId, $uid]
                );
                if ($village === null) {
                    throw new \DomainException('The selected village is no longer available.');
                }
                // A full warehouse or granary takes nothing; the stack stays with the hero.
                $used = min($used, max(0, (int)floor((float)$village['capacity'] - (float)$village['stock'])));
                if ($used < 1) {
                    $this->applied = 0;
                    return;
                }
                $this->applied = $used;
                $credited = $db->run(
                    "UPDATE vdata SET `$field`=`$field`+? WHERE kid=? AND owner=?",
                    [$used, $villageId, $uid]
                );
                if (!$credited || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('The selected village is no longer available.');
                }
                $reduced = $db->run(
                    "UPDATE hero_resources SET `$field`=`$field`-?, updated_at=? WHERE uid=? AND `$field`>=?",
                    [$used, time(), $uid, $used]
                );
                if (!$reduced || $reduced->affected_rows !== 1 || !$this->consumeItem($uid, $item, $used)) {
                    throw new \RuntimeException('Could not collect the hero resources.');
                }
                return;

            default:
                throw new \DomainException('This item cannot be used here.');
        }

        if (!$this->consumeItem($uid, $item, $amount)) {
            throw new \RuntimeException('Could not consume the hero item.');
        }
    }

    /**
     * @param array<string, mixed> $hero
     */
    private function currentHealth(array $hero): float
    {
        $elapsed = max(0, time() - (int)$hero['lastupdate']);
        $rate = (new HeroHelper())->calcHealth() + (float)$hero['itemHealth'];
        $rate *= max(1, (float)Config::getProperty('game', 'heroRegenerationMultiplier'));
        return min(100, (float)$hero['health'] + ($rate / 86400 * $elapsed));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function consumeItem(int $uid, array $item, int $amount): bool
    {
        $db = DB::getInstance();
        if ($amount >= (int)$item['num']) {
            $statement = $db->run('DELETE FROM items WHERE id=? AND uid=? AND num>=?', [(int)$item['id'], $uid, $amount]);
        } else {
            $statement = $db->run('UPDATE items SET num=num-? WHERE id=? AND uid=? AND num>=?', [$amount, (int)$item['id'], $uid, $amount]);
        }
        return $statement !== false && $db->affectedRows() === 1;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function splitStack(int $uid, array $item, int $amount, int $placeId, bool $equip): void
    {
        $db = DB::getInstance();
        $reduced = $db->run('UPDATE items SET num=num-? WHERE id=? AND uid=? AND proc=0 AND num>?', [$amount, (int)$item['id'], $uid, $amount]);
        $inserted = $db->run(
            'INSERT INTO items (uid, btype, type, num, placeId, proc) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, (int)$item['btype'], (int)$item['type'], $amount, $placeId, $equip ? 1 : 0]
        );
        if (!$reduced || !$inserted) {
            throw new \RuntimeException('Could not split the item stack.');
        }
    }

    /**
     * @param array<string, mixed> $inventory
     */
    private function findEquippedSlot(array $inventory, int $itemId): ?string
    {
        foreach (self::EQUIPMENT_SLOTS as $slot) {
            if ((int)$inventory[$slot] === $itemId) {
                return $slot;
            }
        }
        return null;
    }

    private function setInventorySlot(int $uid, string $slot, int $itemId): void
    {
        if (!in_array($slot, self::EQUIPMENT_SLOTS, true)) {
            throw new \DomainException('Invalid equipment slot.');
        }
        $statement = DB::getInstance()->run("UPDATE inventory SET `$slot`=?, lastupdate=? WHERE uid=?", [$itemId, time(), $uid]);
        if (!$statement) {
            throw new \RuntimeException('Could not save the equipment slot.');
        }
        \Game\Hero\HeroImage::forget($uid);
    }

    private function touchHeroBody(int $uid): void
    {
        // The portrait URL uses lastupdate as its cache-busting version. A wall-clock
        // timestamp alone is not enough here: two drag operations can complete in
        // the same second and otherwise leave the browser displaying the previous
        // equipment render. Keep the version monotonically increasing while still
        // advancing it to the current time after a quiet period.
        if (!DB::getInstance()->run(
            'UPDATE inventory SET lastupdate=GREATEST(lastupdate + 1, ?) WHERE uid=?',
            [time(), $uid]
        )) {
            throw new \RuntimeException('Could not refresh the hero appearance.');
        }
        \Game\Hero\HeroImage::forget($uid);
    }

    /**
     * @param list<int> $excludedIds
     */
    private function findFreePlace(int $uid, array $excludedIds = []): int
    {
        $db = DB::getInstance();
        $statement = $db->run('SELECT id, placeId FROM items WHERE uid=? AND proc=0 FOR UPDATE', [$uid]);
        if (!$statement) {
            throw new \RuntimeException('Could not read the inventory slots.');
        }
        $occupied = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!in_array((int)$row['id'], $excludedIds, true)) {
                $occupied[(int)$row['placeId']] = true;
            }
        }

        // Capacity is the inventory grid the client can render (8 columns x 6 rows);
        // the visible size only grows to expose the lowest free slot inside it.
        $capacity = (new AuctionModel())->getInventorySize($uid);
        for ($placeId = 1; $placeId <= $capacity; ++$placeId) {
            if (!isset($occupied[$placeId])) {
                return $placeId;
            }
        }
        throw new \DomainException('Your hero inventory is full.');
    }

    /**
     * @param list<int|string> $parameters
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $query, array $parameters): ?array
    {
        $statement = DB::getInstance()->run($query, $parameters);
        if (!$statement) {
            throw new \RuntimeException('Could not read hero inventory data.');
        }
        $row = $statement->get_result()->fetch_assoc();
        return $row ?: null;
    }
}
