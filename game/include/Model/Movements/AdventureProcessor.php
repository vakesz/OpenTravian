<?php

declare(strict_types=1);

namespace Model\Movements;

use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use Game\Hero\Artwork;
use Game\Hero\HeroExperience;
use Game\Helpers\HeroHealthHelper;
use Game\Hero\HeroHelper;
use Game\NoticeHelper;
use Game\ResourcesHelper;
use Game\SpeedCalculator;
use Model\AdventureModel;
use Model\ArtefactsModel;
use Model\AuctionModel;
use Model\DailyQuestModel;
use Model\HeroResourceInventory;
use Model\MovementsModel;
use Model\VillageModel;
use function game_progress;
use function getGameSpeed;
use function nrToUnitId;

class AdventureProcessor
{
    /**
     * @param array<string, mixed> $row the arriving adventure movement row
     */
    public function __construct(array $row)
    {
        $row['end_time_seconds'] = (int) ceil($row['end_time'] / 1000);

        $db = DB::getInstance();
        $owner = $db->fetchScalar("SELECT owner FROM vdata WHERE kid={$row['kid']}");
        if ($owner === false) {
            return;
        }
        $owner = (int) $owner;
        $m = new AdventureModel();
        if (!$m->checkForAdventure($owner, $row['to_kid'])) {
            $this->returnHero($owner, $row);
            return;
        }
        $config = Config::getInstance();
        (new DailyQuestModel())->setQuestAsCompleted($owner, 1);
        $stmt = $db->query("SELECT dif, id, created FROM adventure WHERE uid=$owner AND end=0 AND kid={$row['to_kid']}");
        if (!$stmt->num_rows) {
            $this->returnHero($owner, $row);
            return;
        }
        $adventureRow = $stmt->fetch_assoc();
        $db->query("UPDATE adventure SET end=1 WHERE id={$adventureRow['id']}");
        $diff = (int) $adventureRow['dif'];
        $user = $db->query("SELECT name, success_adventures_hex, success_adventures_count, signupTime FROM users WHERE id=$owner")->fetch_assoc();
        $success_adventures = (int) $user['success_adventures_count'];
        $success_adventures_hex = (int) $user['success_adventures_hex'];
        $helper = new HeroHelper();
        HeroHealthHelper::updateUserHeroHealth($owner);
        $hero = $db->query("SELECT * FROM hero WHERE uid=$owner")->fetch_assoc();
        $inventory = $db->query("SELECT * FROM inventory WHERE uid=$owner")->fetch_assoc();
        $total_power = $helper->calcTotalPower($row['race'],
            $hero['power'],
            $inventory['rightHand'],
            $inventory['leftHand'],
            $inventory['body']);
        $heroPureExp = 8 + mt_rand($success_adventures, $success_adventures * 2);
        if ($diff) {
            $heroPureExp *= 2;
        }
        $sgh = $diff ? mt_rand(800, 1200) : mt_rand(500, 800);
        $rate = 3.007;
        if ($success_adventures < 20) {
            $rate /= 2.7;
        }
        $sgh *= abs((time() - $user['signupTime']) / ($config->game->round_length * 86400)) + 1;
        $exp = (int) round($heroPureExp * ((100 + $helper->calcMoreExp($inventory['helmet'])) / 100));
        $expMul = Formulas::getHeroExpLevelMultiplier();
        if ($expMul > 1) {
            $exp = (int) round($exp * mt_rand((int) floor($expMul / 2), (int) $expMul));
        }
        $total_power *= 1 + $helper->calcOffBonus($hero['offBonus']) / 100;
        $sgh *= 1 + ($total_power / 500);
        $resist = $helper->calcResist($inventory['body']);
        $damage = max(0, (int) round($rate * $sgh / $total_power - $resist));
        $report = [
            "kid" => (int) $row['kid'],
            "uid" => $owner,
            "uname" => $db->fetchScalar("SELECT name FROM users WHERE id={$owner}"),
            'race' => $row['race'],
            'adventureNumber' => $success_adventures + 1,
        ];
        if ($damage >= min(90, $hero['health'])) {
            $report['dead'] = true;
            $db->query("UPDATE adventure SET end=0 WHERE id={$adventureRow['id']}");
            $heroKid = (int)$hero['kid'];
            ResourcesHelper::updateVillageResources($heroKid, true);
            // Death first: a level-up in the same adventure must not revive the hero.
            $db->query("UPDATE hero SET health=0 WHERE uid=$owner");
            HeroExperience::add($owner, $exp);
            ResourcesHelper::updateVillageResources($heroKid, false, false);
            NoticeHelper::addNotice(0,
                $owner,
                $row['kid'],
                $row['to_kid'],
                NoticeHelper::TYPE_ADVENTURE,
                '-1',
                $report,
                $row['end_time_seconds']);
            return;
        }
        $db->query("UPDATE users SET success_adventures_count=success_adventures_count+1 WHERE id=$owner");
        $report['damage'] = $damage;
        $report['exp'] = $exp;
        // Damage first so a lethal hit is already health 0 before the level-up heal can run.
        $db->query("UPDATE hero SET health=health-$damage WHERE uid=$owner");
        HeroExperience::add($owner, $exp);
        if ($success_adventures < 9) {
            $randGive = [1, 2, 4, 8, 16, 32, 64, 128, 256, 512];
            foreach ($randGive as $giveKey => $give) {
                if ($success_adventures_hex & $give) {
                    unset($randGive[$giveKey]);
                }
            }
            shuffle($randGive);
            sort($randGive);
            $randP = $randGive[mt_rand(0, count($randGive) - 1)];
            if($success_adventures == 2 && in_array(4, $randGive)){
                $randP = 4;
            }
            switch ($randP) {
                case 1://pomad
                    $num = mt_rand(20, 25);
                    $auction = new AuctionModel();
                    $auction->addItemToUser($owner, 11, 106, $num);
                    NoticeHelper::addNotice(0,
                        $owner,
                        $row['kid'],
                        $row['to_kid'],
                        NoticeHelper::TYPE_ADVENTURE,
                        [
                            1,
                            11,
                            106,
                            $num,
                        ],
                        $report,
                        $row['end_time_seconds']);
                    break;
                case 2://troop 3x
                    $troop = $this->getTroops($row['race'], true);
                    $this->sendTroops($owner, $row, $troop['nr'], $troop['num']);
                    NoticeHelper::addNotice(0,
                        $owner,
                        $row['kid'],
                        $row['to_kid'],
                        NoticeHelper::TYPE_ADVENTURE,
                        [
                            4,
                            $row['race'],
                            $troop['nr'],
                            $troop['num'],
                        ],
                        $report,
                        $row['end_time_seconds']);
                    ResourcesHelper::updateVillageResources($row['kid'], false);
                    break;
                case 4://asb
                    $auction = new AuctionModel();
                    $auction->addItemToUser($owner, 6, 103, 1);
                    NoticeHelper::addNotice(0,
                        $owner,
                        $row['kid'],
                        $row['to_kid'],
                        NoticeHelper::TYPE_ADVENTURE,
                        [
                            1,
                            6,
                            103,
                            1,
                        ],
                        $report,
                        $row['end_time_seconds']);
                    break;
                case 8://ghafas
                    $num = 6 * $config->game->cage_multiplier;
                    $auction = new AuctionModel();
                    $auction->addItemToUser($owner, 9, 114, $num);
                    NoticeHelper::addNotice(0,
                        $owner,
                        $row['kid'],
                        $row['to_kid'],
                        NoticeHelper::TYPE_ADVENTURE,
                        [
                            1,
                            9,
                            114,
                            $num,
                        ],
                        $report,
                        $row['end_time_seconds']);
                    break;
                case 16://resources
                case 32://resources
                    $rPerType = $this->calculateResourcesPerType();
                    $this->grantAdventureResources($owner, $rPerType);
                    NoticeHelper::addNotice(0,
                        $owner,
                        $row['kid'],
                        $row['to_kid'],
                        NoticeHelper::TYPE_ADVENTURE,
                        [
                            2,
                            $rPerType[1],
                            $rPerType[2],
                            $rPerType[3],
                            $rPerType[4],
                        ],
                        $report,
                        $row['end_time_seconds']);
                    break;
                case 64://none or resources
                    if (mt_rand(1, 3) == 2) {
                        $rPerType = $this->calculateResourcesPerType();
                        $this->grantAdventureResources($owner, $rPerType);
                        NoticeHelper::addNotice(0,
                            $owner,
                            $row['kid'],
                            $row['to_kid'],
                            NoticeHelper::TYPE_ADVENTURE,
                            [
                                2,
                                $rPerType[1],
                                $rPerType[2],
                                $rPerType[3],
                                $rPerType[4],
                            ],
                            $report,
                            $row['end_time_seconds']);
                    } else {
                        NoticeHelper::addNotice(0,
                            $owner,
                            $row['kid'],
                            $row['to_kid'],
                            NoticeHelper::TYPE_ADVENTURE,
                            '0',
                            $report,
                            $row['end_time_seconds']);
                    }
                    break;
                case 128://silver
                case 256://silver
                    $silver = mt_rand(100 - mt_rand(1, 50), 400 + mt_rand(1, 50));
                    $m = new AuctionModel();
                    $m->addBooking($owner,
                        1,
                        +$silver,
                        +$silver + $db->fetchScalar("SELECT silver FROM users WHERE id=$owner"),
                        $row['end_time_seconds']);
                    $db->query("UPDATE users SET silver=silver+$silver WHERE id=$owner");
                    NoticeHelper::addNotice(0,
                        $owner,
                        $row['kid'],
                        $row['to_kid'],
                        NoticeHelper::TYPE_ADVENTURE,
                        [
                            3,
                            $silver,
                        ],
                        $report,
                        $row['end_time_seconds']);
                    break;
                case 512:
                    //ketab
                    $auction = new AuctionModel();
                    $auction->addItemToUser($owner, 13, 110, 1);
                    NoticeHelper::addNotice(0,
                        $owner,
                        $row['kid'],
                        $row['to_kid'],
                        NoticeHelper::TYPE_ADVENTURE,
                        [
                            1,
                            13,
                            110,
                            1,
                        ],
                        $report,
                        $row['end_time_seconds']);
                    break;
            }
            $success_adventures_hex |= $randP;
            $db->query("UPDATE users SET success_adventures_hex=$success_adventures_hex WHERE id=$owner");
            $this->returnHero($owner, $row);
            return;
        }
        $rand = mt_rand(1, 14);
        if ($rand <= 8) {
            if ($success_adventures <= 10) {
                $bonus = mt_rand(1, 15);
            } else if ($success_adventures <= 20) {
                $bonus = mt_rand(1, 20);
            } else {
                $bonus = mt_rand(1, max(18, 30 - $success_adventures));
            }
            $tearNum = Formulas::heroItemsTierAt((int) $adventureRow['created'] - (int) Config::getInstance()->game->start_time);
            do {
                if($db->fetchScalar("SELECT COUNT(id) FROM items WHERE uid=$owner AND btype=6 AND type=" . (102+$tearNum))){
                    $bonus = mt_rand(1, max(18, 30 - $success_adventures));
                } else {
                    break;
                }
            } while($bonus == 6);
            switch ($bonus) {
                case 1:
                    $rand = [
                        1 => [1 => 1, 4, 7, 10, 13],
                        2 => [1 => 2, 5, 8, 11, 14],
                        3 => [1 => 3, 6, 9, 12, 15],
                    ][$tearNum];
                    break;
                case 2:
                    $rand = [
                        1 => [1 => 82, 85, 88, 91],
                        2 => [1 => 83, 86, 89, 92],
                        3 => [1 => 84, 87, 90, 93],
                    ][$tearNum];
                    break;
                case 3:
                    $rand = [
                        1 => [1 => 61, 64, 67, 73, 79],
                        2 => [1 => 62, 65, 68, 74, 77, 80],
                        3 => [1 => 63, 66, 69, 75, 78, 81],
                    ][$tearNum];
                    break;
                case 4:
                    $rand = [
                        1 => [
                            1 => [1 => 16, 19, 22, 25, 28],
                            2 => [1 => 17, 20, 23, 26, 29],
                            3 => [1 => 18, 21, 24, 27, 30],
                        ],
                        2 => [
                            1 => [1 => 46, 49, 52, 55, 58],
                            2 => [1 => 47, 50, 53, 56, 59],
                            3 => [1 => 48, 51, 54, 57, 60],
                        ],
                        3 => [
                            1 => [1 => 31, 34, 37, 40, 43],
                            2 => [1 => 32, 35, 38, 41, 44],
                            3 => [1 => 33, 36, 39, 42, 45],
                        ],
                        6 => [
                            1 => [1 => 115, 118, 121, 124, 127],
                            2 => [1 => 116, 119, 122, 125, 128],
                            3 => [1 => 117, 120, 123, 126, 129],
                        ],
                        7 => [
                            1 => [1 => 130, 133, 136, 139, 142],
                            2 => [1 => 131, 134, 137, 140, 143],
                            3 => [1 => 132, 135, 138, 141, 144],
                        ],
                    ][$row['race']][$tearNum];
                    break;
                case 5:
                    $rand = [
                        1 => [1 => 94, 97, 100],
                        2 => [1 => 95, 98, 101],
                        3 => [1 => 96, 99, 102],
                    ][$tearNum];
                    break;
                case 6:
                    $rand = [
                        1 => [1 => 103],
                        2 => [1 => 104],
                        3 => [1 => 105],
                    ][$tearNum];
                    break;
                case 7:
                    $rand = [1 => 112];
                    break;
                case 8:
                    $rand = [1 => 113];
                    break;
                case 9:
                    $rand = [1 => 114];
                    break;
                case 10:
                    $rand = [1 => 107];
                    break;
                case 11:
                    $rand = [1 => 106];
                    break;
                case 12:
                    $rand = [1 => 108];
                    break;
                case 13:
                    $rand = [1 => 110];
                    break;
                case 14:
                    $rand = [1 => 109];
                    break;
                case 15:
                    $rand = Artwork::canDrop((int)$success_adventures) ? [1 => 111] : 0;
                    break;
                default:
                    $rand = 0;
                    break;
            }
            if ($rand == 0) {
                NoticeHelper::addNotice(0,
                    $owner,
                    $row['kid'],
                    $row['to_kid'],
                    NoticeHelper::TYPE_ADVENTURE,
                    '0',
                    $report,
                    $row['end_time_seconds']);
            } else {
                $base = $bonus <= 6 || $bonus >= 12 ? 1 : mt_rand(20, 50);
                if ($bonus == 7 || $bonus == 8 || $bonus == 9) {
                    $base *= $config->game->cage_multiplier;
                }
                $rand = $rand[mt_rand(1, sizeof($rand))];
                $tolerance = 3 * game_progress();
                if ($bonus <= 6 || ($bonus >= 12 && $bonus <= 15)) {
                    $tolerance = 0;
                }
                $num = $base + ($base * $tolerance);
                $num = (int) ceil($num);
                $auction = new AuctionModel();
                $auction->addItemToUser($owner, $bonus, $rand, $num);
                NoticeHelper::addNotice(0,
                    $owner,
                    $row['kid'],
                    $row['to_kid'],
                    NoticeHelper::TYPE_ADVENTURE,
                    [
                        1,
                        $bonus,
                        $rand,
                        $num,
                    ],
                    $report,
                    $row['end_time_seconds']);
            }
            $this->returnHero($owner, $row);
        } else if ($rand <= 10) {
            //resources
            $rPerType = $this->calculateResourcesPerType();
            $this->grantAdventureResources($owner, $rPerType);
            NoticeHelper::addNotice(0,
                $owner,
                $row['kid'],
                $row['to_kid'],
                NoticeHelper::TYPE_ADVENTURE,
                [
                    2,
                    $rPerType[1],
                    $rPerType[2],
                    $rPerType[3],
                    $rPerType[4],
                ],
                $report,
                $row['end_time_seconds']);
            $this->returnHero($owner, $row);
        } else if ($rand <= 12) {
            $base = mt_rand(200, 400);
            $tolerance = 4 * game_progress();
            $silver = (int) ceil($base + ($base * $tolerance));
            $m = new AuctionModel();
            $m->addBooking($owner,
                1,
                +$silver,
                +$silver + $db->fetchScalar("SELECT silver FROM users WHERE id=$owner"),
                $row['end_time_seconds']);
            $db->query("UPDATE users SET silver=silver+$silver WHERE id=$owner");
            NoticeHelper::addNotice(0,
                $owner,
                $row['kid'],
                $row['to_kid'],
                NoticeHelper::TYPE_ADVENTURE,
                [
                    3,
                    $silver,
                ],
                $report,
                $row['end_time_seconds']);
            $this->returnHero($owner, $row);
        } else {
            //units.
            $troop = $this->getTroops($row['race']);
            $this->sendTroops($owner, $row, $troop['nr'], $troop['num']);
            NoticeHelper::addNotice(0,
                $owner,
                $row['kid'],
                $row['to_kid'],
                NoticeHelper::TYPE_ADVENTURE,
                [
                    4,
                    $row['race'],
                    $troop['nr'],
                    $troop['num'],
                ],
                $report,
                $row['end_time_seconds']);
            $this->returnHero($owner, $row);
            ResourcesHelper::updateVillageResources($row['kid'], false);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function returnHero(int $owner, array $row): void
    {
        $db = DB::getInstance();
        $inventory = $db->query("SELECT * FROM inventory WHERE uid=$owner")->fetch_assoc();
        $artifact_effect = ArtefactsModel::getArtifactEffectByType($owner, $row['kid'], ArtefactsModel::ARTIFACT_INCREASE_SPEED);
        $heroHelper = new HeroHelper();
        $heroSpeed = $heroHelper->calcTotalSpeed($row['race'], $inventory['horse'], $inventory['shoes']);
        $calc = new SpeedCalculator();
        $calc->setFrom($row['to_kid']);
        $calc->setTo($row['kid']);
        $calc->isReturn();
        $calc->hasHero();
        $calc->setHunnicMountedArmySpeedBonus($heroHelper->getHunnicMountedArmySpeedBonus(
            $row['race'],
            $inventory['horse'],
            true
        ));
        $calc->setTournamentSqLvl(VillageModel::getTournamentSquireLevel($row['kid']));
        $calc->setMinSpeed($heroSpeed);
        $calc->setLeftHand($inventory['leftHand']);
        $calc->setShoes($inventory['shoes']);
        $calc->setArtefactEffect($artifact_effect);
        $units = array_fill(1, 11, 0);
        $units[11] = 1;
        $timeNeeded = $calc->calc();
        (new MovementsModel())->addMovement((int) $row['to_kid'],
            (int) $row['kid'],
            (int) $row['race'],
            $units,
            0,
            0,
            0,
            0,
            1,
            (int) $row['attack_type'],
            $row['end_time'],
            $row['end_time'] + 1000 * $timeNeeded,
            $row['data'] === null ? null : (string) $row['data']);
    }

    /**
     * @return array{nr: int, num: int}
     */
    private function getTroops(int $race, bool $default = false): array
    {
        $nr = $default ? 1 : mt_rand(1, 6);
        if ($default && getGameSpeed() <= 10) {
            $num = 3;
        } else {
            if ($nr <= Formulas::getSpyId($race)) {
                $base = getGameSpeed() <= 10 ? mt_rand(3, 12) : mt_rand(12, 48);
            } else {
                $base = getGameSpeed() <= 10 ? mt_rand(2, 8) : mt_rand(8, 32);
            }
            if (getGameSpeed() > 20) {
                $base *= (int) ceil(Config::getProperty('game', 'speed') / 10);
            }
            $tolerance = 10 * game_progress();
            $num = (int) ($base + ceil($base * $tolerance));
        }
        return ['nr' => $nr, 'num' => $num];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function sendTroops(int $owner, array $row, int $nr, int $num): void
    {
        $calc = new SpeedCalculator();
        $calc->setFrom($row['to_kid']);
        $calc->setTo($row['kid']);
        $calc->isReturn();
        $units = array_fill(1, 11, 0);
        $units[$nr] = $num;
        $speeds[] = Formulas::uSpeed(nrToUnitId($nr, $row['race']));
        $db = DB::getInstance();
        $buildings = $db->query("SELECT * FROM fdata WHERE kid={$row['kid']}")->fetch_assoc();
        for ($i = 19; $i <= 38; ++$i) {
            if ($buildings['f' . $i . 't'] == 14) {
                $calc->setTournamentSqLvl($buildings['f' . $i]);
                break;
            }
        }
        $calc->setMinSpeed($speeds);
        $calc->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($owner, $row['kid'], ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        (new MovementsModel())->addMovement(
            (int) $row['to_kid'],
            (int) $row['kid'],
            (int) $row['race'],
            $units,
            0,
            0,
            0,
            0,
            1,
            (int) $row['attack_type'],
            $row['end_time'],
            $row['end_time'] + 1000 * $calc->calc(),
            null
        );
    }

    /**
     * @return array<int, int> the same amount for resource types 1 to 4
     */
    private function calculateResourcesPerType(): array
    {
        $tolerance = 15 * game_progress();
        $base = mt_rand(450, 700) * getGameSpeed();

        return array_fill(1, 4, (int) ($base + ceil($base * $tolerance)));
    }

    /** @param array<int, int> $resources */
    private function grantAdventureResources(int $owner, array $resources): void
    {
        $granted = (new HeroResourceInventory())->add($owner, [
            'wood' => (int)$resources[1],
            'clay' => (int)$resources[2],
            'iron' => (int)$resources[3],
            'crop' => (int)$resources[4],
        ]);
        if (!$granted) {
            throw new \RuntimeException('Could not add adventure resources to the hero inventory.');
        }
    }
}
