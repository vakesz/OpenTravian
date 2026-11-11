<?php

namespace Controller\Ajax\adventure;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Core\Session;
use Game\Hero\HeroHelper;
use Game\SpeedCalculator;
use Model\AdventureModel;
use Model\ArtefactsModel;
use Model\VillageModel;

final class calculateDuration extends AjaxBase
{
    /**
     * @return mixed
     */
    public function dispatch()
    {
        $originKid = $this->originKid($_POST['currentKidAndDid'] ?? null);
        $adventureKids = $_POST['adventureKids'] ?? null;

        if ($originKid === null || !is_array($adventureKids) || !$this->ownsVillage($originKid)) {
            return $this->error('Invalid adventure duration request.');
        }

        $inventory = DB::getInstance()->query(
            'SELECT horse, shoes, leftHand FROM inventory WHERE uid=' . (int)Session::getInstance()->getPlayerId()
        )->fetch_assoc();
        if (!$inventory) {
            return $this->error('Hero inventory was not found.', 404);
        }

        $this->response['noAdventures'] = true;
        $this->response['responseArray'] = [];
        $adventureModel = new AdventureModel();
        foreach ($adventureKids as $adventureKid) {
            $adventureKid = filter_var($adventureKid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($adventureKid === false || !$adventureModel->checkForAdventure(Session::getInstance()->getPlayerId(), $adventureKid)) {
                continue;
            }

            $this->response['noAdventures'] = false;
            $this->response['responseArray']['walktime' . $adventureKid] = secondsToString(
                $this->calculateDuration($originKid, (int)$adventureKid, $inventory)
            );
        }
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    private function originKid($value)
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        if (!preg_match('/^(\d+)(?:-|$)/', (string)$value, $matches)) {
            return null;
        }
        $kid = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $kid === false ? null : (int)$kid;
    }

    /**
     * @param mixed $kid
     */
    private function ownsVillage($kid): bool
    {
        $db = DB::getInstance();
        return (bool)$db->fetchScalar(
            'SELECT 1 FROM vdata WHERE kid=' . (int)$kid . ' AND owner=' . (int)Session::getInstance()->getPlayerId() . ' LIMIT 1'
        );
    }

    /**
     * @param mixed $originKid
     * @param int $adventureKid
     * @param array<mixed> $inventory
     * @return mixed
     */
    private function calculateDuration($originKid, $adventureKid, array $inventory)
    {
        $helper = new HeroHelper();
        $calculator = new SpeedCalculator();
        $calculator->setFrom($originKid);
        $calculator->setTo($adventureKid);
        $calculator->hasHero();
        $calculator->setHunnicMountedArmySpeedBonus($helper->getHunnicMountedArmySpeedBonus(
            Session::getInstance()->getRace(),
            $inventory['horse'],
            true
        ));
        $calculator->setMinSpeed($helper->calcTotalSpeed(
            Session::getInstance()->getRace(),
            $inventory['horse'],
            $inventory['shoes']
        ));
        $calculator->setLeftHand($inventory['leftHand']);
        $calculator->setShoes($inventory['shoes']);
        $calculator->setArtefactEffect(ArtefactsModel::getArtifactEffectByType(
            Session::getInstance()->getPlayerId(),
            $originKid,
            ArtefactsModel::ARTIFACT_INCREASE_SPEED
        ));
        $calculator->setTournamentSqLvl(VillageModel::getTournamentSquireLevel($originKid));

        return $calculator->calc();
    }
}
