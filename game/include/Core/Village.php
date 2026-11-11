<?php

namespace Core;

use Core\Database\DB;
use Core\Database\DBI;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Game\Buildings\BuildingAction;
use Game\Buildings\BuildingHelper;
use Game\Formulas;
use Game\ResourcesHelper;
use Game\Starvation;
use Model\AccountDeleter;
use Model\MasterBuilder;
use Model\Quest;
use PDO;
use function get_random_string;
use function logError;
use function miliseconds;
use function uniqid;

class Village
{
    /** @var \Core\Village|null */
    private static $_self;
    /** @var array<string, array<mixed>> */
    public $onLoadBuildings = ['normal' => [], 'master' => []];
    /** @var array<mixed> */
    public $buildings = [];
    /** @var mixed */
    public $village = [];
    /** @var mixed */
    public $demolitionTasks = null;
    /** @var int */
    public $cropLoading = 0;
    /** @var int */
    public $cropLoadingMaster = 0;
    /** @var array<string, int> */
    public $workers = [
        'buildsNum' => 0,
        'fieldsNum' => 0,
        'master' => 0,
        'WW' => 0,
    ];
    /** @var array<string, array<mixed>|int> */
    public $specialBuildingsLvl = [
        "Barracks" => 0,
        "Stable" => 0,
        "Workshop" => 0,
        "Marketplace" => 0,
        "MainBuilding" => 0,
        "HorseDrinkingPool" => 0,
        "RallyPoint" => 0,
        "GrainMill" => 0,
        "Bakery" => 0,
        "Sawmill" => 0,
        "Brickyard" => 0,
        'Residence' => 0,
        'Palace' => 0,
        'CommandCenter' => 0,
        "IronFoundry" => 0,
        "Trapper" => [],
        "Cranny" => [],
    ];
    /** @var array<int, string> */
    public $specialBuildingsName = [
        5 => 'Sawmill',
        6 => 'Brickyard',
        7 => 'IronFoundry',
        8 => 'GrainMill',
        9 => 'Bakery',
        15 => 'MainBuilding',
        17 => 'Marketplace',
        19 => 'Barracks',
        20 => 'Stable',
        21 => 'Workshop',
        23 => 'Cranny',
        36 => 'Trapper',
        41 => 'HorseDrinkingPool',
        25 => 'Residence',
        26 => 'Palace',
        44 => 'CommandCenter',
    ];
    /** @var \Game\Buildings\BuildingHelper */
    private $helper;
    /** @var bool */
    private $isSupport = false;
    /** @var \Core\Database\DBI */
    private $db;
    /** @var mixed */
    private $session;

    /**
     * @param \Core\Session|null $session
     */
    public function __construct($session = null)
    {
        $this->session = $session ? $session : Session::getInstance();
        $this->db = DBI::getInstance();
        $this->helper = new BuildingHelper();

        if (!$this->setBasics()) {
            if (!isset($_GET['refreshBasics'])) {
                redirect("/dorf1.php?refreshBasics");
            }
            exit();
        }

        $now = miliseconds();
        $previousLastmupdate = (int)$this->village['lastmupdate'];
        $elapsedTime = $now - $previousLastmupdate;
        $this->village['wood'] = min($this->village['wood'] + $elapsedTime * $this->village['woodp'] / 3600000, $this->village['maxstore']);
        $this->village['clay'] = min($this->village['clay'] + $elapsedTime * $this->village['clayp'] / 3600000, $this->village['maxstore']);
        $this->village['iron'] = min($this->village['iron'] + $elapsedTime * $this->village['ironp'] / 3600000, $this->village['maxstore']);
        $this->village['crop'] = min($this->village['crop'] + $elapsedTime * ($this->village['cropp'] - $this->village['pop'] - $this->village['upkeep']) / 3600000, $this->village['maxcrop']);
        $this->village['lastmupdate'] = $now;
        if ($elapsedTime > 0) {
            $this->persistAccruedResources($previousLastmupdate);
        }

        $this->populateBuildings();
        $this->populateOnLoadBuildings();
        $this->populateDemolishTasks();
    }

    private function setBasics(): bool
    {
        $this->isSupport = $this->session->getPlayerId() == 0;
        if ($this->isSupport) {
            exit();
        }
        new Starvation($this->session->getKid());
        $this->village = $this->db->fetchSingleRow("SELECT * FROM vdata WHERE kid=?", (int)$this->session->getKid());
        // Apply fix when village does not exists (kid invalid)
        if (!$this->village) {
            $this->session->data['kid'] = $this->db->fetchScalar("SELECT kid FROM vdata WHERE owner=? ORDER BY pop DESC LIMIT 1", (int)$this->session->getPlayerId());
            $_SESSION[$this->session->fixSessionPrefix("kid")] = $this->session->data['kid'];
            $this->db->run("UPDATE users SET kid=? WHERE id=?", [
                $this->session->getKid(),
                $this->session->getPlayerId(),
            ]);
            $this->village = $this->db->fetchSingleRow("SELECT * FROM vdata WHERE kid=?", (int)$this->session->getKid());
        }
        return $this->village !== false;
    }

    private function populateBuildings(): void
    {
        if ($this->isSupport) {
            return;
        }
        $quest = Quest::getInstance();
        $buildings = $this->db->fetchSingleRow("SELECT * FROM fdata WHERE kid=?", (int)$this->getKid());
        if (!$buildings) {
            logError('Field data not found for village %s, Fixing...', [$this->getKid()]);
            (new AccountDeleter())->deleteVillage($this->getKid());
            WebService::redirect("/dorf1.php");
            exit();
        }
        $specialKeys = array_keys($this->specialBuildingsName);
        $resources_levels = [];
        $resource_levels = [1 => [], 2 => [], 3 => [], 4 => []];
        for ($i = 1; $i <= 41; ++$i) {
            if ($i == 41) {
                $i = 99;
            }
            $this->buildings[$i] = [
                "item_id" => (int)$buildings['f' . $i . 't'],
                "level" => (int)$buildings['f' . $i],
                "upgrade_state" => 0,
                "demolition_state" => 0,
            ];
            if (in_array($this->buildings[$i]['item_id'], $specialKeys)) {
                $this->setSpecialBuildingLevel($this->specialBuildingsName[$this->buildings[$i]['item_id']],
                    $this->buildings[$i]['level']);
            }
            if ($this->buildings[$i]['item_id'] == 3 && $this->buildings[$i]['level'] >= 1) {
                $quest->setQuestBitwise('economy', 1, 1);
            }
            if ($this->buildings[$i]['item_id'] == 23 && $this->buildings[$i]['level'] >= 1) {
                $quest->setQuestBitwise('battle', 2, 1);
            }
            if ($this->buildings[$i]['item_id'] == 19 && $this->buildings[$i]['level'] >= 3) {
                $quest->setQuestBitwise('battle', 10, 1);
            }
            if ($this->buildings[$i]['item_id'] == 22 && $this->buildings[$i]['level'] >= 1) {
                $quest->setQuestBitwise('battle', 11, 1);
            }
            if ($this->buildings[$i]['item_id'] == 13 && $this->buildings[$i]['level'] >= 0) {
                $quest->setQuestBitwise('battle', 13, 1);
            }
            if (in_array($this->buildings[$i]['item_id'], \Game\Formulas::WALL_BUILDINGS) && $this->buildings[$i]['level'] == 1) {
                $quest->setQuestBitwise('battle', 6, 1);
            }
            if ($this->buildings[$i]['item_id'] == 11 && $this->buildings[$i]['level'] >= 1) {
                $quest->setQuestBitwise('economy', 3, 1);
            }
            if ($this->buildings[$i]['item_id'] == 17 && $this->buildings[$i]['level'] >= 1) {
                $quest->setQuestBitwise('economy', 6, 1);
            }
            if ($this->buildings[$i]['item_id'] == 8 && $this->buildings[$i]['level'] >= 1) {
                $quest->setQuestBitwise('economy', 11, 1);
            }
            if ($this->buildings[$i]['item_id'] == 18 && $this->buildings[$i]['level'] >= 1) {
                $quest->setQuestBitwise('world', 4, 1);
            }
            if (in_array($this->buildings[$i]['item_id'], [25, 26, 44]) && $this->buildings[$i]['level'] >= 1) {
                $quest->setQuestBitwise('world', 10, 1);
            }
            if ($this->buildings[$i]['item_id'] == 10 && $this->buildings[$i]['level'] >= 3) {
                $quest->setQuestBitwise('economy', 9, 1);
            }
            if ($this->buildings[$i]['item_id'] == 11 && $this->buildings[$i]['level'] >= 3) {
                $quest->setQuestBitwise('economy', 10, 1);
            }
            if ($this->buildings[$i]['item_id'] == 15 && $this->buildings[$i]['level'] >= 3) {
                $quest->setQuestBitwise('world', 3, 1);
            }
            if ($this->buildings[$i]['item_id'] == 15 && $this->buildings[$i]['level'] >= 5) {
                $quest->setQuestBitwise('world', 9, 1);
            }
            if ($this->buildings[$i]['item_id'] == 10 && $this->buildings[$i]['level'] >= 7) {
                $quest->setQuestBitwise('world', 12, 1);
            }
            if ($this->buildings[$i]['level'] >= 10 && in_array($this->buildings[$i]['item_id'], [25, 26, 44])) {
                $quest->setQuestBitwise('world', 14, 1);
            }
            if ($this->buildings[$i]['level'] > 0 && $this->buildings[$i]['item_id'] == 12) {
                $quest->setQuestBitwise('economy', 3, Quest::QUEST_FINISHED);
            }
            if ($this->buildings[$i]['level'] > 0 && $this->buildings[$i]['item_id'] == 23) {
                $quest->setQuestBitwise('battle', 2, Quest::QUEST_FINISHED);
            }
            if ($this->buildings[$i]['level'] > 0 && $this->buildings[$i]['item_id'] == 13) {
                $quest->setQuestBitwise('battle', 13, Quest::QUEST_FINISHED);
            }
            if ($this->buildings[$i]['level'] > 0 && $this->buildings[$i]['item_id'] == 19) {
                $quest->setQuestBitwise('battle', 3, Quest::QUEST_FINISHED);
            }
            if ($this->buildings[$i]['level'] > 0 && $this->buildings[$i]['item_id'] >= 31 && $this->buildings[$i]['item_id'] <= 33) {
                $quest->setQuestBitwise('battle', 5, Quest::QUEST_FINISHED);
            }
            if ($i <= 18) {
                $resources_levels[$i] = $this->buildings[$i]['level'];
                $resource_levels[$this->buildings[$i]['item_id']][] = $this->buildings[$i]['level'];
            }
        }
        if ($quest->getQuest("economy", 2) == 0) {
            //One of each to level 1
            $count1 = [
                sizeof(array_filter($resource_levels[1],
                    function ($x) {
                        return $x >= 1;
                    })),
                sizeof(array_filter($resource_levels[2],
                    function ($x) {
                        return $x >= 1;
                    })),
                sizeof(array_filter($resource_levels[3],
                    function ($x) {
                        return $x >= 1;
                    })),
                sizeof(array_filter($resource_levels[4],
                    function ($x) {
                        return $x >= 1;
                    })),
            ];
            if (array_sum($count1) >= 4) {
                $quest->setQuestBitwise("economy", 2, 1);
            }
        }
        if ($quest->getQuest("economy", 5) == 0) {
            //One of each to level 2
            $count2 = [
                sizeof(array_filter($resource_levels[1],
                    function ($x) {
                        return $x >= 2;
                    })),
                sizeof(array_filter($resource_levels[2],
                    function ($x) {
                        return $x >= 2;
                    })),
                sizeof(array_filter($resource_levels[3],
                    function ($x) {
                        return $x >= 2;
                    })),
                sizeof(array_filter($resource_levels[4],
                    function ($x) {
                        return $x >= 2;
                    })),
            ];
            if (array_sum($count2) >= 4) {
                $quest->setQuestBitwise("economy", 5, 1);
            }
        }
        $minResourceLevel = min($resources_levels);
        if ($quest->getQuest("economy", 4) == 0 && $minResourceLevel == 1) {
            $quest->setQuestBitwise("economy", 4, 1);
        }
        if ($quest->getQuest("economy", 8) == 0 && $minResourceLevel == 2) {
            $quest->setQuestBitwise("economy", 8, 1);
        }
        if ($quest->getQuest("economy", 12) == 0 && $minResourceLevel == 5) {
            $quest->setQuestBitwise("economy", 12, 1);
        }
    }

    /**
     * @return mixed
     */
    public function getKid()
    {
        return $this->get("kid") == '' ? 0 : $this->get("kid");
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        if (!array_key_exists($name, $this->village)) {
            return null;
        }

        return $this->village[$name];
    }

    /**
     * @param mixed $name
     * @param mixed $level
     */
    private function setSpecialBuildingLevel($name, $level): void
    {
        if ($name == 'Cranny' || $name == 'Trapper') {
            $this->specialBuildingsLvl[$name][] = $level;
        } else {
            $this->specialBuildingsLvl[$name] = $level;
        }
    }

    private function populateOnLoadBuildings(): void
    {
        if ($this->isSupport) {
            return;
        }
        $onLoadBuildings = $this->db->run("SELECT * FROM building_upgrade WHERE kid=? ORDER BY commence ASC, id ASC", [(int)$this->getKid()]);
        $tmp = [];
        while ($building = $onLoadBuildings->fetch(PDO::FETCH_ASSOC)) {
            $this->onLoadBuildings[$building['isMaster'] ? 'master' : 'normal'][$building['id']] = $building;
            $row =& $this->onLoadBuildings[$building['isMaster'] ? 'master' : 'normal'][$building['id']];
            if (!isset($tmp[$row['building_field']])) {
                $tmp[$row['building_field']] = 0;
            }
            ++$tmp[$row['building_field']];
            $lvl = $this->getField($row['building_field'])['level'] + $tmp[$row['building_field']];
            if ($row['isMaster']) {
                ++$this->workers['master'];
                $this->cropLoadingMaster += Formulas::buildingCropConsumption($this->getField($row['building_field'])['item_id'],
                    $lvl);
                continue;
            } else {
                $this->cropLoading += Formulas::buildingCropConsumption($this->getField($row['building_field'])['item_id'],
                    $lvl);
            }
            if ($this->getField($row['building_field'])['item_id'] == 40) {
                ++$this->workers['WW'];
            }
            // A level chained behind another level of the same building (a multi-level order) shares that level's worker.
            if (!(int)($row['chained'] ?? 0)) {
                ++$this->workers[$this->getField($row['building_field'])['item_id'] <= 4 ? 'fieldsNum' : 'buildsNum'];
            }
            ++$this->buildings[$row['building_field']]['upgrade_state'];
        }
    }

    /**
     * @param mixed $field
     * @return mixed
     */
    public function getField($field)
    {
        return !isset($this->buildings[$field]) ? false : $this->buildings[$field];
    }

    private function populateDemolishTasks(): void
    {
        if ($this->isSupport) {
            return;
        }
        $this->demolitionTasks = $this->db->fetchSingleRow("SELECT * FROM demolition WHERE kid={$this->getKid()}");
        if (!$this->demolitionTasks) {
            $this->demolitionTasks = null;
            return;
        }
        ++$this->buildings[$this->demolitionTasks['building_field']]['demolition_state'];
    }

    /**
     * @return \Core\Village
     */
    public static function getInstance()
    {
        if (!(self::$_self instanceof self)) {
            self::$_self = new self();
        }
        return self::$_self;
    }

    /**
     * @return mixed
     */
    public function getCP()
    {
        return $this->get("cp");
    }

    /**
     * @return mixed
     */
    public function getFieldType()
    {
        return $this->get("fieldtype");
    }

    /**
     * @param mixed $k
     * @return (float|int)|null
     */
    public function calculateResourcesTotalBonusProduction($k)
    {
        if ($this->isSupport) {
            return null;
        }
        $percent = 0;
        switch ($k) {
            case 1:
                $percent += ($this->specialBuildingsLvl['Sawmill']) * 5;
                break;
            case 2:
                $percent += ($this->specialBuildingsLvl['Brickyard']) * 5;
                break;
            case 3:
                $percent += ($this->specialBuildingsLvl['IronFoundry']) * 5;
                break;
            case 4:
                $percent += ($this->specialBuildingsLvl['GrainMill']) * 5;
                $percent += ($this->specialBuildingsLvl['Bakery']) * 5;
                break;
        }
        return $percent + \Game\ResourcesHelper::oasisBonus($this->getKid())['bonus'][$k - 1];
    }

    /**
     * @return mixed
     */
    public function getCrannyLvls()
    {
        return $this->specialBuildingsLvl['Cranny'];
    }

    /**
     * @return mixed
     */
    public function getTrapperLvls()
    {
        return $this->specialBuildingsLvl['Trapper'];
    }

    /**
     * @return mixed
     */
    public function hasMarketPlace()
    {
        return $this->specialBuildingsLvl['Marketplace'];
    }

    /**
     * @return mixed
     */
    public function hasWorkshop()
    {
        return $this->specialBuildingsLvl['Workshop'];
    }

    /**
     * @return mixed
     */
    public function hasStable()
    {
        return $this->specialBuildingsLvl['Stable'];
    }

    /**
     * @return mixed
     */
    public function hasBarracks()
    {
        return $this->specialBuildingsLvl['Barracks'];
    }

    /**
     * @return mixed
     */
    public function getPop()
    {
        return $this->get("pop");
    }

    /**
     * @return mixed
     */
    public function getHorseDrinkingPoolLvl()
    {
        return $this->specialBuildingsLvl['HorseDrinkingPool'];
    }

    /**
     * @return mixed
     */
    public function getCelebration()
    {
        return $this->village['celebration'];
    }

    /**
     * @return mixed
     */
    public function getFestival()
    {
        return $this->village['festival'];
    }

    /**
     * @param mixed $value
     */
    public function setCelebration($value): void
    {
        $this->village['celebration'] = $value;
    }

    /**
     * @param mixed $value
     */
    public function setFestival($value): void
    {
        $this->village['festival'] = $value;
    }

    /**
     * @return mixed
     */
    public function getCelebrationType()
    {
        return $this->village['type'];
    }

    /**
     * @param mixed $type
     */
    public function setCelebrationType($type): void
    {
        $this->village['type'] = $type;
    }

    /**
     * @return mixed
     */
    public function getDemolishTask()
    {
        return $this->demolitionTasks;
    }

    /**
     * @param mixed $value
     */
    public function setDemolishTask($value): void
    {
        $this->demolitionTasks = $value;
    }

    /**
     * @param mixed $value
     */
    public function setCapital($value): void
    {
        $this->village['capital'] = $value;
    }

    /**
     * @param int $gid
     * @return list<mixed>|null
     */
    public function getTypeLevel($gid)
    {
        if ($this->isSupport) {
            return null;
        }
        if ($gid == 16) {
            return [$this->buildings[39]['level']];
        }
        $lvl = [];
        if ($gid <= 4) {
            for ($i = 1; $i <= 18; ++$i) {
                if ($this->getField($i)['item_id'] == $gid) {
                    $lvl[] = $this->getField($i)['level'];
                }
            }
            if (!sizeof($lvl)) {
                $lvl[] = 0;
            }

            return $lvl;
        }
        $multi = false;
        if (isset(Formulas::$data['buildings'][$gid - 1]['req']) && isset(Formulas::$data['buildings'][$gid - 1]['req']['multi'])) {
            $multi = Formulas::$data['buildings'][$gid - 1]['req']['multi'] == 'true';
        }
        for ($i = 19; $i <= 40; ++$i) {
            if ($this->getField($i)['item_id'] == $gid) {
                $lvl[] = $this->getField($i)['level'];
                if (!$multi) {
                    break;
                }
            }
        }
        if (!sizeof($lvl)) {
            $lvl[] = 0;
        }

        return $lvl;
    }

    /**
     * @return bool|null
     */
    public function isCapital()
    {
        if ($this->isSupport) {
            return null;
        }

        return $this->village['capital'] == 1;
    }

    /**
     * @param int|string $gid
     * @return bool|int
     */
    public function findBuildingByGid($gid)
    {
        if (!is_numeric($gid)) {
            return false;
        }

        if (!isset($this->buildings[20]['item_id'])) {
            logError('Incomplete buildings for village %s', [$this->getKid()]);
            return 0;
        }

        if ($gid <= 4) {
            for ($i = 1; $i <= 18; ++$i) {
                if ($this->buildings[$i]['item_id'] == $gid) {
                    return $i;
                }
            }

            return 0;
        }
        for ($i = 19; $i <= 40; ++$i) {
            if ($this->buildings[$i]['item_id'] == $gid) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * @param mixed $field
     */
    public function getFieldTitleAsString($field): string
    {
        $title = $this->getFieldTitle($field);
        return $title['newTitle'] . '||' . $title['newText'];
    }

    /**
     * @param mixed $gid
     * @param (float|int) $nextLevel
     */
    private function renderUpgradeCostTooltip($gid, $nextLevel): string
    {
        $cost = Formulas::buildingUpgradeCosts($gid, $nextLevel);
        $duration = Formulas::buildingUpgradeTime($gid, $nextLevel, $this->getMainBuildingLvl(), $this->isWW());
        $html = sprintf(T("Buildings", "upgradeNotices.upCostsToLevel"), $nextLevel) . '<br />'
            . '<div class="inlineIconList resourceWrapper">';
        foreach ($cost as $i => $amount) {
            $html .= '<div class="inlineIcon resources" title=""><i class="r' . ($i + 1) . 'Big"></i>'
                . '<span class="value ">' . (int) $amount . '</span></div>';
        }
        return $html . '</div><br /><div class="inlineIconList resourceWrapper">'
            . '<div class="inlineIcon duration" title=""><i class="clock_medium"></i>'
            . '<span class="value ">' . secondsToString((int) $duration) . '</span></div></div>';
    }

    /**
     * @param mixed $field
     * @return array<string, mixed>|array<string, null>|array<string, string|null>
     */
    public function getFieldTitle($field)
    {
        $field = (int)$field;
        $building = $this->getField($field);
        if (!$building || ($field == 21 && $this->isWW())) {
            return ['newTitle' => null, 'newText' => null];
        }
        if ((int)$building['item_id'] == 0) {
            if ($field == 39) {
                return ['newTitle' => null, 'newText' => T("Buildings", "buildingSites.rallyPoint")];
            }
            if ($field == 99 && $this->isWW()) {
                return ['newTitle' => null, 'newText' => T("Buildings", "buildingSites.WorldWonder")];
            } else {
                return ['newTitle' => null, 'newText' => T("Buildings", "buildingSites.building")];
            }
            //free site of rally point or wall or ww
        }
        $data = ['newTitle' => null, 'newText' => null];
        $data['newTitle'] = T("Buildings", "{$building['item_id']}.title") . ' <span class="level">' . T("Buildings", "level") . ' ' . $building['level'] . '</span>';
        $HTML = &$data['newText'];
        $maxLevel = Formulas::buildingMaxLvl($building['item_id'], $this->isCapital());
        if ($building['upgrade_state'] == 0) {
            if ($building['level'] >= $maxLevel) { //building has reached max
                $HTML .= T("Buildings", "upgradeNotices.completelyUpgraded");
            } else { //show up level
                $HTML .= $this->renderUpgradeCostTooltip($building['item_id'], $building['level'] + 1);
            }

            return $data;
        }
        $k = 1;
        do {
            if ($building['level'] + $k < $maxLevel) {
                $HTML .= '<span class="notice">' . sprintf(T("Buildings", "upgradeNotices.currentlyUpgradingToLevel"), $building['level'] + $k) . '.</span>';
            } else {
                $HTML .= '<span class="notice">' . sprintf(T("Buildings", "upgradeNotices.currentlyReachingMaxLevel"), T("Buildings", "{$building['item_id']}.title")) . '</span>';
            }
            if ($k <= $building['upgrade_state']) {
                $HTML .= "<br />";
            }
            ++$k;
        } while ($k <= $building['upgrade_state']);
        if ($building['level'] + $building['upgrade_state'] < $maxLevel) {
            $HTML .= $this->renderUpgradeCostTooltip($building['item_id'], $building['level'] + $building['upgrade_state'] + 1);
        }
        return $data;
    }

    /**
     * @return bool|null
     */
    public function isWW()
    {
        if ($this->isSupport) {
            return null;
        }

        return $this->village['isWW'] == 1;
    }

    /**
     * @param int $field
     * @param bool $isMaster
     * @return bool|null
     */
    public function upgradeBuilding($field, $isMaster, bool $chained = false)
    {
        if ($this->session->isInVacationMode()) {
            redirect('/options/vacation');
            return false;
        }
        if ($isMaster && !$this->isWW()) {
            if (!$this->session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
                return false;
            }
            if ($this->session->getAvailableGold() < Config::getInstance()->gold->masterBuilderGold) {
                return false;
            }
        }
        if ($this->session->banned() || Config::getInstance()->dynamic->serverFinished) {
            return false;
        }
        if (!isset($this->buildings[$field])) {
            return false;
        }
        if (!$this->getField($field)['item_id']) {
            return false;
        }
        $nextLevel = $this->getField($field)['level'] + $this->getField($field)['upgrade_state'] + 1;
        $item_id = (int)$this->getField($field)['item_id'];
        if ($item_id == 16) {
            $field = 39;
        }
        if (in_array($item_id, \Game\Formulas::WALL_BUILDINGS)) {
            $field = 40;
        }
        if ($item_id == 40) {
            $field = 99;
        }
        if ($isMaster) {
            foreach ($this->onLoadBuildings['master'] as $task) {
                if ($task['building_field'] == $field) {
                    $nextLevel++;
                }
            }
        }
        /* Keep this state when demolition is cancelled. */
        if ($this->getOnDemolishBuildingFieldId() == $field) {
            return false;
        }
        $workers = $this->isWorkersBusy($field <= 18, $item_id == 40);
        if (!$isMaster && !$chained && $workers['isBusy']) {
            return false;
        } else if ($nextLevel > Formulas::buildingMaxLvl($item_id, $this->isCapital())) { //reached max lvl
            return false;
        } else if (($isMaster && $this->isWW() && $item_id <> 40) || ($isMaster && $workers['isMasterBusy'])) { // World Wonder master queue is zero-gold.
            return false;
        } else if ($this->checkArtifactDependencies($item_id) <> 0) {
            return false;
        } else if ($this->checkDependencies($item_id, $nextLevel) <> 0) {
            return false;
        }
        $costs = Formulas::buildingUpgradeCosts($item_id, $nextLevel);
        if (!$this->isResourcesAvailable($costs) && (!$isMaster || $item_id == 40)) {
            return false;
        } else if ($isMaster && $this->isResourcesAvailable($costs) && !$workers['isBusy']) {
            return false;
        }
        if (!$isMaster) {
            //finalized
            $commence = $this->getQueueTail((int)$field);
            $duration = Formulas::buildingUpgradeTime($item_id, $nextLevel, $this->getMainBuildingLvl(), $this->isWW());
            $commence += $duration;
            $isMaster = 0;
            $kid = (int)$this->getKid();
            $mdb = DB::getInstance();
            $taskId = 0;
            // Payment, queue row and item type commit together under the village lock.
            $queued = $this->transaction(function () use ($mdb, $kid, $field, $item_id, $commence, $costs, $chained, &$taskId): bool {
                // Another request changed the queue after this page loaded: do not overfill it.
                $current = (int)$mdb->fetchScalar('SELECT COUNT(*) FROM building_upgrade WHERE kid=? AND isMaster=0', [$kid]);
                if ($current !== count($this->onLoadBuildings['normal'])) {
                    return false;
                }
                if ($mdb->run("UPDATE fdata SET f{$field}t=? WHERE kid=?", [$item_id, $kid]) === false) {
                    return false;
                }
                $insert = $mdb->run(
                    "INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence, chained) VALUES (?, ?, 0, ?, ?, ?)",
                    [$kid, $field, time(), $commence, $chained ? 1 : 0]
                );
                if ($insert === false || $insert->affected_rows !== 1) {
                    return false;
                }
                $taskId = $mdb->lastInsertId();

                return $this->spend($costs);
            });
            if (!$queued) {
                return false;
            }
            $this->buildings[$field]['item_id'] = $item_id;
            $this->buildings[$field]['upgrade_state']++;
            $this->onLoadBuildings['normal'][$taskId] = [
                "id" => (int)$taskId,
                "kid" => $this->getKid(),
                "building_field" => (int)$field,
                "commence" => (int)$commence,
                'isMaster' => $isMaster,
                'chained' => $chained ? 1 : 0,
            ];
            if (!$chained) {
                ++$this->workers[$field <= 18 ? 'fieldsNum' : 'buildsNum'];
            }
            if (sizeof($this->onLoadBuildings['master'])) {
                $Builder = new MasterBuilder();
                $Builder->updateCommence($this->getKid(), false);
                $this->fetchMasterBuildsAll();
            } else {
                $this->repopCropLoadings();
            }
        } else {
            $this->db->run("UPDATE fdata SET f{$field}t=? WHERE kid=?", [$item_id, $this->getKid()]);
            $this->buildings[$field]['item_id'] = $item_id;
            $commence = time() + $this->calcWhenResourcesAreAvailable($costs);
            if (sizeof($this->onLoadBuildings['master'])) {
                $end = end($this->onLoadBuildings['master']);
                $commence += $end['commence'] - time();
            }
            $isMaster = 1;
            $this->db->run("INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES (?,?,?,?,?)",
                [
                    $this->getKid(),
                    $field,
                    $isMaster,
                    time(),
                    $commence,
                ]);
            $taskId = $this->db->lastInsertId();
            $this->onLoadBuildings['master'][$taskId] = [
                "id" => (int)$taskId,
                "kid" => $this->getKid(),
                "building_field" => (int)$field,
                "commence" => (int)$commence,
                'isMaster' => $isMaster,
            ];
            $Builder = new MasterBuilder();
            $Builder->updateCommence($this->getKid(), false);
            $this->fetchMasterBuildsAll();
            $this->onLoadBuildings['master'][$taskId]['commence'] = $this->db->fetchScalar("SELECT commence FROM building_upgrade WHERE id=?",
                $taskId);
        }
        $i = $field;
        if (isset($this->specialBuildingsName[$item_id])) {
            $this->setSpecialBuildingLevel($this->specialBuildingsName[$item_id], $this->buildings[$i]['level']);
        }
        return null;
    }

    public function getOnDemolishBuildingFieldId(): int
    {
        if ($this->demolitionTasks == null) {
            return 0;
        }

        return (int)$this->demolitionTasks['building_field'];
    }


    /**
     * @param bool $isField
     * @param bool $isWWQueue
     * @return array<string, bool>
     */
    public function isWorkersBusy($isField, $isWWQueue = false)
    {
        $config = Config::getInstance();
        $hasPlus = $this->session->hasPlus();
        $maxTasks = $hasPlus ? 2 : 1; //its with wonder
        $fieldsNum = $this->workers['fieldsNum'];
        $buildsNum = $this->workers['buildsNum'];
        $master = $this->workers['master'];
        if ($isWWQueue) {
            $maxTasks = 2;
        }
        if ($this->session->getRace() == 1) {
            return [
                'isMasterBusy' => $master >= ($this->isWW() ? $config->masterBuilder->maxTasksInWonder : $config->masterBuilder->maxTasksInNoneWonder),
                'isBusy' => (($isField) ? ($fieldsNum >= $maxTasks) : ($buildsNum >= $maxTasks)) || ($fieldsNum + $buildsNum) >= 3,
                'isPlusUsed' => ($hasPlus ? ($isField ? ($fieldsNum > 0) : ($buildsNum > 0)) : false),
            ];
        }
        return [
            'isMasterBusy' => $master >= ($this->isWW() ? $config->masterBuilder->maxTasksInWonder : $config->masterBuilder->maxTasksInNoneWonder),
            'isBusy' => ($buildsNum + $fieldsNum) >= $maxTasks,
            'isPlusUsed' => ($hasPlus ? (($buildsNum + $fieldsNum) > 0) : false),
        ];
    }

    /**
     * @param int $item_id
     * @return mixed
     */
    public function checkArtifactDependencies($item_id)
    {
        if (!in_array($item_id, [38, 39, 40])) {
            return 0;
        }

        return $this->helper->checkArtifactDependencies($this->session->getAllianceId(),
            $this->session->getPlayerId(),
            $this->session->getKid(),
            $item_id,
            $this->isWW(),
            $this->buildings[99]['level']);
    }

    /**
     * @param int $item_id
     * @param (float|int)|int $level
     * @return mixed
     */
    public function checkDependencies($item_id, $level)
    {
        $dep = $this->helper->checkDependencies($item_id,
            $level,
            $this->isWW(),
            $this->village['cropp'] - $this->village['pop'] - $this->cropLoading - $this->cropLoadingMaster,
            $this->get("maxstore"),
            $this->get("maxcrop"));

        return $dep;
    }

    /**
     * @param int $type
     * @return (float|int)|list<(float|int)>
     */
    public function getProduction($type = -1)
    {
        $production = [
            round($this->village['woodp']),
            round($this->village['clayp']),
            round($this->village['ironp']),
            round($this->village['cropp'] - $this->village['pop'] - $this->village['upkeep']),
        ];
        // We need bare production - village pop only
        $production[4] = $this->village['cropp'] - $this->village['pop'] - $this->cropLoading - $this->cropLoadingMaster;
        if ($type > -1) {
            return $production[$type];
        }
        return $production;
    }

    /**
     * @return (array<mixed>|float|int)
     */
    public function getCropLoading()
    {
        return $this->cropLoading + $this->cropLoadingMaster;
    }

    /**
     * @param mixed $costs
     */
    public function isResourcesAvailable($costs): bool
    {
        $cur_res = $this->getCurrentResources(-1, true);
        for ($i = 0; $i < 4; ++$i) {
            if (floor($cur_res[$i]) < $costs[$i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param int $type
     * @param bool $round
     * @return mixed
     */
    public function getCurrentResources($type = -1, $round = true)
    {
        $resources = [
            $this->village['wood'],
            $this->village['clay'],
            $this->village['iron'],
            max($this->village['crop'], 0),
        ];
        if ($round) {
            foreach ($resources as &$v) {
                $v = floor($v);
            }
        }

        return $type <> -1 ? $resources[$type] : $resources;
    }

    /**
     * Name of the construction queue a field belongs to. Romans run a resource
     * field queue (1-18) and an infrastructure queue (19-99) in parallel; every
     * other tribe has a single queue.
     *
     * @return list<int>
     */
    private function getQueueBounds(int $field): array
    {
        if ($this->session->getRace() == 1) {
            return $field <= 18 ? [1, 18] : [19, 99];
        }

        return [0, 99];
    }

    /**
     * When the queue that $field belongs to becomes free: the latest end time
     * of the queued (non-master) tasks in that queue, or now when it is empty
     * or already past. Bounds are inclusive so fields 1/18 and slot 19 chain.
     */
    private function getQueueTail(int $field): int
    {
        [$minField, $maxField] = $this->getQueueBounds($field);
        // Read through the connection that queues the orders: the levels of a multi-level order are inserted inside one
        // open transaction that a second connection cannot see.
        $tail = (int)DB::getInstance()->fetchScalar(
            "SELECT MAX(commence) FROM building_upgrade WHERE kid=? AND isMaster=0 AND building_field BETWEEN ? AND ?",
            [(int)$this->getKid(), $minField, $maxField]
        );

        return max(time(), $tail);
    }

    /**
     * @return mixed
     */
    public function getMainBuildingLvl()
    {
        return $this->specialBuildingsLvl['MainBuilding'];
    }

    /**
     * Atomically debits $costs ([wood, clay, iron, crop]) with a guarded relative
     * UPDATE. Returns false (and changes nothing) when any stock is below its cost.
     * Run it inside {@see transaction()} together with the row that the payment buys.
     *
     * @param array<mixed> $costs
     */
    public function spend(array $costs): bool
    {
        $costs = $this->normalizeCosts($costs);
        if ($costs === null || !self::debit((int)$this->village['kid'], $costs)) {
            return false;
        }
        foreach (['wood', 'clay', 'iron', 'crop'] as $i => $column) {
            $this->village[$column] -= $costs[$i];
        }

        return true;
    }

    /**
     * The guarded relative debit behind spend(), usable without a loaded village (engine
     * jobs). One UPDATE, so it either takes every amount or none.
     *
     * @param array<int, int|float> $costs four non-negative amounts [wood, clay, iron, crop]
     */
    public static function debit(int $kid, array $costs): bool
    {
        if (array_sum($costs) <= 0) {
            return true;
        }
        $guards = [];
        $params = [...$costs, $kid];
        foreach (['wood', 'clay', 'iron', 'crop'] as $i => $column) {
            if ($costs[$i] > 0) {
                $guards[] = "$column>=?";
                $params[] = $costs[$i];
            }
        }
        $stmt = DB::getInstance()->run(
            'UPDATE vdata SET wood=wood-?, clay=clay-?, iron=iron-?, crop=crop-? WHERE kid=? AND ' . implode(' AND ', $guards),
            $params
        );

        return $stmt !== false && $stmt->affected_rows === 1;
    }

    /**
     * What cancelling the queued construction of `$itemId` to `$level` gives back: the cost of the previous level (the
     * full cost for level 1), never more than the order was charged. The abort dialog shows the share as the
     * wood cost ratio rounded down. See docs/world-speed.md, "Cancelling a construction".
     *
     * @return array{percent:int, amounts:list<int>}
     */
    public static function cancelRefund(int $itemId, int $level): array
    {
        $charged = array_map('intval', array_slice(array_values(Formulas::buildingUpgradeCosts($itemId, $level)), 0, 4));
        if ($level <= 1) {
            return ['percent' => 100, 'amounts' => $charged];
        }
        $previous = array_map('intval', array_slice(array_values(Formulas::buildingUpgradeCosts($itemId, $level - 1)), 0, 4));
        $amounts = array_map('min', $previous, $charged);
        $percent = $charged[0] > 0 ? min(100, intdiv(100 * $amounts[0], $charged[0])) : 100;

        return ['percent' => $percent, 'amounts' => $amounts];
    }

    /**
     * Relative credit, clamped to the warehouse and granary capacity.
     *
     * @param array<mixed> $amounts
     */
    public function refund(array $amounts): bool
    {
        $amounts = $this->normalizeCosts($amounts);
        if ($amounts === null) {
            return false;
        }
        if (array_sum($amounts) <= 0) {
            return true;
        }
        $stmt = DB::getInstance()->run(
            'UPDATE vdata SET wood=LEAST(maxstore, wood+?), clay=LEAST(maxstore, clay+?), iron=LEAST(maxstore, iron+?), crop=LEAST(maxcrop, crop+?) WHERE kid=?',
            [$amounts[0], $amounts[1], $amounts[2], $amounts[3], (int)$this->village['kid']]
        );
        if ($stmt === false) {
            return false;
        }
        foreach (['wood', 'clay', 'iron'] as $i => $column) {
            $this->village[$column] = min($this->village[$column] + $amounts[$i], $this->village['maxstore']);
        }
        $this->village['crop'] = min($this->village['crop'] + $amounts[3], $this->village['maxcrop']);

        return true;
    }

    /** Nesting depth of transaction(): a nested call joins the open transaction. */
    private int $transactionDepth = 0;

    /**
     * Runs a multi-step handler under a per-village action lock and one DB
     * transaction. The callback must return true to commit; false, or an
     * exception, rolls everything back (including a prior spend()).
     */
    public function transaction(callable $callback): bool
    {
        if ($this->transactionDepth > 0) {
            // Already inside the village's transaction (a multi-level order queues each level through the same
            // path): join it. The outermost call commits or rolls back.
            return $callback() === true;
        }
        $db = DB::getInstance();
        $lock = 'v:' . (int)$this->village['kid'];
        $got = $db->fetchScalar("SELECT GET_LOCK('$lock', 5)");
        if ((int)$got !== 1) {
            return false;
        }
        try {
            if (!$db->begin_transaction()) {
                return false;
            }
            ++$this->transactionDepth;
            try {
                $ok = $callback() === true;
            } catch (\Throwable $exception) {
                $db->rollback();
                throw $exception;
            } finally {
                --$this->transactionDepth;
            }
            if (!$ok) {
                $db->rollback();

                return false;
            }

            return (bool)$db->commit();
        } finally {
            $db->query("SELECT RELEASE_LOCK('$lock')");
        }
    }

    /**
     * @return int[]|float[]|null four non-negative amounts, or null when invalid
     *
     * @param array<mixed> $costs
     */
    private function normalizeCosts(array $costs): ?array
    {
        $normalized = [];
        for ($i = 0; $i < 4; ++$i) {
            $value = $costs[$i] ?? 0;
            if (!is_numeric($value) || $value < 0) {
                return null;
            }
            $normalized[] = $value + 0;
        }

        return $normalized;
    }

    /**
     * Persist load-time accrual with an optimistic lastmupdate guard so a concurrent
     * writer that already advanced the row is not overwritten (avoids double accrual).
     */
    private function persistAccruedResources(int $previousLastmupdate): bool
    {
        $stmt = $this->db->run(
            'UPDATE vdata SET wood=?, clay=?, iron=?, crop=?, lastmupdate=? WHERE kid=? AND lastmupdate=?',
            [
                $this->village['wood'],
                $this->village['clay'],
                $this->village['iron'],
                $this->village['crop'],
                $this->village['lastmupdate'],
                $this->village['kid'],
                $previousLastmupdate,
            ]
        );

        return $stmt->rowCount() > 0;
    }

    private function fetchMasterBuildsAll(): void
    {
        $this->onLoadBuildings['master'] = [];
        $masterBuilders = $this->db->run("SELECT * FROM building_upgrade WHERE isMaster=1 AND kid=? ORDER BY id",
            $this->getKid());
        while ($row = $masterBuilders->fetch(PDO::FETCH_ASSOC)) {
            $this->onLoadBuildings['master'][$row['id']] = $row;
        }
        $this->repopCropLoadings();
    }

    public function repopCropLoadings(): void
    {
        $tmp = [];
        $this->cropLoading = $this->cropLoadingMaster = 0;
        $find = $this->onLoadBuildings['normal'];
        foreach ($find as $row) {
            if (!isset($tmp[$row['building_field']])) {
                $tmp[$row['building_field']] = 0;
            }
            ++$tmp[$row['building_field']];
            $lvl = $this->getField($row['building_field'])['level'] + $tmp[$row['building_field']];
            if ($row['isMaster']) {
                ++$this->workers['master'];
                $this->cropLoadingMaster += Formulas::buildingCropConsumption($this->getField($row['building_field'])['item_id'],
                    $lvl);
            } else {
                $this->cropLoading += Formulas::buildingCropConsumption($this->getField($row['building_field'])['item_id'],
                    $lvl);
            }
        }
        $this->workers['master'] = 0;
        $find = $this->onLoadBuildings['master'];
        foreach ($find as $row) {
            if (!isset($tmp[$row['building_field']])) {
                $tmp[$row['building_field']] = 0;
            }
            ++$tmp[$row['building_field']];
            $lvl = $this->getField($row['building_field'])['level'] + $tmp[$row['building_field']];
            if ($row['isMaster']) {
                ++$this->workers['master'];
                $this->cropLoadingMaster += Formulas::buildingCropConsumption($this->getField($row['building_field'])['item_id'],
                    $lvl);
            } else {
                $this->cropLoading += Formulas::buildingCropConsumption($this->getField($row['building_field'])['item_id'],
                    $lvl);
            }
        }
    }

    /**
     * @param mixed $cost
     * @param bool $asString
     * @return float|int|string
     */
    public function calcWhenResourcesAreAvailable($cost, $asString = false)
    {
        $code = 0;
        do {
            if ($cost[0] > $this->village['maxstore'] || $cost[1] > $this->village['maxstore'] || $cost[2] > $this->village['maxstore']) {
                if ($this->village['maxstore'] <= Formulas::storeCAP(0)) {
                    $code = 22;
                    break;
                }
                $code = 2;
                break;
            }
            if ($cost[3] > $this->village['maxcrop']) {
                if ($this->village['maxcrop'] <= Formulas::storeCAP(0)) {
                    $code = 33;
                    break;
                }
                $code = 3;
                break;
            }
        } while(false);

        if($code > 0){
            if($asString){
                switch ($code) {
                    case 1:
                        return "<div class=\"errorMessage\">" . T("Buildings", "errors.foodShortage") . "</div>";
                    case 2:
                        return "<div class=\"errorMessage\">" . T("Buildings", "errors.upgradeWareHouse") . "</div>";
                    case 22:
                        return "<div class=\"errorMessage\">" . T("Buildings", "errors.constructWarehouse") . "</div>";
                    case 3:
                        return "<div class=\"errorMessage\">" . T("Buildings", "errors.upgradeGranny") . "</div>";
                    case 33:
                        return "<div class=\"errorMessage\">" . T("Buildings", "errors.constructGranny") . "</div>";
                }
            }
            return -1;
        }

        $timeNeeded = 0;
        $cur_res = $this->getCurrentResources(-1, true);
        $cur_prod = $this->getProduction();
        $minus = false;
        for ($i = 0; $i < 4; ++$i) {
            if (floor($cur_res[$i]) < $cost[$i]) {
                if ($i == 3 && $cur_prod[$i] <= 0) {
                    $minus = true;
                    break;
                } else {
                    $neededTime = ($cost[$i] - floor($cur_res[$i])) / $cur_prod[$i] * 3600000;
                }
                if ($neededTime > $timeNeeded) {
                    $timeNeeded = $neededTime;
                }
            }
        }
        $timeNeeded /= 1000;
        if ($asString) {
            if ($minus) {
                return '<div class="errorMessage">' . T("Buildings", "enoughResourcesAtNever") . '</div>';
            }
            return '<div class="errorMessage">' . sprintf(T("Buildings", "enoughResourcesAt"), TimezoneHelper::autoDateString(time() + (int) floor($timeNeeded), true)) . '</div>';
        }
        if ($minus) {
            $timeNeeded = -1;
        }
        return floor($timeNeeded);
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->get("name");
    }

    /**
     * @return (float|int)
     */
    public function getLoyalty()
    {
        $points = 0;
        if ($this->village['loyalty'] < 100) {
            $points = max($this->specialBuildingsLvl['Palace'] + $this->specialBuildingsLvl['Residence'], 0);
            $points /= 3600;
            $elapsedSeconds = time() - $this->village['last_loyalty_update'];
            $points *= $elapsedSeconds;
            $loyalty = min(100, $this->village['loyalty'] + $points);
        } else {
            $loyalty = min(125, $this->village['loyalty'] + $points);
        }
        return $loyalty;
    }

    /**
     * @param mixed $fieldId
     * @param bool $complete
     * @param bool $checkOnly
     * @phpstan-impure
     */
    public function demolishBuilding($fieldId, $complete = false, $checkOnly = false): bool
    {
        if ($this->session->isInVacationMode()) {
            redirect('/options/vacation');
        }
        if (!isset($this->buildings[$fieldId])) {
            return false;
        }
        if (!$this->getField($fieldId)['item_id']) {
            return false;
        }
        if ($this->getOnDemolishBuildingFieldId()) {
            return false;
        }
        if (Config::getInstance()->dynamic->serverFinished || $this->session->banned()) {
            return false;
        }
        if ($checkOnly) {
            return true;
        }
        foreach ($this->onLoadBuildings['normal'] as $key => $build) {
            if ($build['building_field'] == $fieldId) {
                $this->removeBuilding($key);
            }
        }
        foreach ($this->onLoadBuildings['master'] as $key => $build) {
            if ($build['building_field'] == $fieldId) {
                $this->removeBuilding($key);
            }
        }
        if ($complete) {
            BuildingAction::downgrade($this->getKid(), $fieldId, 1, true);
            return true;
        }
        $up_time = Formulas::buildingUpgradeTime(
            $this->getField($fieldId)['item_id'],
            max($this->getField($fieldId)['level'] - 1, 1),
            $this->getMainBuildingLvl(),
            $this->isWW()
        );
        $demolishTime = time() + (int)round($up_time / 4);
        $this->db->run(
            'INSERT INTO demolition (kid, building_field, end_time, complete) VALUES (?,?,?,?)',
            [$this->getKid(), $fieldId, $demolishTime, 0]
        );
        $this->demolitionTasks = [
            'id' => $this->db->lastInsertId(),
            'kid' => $this->getKid(),
            'building_field' => $fieldId,
            'end_time' => $demolishTime,
            'complete' => 0,
        ];
        return true;
    }

    /**
     * @param mixed $id
     * @return bool|null
     */
    public function removeBuilding($id)
    {
        $updateMaster = sizeof($this->onLoadBuildings['master']);
        if (!isset($this->onLoadBuildings['normal'][$id]) && !isset($this->onLoadBuildings['master'][$id])) {
            return false;
        }
        if ($this->session->banned() || Config::getInstance()->dynamic->serverFinished) {
            return false;
        }
        // The levels chained behind this one (a multi-level order) go with it, last level first, each refunded for itself.
        foreach (array_reverse($this->chainedFollowerIds((int)$id)) as $followerId) {
            $this->removeBuilding($followerId);
        }
        $row = $this->onLoadBuildings[isset($this->onLoadBuildings['normal'][$id]) ? 'normal' : 'master'][$id];
        $item_id = $this->buildings[$row['building_field']]['item_id'];
        $queuedLevel = !$row['isMaster']
            ? $this->getQueuedBuildingLevel((int)$row['building_field'], (int)$row['id'])
            : 0;
        if ($row['building_field'] > 18 && $row['building_field'] < 99 && $this->getField($row['building_field'])['level'] == 0) {
            $x = $this->getField($row['building_field'])['upgrade_state'];
            foreach ($this->onLoadBuildings['master'] as $k => $v) {
                if ($v['building_field'] == $row['building_field']) {
                    $x++;
                }
            }
            if ($x == 1) {
                $this->db->run("UPDATE fdata SET f{$row['building_field']}t=0 WHERE kid=?", $this->getKid());
                $this->buildings[$row['building_field']]['item_id'] = 0;
            }
        }
        unset($this->onLoadBuildings[isset($this->onLoadBuildings['normal'][$id]) ? 'normal' : 'master'][$id]);
        if (!$row['isMaster']) {
            // Refund the level this exact queued order was charged for, not the
            // currently completed level or a task that remains after removal.
            // Only the request whose DELETE removed the row refunds it.
            $deleted = $this->db->run("DELETE FROM building_upgrade WHERE id=?", (int)$id);
            if ($deleted->rowCount() === 1) {
                $this->refund(self::cancelRefund((int)$item_id, (int)$queuedLevel)['amounts']);
            }
        } else {
            $this->db->run("DELETE FROM building_upgrade WHERE id=?", (int)$id);
        }
        if ($this->getField($row['building_field'])['item_id'] == 40) {
            --$this->workers['WW'];
        }
        if ($row['isMaster']) {
            --$this->workers['master'];
        } else {
            if (!(int)($row['chained'] ?? 0)) {
                --$this->workers[$this->getField($row['building_field'])['item_id'] <= 4 ? 'fieldsNum' : 'buildsNum'];
            }
            --$this->buildings[$row['building_field']]['upgrade_state'];
        }

        if (!$row['isMaster']) {
            $this->recalculateBuildingTimes((int)$row['commence'], (int)$row['building_field']);
        }
        //update master builder here
        unset($row);
        if ($updateMaster) {
            $master = new MasterBuilder();
            $master->updateCommence($this->getKid(), false);
            $this->fetchMasterBuildsAll();
        } else {
            $this->repopCropLoadings();
        }
        return null;
    }

    /**
     * The queued levels that follow `$taskId` in a multi-level order: the chained rows of the same building that
     * start after it, in queue order.
     *
     * @return list<int>
     */
    private function chainedFollowerIds(int $taskId): array
    {
        $task = $this->onLoadBuildings['normal'][$taskId] ?? null;
        if ($task === null) {
            return [];
        }
        $followers = array_filter(
            $this->onLoadBuildings['normal'],
            static fn (array $other): bool => (int)$other['building_field'] === (int)$task['building_field']
                && (int)($other['chained'] ?? 0) === 1
                && [(int)$other['commence'], (int)$other['id']] > [(int)$task['commence'], (int)$task['id']]
        );
        uasort($followers, static fn (array $left, array $right): int => [(int)$left['commence'], (int)$left['id']] <=> [(int)$right['commence'], (int)$right['id']]);

        return array_map('intval', array_keys($followers));
    }

    /**
     * Target level for one normal construction order. Queue entries do not
     * persist their target level, so derive it from the completed building
     * level and the order's stable (commence, id) queue position.
     */
    private function getQueuedBuildingLevel(int $field, int $taskId): int
    {
        $level = (int)$this->getField($field)['level'];
        $tasks = $this->onLoadBuildings['normal'];
        uasort($tasks, static function (array $left, array $right): int {
            return [(int)$left['commence'], (int)$left['id']] <=> [(int)$right['commence'], (int)$right['id']];
        });
        foreach ($tasks as $task) {
            if ((int)$task['building_field'] !== $field) {
                continue;
            }
            ++$level;
            if ((int)$task['id'] === $taskId) {
                return $level;
            }
        }

        throw new \LogicException("Queued building task {$taskId} is missing from field {$field}.");
    }

    /**
     * Re-chain queued tasks after a cancel. Each task starts when the task
     * before it in the same queue ends (never earlier than now) and runs for
     * its own full duration.
     *
     * With $removedCommence (end time of the cancelled task) only the tasks
     * that followed it in its queue ($removedField) are shifted; tasks ahead of
     * it, and tasks in the other queue, keep their end time.
     * Without it the first task of each queue is treated as running and kept.
     */
    public function recalculateBuildingTimes(?int $removedCommence = null, ?int $removedField = null): void
    {
        $tasks =& $this->onLoadBuildings['normal'];
        uasort($tasks, function ($a, $b) {
            return [(int)$a['commence'], (int)$a['id']] <=> [(int)$b['commence'], (int)$b['id']];
        });
        $now = time();
        $levels = [];
        $previousEnd = [];
        foreach ($tasks as &$task) {
            $field = (int)$task['building_field'];
            $building = $this->getField($field);
            $levels[$field] = ($levels[$field] ?? (int)$building['level']) + 1;
            $queue = implode('-', $this->getQueueBounds($field));
            if ($removedCommence === null) {
                $keep = !isset($previousEnd[$queue]);
            } else {
                $keep = $queue !== implode('-', $this->getQueueBounds((int)$removedField))
                    || (int)$task['commence'] < $removedCommence;
            }
            if (!$keep) {
                $start = max($now, $previousEnd[$queue] ?? 0);
                $queuedDuration = Formulas::buildingUpgradeTime(
                    $building['item_id'],
                    $levels[$field],
                    $this->getMainBuildingLvl(),
                    $this->isWW()
                );
                $task['commence'] = $start + $queuedDuration;
                $this->db->run("UPDATE building_upgrade SET commence=? WHERE id=?", [(int)$task['commence'], (int)$task['id']]);
            }
            $previousEnd[$queue] = (int)$task['commence'];
        }
        unset($task);
    }

    /**
     * @param int $field
     * @param int $gid
     * @param bool $isMaster
     * @return bool|null
     */
    public function constructBuilding($field, $gid, $isMaster)
    {
        if ($this->session->isInVacationMode()) {
            redirect('/options/vacation');
        }
        if ($isMaster && !$this->isWW()) {
            if (!$this->session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
                return false;
            }
            if ($this->session->getAvailableGold() < Config::getInstance()->gold->masterBuilderGold) {
                return false;
            }
        }
        if ($this->session->banned()) {
            return false;
        }
        if (Config::getInstance()->dynamic->serverFinished) {
            return false;
        }
        if ($this->isWW() && in_array($field, [21, 26, 30, 31, 32, 99])) {
            $field = 99;
        }
        if (!isset($this->buildings[$field])) {
            return false;
        }
        $nextLevel = 1;
        $item_id = (int)$gid;
        if ($isMaster && $this->isWW() && $item_id <> 40) {
            return false;
        }
        if ($item_id == 16) {
            $field = 39;
        }
        if (in_array($item_id, \Game\Formulas::WALL_BUILDINGS)) {
            $field = 40;
        }
        if ($item_id == 40) {
            $field = 99;
        }
        //fuck u users.
        if ($field == 40 && !in_array($item_id, \Game\Formulas::WALL_BUILDINGS)) {
            return false;
        } else if ($field == 99 && $item_id <> 40) {
            return false;
        } else if ($field == 39 && $item_id <> 16) {
            return false;
        }
        if ($this->getField($field)['item_id'] > 0) {
            return false;
        }
        $workers = $this->isWorkersBusy($field <= 18);
        if ($this->canCreateNewBuild($item_id) <> 1) {
            return false;
        } else if (!$isMaster && $workers['isBusy']) {
            return false;
        } else if ($nextLevel > Formulas::buildingMaxLvl($item_id, $this->isCapital())) { //reached max lvl
            return false;
        } else if ($isMaster && $workers['isMasterBusy']) {
            return false;
        } else if ($this->checkArtifactDependencies($item_id) <> 0) {
            return false;
        } else if ($this->checkDependencies($item_id, $nextLevel) <> 0) {
            return false;
        }
        $costs = Formulas::buildingUpgradeCosts($item_id, $nextLevel);
        if (!$this->isResourcesAvailable($costs) && (!$isMaster || $item_id == 40)) {
            return false;
        } else if ($isMaster && $this->isResourcesAvailable($costs) && !$workers['isBusy']) {
            return false;
        }
        if (!$isMaster) {
            $commence = $this->getQueueTail((int)$field);
            $commence += Formulas::buildingUpgradeTime($item_id,
                $nextLevel,
                $this->getMainBuildingLvl(),
                $this->isWW());
            $isMaster = 0;
            $kid = (int)$this->getKid();
            $mdb = DB::getInstance();
            $taskId = 0;
            // Payment, queue row and item type commit together under the village lock.
            $queued = $this->transaction(function () use ($mdb, $kid, $field, $item_id, $commence, $costs, &$taskId): bool {
                // Another request changed the queue after this page loaded: do not overfill it.
                $current = (int)$mdb->fetchScalar('SELECT COUNT(*) FROM building_upgrade WHERE kid=? AND isMaster=0', [$kid]);
                if ($current !== count($this->onLoadBuildings['normal'])) {
                    return false;
                }
                $claimed = $mdb->run("UPDATE fdata SET f{$field}t=? WHERE kid=? AND f{$field}t=0", [$item_id, $kid]);
                if ($claimed === false || $claimed->affected_rows !== 1) {
                    return false;
                }
                $insert = $mdb->run(
                    "INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES (?, ?, 0, ?, ?)",
                    [$kid, $field, time(), $commence]
                );
                if ($insert === false || $insert->affected_rows !== 1) {
                    return false;
                }
                $taskId = $mdb->lastInsertId();

                return $this->spend($costs);
            });
            if (!$queued) {
                return false;
            }
            $this->buildings[$field]['item_id'] = $item_id;
            $this->buildings[$field]['upgrade_state']++;
            $this->onLoadBuildings['normal'][$taskId] = [
                "id" => $taskId,
                "kid" => $this->getKid(),
                "building_field" => $field,
                "commence" => $commence,
                'isMaster' => $isMaster,
            ];
            if (sizeof($this->onLoadBuildings['master'])) {
                (new MasterBuilder())->updateCommence($this->getKid(), false);
            }
            if ($this->getField($field)['item_id'] == 40) {
                ++$this->workers['WW'];
            }
            ++$this->workers[$field <= 18 ? 'fieldsNum' : 'buildsNum'];
        } else {
            $this->db->run("UPDATE fdata SET f{$field}t=? WHERE kid=?", [$item_id, $this->getKid()]);
            $this->buildings[$field]['item_id'] = $item_id;
            $commence = time() + $this->calcWhenResourcesAreAvailable($costs);
            if (sizeof($this->onLoadBuildings['master'])) {
                $end = end($this->onLoadBuildings['master']);
                $commence += $end['commence'] - time();
            }
            $isMaster = 1;

            $this->db->run("INSERT INTO building_upgrade (kid, building_field, isMaster, start_time, commence) VALUES (?,?,?,?,?)",
                [
                    $this->getKid(),
                    $field,
                    $isMaster,
                    time(),
                    $commence,
                ]);

            $taskId = $this->db->lastInsertId();
            $this->onLoadBuildings['master'][$taskId] = [
                "id" => $taskId,
                "kid" => $this->getKid(),
                "building_field" => $field,
                "commence" => $commence,
                'isMaster' => $isMaster,
            ];
            $Builder = new MasterBuilder();
            $Builder->updateCommence($this->getKid(), false);
            $this->onLoadBuildings['master'][$taskId]['commence'] = $this->db->fetchScalar("SELECT commence FROM building_upgrade WHERE id=?",
                $taskId);
        }
        $i = $field;
        if (isset($this->specialBuildingsName[$item_id])) {
            $this->setSpecialBuildingLevel($this->specialBuildingsName[$item_id], $this->buildings[$i]['level']);
        }
        $this->repopCropLoadings();
        return null;
    }

    /**
     * @param int $item_id
     * @return mixed
     */
    public function canCreateNewBuild($item_id)
    {
        if ($item_id == 40 && !$this->isWW()) return -1;
        $dep = $this->helper->canCreateNewBuild($this->isCapital(),
            $this->session->getRace(),
            $item_id,
            $this->buildings,
            FALSE,
            $item_id == 49 && $this->helper->isShoreVillage((int)$this->get('kid')));
        if ($item_id == 26) {
            if ($this->helper->hasPalaceAnywhere($this->get('owner'))) {
                $dep = -1;
            }
        }
        return $dep;
    }

    /**
     * @param mixed $cost
     * @return array<string, int|null>|array<string, mixed>
     */
    public function contractResourcesLink($cost)
    {
        if ($this->isResourcesAvailable($cost)) {
            return ["code" => 0, "text" => null];
        }
        $needUpgradeType = $this->helper->checkWarehouseDependencies(
            $this->village['maxstore'],
            $this->village['maxcrop'],
            $cost
        );
        switch ($needUpgradeType) {
            case 1:
                return [
                    "code" => $needUpgradeType,
                    "text" => "<span class=\"errorMessage\">" . T("Buildings", "errors.foodShortage") . "</span>",
                ];
            case 2:
                return [
                    "code" => $needUpgradeType,
                    "text" => "<span class=\"errorMessage\">" . T("Buildings", "errors.upgradeWareHouse") . "</span>",
                ];
            case 22:
                return [
                    "code" => $needUpgradeType,
                    "text" => "<span class=\"errorMessage\">" . T("Buildings", "errors.constructWarehouse") . "</span>",
                ];
            case 3:
                return [
                    "code" => $needUpgradeType,
                    "text" => "<span class=\"errorMessage\">" . T("Buildings", "errors.upgradeGranny") . "</span>",
                ];
            case 33:
                return [
                    "code" => $needUpgradeType,
                    "text" => "<span class=\"errorMessage\">" . T("Buildings", "errors.constructGranny") . "</span>",
                ];
        }

        return [
            "code" => -1,
            "text" => $this->calcWhenResourcesAreAvailable($cost, true),
        ];
    }

    /**
     * @return list<(int|string)>
     */
    public function getEmptyFields()
    {
       return array_keys(
            array_filter($this->buildings, function($elem, $index){
               return $elem['item_id'] === 0 && $index >18 && $index < 39;
            },ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param mixed $gid
     * @return array<mixed>
     */
    public function findBuildingsByGid($gid)
    {
        return array_filter($this->buildings, function($elem) use($gid){
            return $elem['item_id'] == $gid;
         });
    }
}
