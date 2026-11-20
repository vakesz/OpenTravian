<?php

declare(strict_types=1);

namespace Game;

use Core\Config;
use Core\Dev\RequestTiming;

/**
 * The speed-world shortcuts: gold Upgrade to max level (instant, no resources), Train maximum, and Smithy gold max.
 * A world rule (`speedShortcuts`, off by default, suggested from x100) switches them on; with it off the pages show
 * nothing and every endpoint refuses. They have no counterpart on a standard world.
 *
 * A local development stack (TRAVIAN_DEV_TOOLS=1, set by compose.dev.yaml) always has them, whatever the rule says,
 * so a local world, already installed or new, can be maxed out for gold without a reinstall.
 */
final class SpeedShortcuts
{
    private function __construct()
    {
    }

    public static function enabled(): bool
    {
        return RequestTiming::available() || (bool)(Config::getInstance()->custom->speedShortcuts ?? false);
    }
}
