<?php

namespace Controller\Ajax\auction;

use Controller\Ajax\AjaxBase;
use Controller\HeroAuctionCtrl;
use Game\Hero\HeroInventoryPayload;

/**
 * `POST auction/sell-item` {id, amount}: puts an inventory item (or a stack of it) up for auction, like the
 * reference `hero/auction/sell-item`. An error answers with the message the page shows as a toast.
 */
final class sellItem extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error(T('inGame', 'bannedSmallPage'), 403);

            return;
        }
        $itemId = HeroInventoryPayload::positiveInteger($_POST['id'] ?? null);
        $amount = HeroInventoryPayload::positiveInteger($_POST['amount'] ?? null);
        if ($itemId === null || $amount === null) {
            $this->error(T('Auction', 'AuctionNotFound'));

            return;
        }
        $_REQUEST['action'] = $_GET['action'] = 'sell';
        $error = (new HeroAuctionCtrl(true))->sellItem($itemId, $amount);
        if ($error !== null) {
            $this->error($error);
        }
    }
}
