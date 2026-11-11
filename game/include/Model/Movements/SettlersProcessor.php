<?php

declare(strict_types=1);

namespace Model\Movements;

use Core\Database\DB;
use Game\Formulas;
use Game\Helpers\CulturePointsHelper;
use Game\NoticeHelper;
use Game\SpeedCalculator;
use Model\AllianceModel;
use Model\ArtefactsModel;
use Model\MovementsModel;
use Model\RegisterModel;
use Model\SummaryModel;

class SettlersProcessor
{
    /**
     * @param array<string, mixed> $row the arriving settlers movement row
     */
    public function __construct(array $row)
    {
        $kid = (int) $row['kid'];
        $toKid = (int) $row['to_kid'];
        $endSeconds = (int) ceil($row['end_time'] / 1000);

        $db = DB::getInstance();
        $wdata = $db->query("SELECT occupied, fieldtype FROM wdata WHERE id=$toKid")->fetch_assoc();
        $owner = $db->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid");
        if ($owner === false) {
            return;
        }
        $owner = (int) $owner;
        if ($wdata === null || $wdata['occupied']) {
            $this->returnSettlers($row, $owner);
            return;
        }
        $race = $db->fetchScalar("SELECT race FROM users WHERE id=$owner");
        if ($race === false) {
            return;
        }
        //maybe they're killed on the way.
        if ($db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE kid=$toKid") >= 1 || (int) $row['u10'] < 3) {
            $this->returnSettlers($row, $owner);
            return;
        }
        CulturePointsHelper::updateUserCP($owner);
        [$cp, $total_villages, $aid, $name] = $db->query("SELECT cp, total_villages, aid, name FROM users WHERE id=$owner")->fetch_row();
        if ($cp < Formulas::newVillageCP($total_villages + 1)) {
            $this->returnSettlers($row, $owner);
            return;
        }
        $register = new RegisterModel();
        $register->createNewVillage($owner, (int) $race, $toKid, $kid);

        (new SummaryModel())->setFirstVillageUser($name);

        $xy = Formulas::kid2xy($toKid);
        NoticeHelper::addSurrounding($xy['x'],
            $xy['y'],
            NoticeHelper::SURROUNDING_VILLAGE_FOUND,
            [
                $owner,
                $name,
                $toKid,
            ],
            $endSeconds);
        if ($aid) {
            (new AllianceModel())->addLog($aid,
                [AllianceModel::LOG_NEW_VILLAGE, $owner, $name],
                $endSeconds);
        }
        NoticeHelper::addNotice($aid,
            $owner,
            $kid,
            $toKid,
            NoticeHelper::TYPE_NEW_VILLAGE,
            null,
            ['kid' => $kid],
            $endSeconds);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function returnSettlers(array $row, int $owner): void
    {
        $kid = (int) $row['kid'];
        $toKid = (int) $row['to_kid'];
        $calc = new SpeedCalculator();
        $calc->setFrom($toKid);
        $calc->setTo($kid);
        $calc->isReturn();
        $calc->setMinSpeed(Formulas::uSpeed(nrToUnitId(10, (int) $row['race'])));
        $db = DB::getInstance();
        $buildings = $db->query("SELECT * FROM fdata WHERE kid=$kid")->fetch_assoc();
        for ($i = 18; $i <= 38; ++$i) {
            if ((int) $buildings['f' . $i . 't'] === 14) {
                $calc->setTournamentSqLvl($buildings['f' . $i]);
                break;
            }
        }
        $calc->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($owner, $toKid, ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        $units = array_fill(1, 11, 0);
        $units[10] = 3;
        (new MovementsModel())->addMovement(
            $toKid,
            $kid,
            (int) $row['race'],
            $units,
            0,
            0,
            0,
            0,
            1,
            (int) $row['attack_type'],
            $row['end_time'],
            $row['end_time'] + 1000 * $calc->calc()
        );
        $db->query("UPDATE vdata SET wood=wood+750, clay=clay+750, iron=iron+750, crop=crop+750 WHERE kid=$kid");
    }
}
