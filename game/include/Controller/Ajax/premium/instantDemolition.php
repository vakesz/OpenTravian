<?php

declare(strict_types=1);

namespace Controller\Ajax\premium;

use Core\Config;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use Game\GoldHelper;
use resources\View\PHPBatchView;

/** Demolish now: tears down the building in a Main Building slot at once. Body {villageId, slotId}. */
final class instantDemolition extends InstantPurchase
{
    protected function action(): string
    {
        return 'instant-demolition';
    }

    protected function price(): int
    {
        return (int)Config::getInstance()->gold->completeDemolishGold;
    }

    protected function target(): array|string
    {
        $village = Village::getInstance();
        $slotId = $_POST['slotId'] ?? null;
        $villageId = $_POST['villageId'] ?? null;
        if (!is_scalar($slotId) || !ctype_digit((string)$slotId) || (int)$slotId < 1
            || ($villageId !== null && (!is_scalar($villageId) || (int)$villageId !== (int)$village->getKid()))) {
            return T('map', 'please_resend_all_data');
        }
        if ($village->isWW() && !getGame('allowDemolishNowInWW')) {
            return T('PaymentWizard', 'WWDisabled');
        }
        $slotId = (int)$slotId;
        if (!$village->demolishBuilding($slotId, true, true)) {
            return T('Buildings', 'mainBuilding.complete_demolish_title');
        }

        $field = $village->getField($slotId);

        return ['slotId' => $slotId, 'gid' => (int)$field['item_id'], 'level' => (int)$field['level']];
    }

    protected function dialogHtml(array $target): string
    {
        $view = new PHPBatchView('gold/demolishNowPopup');
        $view->vars['title'] = T('Buildings', 'mainBuilding.complete_demolish_title');
        $view->vars['goldCost'] = $this->price();

        return $view->output();
    }

    protected function perform(array $target): ?string
    {
        $failure = T('Buildings', 'mainBuilding.complete_demolish_title');
        $village = Village::getInstance();
        $uid = (int)Session::getInstance()->getPlayerId();
        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return $failure;
        }

        try {
            // The check and the demolition run inside the purchase transaction, so a concurrent
            // queue change cannot charge gold for a no-op.
            if (!$village->demolishBuilding($target['slotId'], true, true)
                || !$village->demolishBuilding($target['slotId'], true)) {
                throw new \DomainException($failure);
            }
            if (!GoldHelper::decreaseGold($uid, $this->price(), true)) {
                throw new \DomainException('notEnoughGold');
            }
            if (!$db->commit()) {
                throw new \DomainException($failure);
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }

            return $exception instanceof \DomainException ? $exception->getMessage() : $failure;
        }

        GoldHelper::syncSessionGold($uid);

        return null;
    }
}
