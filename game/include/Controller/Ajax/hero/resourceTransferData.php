<?php

namespace Controller\Ajax\hero;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Model\HeroResourceInventory;

/**
 * Data of the reference resource transfer dialog: the active village's stock
 * and storage limits plus the hero's carried resource stacks (items 145-148).
 */
class resourceTransferData extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error('You cannot transfer hero resources from this session.', 403);
            return;
        }

        $uid = (int)$this->session->getPlayerId();
        $villageId = (int)$this->session->getKid();
        (new HeroResourceInventory())->syncInventoryItems($uid);
        $db = DB::getInstance();
        $statement = $db->run(
            'SELECT kid, name, wood, clay, iron, crop, maxstore, maxcrop FROM vdata WHERE kid=? AND owner=?',
            [$villageId, $uid]
        );
        $village = $statement ? $statement->get_result()->fetch_assoc() : null;
        if (!is_array($village)) {
            $this->error('The active village could not be loaded.');
            return;
        }

        $inventory = [];
        foreach (HeroResourceInventory::ITEM_TYPES as $field => $typeId) {
            $stack = $db->run(
                'SELECT id, num FROM items WHERE uid=? AND btype=? AND type=? AND proc=0 ORDER BY placeId LIMIT 1',
                [$uid, HeroResourceInventory::ITEM_BTYPE, $typeId]
            );
            $row = $stack ? $stack->get_result()->fetch_assoc() : null;
            $inventory[$field === 'wood' ? 'lumber' : $field] = [
                'typeId' => $typeId,
                'id' => (int)($row['id'] ?? 0),
                'amount' => (int)($row['num'] ?? 0),
            ];
        }

        $this->response['village'] = [
            'id' => (int)$village['kid'],
            'name' => (string)$village['name'],
            'resources' => [
                'lumberStock' => (int)floor((float)$village['wood']),
                'clayStock' => (int)floor((float)$village['clay']),
                'ironStock' => (int)floor((float)$village['iron']),
                'cropStock' => (int)floor((float)$village['crop']),
                'maxStorageCapacity' => (int)$village['maxstore'],
                'maxCropStorageCapacity' => (int)$village['maxcrop'],
            ],
        ];
        $this->response['inventory'] = $inventory;
        $texts = [];
        foreach (['Headline', 'Description', 'ButtonAll', 'ButtonInput', 'ButtonSelected', 'Lumber', 'Clay', 'Iron', 'Crop',
            'WarehouseFullTooltip', 'GranaryFullTooltip', 'Error_emptyInventory', 'Error_notEnoughStorage', 'Error_notEnoughCropStorage'] as $key) {
            $texts[$key] = (string)T('HeroInventory', 'resourceTransfer' . $key);
        }
        foreach (['lumber', 'clay', 'iron', 'crop'] as $resource) {
            foreach (['Success', 'ErrorStorageFull'] as $kind) {
                $texts[$kind . '_' . $resource] = (string)T('HeroInventory', 'resourceTransfer' . $kind . '_' . $resource);
            }
        }
        $this->response['texts'] = $texts;
    }
}
