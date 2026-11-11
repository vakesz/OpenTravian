<?php
namespace Controller\Ajax;
use Core\Session;
use Controller\Ajax\AjaxBase;

class favouriteTab extends AjaxBase
{
    /** Named tab keys per nested favourite; the list position is the stored value. */
    public const NESTED_KEYS = [
        'villageStatisticsResources' => ['resources', 'warehouse', 'production', 'capacity'],
        'villageOverviewTroops' => ['own', 'support', 'smithy', 'hospital', 'training'],
        'auctions' => ['buy', 'sell', 'bids'],
    ];

    /** The report strip posts route keys while the local preference column stores its fixed tab slot. */
    public const KEYED_TABS = [
        'allianceOverview' => ['internalProfile' => 0, 'top10' => 1],
        'allyPageProfile' => ['description' => 0, 'members' => 1],
        'embassyBuildingSubTabs' => ['info' => 0, 'villages_nearby' => 1, 'join' => 2, 'leave' => 3],
        'buildingExpansion' => ['0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4],
        'buildingRallyPoint' => [
            '0' => 0,
            '1' => 1,
            '2' => 2,
            '3' => 3,
            '99' => 99,
        ],
        'village/statistics' => [
            'overview' => 0,
            'resources' => 2,
            'culturepoints' => 4,
            'troops' => 5,
        ],
        'report' => ['overview', 'offensive', 'defensive', 'scouting', 'other', 'archive', 'surrounding'],
        'alliance' => [
            'overview' => 7,
            'profile' => 1,
            'reports' => 3,
            'bonuses' => 8,
            'forum' => 2,
            'options' => 5,
        ],
        'messages' => ['inbox', 'write', 'sent', 'archive', 'notes', 'ignore'],
        'statistics' => [
            'player' => 0,
            'alliance' => 1,
            'village' => 2,
            'hero' => 3,
            'general' => 5,
            'wonderoftheworld' => 6,
            'bonus' => 7,
            'farm' => 8,
        ],
        'statisticsTablePlayer' => ['overview', 'attackers', 'defenders', 'top10'],
        'statisticsTableAlly' => ['overview', 'attackers', 'defenders', 'top10'],
    ];

    /** Request names whose preference is stored under the legacy fixed slot name. */
    public const KEYED_TAB_STORAGE_NAMES = [
        'report' => 'reports',
        'village/statistics' => 'villageOverview',
        'allianceOverview' => 'allyPageOverview',
    ];

    /**
     * @return bool|null
     */
    public function dispatch()
    {
        // keyed tab rows send a named `key` instead of a numeric `number`.
        if (isset($_POST['name'], $_POST['key']) && is_scalar($_POST['name']) && is_scalar($_POST['key'])) {
            $requestName = (string)$_POST['name'];
            $keys = self::NESTED_KEYS[$requestName] ?? self::KEYED_TABS[$requestName] ?? null;
            if ($keys === null) {
                // Fall through to the standard numeric favourite validation below.
            } else {
                $key = (string)$_POST['key'];
                $position = array_key_exists($key, $keys) ? $keys[$key] : array_search($key, $keys, true);
                if ($position === false) {
                    $this->response['error'] = TRUE;
                    $this->response['message'] = "Tab number is not valid!";
                    return FALSE;
                }
                $storageName = self::KEYED_TAB_STORAGE_NAMES[$requestName] ?? $requestName;
                $session = Session::getInstance();
                if ($session->getFavoriteTab($storageName) === FALSE) {
                    $this->response['error'] = TRUE;
                    $this->response['message'] = "Tab name is not valid!";
                    return FALSE;
                }
                if ($requestName === 'buildingRallyPoint' && $position === 99 && !$session->hasGoldClub()) {
                    $this->response['error'] = TRUE;
                    $this->response['message'] = "Tab number is not valid!";
                    return FALSE;
                }
                $session->setFavoriteTab($storageName, $position);
                $this->response['result'] = FALSE;
                $this->response['success'] = TRUE;
                return TRUE;
            }
        }
		if(!isset($_POST['name']) || !isset($_POST['number']) || !is_scalar($_POST['name']) || !is_scalar($_POST['number'])){
            $this->response['error'] = TRUE;
			$this->response['message'] = T('map', 'please_resend_all_data');
            return false;
		}
		
        $name = (string)$_POST['name'];
        $numberStr = (string)$_POST['number'];
        $number = ctype_digit($numberStr) ? (int)$numberStr : -1;
        $session = Session::getInstance();
        $x =& $this->response;
        if($session->getFavoriteTab($name) === FALSE) {
            $x['error'] = TRUE;
            $x['message'] = "Tab name is not valid!";
            return FALSE;
        }
        switch($name) {
            case "buildingRallyPoint":
                if(in_array($number, [0, 1, 2, 3, 99])) {
                    if(!$session->hasGoldClub() && $number == 99) {
                        $x['error'] = TRUE;
                        $x['message'] = "Tab number is not valid!";
                        return FALSE;
                    }
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                } else {
                    $x['error'] = TRUE;
                    $x['message'] = "Tab number is not valid!";
                    return FALSE;
                }
            case 'reports':
                if(in_array($number, [0, 1, 2, 3, 4, 5, 6, 7])) {
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                } else {
                    $x['error'] = TRUE;
                    $x['message'] = "Tab number is not valid!";
                    return FALSE;
                }
            case 'alliance':
                if(in_array($number, [7, 8, 1, 3, 2, 5])) {
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                } else {
                    $x['error'] = TRUE;
                    $x['message'] = "Tab number is not valid!";
                    return FALSE;
                }
            case 'buildingTreasury':
                if(in_array($number, [0, 5, 1, 2])) {
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                } else {
                    $x['error'] = TRUE;
                    $x['message'] = "Tab number is not valid!";
                    return FALSE;
                }
            case 'auctions':
                if (in_array($number, [0, 1, 2], true)) {
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                }
                $x['error'] = TRUE;
                $x['message'] = "Tab number is not valid!";
                return FALSE;
            case "buildingMarket":
                if(in_array($number, [0, 5, 1, 2])) {
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                } else {
                    $x['error'] = TRUE;
                    $x['message'] = "Tab number is not valid!";
                    return FALSE;
                }
            case 'hero':
                if(in_array($number, [1, 2, 3, 4])) {
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                } else {
                    $x['error'] = TRUE;
                    $x['message'] = "Tab number is not valid!";
                    return FALSE;
                }
            case 'statistics':
                if(in_array($number, [0, 1, 2, 3, 5, 6, 7, 8])) {
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                } else {
                    $x['error'] = TRUE;
                    $x['message'] = "Tab number is not valid!";
                    return FALSE;
                }
            case 'statisticsTablePlayer':
            case 'statisticsTableAlly':
                if(in_array($number, [0, 1, 2, 3])) {
                    $x['result'] = FALSE;
                    $x['success'] = TRUE;
                    $session->setFavoriteTab($name, $number);
                    return TRUE;
                } else {
                    $x['error'] = TRUE;
                    $x['message'] = "Tab number is not valid!";
                    return FALSE;
                }
        }
        return false;
    }
}
