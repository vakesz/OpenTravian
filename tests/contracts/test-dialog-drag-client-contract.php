<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$clientFiles = [
    $root . '/game/deployment/public/game-client.js',
];

foreach ($clientFiles as $clientFile) {
    $source = file_get_contents($clientFile);
    if (!is_string($source)) {
        fwrite(STDERR, "Unable to read {$clientFile}.\n");
        exit(1);
    }

    if (!str_contains($source, 'new Travian.Draggable(this.wrapper, {')
        || str_contains($source, 'new Travian.Moveable(this.wrapper, {')
        || !str_contains($source, 'onStop: function (t) {')
        || !str_contains($source, 'n = { x: i.left, y: i.top };')
        || !str_contains($source, 'this.options.onStop(this.element, e)')) {
        fwrite(STDERR, "Draggable dialogs must persist native positions after a drag.\n");
        exit(1);
    }

    if (!str_contains($source, 'document.removeEventListener("touchmove", this.bound.check)')
        || !str_contains($source, 'document.removeEventListener("touchmove", this.bound.drag)')) {
        fwrite(STDERR, "Draggable dialogs must detach touch handlers after a gesture.\n");
        exit(1);
    }
}

echo "Dialog drag client contract passed.\n";
