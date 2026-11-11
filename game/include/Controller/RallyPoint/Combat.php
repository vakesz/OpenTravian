<?php

namespace Controller\RallyPoint;

use Core\Session;
use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\Hero\HeroItems;
use Game\NoticeHelper;
use resources\View\PHPBatchView;
use Model\ReportsModel;

class Combat
{
    /** @var mixed */
    private $output = [];

    public function __construct()
    {

        $session = Session::getInstance();
        $this->output = [];
        $this->output['uid'] = $session->getPlayerId();
        $this->output['attack_type'] = 3;
        $this->output['attacker'] = [
            'race'                  => $session->getRace(),
            'units'                 => array_fill(0, 10, ["number" => 0, "level" => 0,]),
            'pop'                   => 1,
            'catapult_target_level' => 0,
            'hero'                  => false,
            'heroData'              => [],
            'allianceBonus'         => 1.0,
            'artefactSpy'           => 0.0,
            'brewery'               => 0,
            'celebration'           => false,
        ];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && isset($_GET['kid']) && is_numeric($_GET['kid'])) {
            $_POST += $this->oasisRaidInput((int)$_GET['kid']);
        }
        $this->output['role'] = ($_POST['role'] ?? '') === 'defender' ? 'defender' : 'attacker';
        $this->output['heroEquipment'] = $this->simulatorHeroEquipment();
        $this->output['villageSnapshots'] = $this->ownVillageSnapshots();
        $this->output['villages'] = array_values(array_map(
            static fn (array $snapshot): array => ['kid' => (int)$snapshot['kid'], 'name' => (string)$snapshot['name']],
            $this->output['villageSnapshots']
        ));
        $this->output['village'] = 0;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            // The reference opens with the active village selected and its troops loaded.
            $_POST['village'] = (int)$session->getKid();
            $_POST['reloadVillage'] = 1;
        }
        $this->prefillFromVillage();
        if (!in_array($this->output['attacker']['race'], self::attackerTribes(), true)) {
            $this->output['attacker']['race'] = self::attackerTribes()[0];
        }
        if (isset($_POST['a1_v']) && in_array((int)$_POST['a1_v'], self::attackerTribes(), true)) {
            $this->output['attacker']['race'] = (int)$_POST['a1_v'];
        }
        $this->output['attacker']['hero'] = isset($_POST['a1_hero']) && $_POST['a1_hero'] >= 1;
        $this->output['attacker']['heroData'] = $this->simulatorHeroData(
            (int)$this->output['attacker']['race'],
            'off',
            'a1'
        );
        for ($i = 1; $i <= 10; ++$i) {
            $this->output['attacker']['units'][$i - 1]['number'] = isset($_POST['a1_' . $i]) ? (int)$_POST['a1_' . $i] : 0;
            $this->output['attacker']['units'][$i - 1]['level'] = isset($_POST['f1_' . $i]) ? (int)$_POST['f1_' . $i] : 0;
        }
        $this->output['defender'] = [
            "pop"                 => 1,
            'races'               => [],
            'allianceBonus'       => 1.0,
            'buildingMasterBonus' => 0.0,
            'artefactSpy'         => 0.0,
            'hero'                => false,
            'heroData'            => [],
        ];
        // The reference opens against Romans when you attack, and against you when you defend.
        $main = isset($_POST['a2_main']) ? (int)$_POST['a2_main'] : ($this->output['role'] === 'defender' ? (int)$session->getRace() : self::defenderTribes()[0]);
        if (!in_array($main, self::defenderTribes(), true)) {
            $main = self::defenderTribes()[0];
        }
        $this->output['defender']['main'] = $main;
        $this->output['defender']['hero'] = isset($_POST['a2_hero']) && $_POST['a2_hero'] >= 1;
        $this->output['defender']['heroData'] = $this->simulatorHeroData($main, 'def', 'a2');
        $this->addDefenderTribe($main);
        foreach (self::reinforcementTribes() as $defenderTribe) {
            if (isset($_POST['a2_v' . $defenderTribe])) {
                $this->addDefenderTribe($defenderTribe);
            }
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
                        $data = NoticeHelper::parseReport($report['type'], $report['data']);
                        $_POST['a1_v'] = $this->output['attacker']['race'] = $data['attacker']['race'];
                        $attacker_troops = $data['attacker']['num'];
                        for ($i = 1; $i <= 10; ++$i) {
                            if (isset($data['attacker']['smithy'][$i])) {
                                $this->output['attacker']['units'][$i - 1]['level'] = $data['attacker']['smithy'][$i];
                            }
                            $this->output['attacker']['units'][$i - 1]['number'] = $attacker_troops[$i];
                        }
                        $this->output['defender']['races'] = [];
                        $firstDefender = true;
                        foreach ($data['defender'] as $row) {
                            if ($firstDefender) {
                                $this->output['defender']['main'] = (int)$row['race'];
                                $firstDefender = false;
                            }
                            $this->addDefenderTribe((int)$row['race']);
                            for ($i = 1; $i <= 10; ++$i) {
                                $this->output['defender']['races'][$row['race']]['units'][$i - 1]['number'] = $row['num'][$i];
                            }
                        }
                        break;
                }
            }
        }
        // The simulator indexes defender casualties by wave order.  Preserve the
        // main village first and every selected reinforcement after it: sorting
        // by tribe ID would make both the rendered cards and hero-loss indexes
        // disagree with the result surface (for example Hun main village
        // followed by Roman reinforcement).
        $this->output['defender']['wallLevel'] = 0;
        $this->output['defender']['steinmetz'] = 0;
        $this->output['defender']['palast'] = 0;
        foreach ($this->output['defender']['races'] as $race => &$value) {
            for ($i = 1; $i <= 10; ++$i) {
                if ($value['units'][$i - 1]['number'] == 0) {
                    $value['units'][$i - 1]['number'] = isset($_POST['a2_' . nrToUnitId($i,
                            $race)]) ? (int)$_POST['a2_' . nrToUnitId($i, $race)] : 0;
                }
                if ($race <> 4 && $i < 8) {
                    $value['units'][$i - 1]['level'] = isset($_POST['f2_' . nrToUnitId($i,
                            $race)]) ? (int)$_POST['f2_' . nrToUnitId($i, $race)] : 0;
                }
            }
        }
        unset($value);
        if (isset($_POST['ew1']) && $_POST['ew1'] >= 0) {
            $this->output['attacker']['pop'] = (int)$_POST['ew1'];
        }
        if (isset($_POST['ew2']) && $_POST['ew2'] >= 0) {
            $this->output['defender']['pop'] = (int)$_POST['ew2'];
        }
        if (isset($_POST['palast']) && $_POST['palast'] >= 0) {
            $this->output['defender']['palast'] = min((int)$_POST['palast'], 20);
        }
        // The wall belongs to the village of the main defending tribe (Nature has none).
        $wallKey = 'wall' . $this->output['defender']['main'];
        if ($this->output['defender']['main'] != 4 && isset($_POST[$wallKey]) && $_POST[$wallKey] > 0) {
            $this->output['defender']['wallLevel'] = min((int)$_POST[$wallKey], 20);
        }
        if (isset($_POST['kata']) && $_POST['kata'] > 0) {
            $this->output['attacker']['catapult_target_level'] = (int)$_POST['kata'];
        }
        if (isset($_POST['steinmetz']) && $_POST['steinmetz'] > 0) {
            $this->output['defender']['steinmetz'] = min((int)$_POST['steinmetz'], 20);
        }
        $this->output['attacker']['allianceBonus'] = $this->selectedMultiplier('a1_allyBonus', [1, 1.02, 1.04, 1.06, 1.08, 1.1], 1);
        $this->output['defender']['allianceBonus'] = $this->selectedMultiplier('a2_allyBonus', [1, 1.02, 1.04, 1.06, 1.08, 1.1], 1);
        $this->output['attacker']['artefactSpy'] = $this->selectedMultiplier('a1_artefactSpy', [0, 3, 5, 10, .33, .2, .1], 0);
        $this->output['defender']['artefactSpy'] = $this->selectedMultiplier('a2_artefactSpy', [0, 3, 5, 10, .33, .2, .1], 0);
        $this->output['defender']['buildingMasterBonus'] = $this->selectedMultiplier('a2_buildingMasterBonus', [0, 3, 4, 5, .33, .25, .2], 0);
        if (isset($_POST['a1_brewery']) && $_POST['a1_brewery'] >= 0) {
            $this->output['attacker']['brewery'] = min((int)$_POST['a1_brewery'], 20);
        }
        $this->output['attacker']['celebration'] = isset($_POST['a1_celebration']);
        if (isset($_POST['ktyp']) && $_POST['ktyp'] >= 0 && $_POST['ktyp'] <= 2) {
            $this->output['attack_type'] = (int)$_POST['ktyp'] + 1;
        }
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
            $attack_type = $this->output['attack_type'];
            $sum = 0;
            for ($i = 1; $i <= 10; ++$i) {
                $sum += $this->output['attacker']['units'][$i - 1]['number'];
            }
            if (!$sum) {
                goto output;
            }
            $spyCount = $this->output['attacker']['units'][Formulas::getSpyId($this->output['attacker']['race']) - 1]['number'];
            if ($sum - $spyCount == 0) {
                $attack_type = 1;
            }
            $race = $this->output['defender']['main'];
            $data = [
                "R"              => $this->output['attack_type'] == 3,
                "trapped2killed" => [],
                "defender"       => [
                    "r"         => $race - 1,
                    't'         => 0,
                    "p"         => $this->output['defender']['pop'], //pop
                    "rpLevel"   => $this->output['defender']['palast'], //residence palace level
                    "wLevel"    => $this->output['defender']['wallLevel'], //wall level
                    "stone"     => $this->output['defender']['steinmetz'], //wall level
                    "artifacts" => [
                        "durability" => $this->output['defender']['buildingMasterBonus'],
                        "scout"      => $this->output['defender']['artefactSpy'],
                    ],
                ],
                "waves"          => [],
            ];
            $addDefenderWave = static function ($race, $row, float $allianceBonus, bool $hasHero, array $hero)
            {
                $battle = [
                    "r" => $race - 1,
                    "u" => [],
                    "U" => [],
                    "side" => "def",
                    'h' => $hasHero,
                    'M' => $allianceBonus,
                    'hero' => $hero,
                ];
                foreach ($row['units'] as $k => $v) {
                    $battle['u'][] = $v['number'];
                    $battle['U'][] = $v['level'];
                }
                return $battle;
            };

            foreach ($this->output['defender']['races'] as $rab => $abc) {
                $isMainDefender = (int)$rab === (int)$this->output['defender']['main'];
                $data['waves'][] = $addDefenderWave(
                    $rab,
                    $abc,
                    $this->output['defender']['allianceBonus'],
                    $isMainDefender && (bool)$this->output['defender']['hero'],
                    $isMainDefender ? $this->output['defender']['heroData'] : []
                );
            }
            $battle = [
                "r"    => $this->output['attacker']['race'] - 1,
                "u"    => [],
                "U"    => [],
                "side" => 'off',
                'h'    => (bool)$this->output['attacker']['hero'] && $attack_type <> 1,
                'B'    => $this->output['attacker']['brewery'],
                'C'    => $this->output['attacker']['celebration'],
                'M'    => $this->output['attacker']['allianceBonus'],
                'S'    => $this->output['attacker']['artefactSpy'],
            ];
            foreach ($this->output['attacker']['units'] as $k => &$v) {
                $battle['u'][] =& $v['number'];
                $battle['U'][] =& $v['level'];
            }
            $battle['p'] = $this->output['attacker']['pop'];
            $battle['b'] = [$this->output['attacker']['catapult_target_level'], 0,];
            $battle['hero'] = $this->output['attacker']['heroData'];
            $battle['b'] = [$this->output['attacker']['catapult_target_level'], 0,];
            $data['waves'][] = $battle;
            $combat = new Simulator();
            $response = $combat->init($data);
            $this->output['result'] = $this->renderResult($response);
        }
        output:
        $this->output = PHPBatchView::render('combat/main', $this->output);
    }

    /** @return int[] Playable tribes in ascending id order, the order of the tribe pickers. */
    private static function playableAscending(): array
    {
        $tribes = \Game\Tribes::playable();
        sort($tribes);

        return $tribes;
    }

    /** @return int[] Tribes the attacking side can be set to (order, Natars last). */
    public static function attackerTribes(): array
    {
        return array_merge(self::playableAscending(), [5]);
    }

    /** @return int[] Tribes of the defending village. */
    public static function defenderTribes(): array
    {
        return array_merge(self::playableAscending(), [4, 5]);
    }

    /** @return int[] Tribes that can add reinforcements to the defence (no Natars). */
    public static function reinforcementTribes(): array
    {
        return array_merge(self::playableAscending(), [4]);
    }

    private function addDefenderTribe(int $tribe): void
    {
        $this->output['defender']['races'][$tribe] = [
            "units"     => array_fill(0, 10, ["level" => 0, "number" => 0,]),
        ];
    }

    /** @param float[] $allowed */
    private function selectedMultiplier(string $key, array $allowed, float $default): float
    {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return $default;
        }
        $value = (float)$_POST[$key];
        foreach ($allowed as $option) {
            if (abs($option - $value) < 0.00001) {
                return $option;
            }
        }

        return $default;
    }

    /**
     * The combat simulator is a what-if tool, so its hero fields are bounded
     * simulation inputs rather than account mutations. `Simulator` consumes
     * raw Off/Defence point totals (each point is 0.2%), not the displayed
     * percentage. Keep this conversion in one place for both playable sides.
     *
     * @return array{health:int,strength:int,points:int,horse:bool,total_power:int,offBonus?:int,defBonus?:int,arm:int,n:int,unitBonus:array{unitId:int,power:int},bandage:array{num:int,eff:int},cages:int,selectedItems:array{leftHand:?array{itemTypeId:int,amount:int},body:?array{itemTypeId:int,amount:int},bag:?array{itemTypeId:int,amount:int}},isCavalry?:bool}
     */
    private function simulatorHeroData(int $race, string $side, string $prefix): array
    {
        $health = self::boundedPostInt($prefix . '_hero_health', 100, 0, 100);
        // The simulator constrains the manual hero-strength input
        // to the same 0–100 range as its visible editor. Actual oasis presets use
        // the canonical total-power transport below instead of this manual field.
        $strength = self::boundedPostInt($prefix . '_hero_strength', 0, 0, 100);
        $points = self::boundedPostInt(
            $prefix . ($side === 'off' ? '_hero_off_points' : '_hero_def_points'),
            0,
            0,
            100
        );
        $leftHandType = self::simulatorItemType($prefix . '_hero_left_hand', [76, 77, 78, 79, 80, 81]);
        $rightHandType = self::simulatorItemType(
            $prefix . '_hero_right_hand',
            HeroItems::weaponTypes()
        );
        $bodyType = self::simulatorItemType($prefix . '_hero_body', range(82, 93));
        $bagType = self::simulatorItemType($prefix . '_hero_bag', [112, 113]);
        $bagAmount = self::boundedPostInt($prefix . '_hero_bag_amount', 0, 0, 100000);
        $leftHand = $leftHandType === null ? null : ['btype' => 3, 'type' => $leftHandType];
        $rightHand = $rightHandType === null ? null : ['btype' => 4, 'type' => $rightHandType];
        $body = $bodyType === null ? null : ['btype' => 2, 'type' => $bodyType];
        $bag = $bagType === null ? null : [
            'btype' => $bagType === 112 ? 7 : 8,
            'type' => $bagType,
            'num' => $bagAmount,
        ];
        $heroHelper = new HeroHelper();
        $baseTotalPower = 100 + $strength * ($race === 1 ? 100 : 80)
            + $heroHelper->calcItemPower(0, $leftHand, $body);
        $totalPower = self::boundedPostInt(
            $prefix . '_hero_total_power',
            $baseTotalPower,
            100,
            10000000
        );
        $leftHandProperties = $leftHand === null
            ? []
            : (new HeroItems())->getHeroItemProperties(3, $leftHandType);
        $rightHandProperties = $rightHand === null
            ? []
            : (new HeroItems())->getHeroItemProperties(4, $rightHandType);
        $bandages = $heroHelper->getBandages($bag);
        $hero = [
            'health' => $health,
            'strength' => $strength,
            'points' => $points,
            'horse' => $side === 'off' && (
                isset($_POST[$prefix . '_hero_horse']) || strtolower((string)($_SERVER['REQUEST_METHOD'] ?? 'get')) !== 'post'
            ),
            'total_power' => $totalPower,
            'arm' => self::boundedPostInt($prefix . '_hero_arm', $heroHelper->calcResist($body), 0, 100),
            'n' => self::boundedPostInt($prefix . '_hero_natar_bonus', (int)($leftHandProperties['powerAgainstNatars'] ?? 0), 0, 100),
            'unitBonus' => [
                'unitId' => (int)($rightHandProperties['unitId'] ?? 0),
                'power' => (int)($rightHandProperties['unit_power'] ?? 0),
            ],
            'bandage' => [
                'num' => self::boundedPostInt($prefix . '_hero_bandages', (int)$bandages['num'], 0, 100000),
                'eff' => self::boundedPostInt($prefix . '_hero_bandage_effect', (int)$bandages['eff'], 0, 100),
            ],
            'cages' => self::boundedPostInt($prefix . '_hero_cages', 0, 0, 100000),
            'selectedItems' => [
                'leftHand' => $leftHandType === null ? null : ['itemTypeId' => $leftHandType, 'amount' => 1],
                'body' => $bodyType === null ? null : ['itemTypeId' => $bodyType, 'amount' => 1],
                'bag' => $bagType === null ? null : ['itemTypeId' => $bagType, 'amount' => $bagAmount],
            ],
        ];
        $hero[$side . 'Bonus'] = $points;
        if ($side === 'off') {
            $hero['isCavalry'] = isset($_POST[$prefix . '_hero_horse']);
        }

        return $hero;
    }

    private static function boundedPostInt(string $key, int $default, int $minimum, int $maximum): int
    {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return $default;
        }

        return min($maximum, max($minimum, (int)$_POST[$key]));
    }

    /** @param int[] $allowed */
    private static function simulatorItemType(string $key, array $allowed): ?int
    {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return null;
        }

        $type = (int)$_POST[$key];

        return in_array($type, $allowed, true) ? $type : null;
    }

    /**
     * Classic (non-crafting) simulator catalogue. The catalogue lets the active player
     * choose the displayed left-hand/body/bag items even when an entry is not
     * owned, while marking items that belong to that hero. Keep ownership as
     * presentation data only; a simulation never changes the real inventory.
     *
     * @return array{leftHand:array<int,array{icon:string,title:string,items:array<int,array{type:int,name:string,power:int}>}>,body:array<int,array{icon:string,title:string,items:array<int,array{type:int,name:string,power:int}>}>,bag:array<int,array{icon:string,items:array<int,array{type:int,name:string,power:int}>}>,owned:array{leftHand:array<int,int>,body:array<int,int>,bag:array<int,int>}}
     */
    private function simulatorHeroEquipment(): array
    {
        $heroItems = new HeroItems();
        $makeGroup = static function (string $icon, array $types, ?int $titleType = null) use ($heroItems): array {
            $items = [];
            foreach ($types as $type) {
                $btype = $type >= 112 ? ($type === 112 ? 7 : 8) : ($type >= 76 && $type <= 81 ? 3 : 2);
                $properties = $heroItems->getHeroItemProperties($btype, $type);
                $items[] = [
                    'type' => $type,
                    'name' => (string)$properties['name'],
                    'power' => (int)($properties['hero_power'] ?? 0),
                ];
            }

            $group = ['icon' => $icon, 'items' => $items];
            if ($titleType !== null) {
                $btype = $titleType >= 76 && $titleType <= 81 ? 3 : 2;
                $group['title'] = (string)$heroItems->getHeroItemProperties($btype, $titleType)['name'];
            }

            return $group;
        };
        $owned = ['leftHand' => [], 'body' => [], 'bag' => []];
        $statement = \Core\Database\DB::getInstance()->run(
            'SELECT btype, type, num FROM items WHERE uid=? AND btype IN (2, 3, 7, 8)',
            [(int)Session::getInstance()->getPlayerId()]
        );
        $rows = $statement ? $statement->get_result() : null;
        while ($rows && ($row = $rows->fetch_assoc())) {
            $btype = (int)$row['btype'];
            $type = (int)$row['type'];
            $slot = $btype === 2 ? 'body' : ($btype === 3 ? 'leftHand' : 'bag');
            $owned[$slot][$type] = max((int)($owned[$slot][$type] ?? 0), (int)$row['num']);
        }

        return [
            'leftHand' => [
                $makeGroup('shield', [76, 77, 78], 77),
                $makeGroup('horn', [79, 80, 81], 80),
            ],
            'body' => [
                $makeGroup('scaleArmour', [82, 83, 84], 83),
                $makeGroup('breastPlate', [85, 86, 87], 86),
                $makeGroup('segmentedArmour', [88, 89, 90], 89),
                $makeGroup('armourOfHealth', [91, 92, 93], 92),
            ],
            'bag' => [
                $makeGroup('smallBandage', [112]),
                $makeGroup('bandage', [113]),
            ],
            'owned' => $owned,
        ];
    }

    /**
     * Own-village simulator state. The browser needs these snapshots to reproduce
     * the in-place Reload behaviour without turning a harmless form selection
     * into a full-page POST. The same trusted rows remain the server source for a
     * final calculated POST in prefillFromVillage().
     *
     * @return array<int,array{kid:int,name:string,race:int,population:int,units:array<int,int>,smithy:array<int,int>,wall:int,stonemason:int,palace:int}>
     */
    private function ownVillageSnapshots(): array
    {
        $db = \Core\Database\DB::getInstance();
        $statement = $db->run(
            'SELECT v.kid, v.name, v.pop, u.race FROM vdata v LEFT JOIN units u ON u.kid=v.kid WHERE v.owner=? ORDER BY v.kid',
            [(int)Session::getInstance()->getPlayerId()]
        );
        $result = $statement ? $statement->get_result() : null;
        $baseRows = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $baseRows[(int)$row['kid']] = $row;
        }
        if ($baseRows === []) {
            return [];
        }

        $kidList = implode(',', array_keys($baseRows));
        $readByKid = static function (string $table) use ($db, $kidList): array {
            $rows = $db->query('SELECT * FROM ' . $table . ' WHERE kid IN (' . $kidList . ')');
            $byKid = [];
            while ($row = $rows->fetch_assoc()) {
                $byKid[(int)$row['kid']] = $row;
            }

            return $byKid;
        };
        $unitRows = $readByKid('units');
        $smithyRows = $readByKid('smithy');
        $fieldRows = $readByKid('fdata');
        $snapshots = [];
        foreach ($baseRows as $kid => $base) {
            $units = array_fill(1, 11, 0);
            $smithy = array_fill(1, 10, 0);
            $unitRow = $unitRows[$kid] ?? [];
            $smithyRow = $smithyRows[$kid] ?? [];
            for ($slot = 1; $slot <= 11; ++$slot) {
                $units[$slot] = max(0, (int)($unitRow['u' . $slot] ?? 0));
                if ($slot <= 10) {
                    $smithy[$slot] = max(0, (int)($smithyRow['u' . $slot] ?? 0));
                }
            }
            $fields = $fieldRows[$kid] ?? [];
            $snapshots[$kid] = [
                'kid' => $kid,
                'name' => (string)$base['name'],
                'race' => (int)($base['race'] ?? Session::getInstance()->getRace()),
                'population' => max(1, (int)$base['pop']),
                'units' => $units,
                'smithy' => $smithy,
                'wall' => self::buildingLevel($fields, [31, 32, 33, 42, 43, 47, 50]),
                'stonemason' => self::buildingLevel($fields, [34]),
                'palace' => self::buildingLevel($fields, [25, 26]),
            ];
        }

        return $snapshots;
    }

    /**
     * @param array<mixed> $fields
     * @param list<int> $gids
     */
    private static function buildingLevel(array $fields, array $gids): int
    {
        $level = 0;
        for ($slot = 19; $slot <= 40; ++$slot) {
            if (in_array((int)($fields['f' . $slot . 't'] ?? 0), $gids, true)) {
                $level = max($level, (int)($fields['f' . $slot] ?? 0));
            }
        }

        return $level;
    }

    /**
     * "You" side of the simulator: the reload button copies tribe, home troops,
     * smithy levels, population and (as defender) wall, Stonemason and
     * Residence/Palace of the selected own village into the posted form.
     */
    /**
     * Simulator input for a raid on the animals of a free oasis (map tile "simulate raid" link): the
     * defender is the oasis garrison, the attacker brings the account's hero as equipped.
     *
     * @return array<string, int|string>
     */
    private function oasisRaidInput(int $kid): array
    {
        $db = \Core\Database\DB::getInstance();
        $session = Session::getInstance();
        $isFreeOasis = (int)$db->fetchScalar("SELECT COUNT(*) FROM wdata WHERE id=$kid AND oasistype>0 AND occupied=0");
        if (!$isFreeOasis) {
            return [];
        }
        $input = ['a1_v' => $session->getRace(), 'a2_v4' => 4, 'ew1' => $session->get('total_pop'), 'ew2' => 500];
        $units = $db->query("SELECT * FROM units WHERE kid=$kid")->fetch_assoc() ?: [];
        for ($i = 1; $i <= 10; ++$i) {
            if (!empty($units['u' . $i])) {
                $input['a2_3' . $i] = (int)$units['u' . $i];
            }
        }
        $helper = new HeroHelper();
        $hero = $session->hero;
        $inventory = $db->query('SELECT * FROM inventory WHERE uid=' . $session->getPlayerId())->fetch_assoc() ?: [];
        $rightHand = $helper->procInput($inventory['rightHand'] ?? 0);
        $leftHand = $helper->procInput($inventory['leftHand'] ?? 0);
        $leftHandProperties = is_array($leftHand) ? (new HeroItems())->getHeroItemProperties($leftHand['btype'], $leftHand['type']) : [];
        $horse = $helper->procInput($inventory['horse'] ?? 0);
        $bandages = $helper->getBandages($inventory['bag'] ?? 0);
        $input += [
            'a1_hero' => 1,
            'a1_hero_health' => $hero->getHeroHealth(),
            'a1_hero_strength' => (int)$hero->hero['power'],
            'a1_hero_total_power' => $helper->calcTotalPower(
                $session->getRace(),
                $hero->hero['power'],
                $inventory['rightHand'] ?? 0,
                $inventory['leftHand'] ?? 0,
                $inventory['body'] ?? 0
            ),
            'a1_hero_off_points' => (int)$hero->hero['offBonus'],
            'a1_hero_arm' => $helper->calcResist($inventory['body'] ?? 0),
            'a1_hero_bandages' => (int)$bandages['num'],
            'a1_hero_bandage_effect' => (int)$bandages['eff'],
            'a1_hero_cages' => $helper->getCages($inventory['bag'] ?? 0),
        ];
        if (is_array($rightHand)) {
            $input['a1_hero_right_hand'] = (int)$rightHand['type'];
        }
        if (!empty($leftHandProperties['powerAgainstNatars'])) {
            $input['a1_hero_natar_bonus'] = (int)$leftHandProperties['powerAgainstNatars'];
        }
        if (is_array($horse) && in_array((int)($horse['type'] ?? 0), [103, 104, 105], true)) {
            $input['a1_hero_horse'] = 1;
        }

        return $input;
    }

    private function prefillFromVillage(): void
    {
        $kid = isset($_POST['village']) && is_scalar($_POST['village']) ? (int)$_POST['village'] : 0;
        $snapshot = $this->output['villageSnapshots'][$kid] ?? null;
        if ($kid <= 0 || $snapshot === null) {
            return;
        }
        $this->output['village'] = $kid;
        if (empty($_POST['reloadVillage'])) {
            return;
        }
        $units = $snapshot['units'];
        $smithy = $snapshot['smithy'];
        $race = (int)$snapshot['race'];
        $pop = (int)$snapshot['population'];
        if ($this->output['role'] === 'attacker') {
            $_POST['a1_v'] = $race;
            for ($i = 1; $i <= 10; ++$i) {
                $_POST['a1_' . $i] = (int)($units[$i] ?? 0);
                $_POST['f1_' . $i] = $i <= 8 ? (int)($smithy[$i] ?? 0) : 0;
            }
            $_POST['a1_hero'] = (int)($units[11] ?? 0);
            $_POST['ew1'] = $pop;
            return;
        }
        foreach (self::reinforcementTribes() as $tribe) {
            unset($_POST['a2_v' . $tribe]);
        }
        $_POST['a2_main'] = $race;
        for ($i = 1; $i <= 10; ++$i) {
            $_POST['a2_' . nrToUnitId($i, $race)] = (int)($units[$i] ?? 0);
            if ($i <= 8) {
                $_POST['f2_' . nrToUnitId($i, $race)] = (int)($smithy[$i] ?? 0);
            }
        }
        $_POST['ew2'] = $pop;
        $_POST['wall' . $race] = (int)$snapshot['wall'];
        $_POST['steinmetz'] = (int)$snapshot['stonemason'];
        $_POST['palast'] = (int)$snapshot['palace'];
    }

    /**
     * Turns the canonical simulator payload into the native result surface.
     * The simulator stays the one source of casualty, trap, revival, siege and
     * loyalty facts; this method only supplies the reference-shaped view model.
     *
     * @param array<int,array<string,mixed>> $report
     */
    private function renderResult(array $report): string
    {
        $first = $report[0] ?? [];
        $losses = $first['losses'] ?? [0, 0];
        $attackerIsYou = $this->output['role'] === 'attacker';
        $cards = [];
        $cards[] = $this->resultCard(
            'attack',
            (int)$this->output['attacker']['race'],
            (string)T('combat', $attackerIsYou ? 'you' : 'attacker'),
            $this->output['attacker']['units'],
            (float)($losses[0] ?? 0),
            (array)($first['trapped'] ?? []),
            [],
            (array)($first['revived']['off'] ?? []),
            (bool)$this->output['attacker']['hero'],
            (int)($first['dead_heroes']['off'] ?? 0)
        );

        $defenderIndex = 0;
        foreach ($this->output['defender']['races'] as $race => $defender) {
            $isMainDefender = (int)$race === (int)$this->output['defender']['main'];
            $cards[] = $this->resultCard(
                $isMainDefender ? 'defence' : 'reinforcement',
                (int)$race,
                (string)T('combat', $isMainDefender
                    ? ($attackerIsYou ? 'defender' : 'you')
                    : 'reinforcement', 'Reinforcement'),
                (array)$defender['units'],
                (float)($losses[1] ?? 0),
                [],
                $isMainDefender ? (array)($first['caged'] ?? []) : [],
                [],
                $isMainDefender && (bool)($this->output['defender']['hero'] ?? false),
                (int)($first['dead_heroes']['def'][$defenderIndex] ?? 0)
            );
            ++$defenderIndex;
        }

        $messages = [];
        $info = (array)($first['info'] ?? []);
        if (!empty($info['isSpy'])) {
            $messages[] = (string)T('combat', 'scoutingResult', 'Scouting report');
        }
        if (!empty($info['none_return'])) {
            $messages[] = (string)T('combat', 'noReturn', 'The attacker has no offensive strength.');
        }
        $trapped = array_sum(array_map('intval', (array)($first['trapped'] ?? [])));
        if ($trapped > 0) {
            $messages[] = sprintf((string)T('combat', 'troopsTrapped', 'Troops trapped: %s'), $trapped);
        }
        $caged = array_sum(array_map('intval', (array)($first['caged'] ?? [])));
        if ($caged > 0) {
            $messages[] = sprintf((string)T('combat', 'troopsCaptured', 'Troops captured: %s'), $caged);
        }
        if (!empty($info['free']) && is_array($info['free'])) {
            $freed = array_sum(array_map(static fn(array $row): int => (int)($row['sum'] ?? 0), $info['free']));
            if ($freed > 0) {
                $messages[] = sprintf((string)T('combat', 'troopsFreed', 'Troops freed: %s'), $freed);
            }
        }
        if (isset($info['loy'][0], $info['loy'][1])) {
            $messages[] = (string)T('RallyPoint', 'loyaltyReducedBy') . (int)$info['loy'][0] . '-' . (int)$info['loy'][1] . '%';
        }
        if (isset($info['wall'][0], $info['wall'][1])) {
            $messages[] = (string)T('combat', 'DamageByRam') . ': ' . (string)T('combat', 'from') . ' '
                . (string)T('Buildings', 'level') . ' ' . (int)$info['wall'][0] . ' '
                . (string)T('combat', 'to') . ' ' . (string)T('Buildings', 'level') . ' ' . (int)$info['wall'][1];
        }
        if (isset($info['bl'][0], $info['bl'][1])) {
            $messages[] = (string)T('combat', 'DamageByCatapult') . ': ' . (string)T('combat', 'from') . ' '
                . (string)T('Buildings', 'level') . ' ' . (int)$info['bl'][0] . ' '
                . (string)T('combat', 'to') . ' ' . (string)T('Buildings', 'level') . ' ' . (int)$info['bl'][1];
        }

        $statistics = null;
        if (isset($first['combatStrength']) && is_array($first['combatStrength'])) {
            $attacker = $cards[0];
            $defenders = array_slice($cards, 1);
            $statistics = [
                'attacker' => [
                    'strength' => (int)round((float)($first['combatStrength']['attacker'] ?? 0)),
                    'supplyBefore' => (int)$attacker['supplyBefore'],
                    'supplyLost' => (int)$attacker['supplyLost'],
                ],
                'defender' => [
                    'strength' => (int)round((float)($first['combatStrength']['defender'] ?? 0)),
                    'supplyBefore' => array_sum(array_column($defenders, 'supplyBefore')),
                    'supplyLost' => array_sum(array_column($defenders, 'supplyLost')),
                ],
            ];
        }

        return PHPBatchView::render('combat/simulatorResult', [
            'cards' => $cards,
            'messages' => $messages,
            'statistics' => $statistics,
        ]);
    }

    /**
     * @param array<int,array{number:int,level:int}> $units
     * @param array<int,int> $trapped
     * @param array<int,int> $captured
     * @param array<int,int> $revived
     * @return array{kind:string,tribe:int,label:string,units:array<int,array{unitId:int,initial:int,loss:int,wounded:int,result:int}>,hero:array{initial:int,loss:int,wounded:int,result:int},supplyBefore:int,supplyLost:int}
     */
    private function resultCard(
        string $kind,
        int $tribe,
        string $label,
        array $units,
        float $lossRatio,
        array $trapped,
        array $captured,
        array $revived,
        bool $hasHero,
        int $deadHero
    ): array {
        $rows = [];
        $supplyBefore = 0;
        $supplyLost = 0;
        for ($slot = 1; $slot <= 10; ++$slot) {
            $initial = max(0, (int)($units[$slot - 1]['number'] ?? 0));
            $removed = min($initial, max(0, (int)($trapped[$slot - 1] ?? 0)) + max(0, (int)($captured[$slot - 1] ?? 0)));
            $loss = min($initial - $removed, max(0, (int)round(($initial - $removed) * $lossRatio)));
            $wounded = min($loss, max(0, (int)($revived[$slot - 1] ?? 0)));
            $unitId = nrToUnitId($slot, $tribe);
            $upkeep = (int)Formulas::uUpkeep($unitId, 0, true);
            $supplyBefore += $initial * $upkeep;
            $supplyLost += $loss * $upkeep;
            $rows[] = [
                'unitId' => $unitId,
                'initial' => $initial,
                'loss' => $loss,
                'wounded' => $wounded,
                'result' => max(0, $initial - $removed - $loss + $wounded),
            ];
        }

        $heroInitial = $hasHero ? 1 : 0;
        $heroRemoved = min($heroInitial, max(0, (int)($trapped[10] ?? 0)) + max(0, (int)($captured[10] ?? 0)));
        $heroLoss = min($heroInitial - $heroRemoved, max(0, $deadHero));
        $supplyBefore += $heroInitial * (int)Formulas::uUpkeep(98, 0, true);
        $supplyLost += $heroLoss * (int)Formulas::uUpkeep(98, 0, true);

        return [
            'kind' => $kind,
            'tribe' => $tribe,
            'label' => $label,
            'units' => $rows,
            'hero' => [
                'initial' => $heroInitial,
                'loss' => $heroLoss,
                'wounded' => 0,
                'result' => max(0, $heroInitial - $heroRemoved - $heroLoss),
            ],
            'supplyBefore' => $supplyBefore,
            'supplyLost' => $supplyLost,
        ];
    }

    /**
     * @return mixed
     */
    public function procContent()
    {
        return $this->output;
    }
}
