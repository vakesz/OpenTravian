<?php

declare(strict_types=1);

namespace Controller\Ajax\marketplace;

use Controller\Ajax\AjaxBase;
use Core\Session;
use Core\Village;
use Game\AllianceBonus\AllianceBonus;
use Game\Formulas;
use Model\MarketModel;

/** Refreshes marketplace capacity and movements after a resource shipment. */
final class reload extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to refresh the marketplace.', 403);
            return;
        }

        $village = Village::getInstance();
        $merchantCount = max($village->getTypeLevel(17));
        if ($merchantCount < 1) {
            $this->error('The selected village has no marketplace.', 404);
            return;
        }

        $market = new MarketModel();
        $merchantCapacity = $this->merchantCapacity($village);
        $kid = $village->getKid();
        $this->response['storage'] = $this->stock($village);
        $this->response['merchantsAvailable'] = max(
            0,
            $merchantCount
            - $market->getOfferingMerchantsCount($kid, $merchantCapacity)
            - $market->getOnTheWayMerchantsCount($kid, $merchantCapacity)
        );
        $this->response['deliveries'] = $market->getDeliveryGroups((int)$kid);
        $this->response['ships'] = \Game\Ships::homeShips((int)$kid)[\Game\Ships::TRADE];
    }

    private function merchantCapacity(Village $village): int
    {
        $session = Session::getInstance();
        $allianceBonus = $session->getAllianceId() > 0
            ? AllianceBonus::getTradersBonus($session->getAllianceId(), $session->getAllianceJoinTime())
            : 1;

        return max(1, (int)floor(Formulas::merchantCAP(
            $session->getRace(),
            max($village->getTypeLevel(28)),
            $allianceBonus
        )));
    }

    /** @return array{l1: int, l2: int, l3: int, l4: int} */
    private function stock(Village $village): array
    {
        $stock = $village->getCurrentResources();

        return [
            'l1' => (int)$stock[0],
            'l2' => (int)$stock[1],
            'l3' => (int)$stock[2],
            'l4' => (int)$stock[3],
        ];
    }

}
