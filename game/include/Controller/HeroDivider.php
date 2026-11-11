<?php

namespace Controller;

class HeroDivider extends GameCtrl
{
    public function render(): void
    {
        if (!isset($_GET['t'])) {
            $t = 1;
        } else {
            $t = filter_var($_GET['t'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]) ?: 1;
        }
        $pages = [
            1 => 'Controller\\HeroInventoryCtrl',
            2 => 'Controller\\HeroFaceCtrl',
            3 => 'Controller\\HeroAdventureCtrl',
            4 => 'Controller\\HeroAuctionCtrl',
        ];
        if (!isset($pages[$t])) {
            $t = 1;
        }
        $controller = new $pages[$t]();
        echo $controller->render();
    }
}
