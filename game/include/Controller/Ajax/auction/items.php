<?php

namespace Controller\Ajax\auction;

use Controller\Ajax\AjaxBase;
use Controller\HeroAuctionCtrl;

use function number_format_x;

/**
 * `GET auction/items`: the lists area of one auction page (`view` buy, sell or bids; `type` for an item's own
 * auctions) for the state in the query string, as JSON for auction-lists.js to put in place. It is the
 * controller's own render, so sorting, paging, layouts and "show more" have one implementation; the reference
 * answers the same refreshes with GraphQL.
 *
 * Response: `html` (the area's markup), `state` (the state the server rendered, to send back with the next
 * refresh) and `wallet` (gold and silver, raw and formatted, for the exchange widget).
 */
final class items extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error(T('inGame', 'bannedSmallPage'), 403);

            return;
        }
        $view = $_GET['view'] ?? null;
        if (!is_string($view) || !in_array($view, ['buy', 'sell', 'bids'], true)) {
            $this->error('Parameter "view" is invalid.');

            return;
        }
        $_REQUEST['action'] = $_GET['action'] = $view;
        $type = $view === 'buy' && is_scalar($_GET['type'] ?? null) ? (int)$_GET['type'] : 0;
        if ($type > 0) {
            $_REQUEST['auctionType'] = $type;
        }

        $controller = new HeroAuctionCtrl(true);
        $gold = (int)$this->session->getAvailableGold();
        $silver = (int)$this->session->getAvailableSilver();
        $this->response['html'] = $controller->returnContent();
        $this->response['state'] = $controller->state();
        $this->response['wallet'] = [
            'gold' => $gold,
            'silver' => $silver,
            'goldText' => number_format_x($gold),
            'silverText' => number_format_x($silver),
        ];
    }
}
