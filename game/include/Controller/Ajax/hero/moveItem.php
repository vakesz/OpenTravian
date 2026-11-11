<?php

namespace Controller\Ajax\hero;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Game\Hero\HeroInventoryPayload;
use Model\HeroInventoryModel;

/**
 * Reference POST hero/v2/inventory/move-item: {action, itemId, amount, targetPlaceId}.
 * Negative place ids are equipment slots, positive ones inventory cells and 0
 * means "first free inventory cell". Dropping a consumable on the bag slot uses it.
 */
class moveItem extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error('You cannot change hero items from this session.', 403);
            return;
        }

        $itemId = HeroInventoryPayload::positiveInteger($_POST['itemId'] ?? null);
        $amount = HeroInventoryPayload::positiveInteger($_POST['amount'] ?? null);
        $place = $_POST['targetPlaceId'] ?? null;
        if (($_POST['action'] ?? '') !== 'inventory' || $itemId === null || $amount === null
            || !is_string($place) || !preg_match('/^-?[0-9]{1,6}$/', $place)) {
            $this->error('Invalid inventory request.');
            return;
        }

        $uid = (int)$this->session->getPlayerId();
        $villageId = (int)$this->session->getKid();
        $ownedVillage = DB::getInstance()->run('SELECT kid FROM vdata WHERE kid=? AND owner=?', [$villageId, $uid]);
        if (!$ownedVillage || !$ownedVillage->get_result()->fetch_assoc()) {
            $this->error('The selected village is not yours.', 403);
            return;
        }

        $model = new HeroInventoryModel();
        $target = $model->targetForPlace($uid, (int)$place);
        if ($target === null) {
            $this->error('That inventory slot is not available.');
            return;
        }

        try {
            $model->apply($uid, $villageId, $itemId, $target, $amount, false);
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());
            return;
        } catch (\Throwable $exception) {
            $this->error('The hero inventory could not be updated. Please try again.', 503);
            return;
        }

        foreach (HeroInventoryPayload::build($uid) as $field => $value) {
            $this->response[$field] = $value;
        }
    }
}
