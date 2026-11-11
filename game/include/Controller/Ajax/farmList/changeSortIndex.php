<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListStore;

/** PUT farm-list/<id>/change-sort-index: moves a list within its village. */
final class changeSortIndex extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        return ['changed' => $store->moveList($this->routeId(), $this->body()['newIndex'] ?? null)];
    }
}
