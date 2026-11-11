<?php

declare(strict_types=1);

namespace Controller\Ajax\troop;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Game\Formulas;
use Game\Hero\HeroHelper;
use Game\SpeedCalculator;
use Model\ArtefactsModel;
use Model\VillageModel;

/** Calculates the current rally-point travel duration without sending troops: POST troop/distanceSpeedAndDuration. */
final class distanceSpeedAndDuration extends AjaxBase
{
    public function dispatch(): void
    {
        $originKid = $this->positiveInteger($_POST['origin'] ?? null);
        $fromKid = $this->positiveInteger($_POST['from'] ?? null);
        $toKid = $this->positiveInteger($_POST['to'] ?? null);
        $troops = $_POST['troops'] ?? null;
        // The reference sends the village id and its map id; both are the same number here.
        if ($originKid === null || $fromKid !== $originKid || $toKid === null || !is_array($troops)) {
            $this->error('Invalid travel-time request.');
            return;
        }

        $db = DB::getInstance();
        $uid = (int)$this->session->getPlayerId();
        if (!(bool)$db->fetchScalar('SELECT 1 FROM vdata WHERE kid=' . $fromKid . ' AND owner=' . $uid . ' LIMIT 1')
            || !(bool)$db->fetchScalar('SELECT 1 FROM wdata WHERE id=' . $toKid . ' LIMIT 1')) {
            $this->error('The selected village is no longer available.', 404);
            return;
        }

        $selectedTroops = $this->selectedTroops($troops);
        if ($selectedTroops === null) {
            $this->error('Select at least one valid troop.');
            return;
        }

        $calculator = new SpeedCalculator();
        $calculator->setFrom($fromKid);
        $calculator->setTo($toKid);
        $calculator->setTournamentSqLvl(VillageModel::getTournamentSquireLevel($fromKid));
        $calculator->setArtefactEffect(ArtefactsModel::getArtifactEffectByType(
            $uid,
            $fromKid,
            ArtefactsModel::ARTIFACT_INCREASE_SPEED
        ));
        $calculator->hasCata(($selectedTroops[8] ?? 0) > 0);

        $speeds = [];
        $unitIds = [];
        for ($number = 1; $number <= 10; ++$number) {
            if (($selectedTroops[$number] ?? 0) > 0) {
                $unitId = nrToUnitId($number, $this->session->getRace());
                $speeds[] = Formulas::uSpeed($unitId);
                $unitIds[] = $unitId;
            }
        }

        if (($selectedTroops[11] ?? 0) > 0) {
            $inventory = $db->query('SELECT horse, shoes, leftHand FROM inventory WHERE uid=' . $uid)->fetch_assoc();
            if (!$inventory) {
                $this->error('Hero inventory was not found.', 404);
                return;
            }

            $helper = new HeroHelper();
            $cavalryOnly = $calculator->isCavalryOnly($unitIds);
            $speeds[] = $helper->calcTotalSpeed(
                $this->session->getRace(),
                $inventory['horse'],
                $inventory['shoes']
            );
            $calculator->setHunnicMountedArmySpeedBonus($helper->getHunnicMountedArmySpeedBonus(
                $this->session->getRace(),
                $inventory['horse'],
                $cavalryOnly
            ));
            $calculator->hasHero();
            if (array_sum($selectedTroops) > 1) {
                $calculator->troopsWithHero();
            }
            $calculator->setLeftHand($inventory['leftHand']);
            $calculator->setShoes($inventory['shoes']);
            $this->applyHeroDestinationBonus($calculator, $toKid, $uid);
        }

        $calculator->setMinSpeed($speeds);
        $duration = $calculator->calc();
        $this->response['data'] = [
            'duration' => $duration,
            'durationString' => secondsToString($duration),
            'offset' => $this->displayTimeOffset(),
        ];
    }

    /** @return array<int, int>|null */
    private function selectedTroops(array $troops): ?array
    {
        $selected = [];
        for ($number = 1; $number <= 11; ++$number) {
            $value = $troops['t' . $number] ?? 0;
            if (!is_scalar($value) || !preg_match('/^(?:0|[1-9][0-9]{0,8})$/', (string)$value)) {
                return null;
            }
            $selected[$number] = (int)$value;
        }

        return array_sum($selected) > 0 ? $selected : null;
    }

    private function applyHeroDestinationBonus(SpeedCalculator $calculator, int $toKid, int $uid): void
    {
        $db = DB::getInstance();
        $owner = (int)$db->fetchScalar('SELECT owner FROM vdata WHERE kid=' . $toKid . ' LIMIT 1');
        if ($owner === $uid) {
            $calculator->isOwn();
            return;
        }

        $alliance = (int)$db->fetchScalar('SELECT aid FROM users WHERE id=' . $uid . ' LIMIT 1');
        if ($alliance > 0 && $alliance === (int)$db->fetchScalar('SELECT aid FROM users WHERE id=' . $owner . ' LIMIT 1')) {
            $calculator->isAlliance();
        }
    }

    private function displayTimeOffset(): int
    {
        $date = new \DateTimeImmutable('now', new \DateTimeZone(TimezoneHelper::getTimezone()));
        return ((int)$date->format('G') * 3600) + ((int)$date->format('i') * 60) + (int)$date->format('s');
    }

    private function positiveInteger($value): ?int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,9}$/', (string)$value)) {
            return null;
        }

        return (int)$value;
    }
}
