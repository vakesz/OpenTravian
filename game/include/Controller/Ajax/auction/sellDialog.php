<?php

namespace Controller\Ajax\auction;

use Controller\Ajax\AjaxBase;
use Controller\HeroAuctionCtrl;
use Game\Hero\HeroInventoryPayload;

/**
 * `GET auction/sell-dialog?itemId=`: the content of the "Sell item" dialog of one inventory item (the reference
 * builds it from `itemTypeSellingInformation`). Response: `html`, `amounts` (the stack sizes that can be sold)
 * and `amount` (how many of the item the player owns).
 */
final class sellDialog extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error(T('inGame', 'bannedSmallPage'), 403);

            return;
        }
        $itemId = HeroInventoryPayload::positiveInteger($_GET['itemId'] ?? null);
        if ($itemId === null) {
            $this->error(T('Auction', 'AuctionNotFound'));

            return;
        }
        $_REQUEST['action'] = $_GET['action'] = 'sell';
        $dialog = (new HeroAuctionCtrl(true))->sellDialog($itemId);
        if ($dialog === null) {
            $this->error(T('Auction', 'AuctionNotFound'));

            return;
        }
        foreach ($dialog as $field => $value) {
            $this->response[$field] = $value;
        }
    }
}
