<?php

declare(strict_types=1);

namespace Game;

use Core\Session;
use Core\Village;

/**
 * The reference's one build-action request, shared by dorf1.php and dorf2.php:
 *
 *   /dorf1.php?id=<slot>&gid=<gid>&action=build&checksum=<session token>[&buildmaster]
 *
 * A slot that already holds `gid` is upgraded, an empty slot gets `gid` constructed. The
 * checksum is the session token; `gid` guards against replaying a token on another building.
 * `buildmaster` is the gold master-builder variant.
 */
final class BuildRequest
{
    /** True when the request is a build action (it has been handled, valid or not). */
    public static function handle(Village $village): bool
    {
        if (($_GET['action'] ?? null) !== 'build') {
            return false;
        }
        $checksum = $_GET['checksum'] ?? null;
        if (!Session::claimAction($checksum)) {
            return true;
        }
        if (isset($_GET['cancel'])) {
            $village->removeBuilding((int)$_GET['cancel']);

            return true;
        }
        $slot = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $gid = filter_var($_GET['gid'] ?? null, FILTER_VALIDATE_INT);
        if ($slot === false || $gid === false || $slot < 1 || ($slot > 40 && $slot !== 99)) {
            return true;
        }
        $master = array_key_exists('buildmaster', $_GET);
        $field = $village->getField($slot);
        if (is_array($field) && (int)$field['item_id'] === $gid && $gid > 0) {
            // Gold "Upgrade to max level" is ExtraModules::upgradeToMaxLevel (instant); &max is not a build path.
            $village->upgradeBuilding($slot, $master);
        } elseif (is_array($field) && (int)$field['item_id'] === 0 && $gid > 4) {
            $village->constructBuilding($slot, $gid, $master);
        }

        return true;
    }
}
