<?php

namespace Controller;

use Core\Caching\Caching;
use Core\Config;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\GoldHelper;
use resources\View\PHPBatchView;

class OnLoadBuildingsDorfCtrl extends AnyCtrl
{
    public function __construct()
    {
        $village = Village::getInstance();
        $sort = function ($a, $b) {
            return $a['commence'] == $b['commence'] ? 0 : ($a['commence'] > $b['commence'] ? 1 : -1);
        };
        uasort($village->onLoadBuildings['normal'], $sort);
        uasort($village->onLoadBuildings['master'], $sort);
        if ($village->onLoadBuildings['normal'] + $village->onLoadBuildings['master']) {
            $json = [];
            $tmp = [];
            $HTML = '';
            $firstFlag = TRUE;
            foreach ($village->onLoadBuildings['normal'] as $row) {
                if (!isset($tmp[$row['building_field']])) {
                    $tmp[$row['building_field']] = 0;
                }
                ++$tmp[$row['building_field']];
                $level = $village->getField($row['building_field'])['level'] + $tmp[$row['building_field']];
                $item_id = $village->getField($row['building_field'])['item_id'];
                if ($firstFlag) {
                    $HTML .= '<ul>';
                    $firstFlag = FALSE;
                }
                $HTML .= '<li>' . $this->cancelLink((int)$row['id'], (int)$item_id, (int)$level)
                    . '<div class="name"> ' . T("Buildings", $item_id . '.title') . ' <span class="lvl">' . T("Buildings", "level") . ' ' . $level . '</span></div>'
                    . '<div class="buildDuration">' . appendTimer(($row['commence'] - time())) . ' ' . T("Global", "General.hour") . '. '
                    . T("Global", "General.doneat") . ' ' . TimezoneHelper::date('H:i', $row['commence']) . ' </div>'
                    . '<div class="clear"></div></li>';
                $json[] = [
                    'stufe' => $level,
                    'gid' => $item_id,
                    'aid' => $row['building_field'],
                ];
            }
            if (!$firstFlag) {
                $HTML .= '</ul>';
            }
            $firstFlag = TRUE;
            $ti = TRUE;
            foreach ($village->onLoadBuildings['master'] as $row) {
                $row['commence']++;
                if (!isset($tmp[$row['building_field']])) {
                    $tmp[$row['building_field']] = 0;
                }
                ++$tmp[$row['building_field']];
                $level = $village->getField($row['building_field'])['level'] + $tmp[$row['building_field']];
                $item_id = $village->getField($row['building_field'])['item_id'];
                if ($firstFlag) {
                    $HTML .= '<h5>' . T("Buildings", "masterBuilder.masterBuilder") . ': <span>';
                    if (!$village->isWW()) {
                        $HTML .= "(" . ' <img src="/img/spacer.gif" alt="' . T("inGame", "gold") . '"
                        title="' . T("inGame",
                                "gold") . '" class="gold" /><span class="goldValue">' . Config::getInstance()->gold->masterBuilderGold . '</span> ' . T("Buildings",
                                "masterBuilder.atStartOfConstruction") . ')';
                    }
                    $HTML .= '</h5>';
                    $HTML .= '<ul>';
                    $firstFlag = FALSE;
                }
                $costs = Formulas::buildingUpgradeCosts($item_id, $level);
                $duration = '';
                if (sizeof($json) === 0 && $ti && ($village->isWW() || $village->getProduction(3) > 0 || $costs[3] <= $village->getCurrentResources(3))) {
                    $ti = FALSE;
                    $timeLeft = sprintf(T("Global", "General.startat") . ' %s',
                        appendTimer($row['commence'] - time()) . " ");
                    $duration = <<<HTML
     <div class="buildDuration">
                    {$timeLeft}
                </div>
HTML;
                }
                $HTML .= '<li>' . $this->cancelLink((int)$row['id'], (int)$item_id, (int)$level)
                    . '<div class="name inactive"> ' . T("Buildings", $item_id . '.title') . ' <span class="lvl">' . T("Buildings", "level") . ' ' . $level . '</span></div>'
                    . $duration . '<div class="clear"></div></li>';
            }
            if (!$firstFlag) {
                $HTML .= '</ul>';
            }
            $helper = new GoldHelper();
            $this->view = new PHPBatchView('resource-fields/buildingQueue');
            $this->view->vars = [
                'normal' => sizeof($village->onLoadBuildings['normal']),
                'buildings' => $HTML,
                'finishNowButton' => $helper->finishNowButton(),
                'buildsJson' => json_encode($json),
                'cancelUrl' => '/' . $this->villagePage() . '?cancel=\' + eventId + \'&action=build&checksum=' . Session::getInstance()->getChecker(),
            ];
        }
    }

    /** The village page the request came from: the queue box shows on both, and cancelling stays on it. */
    private function villagePage(): string
    {
        $requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');

        return str_contains($requestPath, 'dorf1') ? 'dorf1.php' : 'dorf2.php';
    }

    /**
     * The cancel control of one queued order: it opens the abort dialog, which names the order and the refund it
     * gives back (Village::cancelRefund) and only then sends the cancel request.
     */
    private function cancelLink(int $eventId, int $itemId, int $level): string
    {
        $name = (string)T('Buildings', $itemId . '.title');
        $refund = '<div class="inlineIconList resourceWrapper">';
        $cancelRefund = Village::cancelRefund($itemId, $level);
        foreach ($cancelRefund['amounts'] as $i => $amount) {
            $refund .= '<div class="inlineIcon resources" title=""><i class="r' . ($i + 1) . '"></i><span class="value ">' . $amount . '</span></div>';
        }
        $refund .= '</div>';
        $percent = "\u{202D}\u{202D}" . $cancelRefund['percent'] . "\u{202C}%\u{202C}";
        $message = sprintf(T('Buildings', 'abortConstruction.message'), $name, $level, $percent)
            . $refund . '<br /><span class="alert">' . T('Buildings', 'abortConstruction.warning') . '</span>';
        $title = sprintf(T('Buildings', 'abortConstruction.title'), $name, $level);

        return '<a onclick="' . htmlspecialchars('showCancelBuildingDialog(' . $eventId . ', ' . self::jsString($title) . ', ' . self::jsString($message) . ')', ENT_QUOTES)
            . '"><img src="/img/spacer.gif" class="del" alt="' . T('Global', 'General.cancel') . '"></a>';
    }

    /** A single-quoted JavaScript string, newlines written as \n like the reference's inline calls. */
    private static function jsString(string $text): string
    {
        return "'" . str_replace(["\\", "'", "\r\n", "\n"], ["\\\\", "\\'", "\\n", "\\n"], $text) . "'";
    }
}
