<?php

namespace Game;

use Core\Config;
use Core\Database\DB;
use Game\Hero\HeroItems;
use Model\ArtefactsModel;
use Model\WonderOfTheWorldModel;
use function getCustom;
use function unitIdToNr;
use function unitIdToTribe;

class SpeedCalculator
{
    /** @var bool */
    private $isOwn           = FALSE;
    /** @var bool */
    private $isReturn        = FALSE;
    /** @var bool */
    private $isAlliance      = FALSE;
    /** @var bool */
    private $hasHero         = FALSE;
    /** @var mixed */
    private $minSpeed        = 1;
    /** @var mixed */
    private $leftHand        = [];
    /** @var mixed */
    private $shoes           = [];
    /** @var mixed */
    private $tournamentSqLvl = 0;
    /** @var mixed */
    private $artefactEffect  = 1;
    /** @var float|int */
    private $hunnicMountedArmySpeedBonus = 0;
    /** @var int */
    private $shipType        = 0;
    /** @var int */
    private $shipTribe       = 0;

    /** @var mixed */
    private $from;
    /** @var mixed */
    private $to;

    public function isOwn(): void
    {
        $this->isOwn = TRUE;
    }

    public function isReturn(): void
    {
        $this->isReturn = TRUE;
    }

    public function isAlliance(): void
    {
        $this->isAlliance = TRUE;
    }

    public function hasHero(): void
    {
        $this->hasHero = TRUE;
    }

    /**
     * The army sails: deep water is crossed at the ship's fixed speed (no hero
     * item, artefact or Tournament Square applies there), the rest of the line
     * at the army's own speed with the Tournament Square after 20 land tiles.
     *
     * @param int $type
     * @param mixed $tribe
     */
    public function setShip($type, $tribe): void
    {
        $this->shipType = (int)$type;
        $this->shipTribe = (int)$tribe;
    }

    /**
     * @param mixed $bonus
     */
    public function setHunnicMountedArmySpeedBonus($bonus): void
    {
        $this->hunnicMountedArmySpeedBonus = max(0, (float)$bonus);
    }

    /**
     * @param list<mixed> $unitsId
     * @param bool $allowOnlyHero
     */
    public function isCavalryOnly($unitsId, $allowOnlyHero = false): bool
    {
        $isCavalryOnly = true;
        foreach ($unitsId as $k) {
            $race = unitIdToTribe($k);
            if (!\Game\Tribes::isCavalry($race, unitIdToNr($k))) {
                return false;
            }
        }
        return $isCavalryOnly;
    }

    /**
     * @param mixed $effect
     */
    public function setArtefactEffect($effect): void
    {
        $this->artefactEffect = $effect;
    }

    /**
     * @param mixed $lvl
     */
    public function setTournamentSqLvl($lvl): void
    {
        if ($lvl <= 0) {
            return;
        }
        $this->tournamentSqLvl = $lvl;
    }

    //kid or x|y
    /**
     * @param mixed $kid
     */
    public function setFrom($kid): void
    {
        $this->from = $kid;
    }

    //kid or x|y
    /**
     * @param mixed $kid
     */
    public function setTo($kid): void
    {
        $this->to = $kid;
    }

    /**
     * @param mixed $minSpeed
     */
    public function setMinSpeed($minSpeed): void
    {
        if (is_array($minSpeed)) {
            if (sizeof($minSpeed) == 0) {
                $minSpeed = 1;
            } else {
                $minSpeed = min($minSpeed);
            }
        }
        if ($minSpeed < 1) {
            $minSpeed = 1;
        }
        $this->minSpeed = $minSpeed;
    }

    /**
     * @param mixed $params
     */
    public function setLeftHand($params): void
    {
        $this->leftHand = $this->procInput($params);
    }

    /**
     * @param mixed $id
     * @return mixed
     */
    public function procInput($id)
    {
        if (is_array($id)) {
            return $id;
        }
        if (!$id) {
            return NULL;
        }
        $db = DB::getInstance();
        return $db->query("SELECT * FROM items WHERE id={$id}")->fetch_assoc();
    }

    /**
     * @param mixed $params
     */
    public function setShoes($params): void
    {
        $this->shoes = $this->procInput($params);
    }

    /**
     * @param bool $result
     */
    public function hasCata($result = true): void
    {
        $this->hasCata = $result;
    }

    /** @var mixed */
    public $hasCata           = false;
    /** @var bool */
    public $troopsAreWithHero = false;

    public function troopsWithHero(): void
    {
        $this->troopsAreWithHero = true;
    }


    public function calc(): float
    {
        $minSpeed = $this->minSpeed + $this->hunnicMountedArmySpeedBonus;
        $distance = Formulas::getDistance($this->from, $this->to);
        $seaTime = 0;
        $bootBonus = 0;
        $bootThreshold = 20;
        if ($this->shipType > 0 && Ships::isShip($this->shipType)) {
            // A ship sails the flat straight line: no shortcut over the world edge.
            $distance = Formulas::getFlatDistance($this->from, $this->to);
            $deepDistance = $distance * \Game\Map\ShipRoute::deepFraction($this->from, $this->to);
            $seaTime = $deepDistance / Ships::deepWaterSpeed($this->shipType, $this->shipTribe) * 3600;
            $distance -= $deepDistance;
        }
        if ($this->hasHero) {
            $heroItems = new HeroItems();
            if (is_array($this->leftHand) && $this->leftHand['btype'] == 3) {
                if ($this->isReturn
                    && in_array($this->leftHand['type'],
                        [
                            61,
                            62,
                            63,
                        ])
                ) {
                    $item = $heroItems->getHeroItemProperties($this->leftHand['btype'], $this->leftHand['type']);
                    $minSpeed *= 1 + $item['return_speed'] / 100;
                }
                if ($this->isOwn
                    && in_array($this->leftHand['type'],
                        [
                            64,
                            65,
                            66,
                        ])
                ) {
                    $item = $heroItems->getHeroItemProperties($this->leftHand['btype'], $this->leftHand['type']);
                    $minSpeed *= 1 + $item['speed_own'] / 100;
                }
                if ($this->isAlliance
                    && in_array($this->leftHand['type'],
                        [
                            67,
                            68,
                            69,
                        ])
                ) {
                    $item = $heroItems->getHeroItemProperties($this->leftHand['btype'], $this->leftHand['type']);
                    $minSpeed *= 1 + $item['speed_alliance'] / 100;
                }
            }
            if (is_array($this->shoes) && $this->shoes['btype'] == 5
                && in_array($this->shoes['type'],
                    [
                        97,
                        98,
                        99,
                    ])
            ) {
                $item = $heroItems->getHeroItemProperties($this->shoes['btype'], $this->shoes['type']);
                if ($this->troopsAreWithHero) {
                    $bootBonus = $item['speed_distance'] / 100;
                    $bootThreshold = max(0, $item['min_distance']);
                }
            }
        }

        //artefact effect.
        if ($this->artefactEffect <> 1) {
            $minSpeed *= $this->artefactEffect;
        }
        if ($this->hasCata) {
            if (getCustom("reduceCataSpeedInAttacks") && Config::getProperty("game", "speed") > 500) {
                $rate = getCustom("cataSpeedLowerRate");
                if (ArtefactsModel::artifactsReleased()) {
                    $rate = getCustom("cataSpeedLowerRateAfterArtifactsRelease");
                }
                if (ArtefactsModel::wwPlansReleased()) {
                    $rate = getCustom("cataSpeedLowerRateAfterWWPlanRelease");
                }
                if ($this->isNatars($this->to) || $this->isNatars($this->from)) {
                    if (getCustom("reduceCataSpeedInNatarAttacks")) {
                        $minSpeed /= $rate;
                    }
                } else if ($this->isWW($this->to) || $this->isWW($this->from)) {
                    //do nothing
                } else {
                    $minSpeed /= $rate;
                }
            }
        }
        $boundaries = [0, $distance];
        if ($this->tournamentSqLvl > 0 && $distance > 20) {
            $boundaries[] = 20;
        }
        if ($bootBonus > 0 && $distance > $bootThreshold) {
            $boundaries[] = $bootThreshold;
        }
        sort($boundaries, SORT_NUMERIC);
        $time = $seaTime;
        for ($i = 1; $i < count($boundaries); ++$i) {
            $start = $boundaries[$i - 1];
            $bonus = 1;
            if ($start >= 20 && $this->tournamentSqLvl > 0) {
                $bonus += Formulas::TournamentSqValue($this->tournamentSqLvl) / 100 - 1;
            }
            if ($start >= $bootThreshold) {
                $bonus += $bootBonus;
            }
            $time += ($boundaries[$i] - $start) / ($minSpeed * $bonus) * 3600;
        }
        // Preserve the legacy calculator's float return type while applying
        // the centralized whole-second floor.
        return (float)Formulas::travelTimeSeconds($time);
    }

    /**
     * @param mixed $kid
     */
    public function isNatars($kid): bool
    {
        $db = DB::getInstance();
        return $db->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid") == 1;
    }

    /**
     * @param mixed $kid
     */
    public function isWW($kid): bool
    {
        $db = DB::getInstance();
        return $db->fetchScalar("SELECT isWW FROM vdata WHERE kid=$kid") == 1;
    }
}
