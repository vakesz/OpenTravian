<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListStore;

/** PUT farm-list/<id>: saves a farm list's name, village, default troops and options. */
final class update extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        return ['changed' => $store->updateList($this->routeId(), $this->body())];
    }
}
