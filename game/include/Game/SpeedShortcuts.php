<?php

declare(strict_types=1);

namespace Game;

use Core\Config;

/**
 * The speed-world shortcuts: gold Upgrade to max level (instant, no resources), Train maximum, and Smithy gold max.
 * A world rule (`speedShortcuts`, off by default, suggested from x100) switches them on; with it off the pages show
 * nothing and every endpoint refuses. They have no counterpart on a standard world.
 */
final class SpeedShortcuts
{
    private function __construct()
    {
    }

    public static function enabled(): bool
    {
        return (bool)(Config::getInstance()->custom->speedShortcuts ?? false);
    }
}
