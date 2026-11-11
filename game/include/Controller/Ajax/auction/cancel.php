<?php

namespace Controller\Ajax\auction;

use Controller\Ajax\AjaxBase;
use Controller\HeroAuctionCtrl;
use Game\Hero\HeroInventoryPayload;

/**
 * `POST auction/cancel` {id}: cancels one of the player's own sales in its first five minutes (the reference
 * answers `DELETE hero/auction` the same way) and returns the item to the inventory. An error answers with the
 * message the page shows as a toast.
 */
final class cancel extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error(T('inGame', 'bannedSmallPage'), 403);

            return;
        }
        $auctionId = HeroInventoryPayload::positiveInteger($_POST['id'] ?? null);
        if ($auctionId === null) {
            $this->error(T('Auction', 'AuctionNotFound'));

            return;
        }
        $_REQUEST['action'] = $_GET['action'] = 'sell';
        $error = (new HeroAuctionCtrl(true))->cancelListing($auctionId);
        if ($error !== null) {
            $this->error($error);
        }
    }
}
