<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Database\DB;
use Core\Session;
use Game\SpeedCalculator;
use Model\ArtefactsModel;
use Model\VillageModel;

/** How long the hero needs from its village to an adventure, with its horse, boots, weapon, artefacts and Tournament Square. */
final class AdventureTravel
{
    /** @var array<string, mixed>|null */
    private static ?array $inventory = null;

    private function __construct()
    {
    }

    /** Seconds of the way out, or of the way out and back when $roundTrip is set. */
    public static function seconds(int $targetKid, bool $roundTrip = false): int|float
    {
        $session = Session::getInstance();
        $heroKid = (int) $session->hero->hero['kid'];
        self::$inventory ??= (array) DB::getInstance()->query('SELECT horse, shoes, leftHand, rightHand FROM inventory WHERE uid=' . (int) $session->getPlayerId())->fetch_assoc();
        $inventory = self::$inventory;
        $helper = new HeroHelper();
        $calc = new SpeedCalculator();
        $calc->setFrom($heroKid);
        $calc->setTo($targetKid);
        $calc->hasHero();
        $calc->setHunnicMountedArmySpeedBonus($helper->getHunnicMountedArmySpeedBonus($session->getRace(), $inventory['horse'], true));
        $calc->setMinSpeed($helper->calcTotalSpeed($session->getRace(), $inventory['horse'], $inventory['shoes']));
        $calc->setLeftHand($inventory['leftHand']);
        $calc->setShoes($inventory['shoes']);
        $calc->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($session->getPlayerId(), $heroKid, ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        $calc->setTournamentSqLvl(VillageModel::getTournamentSquireLevel($heroKid));
        $out = $calc->calc();
        if (!$roundTrip) {
            return $out;
        }
        $calc->isReturn();

        return $out + $calc->calc();
    }
}
