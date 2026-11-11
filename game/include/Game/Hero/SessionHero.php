<?php

namespace Game\Hero;

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\Helpers\HeroHealthHelper;
use Model\MovementsModel;
use Model\TrainingModel;

use function getGameSpeed;
use function getDirection;
use function getGame;
use function htmlspecialchars;
use function nanoseconds;
use function number_format_x;

class SessionHero extends HeroHelper
{
    public const STATUS_HOME = 100;
    public const STATUS_DEAD = 101;
    public const STATUS_REVIVING = '101Regenerate';
    public const STATUS_ON_ADVENTURE = 50;
    public const STATUS_ESCAPING = 40; //101Regenerate
    public const STATUS_ON_ATTACK = 4; // on the way
    public const STATUS_ON_REINFORCE = 5; // on the way //Hero is escaping.
    public const STATUS_IN_RETURN = 9; //on the way
    public const STATUS_TRAPPED = 102; //on the way
    public const STATUS_IN_ENFORCEMENT = 103; //on the way (from attack)
    /** @var \Game\Hero\SessionHero|null */
    private static $_self;
    /** @var mixed */
    public $hero = [];
    /** @var int|string|null */
    public $status;
    /** @var mixed */
    private $heroTrain = null;
    /** @var mixed */
    private $statusShortMessage = '';
    /** @var mixed */
    private $statusLongMessage = '';
    /** @var string */
    private $statusInventoryMessage = '';

    public function __construct()
    {
        $db = DB::getInstance();
        $session = Session::getInstance();
        if (!$session->isValid()) {
            return;
        }
        $this->hero = $db->query("SELECT uid, kid, exp, health, power, offBonus, itemHealth, lastupdate, defBonus, production, productionType, hide FROM hero WHERE uid=" . $session->getPlayerId())->fetch_assoc();
        if ($this->hero['health'] == 0) {
            $this->hero['kid'] = (int)$this->hero['kid'];
            $this->heroTrain = $db->query("SELECT kid, end_time, training_time FROM training WHERE item_id=98 AND kid={$this->hero['kid']}");
            if ($this->heroTrain->num_rows) {
                $this->heroTrain = $this->heroTrain->fetch_assoc();
            } else {
                $this->heroTrain = null;
            }
        } else {
            $healthRate = ($this->calcHealth() + $this->hero['itemHealth'])
                * max(1, (float)Config::getProperty('game', 'heroRegenerationMultiplier'));
            $generatedHealth = $healthRate / 86400 * (time() - $this->hero['lastupdate']);
            $this->hero['health'] = min($this->hero['health'] + $generatedHealth, 100);
        }
        $this->findHero();
    }

    /**
     * @return bool|null
     */
    public function findHero()
    {
        $needTimer = defined('LOADED_PAGE') && in_array(LOADED_PAGE, ['hero.php']);
        $village = Village::getInstance();
        $db = DB::getInstance();
        if ($this->heroTrain !== null) {
            $this->status = self::STATUS_REVIVING;
            $this->statusShortMessage = T("HeroGlobal", "shortStatus.reviving");
            $villageName = $this->hero['kid'] == $village->getKid() ? $village->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$this->hero['kid']} LIMIT 1");
            $this->statusLongMessage = sprintf(T("HeroGlobal", "longStatus.reviving"), $villageName);
            if ($needTimer) {
                $endTime = $this->heroTrain['end_time'];
                if (getGame("useNanoseconds")) {
                    $endTime = ceil($endTime / 1e9);
                } elseif (getGame("useMilSeconds")) {
                    $endTime = ceil($endTime / 1e3);
                }
                $this->statusInventoryMessage = sprintf(
                    T("HeroGlobal", "inventoryStatus.reviving"),
                    $this->hero['kid'],
                    $villageName
                ) . ' ' . appendTimer($endTime - time());
            }
            return true;
        } elseif ($this->hero['health'] == 0) {
            $this->status = self::STATUS_DEAD;
            $this->statusShortMessage = T("HeroGlobal", "shortStatus.dead");
            $villageName = $this->hero['kid'] == $village->getKid() ? $village->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$this->hero['kid']} LIMIT 1");
            $this->statusLongMessage = T("HeroGlobal", "longStatus.dead");
            if ($needTimer) {
                $this->statusInventoryMessage = sprintf(
                    T("HeroGlobal", "inventoryStatus.dead"),
                    $this->hero['kid'],
                    $villageName
                );
            }
            return true;
        } elseif ($this->hero['kid'] == $village->getKid()) {
            $find = $db->query("SELECT u11 FROM units WHERE kid={$this->hero['kid']} AND u11>0 LIMIT 1");
            if ($find->num_rows) {
                $this->status = self::STATUS_HOME;
                $this->statusShortMessage = T("HeroGlobal", "shortStatus.Home");
                $this->statusLongMessage = sprintf(T("HeroGlobal", "longStatus.Home"), $village->getName());
                if ($needTimer) {
                    $this->statusInventoryMessage = sprintf(
                        T("HeroGlobal", "inventoryStatus.Home"),
                        $this->hero['kid'],
                        $village->getName()
                    );
                }
                return true;
            }
        } else {
            $find = $db->query("SELECT u11 FROM units WHERE kid={$this->hero['kid']} AND u11>0 LIMIT 1");
            if ($find->num_rows) {
                $this->status = self::STATUS_HOME;
                $this->statusShortMessage = T("HeroGlobal", "shortStatus.Home");
                $villageName = $db->fetchScalar("SELECT name FROM vdata WHERE kid={$this->hero['kid']} LIMIT 1");
                $this->statusLongMessage = sprintf(T("HeroGlobal", "longStatus.Home"), $villageName);
                if ($needTimer) {
                    $this->statusInventoryMessage = sprintf(
                        T("HeroGlobal", "inventoryStatus.Home"),
                        $this->hero['kid'],
                        $villageName
                    );
                }
                return true;
            }
        }
        $find = $db->query("SELECT to_kid FROM enforcement WHERE u11>0 AND kid=" . $this->hero['kid']);
        if ($find->num_rows) {
            $find = $find->fetch_assoc();
            $this->status = self::STATUS_IN_ENFORCEMENT;
            $this->statusShortMessage = T("HeroGlobal", "shortStatus.defending");
            $villageName = $find['to_kid'] == $village->getKid() ? $village->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$find['to_kid']} LIMIT 1");
            $this->statusLongMessage = sprintf(T("HeroGlobal", "longStatus.defending"), $villageName);
            if ($needTimer) {
                $this->statusInventoryMessage = sprintf(
                    T("HeroGlobal", "inventoryStatus.Home"),
                    $find['to_kid'],
                    $villageName
                );
            }
            return true;
        }
        $find = $db->query("SELECT to_kid FROM trapped WHERE u11>0 AND kid=" . $this->hero['kid']);
        if ($find->num_rows) {
            $find = $find->fetch_assoc();
            $this->status = self::STATUS_TRAPPED;
            $this->statusShortMessage = T("HeroGlobal", "shortStatus.trapped");
            $villageName = $find['to_kid'] == $village->getKid() ? $village->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$find['to_kid']} LIMIT 1");
            $this->statusLongMessage = sprintf(T("HeroGlobal", "longStatus.trapped"), $villageName);
            if ($needTimer) {
                $this->statusInventoryMessage = sprintf(
                    T("HeroGlobal", "inventoryStatus.trapped"),
                    $find['to_kid'],
                    $villageName
                );
            }

            return true;
        }
        // A hero that is not trapped, reinforcing, or in a village is moving.
        $find = $db->query("SELECT kid, to_kid, mode, attack_type, start_time, end_time FROM movement WHERE u11>0 AND ((mode=1 AND to_kid={$this->hero['kid']}) OR (mode=0 AND kid={$this->hero['kid']})) LIMIT 1");
        if ($find->num_rows) {
            $find = $find->fetch_assoc();
            $find['start_time_seconds'] = ceil($find['start_time'] / 1000);
            $find['end_time_seconds'] = ceil($find['end_time'] / 1000);
            if ($find['mode'] == 0) {
                // The hero is en route.
                switch ($find['attack_type']) {
                    case MovementsModel::ATTACKTYPE_ADVENTURE:
                        $this->status = self::STATUS_ON_ADVENTURE;
                        $this->statusShortMessage = T("HeroGlobal", "shortStatus.adventure");
                        $this->statusLongMessage = T("HeroGlobal", "longStatus.adventure");
                        if ($needTimer) {
                            $adventureInventoryStatus = T("HeroGlobal", "inventoryStatus.adventure");
                            $adventureInventoryStatusValues = [
                                appendTimer($find['end_time_seconds'] - time()),
                                TimezoneHelper::date('H:i', (int) $find['end_time_seconds']),
                                $this->hero['kid'],
                            ];
                            // The supported Persian catalog retains the legacy target-coordinate placeholder.
                            if (substr_count($adventureInventoryStatus, '%s') === 4) {
                                $xy = Formulas::kid2xy($find['to_kid']);
                                $coordinates = '<a href="/karte.php?x=' . (int)$xy['x'] . '&amp;y=' . (int)$xy['y'] . '">'
                                    . '<span class="coordinates coordinatesWrapper"><span class="coordinateX">(' . coordinateNumberHTML((int)$xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML((int)$xy['y']) . ')</span></span></a>';
                                array_unshift($adventureInventoryStatusValues, $coordinates);
                            }
                            $this->statusInventoryMessage = vsprintf($adventureInventoryStatus, $adventureInventoryStatusValues);
                        }
                        $this->hero['toKidAdventure'] = $find['to_kid'];
                        break;
                    case MovementsModel::ATTACKTYPE_REINFORCEMENT:
                        $this->status = self::STATUS_ON_REINFORCE;
                        $villageName = $this->hero['kid'] == $village->getKid() ? $village->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$this->hero['kid']} LIMIT 1");
                        $this->statusShortMessage = T("HeroGlobal", "shortStatus.attack");
                        $this->statusLongMessage = sprintf(
                            T("HeroGlobal", "longStatus.attack"),
                            $villageName,
                            secondsToString($find['end_time_seconds'] - time()),
                            TimezoneHelper::date('H:i', (int) $find['end_time_seconds'])
                        );
                        if ($needTimer) {
                            $this->statusInventoryMessage = sprintf(
                                T("HeroGlobal", "inventoryStatus.attack"),
                                $this->hero['kid'],
                                $villageName,
                                appendTimer($find['end_time_seconds'] - time()),
                                TimezoneHelper::date('H:i', (int) $find['end_time_seconds'])
                            );
                        }
                        break;
                    case MovementsModel::ATTACKTYPE_EVASION:
                        $this->status = self::STATUS_ESCAPING;
                        $this->statusShortMessage = T("HeroGlobal", "shortStatus.escape");
                        $this->statusLongMessage = sprintf(
                            T("HeroGlobal", "longStatus.escape"),
                            secondsToString($find['end_time_seconds'] - time()),
                            TimezoneHelper::date('H:i', (int) $find['end_time_seconds'])
                        );
                        if ($needTimer) {
                            $this->statusInventoryMessage = sprintf(
                                T("HeroGlobal", "longStatus.escape"),
                                appendTimer($find['end_time_seconds'] - time()),
                                TimezoneHelper::date('H:i', (int) $find['end_time_seconds'])
                            );
                        }
                        break;
                    case MovementsModel::ATTACKTYPE_RAID:
                    case MovementsModel::ATTACKTYPE_NORMAL:
                        $villageName = $this->hero['kid'] == $village->getKid() ? $village->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$this->hero['kid']} LIMIT 1");
                        $this->status = self::STATUS_ON_ATTACK;
                        $this->statusShortMessage = T("HeroGlobal", "shortStatus.attack");
                        $this->statusLongMessage = sprintf(
                            T("HeroGlobal", "longStatus.attack"),
                            $villageName,
                            secondsToString($find['end_time_seconds'] - time()),
                            TimezoneHelper::date('H:i', (int) $find['end_time_seconds'])
                        );
                        if ($needTimer) {
                            $this->statusInventoryMessage = sprintf(
                                T("HeroGlobal", "inventoryStatus.attack"),
                                $this->hero['kid'],
                                $villageName,
                                appendTimer($find['end_time_seconds'] - time()),
                                TimezoneHelper::date('H:i', (int) $find['end_time_seconds'])
                            );
                        }
                        break;
                    default:
                        // The movement destination is unavailable; keep the current hero state.
                        break;
                }
            } else {
                $this->status = self::STATUS_IN_RETURN;
                $this->statusShortMessage = T("HeroGlobal", "shortStatus.return");
                $villageName = $find['to_kid'] == $village->getKid() ? $village->getName() : $db->fetchScalar("SELECT name FROM vdata WHERE kid={$find['to_kid']} LIMIT 1");
                $this->statusLongMessage = sprintf(
                    T("HeroGlobal", "longStatus.return"),
                    $villageName,
                    secondsToString($find['end_time_seconds'] - time()),
                    TimezoneHelper::date('H:i', (int) $find['end_time_seconds'])
                );
                if ($needTimer) {
                    $this->statusInventoryMessage = sprintf(
                        T("HeroGlobal", "inventoryStatus.return"),
                        $find['to_kid'],
                        $villageName,
                        appendTimer($find['end_time_seconds'] - time()),
                        TimezoneHelper::date('H:i', (int) $find['end_time_seconds'])
                    );
                }
            }
        }
        if ($this->status === null) {
            $this->putLostHeroHome($village, $needTimer);
        }
    }

    /**
     * The hero is in no village, movement, enforcement or trap (for example a movement that never completed): show it
     * at home in its own village and put its unit back there, so the pages always have a status to render.
     */
    private function putLostHeroHome(Village $village, bool $needTimer): void
    {
        $db = DB::getInstance();
        $kid = (int)$this->hero['kid'];
        logError('Hero of player %d was in no village or movement; returned to village %d.', [(int)$this->hero['uid'], $kid]);
        $db->query("UPDATE units SET u11=1 WHERE kid=$kid AND u11=0");
        $villageName = $kid == $village->getKid() ? $village->getName() : (string)$db->fetchScalar("SELECT name FROM vdata WHERE kid=$kid LIMIT 1");
        $this->status = self::STATUS_HOME;
        $this->statusShortMessage = T("HeroGlobal", "shortStatus.Home");
        $this->statusLongMessage = sprintf(T("HeroGlobal", "longStatus.Home"), $villageName);
        if ($needTimer) {
            $this->statusInventoryMessage = sprintf(T("HeroGlobal", "inventoryStatus.Home"), $kid, $villageName);
        }
    }

    /**
     * @return \Game\Hero\SessionHero
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
    public function evasionStatus()
    {
        return $this->hero['hide'];
    }

    public function hasNewPoints(): bool
    {
        return $this->getAvailablePoints() > 0;
    }

    /**
     * @return (float|int)
     */
    public function getAvailablePoints()
    {
        $points = (Formulas::heroLevel($this->hero['exp']) >= 100 ? 100 : Formulas::heroLevel($this->hero['exp'])) * 4;
        if (Formulas::heroLevel($this->hero['exp']) < 100) {
            $points += 4;
        }
        return $points - ($this->hero['power'] + $this->hero['offBonus'] + $this->hero['defBonus'] + $this->hero['production']);
    }

    /**
     * @return mixed
     */
    public function getHeroExp()
    {
        return $this->hero['exp'];
    }

    /**
     * @return (float|int)
     */
    public function getHeroExpPercent()
    {
        $lvl = Formulas::heroLevel($this->hero['exp']);
        return 100 * ($this->hero['exp'] - Formulas::heroExperience($lvl)) / (Formulas::heroExperience($lvl + 1) - Formulas::heroExperience($lvl));
    }

    /**
     * @return mixed
     */
    public function getRegeneratedHealth()
    {
        if ($this->heroTrain == null) {
            return $this->getHeroHealth();
        }
        $milSecond = getGame("useMilSeconds");
        $nanoseconds = getGame("useNanoseconds");
        $now = ($nanoseconds ? nanoseconds() : ($milSecond ? miliseconds() : time()));
        $health = min(100, (($this->heroTrain['training_time'] - ($this->heroTrain['end_time'] - $now)) / $this->heroTrain['training_time']) * 100);
        return floor($health);
    }

    /**
     * The Hero V2 revive card counts down in real seconds, independent of
     * whether the world persists training timestamps in seconds, milliseconds
     * or nanoseconds.
     */
    public function getReviveEndTime(): ?int
    {
        if ($this->heroTrain === null) {
            return null;
        }

        $endTime = (int) $this->heroTrain['end_time'];
        if (getGame('useNanoseconds')) {
            return (int) ceil($endTime / 1e9);
        }
        if (getGame('useMilSeconds')) {
            return (int) ceil($endTime / 1e3);
        }

        return $endTime;
    }

    /**
     * @return float|int
     */
    public function getHeroHealth()
    {
        $health = floor($this->hero['health']);
        if ($health == 0 && $this->hero['health'] > 0) {
            return 1;
        }
        return $health;
    }

    /**
     * @param bool $waterBucket
     */
    public function trainHero($waterBucket = false): void
    {
        $db = DB::getInstance();
        if ($this->heroTrain !== null) {
            if ($waterBucket) {
                $db->query("DELETE FROM training WHERE nr=11 AND kid={$this->heroTrain['kid']}");
                $this->heroTrain = null;
            } else {
                return;
            }
        }
        $this->hero['kid'] = Village::getInstance()->getKid();
        if ($waterBucket) {
            (new TrainingModel())->regenerateHero($this->hero['uid'], $this->hero['kid']);
            $this->hero['health'] = 100;
        } else {
            $milSecond = getGame("useMilSeconds");
            $nanoseconds = getGame("useNanoseconds");
            $neededTime = Formulas::heroRegenerationTime($this->getLevel());
            $end_time = $neededTime + ($nanoseconds ? nanoseconds() : ($milSecond ? miliseconds() : time()));
            $db->query("INSERT INTO training (kid, nr, num, item_id, training_time, commence, end_time) VALUES ({$this->hero['kid']}, 11, 1, 98, {$neededTime}," . $end_time . "," . $end_time . ")");
            $db->query("UPDATE hero SET kid={$this->hero['kid']} WHERE uid=" . $this->hero['uid']);
            $this->heroTrain = [
                "kid" => $this->hero['kid'],
                "end_time" => $end_time,
                "training_time" => $neededTime,
            ];
        }
        $this->findHero();
        $session = Session::getInstance();
        Caching::getInstance()->delete("hero_sidebar_{$session->getPlayerId()}");
    }

    /**
     * @return mixed
     */
    public function getLevel()
    {
        return Formulas::heroLevel($this->hero['exp']);
    }

    /**
     * @return mixed
     */
    public function getShortStatus()
    {
        return $this->statusShortMessage;
    }

    /**
     * @return mixed
     */
    public function getLongStatus()
    {
        return $this->statusLongMessage;
    }

    /**
     * @return mixed
     */
    public function getInventoryStatus()
    {
        return $this->statusInventoryMessage;
    }

    /**
     * @param mixed $inventory
     */
    public function getHeroFightingStrengthTitle($inventory): string
    {
        $HTML = T("HeroInventory", "fightingStrength") . '||';
        $HTML .= T("HeroInventory", "fightingStrengthDesc") . '<br />';
        $HTML .= '<span class="heroAttributeInformation">' . T("HeroInventory", "fightingStrength") . ': ';
        $HTML .= $this->calcPower(Session::getInstance()->getRace(), $this->hero['power']) . ' ' . T(
            "HeroInventory",
            "fromHero"
        );
        $item = $this->calcItemPower($inventory['rightHand'], $inventory['leftHand'], $inventory['body']);
        if ($item) {
            $HTML .= ' +' . $item . ' ' . T("HeroInventory", "fromItem");
        }
        $HTML .= '</span>';

        return $HTML;
    }

    /**
     * @return array<int, array<string, mixed>>|array<mixed>
     */
    public function getHeroItems()
    {
        $items = [];
        $db = DB::getInstance();
        $data = $db->query("SELECT * FROM items WHERE uid=" . Session::getInstance()->getPlayerId());
        if (!$data->num_rows) {
            return $items;
        }
        $array = 0;
        $heroItems = new HeroItems();
        $n = 0;
        while ($row = $data->fetch_assoc()) {

            $itemData = $heroItems->getHeroItemProperties($row['btype'], $row['type']);
            $itemName = $itemData['slot'];
            $QueryData = $db->query("SELECT $itemName  FROM inventory WHERE uid=" . Session::getInstance()->getPlayerId());
            $rrr = $QueryData->fetch_assoc();
            if ($rrr[$itemName] != $row['id']) {

                // if($db->query($sql)!=(int)$row['id'])
                $items[$array]['id'] = (int)$row['id'];
                $items[$array]['typeId'] = (int)$row['type'];
                $items[$array]['placeId'] = $row['proc'] == 1 ? (--$n) : (int)$row['placeId'];
                $items[$array]['name'] = htmlspecialchars("{$itemData['name']}");
                $items[$array]['place'] = $row['proc'] ? $itemData['slot'] : "inventory";
                $items[$array]['slot'] = $itemData['slot'];
                $items[$array]['isConsumable'] = (int)$row['btype'] >= 7
                    && !in_array((int)$row['btype'], [12, 13], true);
                $items[$array]['isMaterial'] = in_array((int)$row['btype'], [12, 13], true);
                $items[$array]['amount'] = (int)$row['num'];
                $items[$array]['instant'] = $itemData['instant'];
                $items[$array]['isUsableIfDead'] = $itemData['isUsableIfDead'];
                $items[$array]['tooltip'] = HeroItemTooltip::html((int)$row['btype'], (int)$row['type'], $this->status !== self::STATUS_DEAD);
                $items[$array]['tileSlot'] = HeroItems::tileSlot((int)$row['btype'], (string)$itemData['slot']);
                if ($items[$array]['isConsumable'] || $items[$array]['isMaterial']) {
                    $equipped = (int)$db->fetchScalar(
                        'SELECT COALESCE(SUM(num), 0) FROM items WHERE uid=? AND btype=? AND type=? AND proc=1',
                        [Session::getInstance()->getPlayerId(), (int)$row['btype'], (int)$row['type']]
                    );
                    $items[$array]['dialog'] = ConsumableDialog::describe(
                        (int)$row['btype'],
                        (int)$row['type'],
                        $this->status !== self::STATUS_DEAD,
                        $equipped,
                        $this->consumableContext((int)$row['btype'], (int)$row['type']),
                        (int)$row['num']
                    );
                }
                if ((int)$row['btype'] === 15) {
                    $uid = (int)Session::getInstance()->getPlayerId();
                    $production = (int)$db->fetchScalar("SELECT COALESCE(SUM(cp), 0) FROM vdata WHERE owner=$uid");
                    $lastUse = (int)$db->fetchScalar("SELECT lastArtworkUse FROM inventory WHERE uid=$uid");
                    $availableAt = Artwork::availableAt((int)getGameSpeed(), $lastUse);
                    $items[$array]['artwork'] = [
                        'cp' => Artwork::reward((int)getGameSpeed(), $production),
                        'availableAt' => $availableAt,
                        'cooldownText' => sprintf(T('HeroItems', '15.111.cooldown'), TimezoneHelper::autoDate($availableAt, true)),
                    ];
                }
                $array++;
            }
        }
        return $items;
    }
    /**
     * The hero numbers the use dialog of a consumable shows next to the amount.
     *
     * @return array{health: int, experience: int, perScroll: int, bonus: int, loyaltyFull: bool}
     */
    private function consumableContext(int $btype, int $type): array
    {
        $db = DB::getInstance();
        $uid = (int)Session::getInstance()->getPlayerId();
        $context = [
            'health' => (int)floor((float)$this->getHeroHealth()),
            'experience' => (int)$this->getHeroExp(),
            'perScroll' => 0,
            'bonus' => 0,
            'loyaltyFull' => false,
        ];
        if ($btype === 10) {
            $properties = (new HeroItems())->getHeroItemProperties($btype, $type);
            $context['perScroll'] = (int)floor((float)$properties['exp']);
            $context['bonus'] = (int)(new HeroHelper())->calcMoreExp((int)$db->fetchScalar('SELECT helmet FROM inventory WHERE uid=?', [$uid]));
        } elseif ($btype === 14) {
            $loyalty = $db->fetchScalar('SELECT loyalty FROM vdata WHERE kid=? AND owner=?', [(int)$this->hero['kid'], $uid]);
            $context['loyaltyFull'] = $loyalty !== false && (int)$loyalty >= 125;
        }

        return $context;
    }

    /**
     * @return array<int, array<string, mixed>>|array<mixed>
     */
    public function getHeroEquipments()
    {
        $items = [];
        $db = DB::getInstance();
        $data = $db->query("SELECT * FROM items WHERE uid=" . Session::getInstance()->getPlayerId());
        if (!$data->num_rows) {
            return $items;
        }
        $array = 0;
        $heroItems = new HeroItems();
        $n = 0;
        while ($row = $data->fetch_assoc()) {

            $itemData = $heroItems->getHeroItemProperties($row['btype'], $row['type']);
            $itemName = $itemData['slot'];
            $QueryData = $db->query("SELECT $itemName  FROM inventory WHERE uid=" . Session::getInstance()->getPlayerId());
            $rrr = $QueryData->fetch_assoc();
            if ($rrr[$itemName] == $row['id']) {

                // if($db->query($sql)!=(int)$row['id'])
                $items[$array]['id'] = (int)$row['id'];
                $items[$array]['typeId'] = (int)$row['type'];
                $items[$array]['placeId'] = $row['proc'] == 1 ? (--$n) : (int)$row['placeId'];
                $items[$array]['name'] = htmlspecialchars("{$itemData['name']}");
                $items[$array]['place'] = $row['proc'] ? $itemData['slot'] : "inventory";
                $items[$array]['slot'] = $itemData['slot'];
                $items[$array]['amount'] = (int)$row['num'];
                $items[$array]['instant'] = $itemData['instant'];
                $items[$array]['isUsableIfDead'] = $itemData['isUsableIfDead'];
                $items[$array]['tooltip'] = HeroItemTooltip::html((int)$row['btype'], (int)$row['type'], $this->status !== self::STATUS_DEAD);
                $items[$array]['benefits'] = HeroItemTooltip::benefits((int)$row['btype'], (int)$row['type']);
                $array++;
            }
        }
        return $items;
    }


    /**
     * @return mixed
     */
    public function getHeroStatus()
    {
        return $this->status;
    }

    /** Isolates every figure of an attribute tooltip: LRO, LRO, value, PDF, percent sign, PDF. */
    private static function isolatedPercent(float|int $value): string
    {
        return "\u{202D}\u{202D}" . $value . "\u{202C}%\u{202C}";
    }

    private static function attributeTip(string $title, string $text, string $information): string
    {
        return $title . '||' . $text . '<br /><span class="heroAttributeInformation">' . $information . '</span>';
    }

    public function getHeroDefBonusTitle(): string
    {
        return self::attributeTip(
            T("HeroInventory", "defBonus"),
            T("HeroInventory", "defBonusDesc"),
            T("HeroInventory", "defBonus") . ': ' . self::isolatedPercent($this->calcDefBonus($this->hero['defBonus']))
        );
    }

    public function getHeroOffBonusTitle(): string
    {
        return self::attributeTip(
            T("HeroInventory", "offBonus"),
            T("HeroInventory", "offBonusDesc"),
            T("HeroInventory", "offBonus") . ': ' . self::isolatedPercent($this->calcOffBonus($this->hero['offBonus']))
        );
    }

    public function getHeroProductBonusTitle(): string
    {
        $type = (int)$this->hero['productionType'];
        $production = $this->hero['production'] * Formulas::heroProductionPerPoint((int)Session::getInstance()->getRace(), $type !== 0);
        if ($type === 4) {
            $production += 6;
        }
        $name = $type === 0 ? T("HeroInventory", "all resources") : T("inGame", "resources.r" . $type);

        return self::attributeTip(
            T("HeroInventory", "productBonus"),
            T("HeroInventory", "productBonusDesc"),
            T("HeroInventory", "increaseOfProduction") . ': <i title="' . $name . '" class="r' . $type . '"></i> ' . number_format_x($production) . ' ' . $name
        );
    }

    /**
     * @param mixed $inventory
     */
    public function getSpeedTitle($inventory): string
    {
        $race = Session::getInstance()->getRace();
        $fieldsPerHour = ' ' . T("HeroInventory", "fieldPerHour") . ' ';
        $parts = [Formulas::heroSpeed($race) . $fieldsPerHour . T("HeroInventory", "fromHero")];
        $horse = (new HeroHelper())->calcSpeed($race, $inventory['horse']) - Formulas::heroSpeed($race, true);
        if ($horse > 0) {
            $parts[] = $horse . $fieldsPerHour . T("HeroInventory", "fromHorse");
        }
        $tribe = Formulas::heroSpeed($race, true) - Formulas::heroSpeed($race);
        if ($tribe > 0 && $horse > 0) {
            $parts[] = $tribe . $fieldsPerHour . T("HeroInventory", "fromTribeAdvantage");
        }
        $itemSpeed = $this->calcItemSpeed($inventory['horse'], $inventory['shoes']);
        if ($itemSpeed) {
            $parts[] = '+' . $itemSpeed . $fieldsPerHour . T("HeroInventory", "fromItem");
        }

        return self::attributeTip(
            T("HeroInventory", "speed"),
            T("HeroInventory", "speedOfYourHero"),
            T("HeroInventory", "speed") . ': ' . implode(' ', $parts)
        );
    }

    public function getHeroLevelTitle(): string
    {
        $HTML = T("HeroInventory", "heroLevel") . '||';
        $HTML .= '<br /><span class="heroAttributeInformation">';
        switch (Session::getInstance()->getRace()) {
            case 1:
                $HTML .= sprintf(T("HeroInventory", "RomanSpecialHeroAttribute"), 100);
                break;
            case 2://percent mark :((
                $HTML .= sprintf(T("HeroInventory", "TeutonSpecialHeroAttribute"), 30 . "%");
                break;
            case 3:
                $HTML .= sprintf(T("HeroInventory", "GualSpecialHeroAttribute"), 5);
                break;
        }
        $HTML .= '</span>';

        return $HTML;
    }

    /**
     * @param mixed $inventory
     */
    public function getHealthTitle($inventory): string
    {
        $regenerationMultiplier = max(1, (float)Config::getProperty('game', 'heroRegenerationMultiplier'));
        $item = $this->calcItemHealth($inventory['helmet'], $inventory['body'], $inventory['shoes']) * $regenerationMultiplier;
        $basic = $this->calcHealth() * $regenerationMultiplier;
        $perDay = ' ' . T("HeroInventory", "perDay") . ' ';
        $information = T("HeroInventory", "health") . ': ' . self::isolatedPercent($basic) . $perDay . T("HeroInventory", "fromHero");
        if ($item) {
            $information .= ' +' . self::isolatedPercent($item) . $perDay . T("HeroInventory", "fromItem");
        }

        return self::attributeTip(
            T("HeroInventory", "health"),
            T("HeroInventory", "heroRegenerationRate") . ': ' . self::isolatedPercent($basic + $item) . ' ' . T("HeroInventory", "perDay"),
            $information
        );
    }

    /**
     * @param mixed $inventory
     */
    public function getExperienceTitle($inventory): string
    {
        $level = $this->getLevel();
        $expNeeded = Formulas::heroExperience($level + 1) - $this->hero['exp'];
        $text = sprintf(T("HeroInventory", "experienceNeededToNextLevel"), number_format_x($expNeeded), $level + 1);
        if (getDirection() == 'RTL') {
            $text = sprintf(T("HeroInventory", "experienceNeededToNextLevel"), $level + 1, number_format_x($expNeeded));
        }
        $title = T("HeroInventory", "experience") . ' ' . self::isolatedPercent((int)floor($this->getHeroExpPercent()));
        $item = $this->calcMoreExp($inventory['helmet']);

        return $item
            ? self::attributeTip($title, $text, T("HeroInventory", "Bonus") . ': +' . $item . '%')
            : $title . '||' . $text;
    }

    public function getHeroProductionTitle(): string
    {
        $type = (int)$this->hero['productionType'];
        $production = $this->hero['production'] * Formulas::heroProductionPerPoint((int)Session::getInstance()->getRace(), $type !== 0);
        $name = $type === 0 ? T("HeroInventory", "all resources") : T("inGame", "resources.r" . $type);
        $crop = '<i title="' . T("inGame", "resources.r4") . '" class="r4"></i>';
        $information = T("HeroInventory", "HeroProduction") . ' ';
        $information .= $type === 4
            ? $crop . number_format_x($production + 6)
            : '<i title="' . $name . '" class="r' . $type . '"></i>' . number_format_x($production) . ' ' . $crop . "\u{202D}6\u{202C}";

        return T("HeroInventory", "HeroProduction") . '||' . T("HeroInventory", "currentHeroProduction") . ':<br />'
            . '<span class="heroAttributeInformation">' . $information . '</span>';
    }
}
