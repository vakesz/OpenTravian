<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Core\Locale;
use Core\Session;
use Core\Time;
use Game\Formulas;
use Game\PremiumDuration;
use Game\WorldSpeedProfile;

use function get_locale;
use function getGameSpeed;
use function number_format_x;

class Quest
{
    private static ?Quest $instance = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** @var list<string> */
    private array $qst_battle;
    /** @var list<string> */
    private array $qst_economy;
    /** @var list<string> */
    private array $qst_world;

    /** The active quest-progress writers use this completed-state marker. */
    public const QUEST_FINISHED = 1;

    /*
     * Quest ids for setQuestBitwise()/getQuest(): 1-based positions in users.qst_battle,
     * qst_economy and qst_world. Names follow the en-US Quest.{battle,economy,world}_NN_name texts.
     */
    /** Next adventure. */
    public const BATTLE_NEXT_ADVENTURE = 1;
    /** Build a cranny. */
    public const BATTLE_BUILD_CRANNY = 2;
    /** Build barracks. */
    public const BATTLE_BUILD_BARRACKS = 3;
    /** Hero level. */
    public const BATTLE_HERO_LEVEL = 4;
    /** Train troops. */
    public const BATTLE_TRAIN_TROOPS = 5;
    /** Build a wall ("Earth Wall"; any wall at level 1). */
    public const BATTLE_BUILD_WALL = 6;
    /** Attack an oasis. */
    public const BATTLE_ATTACK_OASIS = 7;
    /** 10 adventures. */
    public const BATTLE_TEN_ADVENTURES = 8;
    /** Auctions. */
    public const BATTLE_AUCTIONS = 9;
    /** Upgrade barracks. */
    public const BATTLE_UPGRADE_BARRACKS = 10;
    /** Construct an academy. */
    public const BATTLE_BUILD_ACADEMY = 11;
    /** Research a unit. */
    public const BATTLE_RESEARCH_UNIT = 12;
    /** Construct a smithy. */
    public const BATTLE_BUILD_SMITHY = 13;
    /** Improve units. */
    public const BATTLE_IMPROVE_UNITS = 14;
    /** Complete 5 adventures. */
    public const BATTLE_FIVE_ADVENTURES = 15;
    /** Iron mine. */
    public const ECONOMY_IRON_MINE = 1;
    /** More resources. */
    public const ECONOMY_MORE_RESOURCES = 2;
    /** Granary. */
    public const ECONOMY_GRANARY = 3;
    /** All fields to level 1. */
    public const ECONOMY_ALL_FIELDS_TO_1 = 4;
    /** To 2!. */
    public const ECONOMY_FIELDS_TO_2 = 5;
    /** Marketplace. */
    public const ECONOMY_MARKETPLACE = 6;
    /** Trade. */
    public const ECONOMY_TRADE = 7;
    /** All fields to level 2. */
    public const ECONOMY_ALL_FIELDS_TO_2 = 8;
    /** Warehouse level 3. */
    public const ECONOMY_WAREHOUSE_3 = 9;
    /** Granary level 3. */
    public const ECONOMY_GRANARY_3 = 10;
    /** Grain mill. */
    public const ECONOMY_GRAIN_MILL = 11;
    /** All fields to level 5. */
    public const ECONOMY_ALL_FIELDS_TO_5 = 12;
    /** View statistics. */
    public const WORLD_VIEW_STATISTICS = 1;
    /** Change the village name. */
    public const WORLD_RENAME_VILLAGE = 2;
    /** Main building level 3. */
    public const WORLD_MAIN_BUILDING_3 = 3;
    /** Construct an embassy. */
    public const WORLD_BUILD_EMBASSY = 4;
    /** Open the map. */
    public const WORLD_OPEN_MAP = 5;
    /** Read a message. */
    public const WORLD_READ_MESSAGE = 6;
    /** Bonus gold. */
    public const WORLD_BONUS_GOLD = 7;
    /** Alliance. */
    public const WORLD_ALLIANCE = 8;
    /** Main building level 5. */
    public const WORLD_MAIN_BUILDING_5 = 9;
    /** Seat of government. */
    public const WORLD_SEAT_OF_GOVERNMENT = 10;
    /** Culture points. */
    public const WORLD_CULTURE_POINTS = 11;
    /** Warehouse level 7. */
    public const WORLD_WAREHOUSE_7 = 12;
    /** Surroundings report. */
    public const WORLD_SURROUNDINGS_REPORT = 13;
    /** Residence or palace level 10. */
    public const WORLD_RESIDENCE_10 = 14;
    /** 3 settlers. */
    public const WORLD_THREE_SETTLERS = 15;
    /** New village. */
    public const WORLD_NEW_VILLAGE = 16;

    public function __construct()
    {
        $session = Session::getInstance();
        $this->qst_battle = explode(',', (string) ($session->isValid() ? $session->getQuestBattle() : '0,0,0,0,0,0,0,0,0,0,0,0,0,0'));
        $this->qst_economy = explode(',', (string) ($session->isValid() ? $session->getQuestEconomy() : '0,0,0,0,0,0,0,0,0,0,0,0'));
        $this->qst_world = explode(',', (string) ($session->isValid() ? $session->getQuestWorld() : '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0'));
        if ($session->isValid() && $session->getSuccessAdventuresCount() >= 10) {
            $this->setQuestBitwise('battle', self::BATTLE_TEN_ADVENTURES, 1);
        }
        if ($session->isValid() && $session->getSuccessAdventuresCount() >= 5) {
            $this->setQuestBitwise('battle', self::BATTLE_FIVE_ADVENTURES, 1);
        }
    }

    public function setQuestBitwise(string $type, int $id, int $x): void
    {
        $type = $type === 'battle' || $type === 'economy' ? $type : 'world';
        $cate = &$this->{'qst_' . $type};
        if ((int) $cate[$id - 1] >= $x) {
            return;
        }
        $cate[$id - 1] = (string) $x;
        $implode = implode(',', $cate);
        $session = Session::getInstance();
        match ($type) {
            'battle' => $session->setQuestBattle($implode),
            'economy' => $session->setQuestEconomy($implode),
            'world' => $session->setQuestWorld($implode),
        };
        DB::getInstance()->run("UPDATE users SET qst_$type=? WHERE id=?", [$implode, $session->getPlayerId()]);
    }

    public function getQuest(string $type, int $id): string
    {
        $cate = $type === 'battle' ? $this->qst_battle : ($type === 'economy' ? $this->qst_economy : $this->qst_world);

        return $cate[$id - 1];
    }

    public function questBitwiseRewardMatch(string $type, int $id): bool
    {
        return (int) $this->getQuest($type, $id) === 1;
    }

    /**
     * @param int|float|array<array-key, int|float> $x
     * @return ($x is array ? array<array-key, int|float> : int|float)
     */
    public function multiply(int|float|array $x): int|float|array
    {
        $rate = self::rewardRate('res', false);
        if (!is_array($x)) {
            return $x * $rate;
        }
        foreach ($x as $k => $v) {
            $x[$k] = $v * $rate;
        }

        return $x;
    }

    /**
     * The single world-speed reward multiplier for scalable quest rewards
     * (res, item, cp, exp, troops). Published daily-quest resources never scale.
     */
    public static function rewardRate(string $type, bool $dailyQuest): int|float
    {
        if (!Config::getProperty('quest', 'multiplyRewards')) {
            return 1;
        }
        $speed = (int)getGameSpeed();
        $rate = 1;
        switch ($type) {
            case 'res':
                $rate = $dailyQuest && WorldSpeedProfile::isPublishedSpeed($speed) ? 1 : floor($speed / 5);
                break;
            case 'item':
                $rate = floor($speed / 500);
                break;
            case 'cp':
                if ($speed <= 10) {
                    $rate = $dailyQuest ? 1 : 1 / $speed;
                } else {
                    $rate = floor($speed / 100);
                }
                break;
            case 'exp':
                $rate = Formulas::getHeroExpLevelMultiplier();
                break;
            case 'troops':
                $rate = floor($speed / 10);
                break;
        }
        return $rate == 0 ? 1 : $rate;
    }

    public static function effectToString(int|float|string $eff, string $type): int|float|string
    {
        switch ($type) {
            case 'adv':
            case 'item':
                return $eff;
            case 'res':
            case 'cp':
            case 'exp':
            case 'troops':
                return number_format_x($eff);
            case 'plus':
            case 'productionBoost':
                if ($eff >= Time::DAY) {
                    return round($eff / Time::DAY, 1) . ' ' . Locale::units(get_locale())['days'];
                } elseif ($eff >= Time::HOUR) {
                    return round($eff / Time::HOUR, 1) . ' ' . Locale::units(get_locale())['hours'];
                }
                return round($eff / Time::MINUTE, 1) . ' ' . Locale::units(get_locale())['minutes'];
        }
        return $eff;
    }

    public static function calcEffect(int|float|string $eff, string $type, bool $asString = false, bool $dailyQuest = false): int|float|string
    {
        $rate = 1;
        if (in_array($type, ['res', 'item', 'cp', 'exp', 'troops'], true)) {
            $rate = self::rewardRate($type, $dailyQuest);
        } elseif (Config::getProperty("quest", "multiplyRewards")
            && ($type === 'plus' || $type === 'productionBoost')) {
            if ($dailyQuest && WorldSpeedProfile::isPublishedSpeed((int)getGameSpeed())) {
                $eff = PremiumDuration::dailyQuestRewardSeconds();
            } else {
                $divider = getGameSpeed() == 1 ? 7 : (getGameSpeed() == 2 ? 2 : 3);
                $key = $type === 'plus' ? 'plusAccountDurationSeconds' : 'productionBoostDurationSeconds';
                $eff = ceil(Config::getProperty("gold", $key) / $divider * 600) / 600;
            }
        }
        if ($type === 'plus' || $type === 'productionBoost') {
            $eff = PremiumDuration::atLeastOneDay($eff);
        }
        $eff *= $rate;
        return $asString ? self::effectToString($eff, $type) : $eff;
    }
}
