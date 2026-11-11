<?php

namespace Controller\Ajax\tooltip;

use Controller\Ajax\AjaxBase;
use Core\Session;
use Model\AuctionModel;

class freeSilver extends AjaxBase
{
    public function dispatch(): void
    {
        if (!Session::getInstance()->isValid()) {
            return;
        }
        $session = Session::getInstance();
        $bound = (int) (new AuctionModel())->getInBidSilver($session->getPlayerId());
        $total = (int) $session->getSilver();
        $free = $total - $bound;
        $this->response['title'] = T('HeroGlobal', 'Free silver');
        $this->response['text'] = '&#x202d;&#x202d;' . $free . '&#x202c;/&#x202d;' . $total . '&#x202c;&#x202c;<br />'
            . sprintf(T('HeroGlobal', 'silverBoundInAuctions'), $bound);
        $this->response['success'] = true;
    }
}
