<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListSender;
use Game\FarmList\FarmListStore;

/** POST farm-list/send: starts the raids of the given targets; each one starts or says why it did not. */
final class send extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        return (new FarmListSender())->send((array)($this->body()['lists'] ?? []));
    }
}
