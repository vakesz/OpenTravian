<?php

use Core\Helper\WebService;
use Model\AuctionModel;

class HeroAuctionCtrl
{
    public function __construct()
    {
        $result = false;
        if (!isServerFinished() && WebService::isPost()) {
            $int = static fn (string $key): int => is_string($_POST[$key] ?? null) ? abs((int)$_POST[$key]) : 0;
            [$btype, $type] = array_map('intval', array_pad(explode(':', is_string($_POST['item'] ?? null) ? $_POST['item'] : ''), 2, '0'));
            $package_amount = $int('package_amount');
            $repeat = min($int('repeat'), 100);
            $time = $int('time');
            $silver = $int('silver');
            if ($time > 60 && $time < 86400) {
                $m = new AuctionModel();
                for ($i = 1; $i <= $repeat; ++$i) {
                    $m->addAuction(false, 0, $btype, $type, $package_amount, time() + $time, $silver);
                    $result = true;
                }
            }
        }
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load(['result' => $result],
            'tpl/heroAuctionAdd.tpl')->getAsString());
    }
}