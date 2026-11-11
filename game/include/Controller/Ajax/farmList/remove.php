<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListStore;

/** DELETE farm-list/<id>: deletes a farm list; the lists of its village are renumbered. */
final class remove extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        return ['changed' => $store->deleteList($this->routeId()), 'deleted' => [$this->routeId()]];
    }
}
