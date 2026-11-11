<?php

namespace Controller\RallyPoint;

use Controller\BuildCtrl;
use Core\Config;
use Core\Database\DB;
use Core\ErrorHandler;
use Core\Helper\Notification;
use Core\Helper\TimezoneHelper;
use Core\Log;
use Core\Session;
use Core\Village;
use Game\CatapultTargets;
use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\NoticeHelper;
use Game\SpeedCalculator;
use Model\ArtefactsModel;
use Model\ReportsModel;
use Model\InfoBoxModel;
use Model\MovementsModel;
use Model\Quest;
use Model\RallyPoint\RallyPointModel;
use Model\Units;
use Model\VillageModel;
use resources\View\PHPBatchView;

use function array_key_exists;
use function get_random_string;
use function getCustom;
use function miliseconds;

use const MYSQLI_STORE_RESULT;

class SendTroops extends RallyPointHTML
{
    /** @var array<mixed> */
    private $result = [
        "noProcess"   => true,
        "prepare"     => false,
        "beforeError" => false,
        "error"       => false,
        "errorMsg"    => null,
        "settings"    => [
            "redeployHero" => false,
            "units"        => [1 => 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            "to"           => [
                "x"           => '',
                "y"           => '',
                "z"           => '',
                "dname"       => '',
                'kid'         => '',
                "playerName"  => '',
                "uid"         => "",
                "villageName" => "",
            ],
            "attack_type"  => 2,
        ],
        "ui"          => ["attack_type" => '', "showHeroChangeHomeCheckBox" => false,],
    ];
    /** @var \Controller\BuildCtrl|null */
    private $build;

    /**
     * @return mixed
     */
    public function procContent(BuildCtrl $build)
    {
        $this->build = $build;
        // Map links name the target tile as `targetMapId`; the older `z` stays valid.
        if (isset($_GET['targetMapId']) && !isset($_GET['z'])) {
            $_GET['z'] = $_GET['targetMapId'];
        }
        if (isset($_GET['bid']) && Session::getInstance()->hasPlus()) {
            $reportId = (int)$_GET['bid'];
            $rpt = new ReportsModel();
            $report = $rpt->getReport($reportId);
            if ($report['uid'] == Session::getInstance()->getPlayerId()) {
                switch ($report['type']) {
                    case NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES:
                    case NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES:
                    case NoticeHelper::TYPE_LOST_AS_ATTACKER:
                    case NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES:
                    case NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES:
                    case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES:
                    case NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES:
                    case NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES:
                    case NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES:
                    case NoticeHelper::TYPE_LOST_AS_SPY:
                    case NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY:
                    case NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY:
                        $_GET['z'] = $report['to_kid'];
                        $data = NoticeHelper::parseReport($report['type'], $report['data'])['attacker']['num'];
                        $row = (new RallyPointModel())->getUnits(Village::getInstance()->getKid());
                        $_REQUEST['troop'] = [];
                        for ($i = 1; $i <= 11; ++$i) {
                            $count = (int)$data[$i];
                            if ($count > $row['u' . $i]) {
                                $count = (int)$row['u' . $i];
                            }
                            $_REQUEST['troop']['t' . $i] = $count;
                        }
                        $this->result['settings']['attack_type'] = 4;
                        $_REQUEST['eventType'] = 4;
                        break;
                }
            }
        }
        $this->getAvailableAttackTypes();
        $this->isHeroDeployAble();
        if (isset($_GET['z']) && is_numeric($_GET['z'])) {
            $this->result['settings']['to']['z'] = (int)$_GET['z'];
            $xy = Formulas::kid2xy($this->result['settings']['to']['z']);
            $this->result['settings']['to']['x'] = $xy['x'];
            $this->result['settings']['to']['y'] = $xy['y'];
            $this->build->view->newVillagePrefix['z'] = (int)$_GET['z'];
            $this->getAvailableAttackTypes();
        }
        if (array_key_exists('x', $_REQUEST) && array_key_exists('y', $_REQUEST)) {
            $this->result['settings']['to']['x'] = Formulas::coordinateFixer($_REQUEST['x']);
            $this->result['settings']['to']['y'] = Formulas::coordinateFixer($_REQUEST['y']);
            $this->result['settings']['to']['kid'] = Formulas::xy2kid(
                (int)$this->result['settings']['to']['x'],
                (int)$this->result['settings']['to']['y']
            );
            $this->build->view->newVillagePrefix['x'] = Formulas::coordinateFixer($_REQUEST['x']);
            $this->build->view->newVillagePrefix['y'] = Formulas::coordinateFixer($_REQUEST['y']);
            $this->getAvailableAttackTypes();
        }
        $villageName = $_REQUEST['villagename'] ?? $_REQUEST['dname'] ?? null;
        if (isset($villageName)) {
            $this->result['settings']['to']['dname'] = is_string($villageName)
                ? strip_tags($villageName)
                : '';
        }
        if (isset($_REQUEST['redeployHero']) && $this->result['ui']['showHeroChangeHomeCheckBox']) {
            $this->result['settings']['redeployHero'] = true;
        }
        $postedAttackType = $this->readPostedAttackType();
        if ($postedAttackType !== null) {
            $this->result['settings']['attack_type'] = $postedAttackType;
        }
        $a2b_find_fail = isset($_REQUEST['troopsSent']);

        if (isset($_POST['timestamp']) && isset($_POST['timestamp_checksum'])) {
            $this->result['settings']['timestamp'] = (int)$_POST['timestamp'];
            $this->result['settings']['timestamp_checksum'] = is_string($_POST['timestamp_checksum'])
                ? strip_tags($_POST['timestamp_checksum'])
                : '';

            if (!(isset($_REQUEST['edit']) && $_REQUEST['edit'] == 'edit')) {
                $a2b = $this->getA2b(
                    $this->result['settings']['timestamp'],
                    $this->result['settings']['timestamp_checksum']
                );
                if ($a2b->num_rows) {
                    return $this->prepare($a2b->fetch_assoc());
                }
            } else {
                $this->removeA2b(
                    $this->result['settings']['timestamp'],
                    $this->result['settings']['timestamp_checksum']
                );
            }
        } else {
            $timestamp_params = $this->generateTimestampParams();
            $this->result['settings']['timestamp'] = $timestamp_params['timestamp'];
            $this->result['settings']['timestamp_checksum'] = $timestamp_params['checksum'];
        }
        $m = new RallyPointModel();
        $row = $m->getUnits(Village::getInstance()->getKid());
        $units = [];
        $postedTroops = $this->readPostedTroopCounts();
        for ($i = 1; $i <= 11; ++$i) {
            $units[$i] = $postedTroops[$i];
            if ($units[$i] > $row['u' . $i]) {
                $units[$i] = $row['u' . $i];
            }
        }
        $this->result['settings']['units'] = $units;
        if (strtolower($_SERVER['REQUEST_METHOD']) != 'post' || $a2b_find_fail || (isset($_REQUEST['edit']) && $_REQUEST['edit'] == 'edit')) {
            return $this->procSendTroopsContent();
        }
        if (!$this->checkCoordniatesAndDname()) {
            goto show;
        }
        if (!$this->checkNatarsCapital()) {
            goto show;
        }
        if (!$this->checkFarmsAttackable()) {
            goto show;
        }
        if (!$this->checkTroopsSelected()) {
            goto show;
        }
        if (!$this->isHeroDeployAble(true)) {
            goto show;
        }
        if (!$this->checkAttackType()) {
            goto show;
        }
        if (!$this->checkAccesses()) {
            goto show;
        }
        return $this->prepare();
        show:
        return $this->procSendTroopsContent();
    }

    /**
     * Map eventType (5=reinforce, 3=normal, 4=raid) to internal
     * attack_type (2/3/4). Type 1 remains an internal spy/sport only value.
     */
    private function readPostedAttackType(): ?int
    {
        if (!isset($_REQUEST['eventType'])) {
            return null;
        }
        return match ((int)$_REQUEST['eventType']) {
            5 => 2,
            3 => 3,
            4 => 4,
            default => null,
        };
    }

    /** @return array<int, int> */
    private function readPostedTroopCounts(): array
    {
        $troopBag = $_REQUEST['troop'] ?? null;
        $units = [];
        for ($i = 1; $i <= 11; ++$i) {
            $raw = is_array($troopBag) ? ($troopBag['t' . $i] ?? 0) : 0;
            $units[$i] = abs((int)$raw);
        }
        return $units;
    }

    /** Internal attack_type → eventType for form posts. */
    public static function attackTypeToEventType(int $attackType): int
    {
        return match ($attackType) {
            2 => 5,
            3 => 3,
            4 => 4,
            default => 5,
        };
    }

    public function getAvailableAttackTypes(): void
    {
        $attackTypes = [
            1 => ["disabled" => false, "checked" => false],//reinforcement
            2 => ["disabled" => false, "checked" => true],//reinforcement
            3 => ["disabled" => false, "checked" => false],//normal attacks
            4 => ["disabled" => false, "checked" => false],//raid attacks
        ];
        if (isset($this->result['settings']['to']['z']) && $this->result['settings']['to']['z'] > 0) {
            $kid = $this->result['settings']['to']['z'];

        } elseif (isset($this->result['settings']['to']['kid']) && $this->result['settings']['to']['kid'] > 0) {
            $kid = $this->result['settings']['to']['kid'];
        } else {
            goto end;
        }
        if ($this->isOasis($kid)) {
            if ($this->getOwner($kid, true) == 0) {
                $attackTypes = [
                    1 => ["disabled" => true, "checked" => false], //reinforcement
                    2 => ["disabled" => true, "checked" => false], //reinforcement
                    3 => ["disabled" => true, "checked" => false], //normal attacks
                    4 => ["disabled" => false, "checked" => true],//raid attacks
                ];
            }
        } else {
            $owner = $this->getOwner($kid, false);
            if ($owner == 1) {
                $attackTypes = [
                    1 => ["disabled" => false, "checked" => false], //reinforcement
                    2 => ["disabled" => true, "checked" => false], //reinforcement
                    3 => ["disabled" => false, "checked" => false], //normal attacks
                    4 => ["disabled" => false, "checked" => true],//raid attacks
                ];
            }
        }
        end:
        if (isset($this->result['settings']['attack_type']) && $this->result['settings']['attack_type'] >= 2 && $this->result['settings']['attack_type'] <= 4) {
            if (!$attackTypes[$this->result['settings']['attack_type']]['disabled']) {
                $attackTypes[2]['checked'] = false;
                $attackTypes[3]['checked'] = false;
                $attackTypes[4]['checked'] = false;
                $attackTypes[$this->result['settings']['attack_type']]['checked'] = true;
            }
        }
        $requestAttackType = $this->readPostedAttackType();
        if ($requestAttackType !== null && isset($attackTypes[$requestAttackType]) && !$attackTypes[$requestAttackType]['disabled']) {
            foreach ($attackTypes as $k => $v) {
                $attackTypes[$k]['checked'] = false;
            }
            $attackTypes[$requestAttackType]['checked'] = true;
        }
        if (isset($attackTypes[4]) && !Session::getInstance()->checkSitterPermission(Session::SITTER_CAN_RAID)) {
            $attackTypes[4]['disabled'] = true;
            $attackTypes[4]['access'] = false;
        }
        if (isset($attackTypes[2]) && !Session::getInstance()->checkSitterPermission(Session::SITTER_CAN_SEND_REINFORCEMENT)) {
            $attackTypes[2]['disabled'] = true;
            $attackTypes[2]['access'] = false;
        }
        if (isset($attackTypes[3]) && Session::getInstance()->isSitter()) {
            $attackTypes[3]['disabled'] = true;
            $attackTypes[3]['access'] = false;
        }
        $this->result['ui']['attack_types'] = $attackTypes;
    }

    /**
     * @param mixed $kid
     */
    public function isOasis($kid): bool
    {
        $db = DB::getInstance();
        $isOasis = $db->fetchScalar("SELECT oasistype FROM wdata WHERE id={$kid}") > 0;
        return $isOasis;
    }

    /**
     * @param mixed $kid
     * @param mixed $isOasis
     * @return mixed
     */
    public function getOwner($kid, $isOasis)
    {
        $db = DB::getInstance();
        if ($isOasis) {
            return $db->fetchScalar("SELECT owner FROM odata WHERE kid={$kid}");
        }
        return $db->fetchScalar("SELECT owner FROM vdata WHERE kid={$kid}");
    }

    /**
     * @param bool $check
     */
    private function isHeroDeployAble($check = false): bool
    {
        $session = Session::getInstance();
        if ($check) {
            if ($this->result['settings']['attack_type'] <> 2) {
                $this->result['settings']['redeployHero'] = false;
                return true;
            }
            if (!$this->result['ui']['showHeroChangeHomeCheckBox']) {
                $this->result['settings']['redeployHero'] = false;
                return true;
            }
            if (!$this->result['settings']['redeployHero']) {
                return true;
            }
            if (array_key_exists('x', $_GET) && array_key_exists('y', $_GET)) {
                $this->result['settings']['to']['x'] = (int)$_GET['x'];
                $this->result['settings']['to']['y'] = (int)$_GET['y'];
            }
            if ($this->result['settings']['units'][11]) {
                $error = false;
                $owner = $this->getOwner($this->result['settings']['to']['kid'], false);
                if ($owner <> $session->getPlayerId()) {
                    $error = true;
                } else {
                    $db = DB::getInstance();
                    $rallyPointLvl = $db->query("SELECT f39, f39t FROM fdata WHERE kid={$this->result['settings']['to']['kid']}")->fetch_assoc();
                    if ($rallyPointLvl['f39'] <= 0) {
                        $error = true;
                    }
                }
                if ($error) {
                    //there's no rallypoint!
                    $this->result['beforeError'] = true;
                    $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.heroDeployError");
                    $this->result['settings']['redeployHero'] = false;
                    return false;
                }
            }
            $this->result['settings']['redeployHero'] = $this->result['settings']['units'][11] > 0;
            return true;
        }
        $village = Village::getInstance();
        $sizeOfVillages = $this->getSizeOfPlayerVillages($session->getPlayerId());
        $db = DB::getInstance();
        $this->result['ui']['showHeroChangeHomeCheckBox'] = $sizeOfVillages > 1 && $db->fetchScalar("SELECT u11 FROM units WHERE kid=" . $village->getKid());
        return true;
    }

    /**
     * @param mixed $id
     * @return mixed
     */
    private function getSizeOfPlayerVillages($id)
    {
        $db = DB::getInstance();
        return $db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE owner={$id}");
    }

    /**
     * @param mixed $timestamp
     * @param string $timestampChecksum
     * @return mixed
     */
    public function getA2b($timestamp, $timestampChecksum)
    {
        $db = DB::getInstance();
        $timestamp = is_scalar($timestamp) ? (int)$timestamp : 0;
        $timestampChecksum = $db->real_escape_string($timestampChecksum);
        return $db->query("SELECT * FROM a2b WHERE timestamp={$timestamp} AND timestamp_checksum='{$timestampChecksum}' AND uid=" . (int)Session::getInstance()->getPlayerId());
    }

    /**
     *
     * @return mixed
     * @param mixed $a2b
     */
    private function prepare($a2b = false)
    {
        $db = DB::getInstance();
        $session = Session::getInstance();
        $village = Village::getInstance();
        if ($a2b) {
            //check all options here! and other in send :D fucked me :(
            $this->result['settings']['units'] = [
                1 => $a2b['u1'],
                $a2b['u2'],
                $a2b['u3'],
                $a2b['u4'],
                $a2b['u5'],
                $a2b['u6'],
                $a2b['u7'],
                $a2b['u8'],
                $a2b['u9'],
                $a2b['u10'],
                $a2b['u11'],
            ];
            $xy = Formulas::kid2xy($a2b['to_kid']);
            $isOasis = $this->isOasis($a2b['to_kid']);
            $isOasisOccupied = $isOasis ? $this->isOasisOccupied($this->result['settings']['to']['kid']) : false;
            $this->result['settings']['to']['x'] = $xy['x'];
            $this->result['settings']['to']['y'] = $xy['y'];
            $this->result['settings']['to']['kid'] = $a2b['to_kid'];
            $this->result['settings']['to']['uid'] = $this->getOwner($a2b['to_kid'], $isOasis);
            $this->result['settings']['to']['villageName'] = $isOasis ? ($this->result['settings']['to']['uid'] <> 0 ? T(
                "RallyPoint",
                "occupiedOasis"
            ) : "") : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$this->result['settings']['to']['kid']}");
            $this->result['settings']['to']['playerName'] = $this->getPlayerName($this->result['settings']['to']['uid']);
            $this->result['settings']['attack_type'] = $a2b['attack_type'];
            $this->result['settings']['redeployHero'] = $a2b['redeployHero'];
            $this->result['settings']['a2bId'] = $a2b['id'];
            $this->result['settings']['timestamp'] = $a2b['timestamp'];
            $this->result['settings']['timestamp_checksum'] = $a2b['timestamp_checksum'];
            if (!isset($_POST['currentDid']) || $_POST['currentDid'] != $village->getKid()) {
                $this->result['beforeError'] = true;
                $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.activeVillageChanged");
                return $this->procSendTroopsContent();
            }
            //errors like attack to ur self and alliance left
            if (isset($_REQUEST['edit']) && $_REQUEST['edit'] == 'edit') {
                $this->removeA2b($a2b['timestamp'], $a2b['timestamp_checksum']);
                $timestamp_params = $this->generateTimestampParams();
                $this->result['settings']['timestamp'] = $timestamp_params['timestamp'];
                $this->result['settings']['timestamp_checksum'] = $timestamp_params['checksum'];
            } elseif (isset($_POST['troopsSent'])) {
                //do troops send :( and remove proc
                if ($this->removeA2b($a2b['timestamp'], $a2b['timestamp_checksum'])) {
                    if ($db->mysqli->begin_transaction()) {
                        if ($this->sendTroops($a2b)) {
                            $db->mysqli->commit();
                        } else {
                            $db->mysqli->rollback();
                        }
                    }
                }
                redirect("/build.php?id=39&tt=1"); //redirect to outgoing troops ;)
                exit();
            }
        } else {
            if (!isset($_POST['currentDid']) || $_POST['currentDid'] != $village->getKid()) {
                $this->result['beforeError'] = true;
                $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.activeVillageChanged");
                return $this->procSendTroopsContent();
            }
            $isOasis = $this->isOasis($this->result['settings']['to']['kid']);
            $isOasisOccupied = $isOasis ? $this->isOasisOccupied($this->result['settings']['to']['kid']) : false;
            //process spy :>
            $sportCount = $this->result['settings']['units'][Formulas::getSpyId($session->getRace())];
            $nonSportCount = array_sum($this->result['settings']['units']) - $sportCount;
            if ($this->result['settings']['attack_type'] != 2 && $sportCount > 0 && $nonSportCount <= 0) {
                $this->result['settings']['attack_type'] = 1;
            }
            $timestamp_params = $this->generateTimestampParams();
            $this->result['settings']['timestamp'] = $timestamp_params['timestamp'];
            $this->result['settings']['timestamp_checksum'] = $timestamp_params['checksum'];
            $id = $this->addA2b(
                $timestamp_params['timestamp'],
                $timestamp_params['checksum'],
                $this->result['settings']['to']['kid'],
                $this->result['settings']['units'],
                $this->result['settings']['attack_type'],
                $this->result['settings']['redeployHero']
            );
            $this->result['settings']['a2bId'] = $id;
        }
        $units = [];
        for ($i = 1; $i <= 11; $i++) {
            $units[nrToUnitId($i, $session->getRace())] = $this->result['settings']['units'][$i];
        }
        $row = [
            "owner" => [
                "wid"         => "",
                "uid"         => $session->getPlayerId(),
                "playerName"  => $session->getName(),
                "villageName" => $village->getName(),
            ],
            "units" => $units,
        ];
        $settings = [
            "noCoordinates"    => true,
            "noVillageLink"    => true,
            "troopHeadline"    => '',
            "info"             => [],
            "showTroopsNum"    => true,
            "showTroopsType"   => true,
            "unitsAreEditable" => false,
        ];
        $settings['troopHeadline'] = T(
            "RallyPoint",
            $this->result['settings']['attack_type'] == 1 ? "spy" : ($this->result['settings']['attack_type'] == 2 ? "supply" : ($this->result['settings']['attack_type'] == 3 ? "attack" : "raid"))
        );
        switch ($this->result['settings']['attack_type']) {
            case 1:
                $settings['troopHeadline'] .= ' ' . T("RallyPoint", "from") . ' ';
                if ($this->result['settings']['to']['uid'] == 0) {
                    $settings['troopHeadline'] .= T("RallyPoint", "unoccupiedOasis");
                } else {
                    $settings['troopHeadline'] .= $this->result['settings']['to']['villageName'];
                }
                break;
            case 2:
                $settings['troopHeadline'] .= ' ' . T("RallyPoint", "for") . ' ';
                if ($this->result['settings']['to']['uid'] == 0) {//not possible
                    $settings['troopHeadline'] .= T("RallyPoint", "unoccupiedOasis");
                } else {
                    $settings['troopHeadline'] .= $this->result['settings']['to']['villageName'];
                }
                break;
            case 3:
            case 4:
                if ($this->result['settings']['attack_type'] == 3) {
                    $settings['troopHeadline'] .= ' ' . T("RallyPoint", "against");
                }
                $settings['troopHeadline'] .= ' ';
                if ($this->result['settings']['to']['uid'] == 0) {//not possible
                    $settings['troopHeadline'] .= T("RallyPoint", "unoccupiedOasis");
                } else {
                    $settings['troopHeadline'] .= $this->result['settings']['to']['villageName'];
                }
                break;
        }
        if ($this->result['settings']['units'][8] > 0 && ($this->result['settings']['attack_type'] == 3 || $this->result['settings']['attack_type'] == 4)) {
            $settings['cata'][] = [
                "type"       => "cata",
                'count'      => $this->result['settings']['units'][8],
                "level"      => $village->getField(39)['level'],
                "isRaid"     => $this->result['settings']['attack_type'] == 4,
            ];
        }
        if ($this->result['settings']['attack_type'] == 1) {
            //spot
            $settings['options'][] = ["type" => "spy", "isOasis" => $isOasis];
        }
        $speeds = [];
        $units_id = [];
        for ($i = 1; $i <= 10; $i++) {
            if ($this->result['settings']['units'][$i] > 0) {
                $speeds[] = Formulas::uSpeed(nrToUnitId($i, $session->getRace()));
                $units_id[] = nrToUnitId($i, $session->getRace());
            }
        }
        $calculator = new SpeedCalculator();
        $calculator->setFrom($session->getKid());
        $calculator->setTo($this->result['settings']['to']['kid']);
        $calculator->hasCata($this->result['settings']['units'][8] > 0);
        if ($this->result['settings']['units'][11]) {
            $calculator->hasHero();
            $inventory = $db->query("SELECT * FROM inventory WHERE uid={$session->getPlayerId()}")->fetch_assoc();
            $calculator->setLeftHand($inventory['leftHand']);
            $calculator->setShoes($inventory['shoes']);
            $helper = new HeroHelper();
            $cavalryOnly = $calculator->isCavalryOnly($units_id);
            $speeds[] = $helper->calcTotalSpeed(
                $session->getRace(),
                $inventory['horse'],
                $inventory['shoes']
            );
            $calculator->setHunnicMountedArmySpeedBonus($helper->getHunnicMountedArmySpeedBonus(
                $session->getRace(),
                $inventory['horse'],
                $cavalryOnly
            ));
            if (array_sum($this->result['settings']['units']) > 1) {
                $calculator->troopsWithHero();
            }
        }
        if ($this->result['settings']['attack_type'] != 2) {
            if ($this->result['settings']['to']['uid'] == $session->getPlayerId()) {
                $this->result['error'] = true;
                $this->result['errorMsg'] = T("RallyPoint", "Errors.reallyAttackOwn?");
            } elseif ($session->getAllianceId()) {
                $aid = $db->fetchScalar("SELECT aid FROM users WHERE id={$this->result['settings']['to']['uid']}");
                if ($aid == $session->getAllianceId()) {
                    $this->result['error'] = true;
                    $this->result['errorMsg'] = T("RallyPoint", "Errors.reallyAttackFriend?");
                } elseif ($aid) {
                    $diplomacy = $db->fetchScalar("SELECT COUNT(id) FROM diplomacy WHERE accepted=1 AND type<>3 AND ((aid1={$session->getAllianceId()} AND aid2=$aid) OR (aid1=$aid AND aid2={$session->getAllianceId()}))");
                    if ($diplomacy > 0) {
                        $this->result['error'] = true;
                        $this->result['errorMsg'] = T("RallyPoint", "Errors.reallyAttackFriend?");
                    }
                }
            }
        }
        $isFarm = !$isOasis && $this->isFarm($this->result['settings']['to']['kid'], $isOasis);
        $skippedProtection = ($isOasis && !$isOasisOccupied) || (isset($this->result['settings']['to']['uid']) && $this->result['settings']['to']['uid'] == 1 && !$isFarm);
        if (Config::getProperty(
            "custom",
            "skipProtectionOnAttack"
        ) && Session::getInstance()->hasProtection() && !$skippedProtection) {
            if (isset($this->result['settings']['to']['uid']) && $this->result['settings']['to']['uid'] == Session::getInstance()->getPlayerId()) {
            } else {
                $artifactCount = $db->fetchScalar("SELECT COUNT(id) FROM artefacts WHERE kid={$this->result['settings']['to']['kid']}");

                if ($artifactCount > 0) {
                    $this->result['error'] = true;
                    $this->result['errorMsg'] = T("RallyPoint", "Errors.youCannotAttackArtifactWhileInProtection");
                }


                $this->result['error'] = true;
                $this->result['errorMsg'] = T("RallyPoint", "Errors.protectionWillBeGone");
            }
        }
        if ($this->result['settings']['to']['uid'] == $session->getPlayerId()) {
            $calculator->isOwn();
        } elseif ($session->getAllianceId() > 0) {
            $aid = $session->getAllianceId();
            $to_aid = $db->fetchScalar("SELECT aid FROM users WHERE id={$this->result['settings']['to']['uid']}");
            if ($aid == $to_aid) {
                $calculator->isAlliance();
            } elseif ($to_aid) {
                $diplomacy = $db->fetchScalar("SELECT COUNT(id) FROM diplomacy WHERE accepted=1 AND type=1 AND ((aid1=$aid AND aid2=$to_aid) OR (aid1=$to_aid AND aid2=$aid))");
                if ($diplomacy > 0) {
                    $calculator->isAlliance();
                }
            }
        }
        $calculator->setMinSpeed($speeds);
        for ($i = 19; $i <= 38; ++$i) {
            if ($village->getField($i)['item_id'] == 14) {
                $calculator->setTournamentSqLvl($village->getField($i)['level']);
                break;
            }
        }
        if (ArtefactsModel::artifactsReleased()) {
            $calculator->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($session->getPlayerId(), $session->getKid(), ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        }
        $neededTime = $calculator->calc();
        $shipForEta = \Game\Ships::pickForArmy((int)$session->getKid(), (int)$this->result['settings']['attack_type'], (int)array_sum($this->result['settings']['units']));
        if ($shipForEta) {
            $sailing = clone $calculator;
            $sailing->setShip($shipForEta, $session->getRace());
            $this->result['settings']['shipDuration'] = $sailing->calc();
        }
        $settings['info'][] = [
            "type"        => "Arrival",
            "countUp"     => true,
            "ArrivalTime" => round(((miliseconds(true) + 1000 * $neededTime)) / 1000),
        ];
        $this->result['settings']['troop_details'] = $this->getMovementTable($row, $settings, true);
        return $this->procPrepareSendContent();
    }

    /**
     * @param mixed $kid
     */
    public function isOasisOccupied($kid): bool
    {
        $db = DB::getInstance();
        return $db->fetchScalar("SELECT occupied FROM wdata WHERE id={$kid}") == 1;
    }

    /**
     * @param mixed $uid
     * @return mixed
     */
    public function getPlayerName($uid)
    {
        if ($uid == 0) {
            return T("Global", "NatureName");
        }
        $db = DB::getInstance();
        return $db->fetchScalar("SELECT name FROM users WHERE id={$uid}");
    }

    /**
     * @return mixed
     */
    private function procSendTroopsContent()
    {
        $village = Village::getInstance();
        //need hero
        $view = new PHPBatchView("rallypoint/sendTroops");
        $view->vars = [
            "village"                    => ["did" => $village->getKid()],
            "process"                    => $this->result,
            'troopsTable'                => $this->getTroopsTable(),
            "timestamp"                  => $this->result['settings']['timestamp'],
            "timestamp_checksum"         => $this->result['settings']['timestamp_checksum'],
            "to"                         => $this->result['settings']['to'],
            "attackTypes"                => $this->result['ui']['attack_types'],
            "showHeroChangeHomeCheckBox" => $this->result['ui']['showHeroChangeHomeCheckBox'],
        ];
        return $view->output();
    }

    public function getTroopsTable(): string
    {
        $session = Session::getInstance();
        $m = new RallyPointModel();
        $villageUnits = $m->getUnits(Village::getInstance()->getKid());
        $start = ($session->getRace() - 1) * 10 + 1;
        $units = $this->result['settings']['units'];
        $formatAvailable = static function (int $value, int $inputIndex) use ($units): string {
            $inputName = 'troop[t' . $inputIndex . ']';
            $maxLength = max(6, strlen((string)$value));
            $current = !empty($units[$inputIndex]) ? (string)$units[$inputIndex] : '';
            $disabled = $value <= 0;
            $html = '<input type="text" inputmode="numeric" class="text' . ($disabled ? ' disabled' : '') . '" name="'
                . $inputName . '" value="' . $current . '" maxlength="' . $maxLength . '"'
                . ($disabled ? ' disabled="disabled"' : '') . ' />';
            if ($value > 0) {
                // The shared base target turns a hash-only fallback into the login route,
                // so the fallback is the Rally Point's own root-absolute route.
                $html .= '&nbsp;/&nbsp;&#8236;<a href="/build.php?gid=16&amp;tt=2" onclick="jQuery(\'table#troops\').find(\'input[name=\\\'' . $inputName
                    . '\\\']\').val(' . $value . ').trigger(\'focus\'); return false">' . number_format_x($value) . '</a>';
            } else {
                $html .= '&nbsp;/&nbsp;&#8236;<span class="none">&#8237;0&#8236;</span>';
            }

            return $html;
        };
        $troopsTable = ' <tr>';
        //3first units.
        $end = $start + 8;
        foreach ([$start, $start + 3, $start + 6, $start + 8] as $i) {
            $x = $i == "hero" ? 11 : ($i - $start + 1);
            $value = (int)$villageUnits['u' . unitIdToNr($i)];
            $troopsTable .= '<td class="line-first ' . ($i == $start ? 'column-first' : ($i == $end ? 'column-last' : '')) . ' ' . ($i == $end ? 'small' : ($i == ($start + 6) ? 'regular' : 'large')) . '">';
            $manualId = (int)$i;
            $troopsTable .= '<img class="unit u' . $i . '" src="/img/spacer.gif" onclick="Travian.Game.Manual.open(\'troop\', ' . $manualId . ');" title="' . ($unitName = T("Troops", $i . '.title')) . '" alt="' . $unitName . '" /> ';
            $troopsTable .= $formatAvailable($value, $x);
            $troopsTable .= '</td>';
        }
        $troopsTable .= ' </tr>';
        $troopsTable .= ' <tr>';
        //3first units.
        $end = $start + 9;
        foreach ([$start + 1, $start + 4, $start + 7, $start + 9] as $i) {
            $x = $i == "hero" ? 11 : ($i - $start + 1);
            $value = (int)$villageUnits['u' . unitIdToNr($i)];
            $troopsTable .= '<td class="' . ($i == $start + 1 ? 'column-first' : ($i == $end ? 'column-last' : '')) . ' ' . ($i == $end ? 'small' : ($i == ($start + 7) ? 'regular' : 'large')) . '">';
            $manualId = (int)$i;
            $troopsTable .= '<img class="unit u' . $i . '" src="/img/spacer.gif" onclick="Travian.Game.Manual.open(\'troop\', ' . $manualId . ');" title="' . ($unitName = T("Troops", $i . '.title')) . '" alt="' . $unitName . '" /> ';
            $troopsTable .= $formatAvailable($value, $x);
            $troopsTable .= '</td>';
        }
        $troopsTable .= ' </tr>';
        $troopsTable .= ' <tr>';
        //3first units.
        foreach ([$start + 2, $start + 5, "hero"] as $i) {
            if ($i == "hero") {
                $troopsTable .= '<td class="line-last regular"></td>';
            }
            $x = $i == "hero" ? 11 : ($i - $start + 1);
            $value = (int)$villageUnits['u' . ($i == "hero" ? 11 : unitIdToNr($i))];
            $l = $i == $start + 2 ? "large" : ($i == $start + 5 ? "large" : "small");
            $unitClass = $i === 'hero' ? 'hero' : (string)$i;
            $troopsTable .= '<td class="line-last ' . ($i == $start + 2 ? 'column-first' : ($i == "hero" ? 'column-last' : '')) . ' ' . $l . '">';
            $manualOpen = $i === 'hero'
                ? ''
                : ' onclick="Travian.Game.Manual.open(\'troop\', ' . (int)$i . ');"';
            $troopsTable .= '<img class="unit u' . $unitClass . '" src="/img/spacer.gif"' . $manualOpen . ' title="' . ($unitName = T("Troops", $i . '.title')) . '" alt="' . $unitName . '" /> ';
            $troopsTable .= $formatAvailable($value, $x);
            $troopsTable .= '</td>';
        }
        $troopsTable .= ' </tr>';
        return $troopsTable;
    }

    /**
     * @param mixed $timestamp
     * @param mixed $timestampChecksum
     */
    public function removeA2b($timestamp, $timestampChecksum): bool
    {
        $db = DB::getInstance();
        $timestamp = is_scalar($timestamp) ? (int)$timestamp : 0;
        $timestampChecksum = $db->real_escape_string(is_string($timestampChecksum) ? $timestampChecksum : '');
        $query = $db->query("DELETE FROM a2b WHERE timestamp={$timestamp} AND timestamp_checksum='{$timestampChecksum}' AND uid=" . (int)Session::getInstance()->getPlayerId() . " LIMIT 1");
        return $query && $db->affectedRows() > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateTimestampParams()
    {
        $timestamp = time();
        $timestamp_checksum = get_random_string(6);
        return [
            'timestamp' => $timestamp,
            'checksum'  => $timestamp_checksum,
        ];
    }

    /**
     * @param array<mixed>|bool $a2b
     */
    private function sendTroops($a2b): bool
    {
        if (Session::getInstance()->banned() || isServerFinished()) {
            return false;
        }
        $village = Village::getInstance();
        $session = Session::getInstance();
        $kid = $a2b['to_kid'];
        $attack_type = $a2b['attack_type'];
        $redeployHero = $a2b['redeployHero'];
        //check if village exists.
        if (!$this->getVillageState($kid)) {
            //process cancelled.
            return false;
        }
        $isOasis = $this->isOasis($kid);
        $owner = $this->getOwner($kid, $isOasis);
        if ($owner == 1) {
            $attackTypes = [
                1 => ["disabled" => false, "checked" => false],//spy
                2 => ["disabled" => true, "checked" => false],//reinforcement
                3 => ["disabled" => false, "checked" => false],//normal attacks
                4 => ["disabled" => false, "checked" => true],//raid attacks
            ];
        } elseif ($owner == 0) {
            $attackTypes = [
                1 => ["disabled" => false, "checked" => false],//spy
                2 => ["disabled" => true, "checked" => false],//reinforcement
                3 => ["disabled" => true, "checked" => false],//normal attacks
                4 => ["disabled" => false, "checked" => true],//raid attacks
            ];
        } elseif ($owner) {//village or occupied oasis
            $attackTypes = [
                1 => ["disabled" => false, "checked" => false],//spy
                2 => ["disabled" => false, "checked" => false],//reinforcement
                3 => ["disabled" => false, "checked" => false],//normal attacks
                4 => ["disabled" => false, "checked" => true],//raid attacks
            ];
        } else {//new village :D
            $attackTypes = [5 => ["disabled" => false, "checked" => true]];//spy

        }
        $units = array_fill(1, 11, 0);
        $m = new RallyPointModel();
        if ($attack_type == 4 && !$m->checkMaxAttacks(Village::getInstance()->getKid())) {
            return false;
        }
        $db = DB::getInstance();
        $row = $db->query("SELECT * FROM units WHERE kid=" . Village::getInstance()->getKid())->fetch_assoc();
        $speeds = [];
        $units_id = [];
        for ($i = 1; $i <= 11; ++$i) {
            $units[$i] = abs((int)$a2b['u' . $i]);
            if ($units[$i] > $row['u' . $i]) {
                $units[$i] = $row['u' . $i];
            }
            if ($units[$i] && $i < 11) {
                $speeds[] = Formulas::uSpeed(nrToUnitId($i, $session->getRace()));
                $units_id[] = nrToUnitId($i, $session->getRace());
            }
        }
        $sportCount = $units[Formulas::getSpyId($session->getRace())];
        $nonSportCount = array_sum($units) - $sportCount;
        if ($attack_type != 2 && $sportCount > 0 && $nonSportCount <= 0) {
            //spy if not spy :D
            $attack_type = 1;
        } elseif ($attack_type == 1) {
            $attack_type = 3;
        }
        if ($attackTypes[$attack_type]['disabled'] === true) {
            //fuck this no problem aborting....
            return false;
        }
        $session = Session::getInstance();
        if (!$session->checkSitterPermission(Session::SITTER_CAN_RAID) && ($attack_type == 4 || $attack_type == 1)) {
            return false;
        }
        if (!$session->checkSitterPermission(Session::SITTER_CAN_SEND_REINFORCEMENT) && ($attack_type == 2)) {
            return false;
        }
        if ($session->isSitter() && ($attack_type == 3)) {
            return false;
        }
        if (!array_sum($units)) {
            // Reject malformed troop input instead of creating an invalid movement.
            return false;
        }
        if ($kid == $village->getKid()) {
            //wowwww! what a ability:D
            return false;
        }
        if ($redeployHero) {
            if ($units[11] <= 0 || $attack_type <> 2) {
                $redeployHero = false;
            } elseif ($this->getSizeOfPlayerVillages($session->getPlayerId()) <= 1) {
                $redeployHero = false;
            }
            if ($owner <> $session->getPlayerId()) {
                $redeployHero = false;
            }
        }
        $cap_kid = Formulas::xy2kid(0, 0);
        if ($kid == $cap_kid) {
            // I told u that u can't.
            return false;
        }
        //maybe u are banned.
        if ($session->banned()) {
            return false;
        }
        $isFarm = $this->isFarm($kid, $isOasis);
        $isOasisOccupied = $isOasis ? $this->isOasisOccupied($this->result['settings']['to']['kid']) : false;
        $skippedProtection = ($isOasis && !$isOasisOccupied) || ($owner == 1 && !$isFarm);
        if (!$skippedProtection) {
            if ($owner <> $session->getPlayerId() && $this->hasBeginnerProtection($kid) == 1) {
                return false;
            }
        } elseif ($this->hasBeginnerProtection($kid) == 1) {
            $artifactCount = $db->fetchScalar("SELECT COUNT(id) FROM artefacts WHERE kid={$this->result['settings']['to']['kid']}");

            if ($artifactCount > 0) {
                return false;
            }
        }
        //should be did :(
        if ($this->result['settings']['to']['kid'] == $village->getKid()) {
            return false;
        }
        if ($owner <> 0) {
            $userAccess = $db->fetchScalar("SELECT access FROM users WHERE id={$owner}");
            if ($owner == 1 && (!Config::getInstance()->dynamic->WWPlansReleased && !Config::getProperty(
                "custom",
                "wwPlansEnabled"
            ))) {
                $db = DB::getInstance();
                $isWW = 1 == $db->fetchScalar("SELECT isWW FROM vdata WHERE kid=$kid");
                if ($isWW) {
                    $userAccess = 0;
                }
            }
            if ($userAccess <= 0) {
                return false;
            }
        }
        if (!getCustom("skipProtectionOnAttack") && $this->hasBeginnerProtection($village->getKid()) == 1 && $owner != $session->getPlayerId()) {
            if (!$skippedProtection) {
                return false;
            }
        } elseif ($this->hasBeginnerProtection($village->getKid()) && $owner != $session->getPlayerId()) {
            if (!$skippedProtection) {
                $db->query("UPDATE users SET protection=" . time() . " WHERE id={$session->getPlayerId()}");
                (new InfoBoxModel())->deleteInfoByType($session->getPlayerId(), 6);
            }
        }
        if ($isOasis) {
            Quest::getInstance()->setQuestBitwise('battle', 7, 1);
        }
        //all right
        //let's send units =>
        $calculator = new SpeedCalculator();
        $calculator->setFrom($session->getKid());
        $calculator->setTo($a2b['to_kid']);
        $calculator->hasCata($units[8] > 0);
        if ($units[11]) {
            $calculator->hasHero();
            $inventory = $db->query("SELECT * FROM inventory WHERE uid={$session->getPlayerId()}")->fetch_assoc();
            $calculator->setLeftHand($inventory['leftHand']);
            $calculator->setShoes($inventory['shoes']);
            $helper = new HeroHelper();
            $cavalryOnly = $calculator->isCavalryOnly($units_id);
            $speeds[] = $helper->calcTotalSpeed(
                $session->getRace(),
                $inventory['horse'],
                $inventory['shoes']
            );
            $calculator->setHunnicMountedArmySpeedBonus($helper->getHunnicMountedArmySpeedBonus(
                $session->getRace(),
                $inventory['horse'],
                $cavalryOnly
            ));
            if (array_sum($units) > 1) {
                $calculator->troopsWithHero();
            }
        }
        //maybe items do for diplomacy alliances.
        if ($this->result['settings']['to']['uid'] == $session->getPlayerId()) {
            $calculator->isOwn();
        } elseif ($session->getAllianceId() > 0) {
            $aid = $session->getAllianceId();
            $to_aid = $db->fetchScalar("SELECT aid FROM users WHERE id={$this->result['settings']['to']['uid']}");
            if ($aid == $to_aid) {
                $calculator->isAlliance();
            } elseif ($to_aid) {
                $diplomacy = $db->fetchScalar("SELECT COUNT(id) FROM diplomacy WHERE accepted=1 AND type=1 AND ((aid1=$aid AND aid2=$to_aid) OR (aid1=$to_aid AND aid2=$aid))");
                if ($diplomacy > 0) {
                    $calculator->isAlliance();
                }
            }
        }
        $calculator->setMinSpeed(min($speeds));
        for ($i = 19; $i <= 38; ++$i) {
            if ($village->getField($i)['item_id'] == 14) {
                $calculator->setTournamentSqLvl($village->getField($i)['level']);
                break;
            }
        }
        $calculator->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($session->getPlayerId(), $session->getKid(), ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        // Sail when the player ticked "use ship" and a fitting ship waits in the harbor.
        $ship = empty($_POST['useShip']) ? 0 : \Game\Ships::pickForArmy((int)$session->getKid(), (int)$attack_type, (int)array_sum($units));
        if ($ship && \Game\Ships::take((int)$session->getKid(), $ship)) {
            $calculator->setShip($ship, $session->getRace());
        } else {
            $ship = 0;
        }
        $neededTime = $calculator->calc();
        $insert = [
            "ctar1"        => 99,
            "ctar2"        => 99,
            "spy"          => 0,
            "attack_type"  => $attack_type,
            "redeployHero" => $redeployHero,
        ];
        $villageModel = new VillageModel();
        if (isset($_POST['ctar1']) || isset($_POST['ctar2'])) {
            $targets = [99];
            if ($session->getRace() <> 2 || $villageModel->getCapBrewery($session->getPlayerId()) <= 0) {
                $targets = CatapultTargets::allowed((int)$village->getField(39)['level']);
            }
            $insert['ctar1'] = isset($_POST['ctar1']) && in_array($_POST['ctar1'], $targets) ? $_POST['ctar1'] : 99;
            if ($village->getField(39)['level'] == 20) {
                $insert['ctar2'] = isset($_POST['ctar2']) && (in_array(
                    $_POST['ctar2'],
                    $targets
                ) || $_POST['ctar2'] == 0) ? $_POST['ctar2'] : 99;
            }
        }
        if (isset($_POST['spy']) && $_POST['spy'] >= 1 && $_POST['spy'] <= 2) {
            $insert['spy'] = $isOasis ? 1 : (int)$_POST['spy'];
        }
        if ($village->getField(39)['level'] < 20 || $units[8] < 20) {
            $insert['ctar2'] = 0;
        }
        $move = new MovementsModel();
        $stmtSuccess = Units::modifyUnits($session->getKid(), $units);
        if (!$stmtSuccess) {
            if ($ship) {
                \Game\Ships::restore((int)$session->getKid(), $ship);
            }
            return false;
        }
        $now = miliseconds(true);
        while (true) {
            $attacksPerSecond = $db->fetchScalar("SELECT COUNT(id) FROM movement WHERE kid={$village->getKid()} AND start_time=$now");
            if ($attacksPerSecond < 4) {
                break;
            }
            $now += 1000;
        }
        $success = $move->addMovement(
            $village->getKid(),
            $kid,
            $session->getRace(),
            $units,
            $insert['ctar1'],
            $insert['ctar2'],
            $insert['spy'],
            $insert['redeployHero'],
            0,
            $insert['attack_type'],
            $now,
            $now + 1000 * $neededTime,
            NULL,
            $ship
        );
        Log::addLog(
            $session->getPlayerId(),
            "rallypoint:movement:" . $attack_type,
            sprintf("Troops: %s, Result: %s", implode(",", array_values($units)), $success > 0)
        );
        if ($isOasis) {
            Quest::getInstance()->setQuestBitwise('battle', 7, Quest::QUEST_FINISHED);
        }
        return true;
    }

    /**
     * @param mixed $kid
     */
    private function getVillageState($kid): bool
    {
        $db = DB::getInstance();
        $status = $db->query("SELECT fieldtype, oasistype, landscape, occupied FROM wdata WHERE id={$kid}");
        if (!$status->num_rows) {
            return false;
        }
        $status = $status->fetch_assoc();
        if ($status['landscape']) {
            return false;
        }
        if ($status['oasistype'] || $status['occupied']) {
            return true;
        }
        return false;
    }

    /**
     * @param mixed $kid
     * @param mixed $isOasis
     */
    public function isFarm($kid, $isOasis): bool
    {
        $db = DB::getInstance();
        if ($isOasis) {
            return false;
        }
        return $db->fetchScalar("SELECT isFarm FROM vdata WHERE kid={$kid}") == 1;
    }

    /**
     * @param mixed $kid
     */
    private function hasBeginnerProtection($kid): int
    {
        $db = DB::getInstance();
        $protect = $db->fetchScalar("SELECT users.protection FROM users, vdata WHERE users.id=vdata.owner AND vdata.kid=$kid LIMIT 1");
        if ($protect >= time()) {
            return 1;
        }
        return 0;
    }

    /**
     * @param mixed $timestamp
     * @param mixed $timestamp_checksum
     * @param mixed $to_kid
     * @param mixed $units
     * @param mixed $attack_type
     * @param mixed $redeployHero
     * @return mixed
     */
    public function addA2b($timestamp, $timestamp_checksum, $to_kid, $units, $attack_type, $redeployHero = 0)
    {
        if (!isset($units[11])) {
            $units[11] = 0;
        }
        $db = DB::getInstance();
        $query = vsprintf(
            "INSERT INTO a2b (`uid`, `timestamp`, `timestamp_checksum`, `to_kid`, `u1`, `u2`, `u3`, `u4`, `u5`, `u6`, `u7`, `u8`, `u9`, `u10`, `u11`, `attack_type`, `redeployHero`) VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s')",
            [
                (int)Session::getInstance()->getPlayerId(),
                $timestamp,
                $timestamp_checksum,
                $to_kid,
                $units[1],
                $units[2],
                $units[3],
                $units[4],
                $units[5],
                $units[6],
                $units[7],
                $units[8],
                $units[9],
                $units[10],
                $units[11],
                $attack_type,
                (int)$redeployHero,
            ]
        );
        $db->query($query);
        return $db->lastInsertId();
    }

    /**
     * @return mixed
     */
    private function procPrepareSendContent()
    {
        $village = Village::getInstance();
        //need hero
        $view = new PHPBatchView("rallypoint/prepareSend");
        $view->vars = [
            "village" => [
                "did" => $village->getKid()//did is diffrent and i'll fix it later:)
            ],
            "process" => $this->result,
            "eventType" => self::attackTypeToEventType((int)$this->result['settings']['attack_type']),
            "ship" => $this->shipChoice(),
        ];
        return $view->output();
    }

    /**
     * The ship this army would sail on and the ships waiting at home, or null when no ship fits.
     *
     * @return array<string, mixed>|null
     */
    private function shipChoice()
    {
        $settings = $this->result['settings'];
        if (empty($settings['to']['kid']) || empty($settings['units']) || !array_sum($settings['units'])) {
            return null;
        }
        $kid = (int)Village::getInstance()->getKid();
        $type = \Game\Ships::pickForArmy($kid, (int)($settings['attack_type'] ?? 0), (int)array_sum($settings['units']));
        if (!$type) {
            return null;
        }

        return [
            'type' => $type,
            'unitId' => 900 + $type,
            'home' => \Game\Ships::homeShips($kid),
            'tribe' => (int)Session::getInstance()->getRace(),
            'checked' => \Game\Map\ShipRoute::deepFraction($kid, (int)$settings['to']['kid']) > 0,
            'duration' => isset($settings['shipDuration']) ? secondsToString((int)$settings['shipDuration'], true) : '',
        ];
    }

    private function checkCoordniatesAndDname(): bool
    {
        if (empty($this->result['settings']['to']['dname']) &&
            ($this->result['settings']['to']['x'] === "" || $this->result['settings']['to']['y'] === "")) {
            $this->result['beforeError'] = true;
            $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.EnterCoordinateOrDname");
            return false;
        }
        if (!empty($this->result['settings']['to']['dname'])) {
            $kid = $this->getVillageByNameLIKE($this->result['settings']['to']['dname']);
            if (!$kid) {
                $this->result['beforeError'] = true;
                $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.noVillageWithThisName");
                return false;
            }
            $this->result['settings']['to']['kid'] = $kid;
            $xy = Formulas::kid2xy($kid);
            $this->result['settings']['to']['x'] = $xy['x'];
            $this->result['settings']['to']['y'] = $xy['y'];
            $this->getAvailableAttackTypes();
            return true;
        }
        if ($this->result['settings']['to']['x'] != "" && $this->result['settings']['to']['y'] != "") {
            $kid = Formulas::xy2kid($this->result['settings']['to']['x'], $this->result['settings']['to']['y']);
            if (!$this->getVillageState($kid)) {
                $this->result['beforeError'] = true;
                $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.noVillageInCoordinate");
                return false;
            }
            $this->result['settings']['to']['kid'] = $kid;
            $this->getAvailableAttackTypes();
            return true;
        }
        return true;
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    private function getVillageByNameLIKE($name)
    {
        $db = DB::getInstance();
        $name = strip_tags(is_string($name) ? $name : '');
        $name = $db->real_escape_string($name);
        $search = "'%$name%'";
        return $db->fetchScalar("SELECT kid FROM vdata WHERE name LIKE " . $search . " LIMIT 1");
    }

    private function checkNatarsCapital(): bool
    {
        $cap_kid = Formulas::xy2kid(0, 0);
        if ($this->result['settings']['to']['kid'] == $cap_kid) {
            $this->result['beforeError'] = true;
            $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.NatarCapitalError");
            return false;
        }
        return true;
    }

    private function checkFarmsAttackable(): bool
    {
        $db = DB::getInstance();
        $isFarm = 1 == $db->fetchScalar("SELECT isFarm FROM vdata WHERE kid={$this->result['settings']['to']['kid']}");
        if ($isFarm && time() <= getCustom("protectFarmsTill")) {
            $this->result['beforeError'] = true;
            $this->result['beforeErrorMsg'] = sprintf(
                T("RallyPoint", "Errors.farmsAreProtectedTill"),
                TimezoneHelper::autoDateString(getCustom("protectFarmsTill"), true)
            );
            return false;
        }
        return true;
    }

    private function checkTroopsSelected(): bool
    {
        if (!array_sum($this->result['settings']['units'])) {
            $this->result['beforeError'] = true;
            $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.noTroopsSelected");
            return false;
        }
        return true;
    }

    private function checkAttackType(): bool
    {
        $this->getAvailableAttackTypes();//remove later not needed but for security i put it here
        $att_type = $this->result['settings']['attack_type'];
        //let us fix this :>
        if (!isset($this->result['ui']['attack_types'][$att_type]) || ($this->result['ui']['attack_types'][$att_type]['disabled'])) {//user thinks he's smart :>
            foreach ($this->result['ui']['attack_types'] as $attackTypeId => $attackTypeSettings) {
                if (!$attackTypeSettings['disabled'] && $attackTypeSettings['checked']) {
                    $this->result['settings']['attack_type'] = $attackTypeId; //reinforcement
                    break;
                }
            }
            return !$this->result['ui']['attack_types'][$att_type]['disabled'];
        }

        $m = new RallyPointModel();
        if ($att_type == 4 && !$m->checkMaxAttacks(Village::getInstance()->getKid())) {
            $this->result['beforeError'] = true;
            $this->result['beforeErrorMsg'] = 'Maximum attack limit reached.';
            return false;
        }
        return true;
    }

    private function checkAccesses(): bool
    {
        $db = DB::getInstance();
        $village = Village::getInstance();
        $session = Session::getInstance();
        if ($session->banned()) {
            $this->result['beforeError'] = true;
            $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.youAreBanned");
            return false;
        }
        if ($session->isInVacationMode()) {
            redirect("/options/vacation");
        }
        if (isServerFinished()) {
            $this->result['error'] = true;
            $this->result['errorMsg'] = T("RallyPoint", "Errors.serverFinished");
            return false;
        }
        $isOasis = $this->isOasis($this->result['settings']['to']['kid']);
        $isOasisOccupied = $isOasis ? $this->isOasisOccupied($this->result['settings']['to']['kid']) : false;
        $villageOwner = $this->getOwner($this->result['settings']['to']['kid'], $isOasis);

        //        if(!$villageOwner){
        //            $this->result['beforeError'] = TRUE;
        //            $this->result['beforeErrorMsg'] = 'No player';
        //            return FALSE;
        //        }

        //cant attack natar can
        //can't reinforce oasis
        $this->result['settings']['to']['uid'] = $villageOwner;
        $this->result['settings']['to']['playerName'] = $villageOwner == 0 ? T(
            "Global",
            "NatureName"
        ) : $db->fetchScalar("SELECT name FROM users WHERE id={$villageOwner}");
        $this->result['settings']['to']['villageName'] = $isOasis ? ($this->result['settings']['to']['uid'] <> 0 ? T(
            "RallyPoint",
            "occupiedOasis"
        ) : "") : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$this->result['settings']['to']['kid']}");
        $this->result['settings']['to']['isFarm'] = !$isOasis && $this->isFarm(
            $this->result['settings']['to']['kid'],
            $isOasis
        );
        //!=Natars | != Oasis
        $skippedProtection = ($isOasis && !$isOasisOccupied) || ($villageOwner == 1 && !$this->result['settings']['to']['isFarm']);
        if (!Config::getProperty("custom", "skipProtectionOnAttack") && !$skippedProtection) {
            if ($villageOwner != $session->getPlayerId() && $this->hasBeginnerProtection($this->result['settings']['to']['kid']) == 1) {
                $this->result['beforeError'] = true;
                $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.playerHasBeginnerProtection");
                return false;
            }
        }
        if ($this->isInVacation($villageOwner)) {
            $this->result['beforeError'] = true;
            $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.playerIsInVacation");
            return false;
        }
        //should be did :(
        if ($this->result['settings']['to']['kid'] == $village->getKid()) {
            $this->result['beforeError'] = true;
            $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.cantAttackUrSelf");
            return false;
        }
        if (Session::getInstance()->hasProtection() == 1 && $villageOwner != $session->getPlayerId()) {
            if (!Config::getProperty("custom", "skipProtectionOnAttack")) {
                if ($this->result['settings']['attack_type'] == 2) {
                    $this->result['beforeError'] = true;
                    $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.cantSendReinforcementsDuringProtection");
                    return false;
                }
                if (!$skippedProtection) {
                    $this->result['beforeError'] = true;
                    $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.cantAttackDuringProtection");
                    return false;
                }
            }
        }
        if ($villageOwner <> 0) {//!$isOasis
            $userAccess = $db->fetchScalar("SELECT access FROM users WHERE id={$villageOwner}");
            if ($villageOwner == 1 && !Config::getProperty(
                "custom",
                "wwPlansEnabled"
            ) && !Config::getInstance()->dynamic->WWPlansReleased) {
                $db = DB::getInstance();
                $isWW = $db->fetchScalar("SELECT isWW FROM vdata WHERE kid={$this->result['settings']['to']['kid']}");
                if ($isWW) {
                    $userAccess = 0;
                }
            }
            if ($userAccess <= 0) {
                $this->result['beforeError'] = true;
                $this->result['beforeErrorMsg'] = T("RallyPoint", "Errors.playerBanned");
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $uid
     */
    private function isInVacation($uid): bool
    {
        $uid = (int) $uid;
        $db = DB::getInstance();
        return time() < $db->fetchScalar("SELECT vacationActiveTil FROM users WHERE id=$uid");
    }

}
