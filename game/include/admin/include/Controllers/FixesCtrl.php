<?php

use Core\Database\DBI;

class FixesCtrl
{
    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        $content = null;
        $fixKid = is_string($_GET['fixKid'] ?? null) && ctype_digit($_GET['fixKid']) ? (int)$_GET['fixKid'] : 0;
        if ($fixKid > 0) {
            $db = DBI::getInstance();
            $village = $db->fetchSingleRow('SELECT * FROM fdata WHERE kid=?', $fixKid);
            if ($village) {
                for ($i = 19; $i <= 40; ++$i) {
                    if ($village['f' . $i . 't'] > 0 && $village['f' . $i] == 0
                        && (int)$db->fetchScalar('SELECT COUNT(id) FROM building_upgrade WHERE kid=? AND building_field=?', [$fixKid, $i]) === 0) {
                        $db->run("UPDATE fdata SET f{$i}t=0 WHERE kid=?", $fixKid);
                    }
                }
                $content .= \Game\AdminUi::notice('Village fixed.', 'success');
            }
        }

        if (is_string($_REQUEST['fix'] ?? null)) {
            switch ($_REQUEST['fix']) {
                case 'storages':
                    require_once INCLUDE_PATH . "fixes/fixStorages.php";
                    $content .= \Game\AdminUi::notice('Storages were updated.', 'success');
                    break;
                case 'cpPop':
                    require_once INCLUDE_PATH . "fixes/fixPopCp.php";
                    $content .= \Game\AdminUi::notice('Population and culture points were recalculated.', 'success');
                    break;
                case 'heroItemHealth':
                    require_once INCLUDE_PATH . "fixes/fixHeroItemHealth.php";
                    $content .= \Game\AdminUi::notice('Hero item health is now accurate.', 'success');
                    break;
                case 'heroPoints':
                    require_once INCLUDE_PATH . "fixes/fixHeroPoints.php";
                    $content .= \Game\AdminUi::notice('Hero points is now accurate.', 'success');
                    break;
                case 'resources':
                    require_once INCLUDE_PATH . "fixes/fixAllResources.php";
                    $content .= \Game\AdminUi::notice('All resources updated.', 'success');
                    break;
                case 'crop_percents':
                    require_once INCLUDE_PATH . "fixes/fixCropPercents.php";
                    $content .= \Game\AdminUi::notice('Crop percents updated.', 'success');
                    break;
                case 'fix_upkeeps':
                    require_once INCLUDE_PATH . "fixes/fixUpkeeps.php";
                    $content .= \Game\AdminUi::notice('Upkeeps were updated.', 'success');
                    break;
                case 'fix_buildings':
                    require_once INCLUDE_PATH . "fixes/fixBuildings.php";
                    $content .= \Game\AdminUi::notice('Buildings fixed.', 'success');
                    break;
                case 'inventory_places':
                    require_once INCLUDE_PATH . "fixes/fixInventoryPlaceIds.php";
                    $content .= \Game\AdminUi::notice('Inventory place ids were fixed.', 'success');
                    break;
            }
        }
        $rows = [
            ['storages', 'Fix storages', 'Recalculate warehouse and granary capacity for every village.'],
            ['fix_upkeeps', 'Fix upkeeps', 'Recalculate the crop upkeep of every village.'],
            ['cpPop', 'Fix culture points and population', 'Recalculate population and culture points.'],
            ['heroItemHealth', 'Fix hero item health', 'Repair hero item durability values.'],
            ['heroPoints', 'Fix hero points (negative)', 'Reset negative hero attribute points.'],
            ['inventory_places', 'Fix inventory places', 'Re-number hero inventory slots.'],
            ['resources', 'Fix productions', 'Recalculate resource production for every village.'],
            ['crop_percents', 'Fix crop percents', 'Recalculate crop production bonuses.'],
            ['fix_buildings', 'Fix level 0 buildings', 'Clear building slots stuck on level 0.'],
        ];
        $table = '<table class="adminTable"><thead><tr><th>Repair</th><th>What it does</th><th></th></tr></thead><tbody>';
        foreach ($rows as [$fix, $label, $description]) {
            $table .= '<tr><td>' . \Game\AdminUi::esc($label) . '</td><td>' . \Game\AdminUi::esc($description) . '</td><td class="center">'
                . \Game\AdminUi::button('Run', 'grey', ['href' => '?action=fixes&fix=' . $fix, 'aria-label' => 'Run: ' . $label])
                . '</td></tr>';
        }
        $table .= '</tbody></table>';
        $dispatcher->appendContent(\Game\AdminUi::page(
            'Fixes',
            'Repairs for world data when a village shows wrong numbers. Each repair asks for confirmation first.',
            (string) $content . \Game\AdminUi::box('Repairs', $table)
        ));
    }
}