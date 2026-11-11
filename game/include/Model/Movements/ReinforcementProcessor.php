<?php

declare(strict_types=1);

namespace Model\Movements;

use Core\Database\DB;
use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\NoticeHelper;
use Game\ResourcesHelper;
use Game\SpeedCalculator;
use Model\AutomationModel;
use Model\ArtefactsModel;
use Model\MovementsModel;
use Model\MultiAccount;
use Model\OasesModel;
use Model\VillageModel;

class ReinforcementProcessor
{
    /**
     * @param array<string, mixed> $row the arriving movement row
     */
    public function __construct(array $row)
    {
        $kid = (int) $row['kid'];
        $toKid = (int) $row['to_kid'];
        $race = (int) $row['race'];
        $startSeconds = (int) ceil($row['start_time'] / 1000);
        $endSeconds = (int) ceil($row['end_time'] / 1000);

        $m = new AutomationModel();
        $db = DB::getInstance();
        if (!$this->getVillageState($toKid)) {
            if ($race !== 5) {
                $this->returnTroops($row);
            }
            return;
        }
        $oasesModel = new OasesModel();
        $isOasis = $oasesModel->isOasis($toKid);
        $redeployHero = false;
        $uid = $race === 4 ? 0 : (int) $m->getVillage($kid, 'owner')['owner'];
        $to_uid = (int) ($isOasis
            ? $db->query("SELECT owner FROM odata WHERE kid=$toKid")->fetch_assoc()['owner']
            : $m->getVillage($toKid, 'owner')['owner']);
        if ($to_uid === 1 || ($to_uid <= 10 && (int) $m->getUser($to_uid, 'hidden')['hidden'] === 1)) {
            $this->returnTroops($row);
            return;
        }
        if ($uid !== $to_uid) {
            MultiAccount::addMultiAccountLog($uid, $to_uid, 2);
        }
        if ($row['redeployHero'] && !$isOasis) {
            $redeployHero = (int) $row['u11'] > 0 && $uid === $to_uid && $this->getRallyPoint($toKid) > 0;
        }
        $units = [];
        for ($i = 1; $i <= 11; ++$i) {
            $units[$i] = $i === 11 && $redeployHero ? 0 : (int) $row['u' . $i];
        }
        if (array_sum($units) > 0) {
            $move = new MovementsModel();
            $enforceId = false;
            if (!$isOasis && $race === 4) {
                //Oasis troops coming!
                $enforceId = $db->fetchScalar("SELECT e.id FROM enforcement e WHERE e.race=4 AND e.to_kid=$toKid LIMIT 1");
            }
            if (!$enforceId) {
                $enforceId = $move->isSameVillageReinforcementExists($kid, $toKid);
            }
            if ($enforceId) {
                //other reinforce exists.
                //modify it.
                $modify = [];
                foreach ($units as $i => $count) {
                    if ($count > 0) {
                        $modify[] = "u{$i}=u{$i}+" . $count;
                    }
                }
                if ($modify !== []) {
                    $db->query('UPDATE enforcement SET ' . implode(',', $modify) . ' WHERE id=' . (int) $enforceId);
                }
            } else {
                $move->addEnforce($uid, $kid, $toKid, $race, $units);
            }
        }
        if ($redeployHero) {
            $db->query("UPDATE hero SET kid=$toKid WHERE uid=$uid");
            $db->query("UPDATE units SET u11=1 WHERE kid=$toKid");
            ResourcesHelper::updateVillageResources($kid, false);
            ResourcesHelper::updateVillageResources($toKid, false);
        }
        //add report here.
        $consumption = 0;
        $units = [];
        for ($i = 1; $i <= 11; ++$i) {
            $units[$i] = (int) $row['u' . $i];
            $consumption += $units[$i] * Formulas::uUpkeep(nrToUnitId($i, $race));
            //here consumption doesn't include effect of artefact or horse drinking pool
        }
        if ($race !== 4) {
            // Updating upkeep for receiver
            $targetKid = $isOasis ? $oasesModel->getOasisCaptureKid($toKid) : $toKid;
            $upkeep = ResourcesHelper::getTotalCropConsumption($race, $units, VillageModel::getHDP($targetKid, $race));
            ResourcesHelper::modifyUpkeep($to_uid, $targetKid, $upkeep);
            $upkeep = ResourcesHelper::getTotalCropConsumption($race, $units, VillageModel::getHDP($kid, $race));
            ResourcesHelper::modifyUpkeep($uid, $kid, $upkeep, 1);
        }

        $report = [
            'sender' => [
                'uid' => $uid,
                'kid' => $kid,
                'uname' => $uid === 0 ? '' : $db->fetchScalar("SELECT name FROM users WHERE id=$uid"),
            ],
            'receiver' => [
                'uid' => $to_uid,
                'kid' => $toKid,
                'uname' => $to_uid ? $db->fetchScalar("SELECT name FROM users WHERE id=$to_uid LIMIT 1") : '',
            ],
            'units' => ['race' => $race, 'num' => $units],
            'consumption' => $consumption,
            'timeTaken' => $endSeconds - $startSeconds,
        ];
        $reportType = NoticeHelper::TYPE_REINFORCEMENT;
        NoticeHelper::addNotice(0, $uid, $kid, $toKid, $reportType, null, $report, $endSeconds);
        if ($uid !== $to_uid) {
            NoticeHelper::addNotice(0, $to_uid, $kid, $toKid, $reportType, null, $report, $endSeconds);
        }
    }

    private function getVillageState(int $kid): bool
    {
        return (bool) DB::getInstance()->fetchScalar("SELECT occupied FROM wdata WHERE id=$kid", [], false);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function returnTroops(array $row): void
    {
        $db = DB::getInstance();
        $kid = (int) $row['kid'];
        $toKid = (int) $row['to_kid'];
        $race = (int) $row['race'];
        $calculator = new SpeedCalculator();
        $calculator->setFrom($toKid);
        $calculator->setTo($kid);
        $calculator->isReturn();
        $calculator->hasCata((int) $row['u8'] > 0);
        $speeds = [];
        $units_id = [];
        $units = array_fill(1, 11, 0);
        for ($i = 1; $i <= 11; ++$i) {
            $count = (int) $row['u' . $i];
            if ($count === 0) {
                continue;
            }
            $units[$i] = $count;
            if ($i === 11) {
                continue;
            }
            $speeds[] = Formulas::uSpeed(nrToUnitId($i, $race));
            $units_id[] = nrToUnitId($i, $race);
        }
        $uid = (int) $db->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid");
        if ($units[11] > 0) {
            $calculator->hasHero();
            $heroHelper = new HeroHelper();
            $inventory = $db->query("SELECT * FROM inventory WHERE uid=$uid")->fetch_assoc();
            $calculator->setLeftHand($inventory['leftHand']);
            $calculator->setShoes($inventory['shoes']);
            $cavalryOnly = $calculator->isCavalryOnly($units_id);
            $speeds[] = $heroHelper->calcTotalSpeed($race, $inventory['horse'], $inventory['shoes']);
            $calculator->setHunnicMountedArmySpeedBonus($heroHelper->getHunnicMountedArmySpeedBonus(
                $race,
                $inventory['horse'],
                $cavalryOnly
            ));

            if (array_sum($units) > 1) {
                $calculator->troopsWithHero();
            }
        }
        $calculator->setMinSpeed(min($speeds));
        $calculator->setTournamentSqLvl($this->getTournamentSqLvl($kid));
        $calculator->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($uid, $kid, ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        (new MovementsModel())->addMovement(
            $toKid,
            $kid,
            $race,
            $units,
            0,
            0,
            0,
            0,
            1,
            (int) $row['attack_type'],
            $row['end_time'],
            $row['end_time'] + 1000 * $calculator->calc()
        );
    }

    private function getTournamentSqLvl(int $kid): int
    {
        $buildings = DB::getInstance()->query("SELECT f18t, f19, f19t, f20, f20t, f21, f21t, f22, f22t, f23, f23t, f24, f24t, f25, f25t, f26, f26t, f27, f27t, f28, f28t, f29, f29t, f30, f30t, f31, f31t, f32, f32t, f33, f33t, f34, f34t, f35, f35t, f36, f36t, f37, f37t, f38, f38t FROM fdata WHERE kid=$kid")->fetch_assoc();
        for ($i = 18; $i <= 38; ++$i) {
            if ((int) $buildings['f' . $i . 't'] === 14) {
                return (int) $buildings['f' . $i];
            }
        }

        return 0;
    }

    private function getRallyPoint(int $kid): int
    {
        return max(0, (int) DB::getInstance()->fetchScalar("SELECT f39 FROM fdata WHERE kid=$kid"));
    }
}
