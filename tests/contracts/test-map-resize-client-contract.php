<?php

declare(strict_types=1);

$client = file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/game-client.js');

if (!is_string($client)) {
    fwrite(STDERR, "Unable to read the standard client bundle.\n");
    exit(1);
}

if (!str_contains($client, 'jQuery(window).on({ resize: this.positionChange.bind(this) })')) {
    fwrite(STDERR, "The full-resolution map must listen for browser resize events.\n");
    exit(1);
}

if (str_contains($client, 'jQuery("window").on({ resize: this.positionChange.bind(this) })')) {
    fwrite(STDERR, "The full-resolution map must not bind resize events to a literal window element.\n");
    exit(1);
}

echo "Map resize client contract passed.\n";
