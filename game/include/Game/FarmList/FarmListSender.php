<?php

declare(strict_types=1);

namespace Game\FarmList;

use Core\Config;
use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Game\Ships;
use Game\SpeedCalculator;
use Model\ArtefactsModel;
use Model\MovementsModel;
use Model\OasesModel;
use Model\RallyPoint\RallyPointModel;
use Model\VillageModel;

use function getCustom;
use function isServerFinished;
use function miliseconds;
use function nrToUnitId;
use function T;

/**
 * Starts the raids of farm list targets (POST farm-list/send). A target either starts or comes back with the
 * reason it did not, in the reference's wording; the raids are ordinary movements, so the engine resolves them.
 */
final class FarmListSender
{
    /** Movement marker: suppress the attacker report when the raid had no losses ("Only create reports with losses"). */
    public const ONLY_LOSSES_MOVEMENT_DATA = '{"farmListOnlyLosses":1}';

    private DB $db;
    private Session $session;
    private int $uid;

    public function __construct()
    {
        $this->db = DB::getInstance();
        $this->session = Session::getInstance();
        $this->uid = $this->session->getPlayerId();
    }

    /**
     * @param array<mixed> $lists [{id, targets: [slotId, ...]}]
     * @return array{lists: list<array{id: int, targets: list<array<string, mixed>>}>}
     */
    public function send(array $lists): array
    {
        if (isServerFinished()) {
            throw new \DomainException(self::text('errorDataChange'), 403);
        }
        if ($this->session->isInVacationMode()) {
            throw new \DomainException(self::text('sendingNotAllowedOnVacationMode'), 403);
        }
        $results = [];
        foreach ($lists as $request) {
            if (!is_array($request) || !isset($request['id']) || !is_array($request['targets'] ?? null)) {
                throw new \DomainException(self::text('errorDataChange'));
            }
            $results[] = $this->sendList((int)$request['id'], array_values(array_unique(array_map('intval', $request['targets']))));
        }

        return ['lists' => $results];
    }

    /**
     * @param list<int> $slotIds
     * @return array{id: int, targets: list<array<string, mixed>>}
     */
    private function sendList(int $listId, array $slotIds): array
    {
        $list = $this->row('SELECT * FROM farmlist WHERE id=? AND owner=?', [$listId, $this->uid]);
        if ($list === null) {
            throw new \DomainException(self::text('farmListNotFound'), 404);
        }
        $kid = (int)$list['kid'];
        $slots = [];
        foreach ($this->rows('SELECT * FROM raidlist WHERE lid=?', [$listId]) as $slot) {
            $slots[(int)$slot['id']] = $slot;
        }
        $targets = [];
        $started = 0;
        $blocked = $this->listRefusal($kid);
        $calculator = new SpeedCalculator();
        $calculator->setTournamentSqLvl(VillageModel::getTournamentSquireLevel($kid));
        $calculator->setArtefactEffect(ArtefactsModel::getArtifactEffectByType($this->uid, $kid, ArtefactsModel::ARTIFACT_INCREASE_SPEED));
        $calculator->setFrom($kid);
        foreach ($slotIds as $slotId) {
            $error = $blocked ?? (isset($slots[$slotId]) ? $this->startTarget($list, $slots[$slotId], $calculator) : self::text('errorDataChange'));
            $targets[] = ['id' => $slotId] + ($error === null ? [] : ['error' => $error]);
            $started += $error === null ? 1 : 0;
        }
        if ($started > 0) {
            $this->db->run('UPDATE farmlist SET lastStartedTime=? WHERE id=? AND owner=?', [time(), $listId, $this->uid]);
        }

        return ['id' => $listId, 'targets' => $targets];
    }

    /** Why nothing can start from the village, or null. */
    private function listRefusal(int $kid): ?string
    {
        $rallyPoint = $this->row('SELECT f39, f39t FROM fdata WHERE kid=?', [$kid]);
        if ($rallyPoint === null || (int)$rallyPoint['f39t'] !== 16 || (int)$rallyPoint['f39'] < 1) {
            $name = FarmListData::plain((string)$this->db->fetchScalar('SELECT name FROM vdata WHERE kid=?', [$kid], ''));

            return str_replace('[NAME]', $name, self::text('noRallyPoint'));
        }

        return (new RallyPointModel())->checkMaxAttacks($kid) ? null : self::text('take_a_break');
    }

    /**
     * Starts one raid; the error is the reason it could not.
     *
     * @param array<string, mixed> $list
     * @param array<string, mixed> $slot
     */
    private function startTarget(array $list, array $slot, SpeedCalculator $calculator): ?string
    {
        $kid = (int)$list['kid'];
        $target = (int)$slot['kid'];
        if ((int)$slot['isActive'] !== 1) {
            return self::text('errorDataChange');
        }
        if ($target === $kid) {
            return self::text('error_start_village');
        }
        $refusal = $this->targetRefusal($target);
        if ($refusal !== null) {
            return $refusal;
        }
        $send = array_fill(1, 11, 0);
        $changed = [];
        $speeds = [];
        for ($unit = 1; $unit <= 10; ++$unit) {
            $amount = (int)$slot['u' . $unit];
            $send[$unit] = $amount;
            if ($amount > 0) {
                $changed[$unit] = $amount;
                $speeds[] = Formulas::uSpeed(nrToUnitId($unit, $this->session->getRace()));
            }
        }
        if ($speeds === []) {
            return self::text('error_no_troops');
        }
        // The check and the deduction happen under one row lock; the UPDATE is conditional as well (uN>=n).
        $this->db->begin_transaction();
        try {
            $units = $this->row('SELECT * FROM units WHERE kid=? FOR UPDATE', [$kid]);
            $enough = $units !== null;
            foreach ($changed as $unit => $amount) {
                $enough = $enough && (int)$units['u' . $unit] >= $amount;
            }
            if (!$enough || !$this->takeUnits($kid, $changed)) {
                $this->db->rollback();

                return self::text('current_player_has_not_enough_troops');
            }
            $calculator->hasCata($send[8] > 0);
            $calculator->setTo($target);
            $calculator->setMinSpeed($speeds);
            // A list that asks for ships sails when a fitting ship waits; a failure below gives the ship back.
            $ship = Ships::boardRaid($kid, (int)$list['useShip'] === 1, (int)array_sum($send), $calculator, (int)$this->session->getRace());
            $movement = (new MovementsModel())->addMovement(
                $kid, $target, (int) $this->session->getRace(), $send, 0, 0, 0, 0, 0, MovementsModel::ATTACKTYPE_RAID, miliseconds(),
                miliseconds() + $calculator->calc() * 1000, (int)$list['onlyLosses'] === 1 ? self::ONLY_LOSSES_MOVEMENT_DATA : null, $ship
            );
            if (!$movement) {
                $this->db->rollback();

                return self::text('error_save');
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollback();
            throw $exception;
        }

        return null;
    }

    /** Why the target cannot be raided (beginner's protection, vacation, a ban, ...), or null. */
    private function targetRefusal(int $target): ?string
    {
        $oases = new OasesModel();
        $oasis = $oases->isOasis($target);
        $natarOrFree = $oasis ? !$oases->isOasisConqured($target) : (int)$this->db->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$target], 0) === 1;
        if ($this->session->hasProtection() && !$natarOrFree) {
            return self::text('current_player_has_noobprotection');
        }
        $row = $oasis
            ? $this->row('SELECT u.vacationActiveTil, u.access, u.protection, o.owner, 0 AS isWW, 0 AS isFarm FROM users u JOIN odata o ON o.owner = u.id WHERE o.kid=?', [$target])
            : $this->row('SELECT u.vacationActiveTil, u.access, u.protection, v.owner, v.isWW, v.isFarm FROM users u JOIN vdata v ON v.owner = u.id WHERE v.kid=?', [$target]);
        if ($row === null) {
            return self::text('noMoreVillage');
        }
        $protection = (int)$row['owner'] === 0 ? 0 : (int)$row['protection'];
        if ((int)$row['vacationActiveTil'] > time()) {
            return self::text('target_is_in_vacation');
        }
        if ($protection > time()) {
            return self::text('target_player_has_noobprotection');
        }
        if ((int)$row['isWW'] === 1 && !getCustom('wwPlansEnabled') && !Config::getInstance()->dynamic->WWPlansReleased) {
            return self::text('errorWorldWonderVillage');
        }
        if ((int)$row['isFarm'] === 1 && (time() <= (int)getCustom('protectFarmsTill') || $this->session->hasProtection())) {
            return self::text('target_player_has_noobprotection');
        }

        return (int)$row['access'] === 0 ? self::text('target_is_banned') : null;
    }

    /** @param array<int, int> $units amounts by unit number */
    private function takeUnits(int $kid, array $units): bool
    {
        $set = [];
        $guard = [];
        foreach ($units as $unit => $amount) {
            $set[] = "u{$unit}=u{$unit}-{$amount}";
            $guard[] = "u{$unit}>={$amount}";
        }

        return $this->db->query('UPDATE units SET ' . implode(',', $set) . " WHERE kid={$kid} AND " . implode(' AND ', $guard)) && $this->db->affectedRows() === 1;
    }

    private static function text(string $key): string
    {
        return (string)T('FarmList', $key);
    }

    /**
     * @param list<mixed> $parameters
     * @return array<string, mixed>|null
     */
    private function row(string $query, array $parameters): ?array
    {
        return $this->rows($query, $parameters)[0] ?? null;
    }

    /**
     * @param list<mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function rows(string $query, array $parameters): array
    {
        $statement = $this->db->run($query, $parameters);

        return $statement === false ? [] : $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
