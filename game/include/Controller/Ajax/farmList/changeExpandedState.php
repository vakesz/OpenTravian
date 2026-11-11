<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListStore;

/** PUT farm-list/change-expanded-state: expands or collapses lists. */
final class changeExpandedState extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        return ['changed' => $store->setExpanded((array)($this->body()['farmLists'] ?? []), $this->body()['isExpanded'] ?? null)];
    }
}
