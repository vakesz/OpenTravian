<?php

namespace Controller\RallyPoint;

use Game\FarmList\FarmListPage;

class FarmList
{
    public function procContent(): string
    {
        return (new FarmListPage())->render();
    }
}
