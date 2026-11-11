<?php

namespace Controller\Ajax\hero;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Game\Hero\HeroInventoryPayload;
use Model\HeroInventoryModel;

/**
 * hero/v2/inventory/use-item: {action, itemId, amount, villageId}.
 * Responds with the amount that was really applied; the hero resource stacks
 * (items 145-148) are capped by the free room of the village storage.
 *
 * Guards this call with a PUT-then-POST nonce handshake. The local
 * dispatcher runs any HTTP method, so a handshake would execute twice; the
 * session's ajax bearer token already authenticates the single request.
 */
class useItem extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error('You cannot change hero items from this session.', 403);
            return;
        }

        $itemId = HeroInventoryPayload::positiveInteger($_POST['itemId'] ?? null);
        $amount = HeroInventoryPayload::positiveInteger($_POST['amount'] ?? null);
        $villageId = HeroInventoryPayload::positiveInteger($_POST['villageId'] ?? null);
        if (($_POST['action'] ?? '') !== 'inventory' || $itemId === null || $amount === null || $villageId === null) {
            $this->error('Invalid inventory request.');
            return;
        }

        $uid = (int)$this->session->getPlayerId();
        $ownedVillage = DB::getInstance()->run('SELECT kid FROM vdata WHERE kid=? AND owner=?', [$villageId, $uid]);
        if (!$ownedVillage || !$ownedVillage->get_result()->fetch_assoc()) {
            $this->error('The selected village is not yours.', 403);
            return;
        }

        try {
            $applied = (new HeroInventoryModel())->apply($uid, $villageId, $itemId, 'bag', $amount, true);
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());
            return;
        } catch (\Throwable $exception) {
            $this->error('The hero inventory could not be updated. Please try again.', 503);
            return;
        }

        $this->response['amount'] = $applied;
        foreach (HeroInventoryPayload::build($uid) as $field => $value) {
            $this->response[$field] = $value;
        }
    }
}
