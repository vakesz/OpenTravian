<?php

declare(strict_types=1);

// Contract: Travian.adjustButtonDisableState remembers a button's earlier disabled state as the string "true" or
// "false". jQuery 4 drops an attribute set to a boolean false, which forgot that state at the second call and left
// the NPC dialog's Redeem button disabled for good.

$client = (string)file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/game-client.js');
if (!str_contains($client, 't.attr("olddisabled", String(i)).attr("disabled", !0);')) {
    fwrite(STDERR, "adjustButtonDisableState must store olddisabled as a string.\n");
    exit(1);
}

echo "Button disable state contract passed.\n";
