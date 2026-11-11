<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListStore;

/** POST, PUT and DELETE farm-list/slot: adds, changes or deletes targets (`?force` overwrites an entry that exists). */
final class slot extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        $slots = (array)($this->body()['slots'] ?? []);
        if ($this->method() === 'DELETE') {
            return ['changed' => $store->deleteSlots(array_column($slots, 'id') ?: $slots)];
        }

        return ['changed' => $store->saveSlots($slots, $this->method() === 'PUT', isset($_GET['force']))];
    }
}
