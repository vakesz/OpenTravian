<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListStore;

/** POST farm-list: creates a farm list. */
final class create extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        $changed = $store->createList($this->body());

        return ['changed' => $changed, 'id' => $changed[0]];
    }
}
