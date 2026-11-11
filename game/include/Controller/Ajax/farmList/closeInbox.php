<?php

declare(strict_types=1);

namespace Controller\Ajax\farmList;

use Core\Session;
use Game\FarmList\FarmListEndpoint;
use Game\FarmList\FarmListInbox;
use Game\FarmList\FarmListStore;

/** POST farm-list/close-inbox: dismisses the notice about targets that changed hands. */
final class closeInbox extends FarmListEndpoint
{
    protected function handle(FarmListStore $store): array
    {
        FarmListInbox::close(Session::getInstance()->getPlayerId());

        return ['changed' => []];
    }
}
