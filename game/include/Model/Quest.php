<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Core\Locale;
use Core\Session;
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

    public function __construct()
    {
        $session = Session::getInstance();
        $this->qst_battle = explode(',', (string) ($session->isValid() ? $session->getQuestBattle() : '0,0,0,0,0,0,0,0,0,0,0,0,0,0'));
        $this->qst_economy = explode(',', (string) ($session->isValid() ? $session->getQuestEconomy() : '0,0,0,0,0,0,0,0,0,0,0,0'));
        $this->qst_world = explode(',', (string) ($session->isValid() ? $session->getQuestWorld() : '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0'));
        if ($session->isValid() && $session->getSuccessAdventuresCount() >= 10) {
            $this->setQuestBitwise('battle', 8, 1);
        }
        if ($session->isValid() && $session->getSuccessAdventuresCount() >= 5) {
            $this->setQuestBitwise('battle', 15, 1);
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
                if ($eff >= 86400) {
                    return round($eff / 86400, 1) . ' ' . Locale::units(get_locale())['days'];
                } elseif ($eff >= 3600) {
                    return round($eff / 3600, 1) . ' ' . Locale::units(get_locale())['hours'];
                }
                return round($eff / 60, 1) . ' ' . Locale::units(get_locale())['minutes'];
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
