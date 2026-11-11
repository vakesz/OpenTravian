<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListData;
use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListPage;
use Game\FarmList\FarmListStore;

/** GET farm-list: the page's data again; GET farm-list/<id>: one list. */
final class view extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        return $this->routeId() > 0 ? ['changed' => [$this->routeId()]] : ['viewData' => (new FarmListData())->viewData(), 'config' => (new FarmListPage())->bootstrap()];
    }
}
