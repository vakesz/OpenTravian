<?php

namespace Controller\Ajax\autocomplete;

/**
 * Village autocomplete shares the scoped search implementation with player
 * autocomplete, but its client endpoint does not send a context marker.
 */
class villagename extends playername
{
    public function dispatch(): void
    {
        $_POST['context'] = 'villagename';
        parent::dispatch();
    }
}
