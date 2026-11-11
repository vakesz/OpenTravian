<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use resources\View\PHPBatchView;
use function appendTimer;

/** The "Your hero is on its way to an adventure" block, shown after the start and again whenever the page is loaded meanwhile. */
final class AdventureStarted
{
    private function __construct()
    {
    }

    /** @param int $arrival unix seconds the hero reaches the adventure */
    public static function html(int $heroKid, int $adventureKid, int $arrival): string
    {
        $wdata = DB::getInstance()->query(
            'SELECT w.fieldtype, w.oasistype, w.landscape, w.occupied, u.race AS villageRace '
            . 'FROM wdata w '
            . 'LEFT JOIN vdata v ON v.kid=w.id AND w.fieldtype>0 '
            . 'LEFT JOIN users u ON u.id=v.owner '
            . 'WHERE w.id=' . $adventureKid
        )->fetch_assoc() ?: [];
        $villageRace = isset($wdata['villageRace']) ? (int) $wdata['villageRace'] : null;

        return (string) PHPBatchView::render('hero/AdventureStarted', [
            'place' => AdventurePlace::listImageClass(AdventurePlace::fromWorldData($wdata, $villageRace)),
            'timer' => appendTimer(max(0, $arrival - time())),
            'arrivalAt' => TimezoneHelper::date('H:i', $arrival),
            'rallyPointUrl' => '/build.php?gid=16&tt=1&newdid=' . $heroKid,
        ]);
    }
}
