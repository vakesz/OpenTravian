<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListStore;

/** PUT farm-list/<id>/change-sorting: stores a list's sort field and direction. */
final class changeSorting extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        return ['changed' => $store->setSorting($this->routeId(), $this->body()['sortField'] ?? null, $this->body()['sortDirection'] ?? null)];
    }
}
