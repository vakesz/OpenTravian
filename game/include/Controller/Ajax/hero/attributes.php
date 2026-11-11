<?php

namespace Controller\Ajax\hero;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Game\Formulas;
use Game\ResourcesHelper;

/**
 * Saves the hero overview's production target, hide preference, and unspent
 * attribute distribution. Points already saved can never be lowered. The
 * browser submits all values, so the budget must be recalculated from the
 * locked database row rather than trusted from it.
 */
class attributes extends AjaxBase
{
    private const ATTRIBUTE_FIELDS = [
        'power' => 'power',
        'offBonus' => 'offBonus',
        'defBonus' => 'defBonus',
        'productionPoints' => 'production',
    ];

    public function dispatch(): void
    {
        if (!$this->session->isValid() || $this->session->isSitter() || $this->session->banned()) {
            $this->error('You cannot change hero attributes from this session.', 403);
            return;
        }

        $resource = $this->readInteger($_POST['resource'] ?? null, 0, 4);
        $attackBehaviour = $_POST['attackBehaviour'] ?? null;
        $attributes = $_POST['attributes'] ?? null;
        if ($resource === null
            || !is_string($attackBehaviour)
            || !in_array($attackBehaviour, ['hide', 'fight', ''], true)
            || !is_array($attributes)) {
            $this->error('Invalid hero attribute request.');
            return;
        }

        $requested = [];
        foreach (self::ATTRIBUTE_FIELDS as $requestKey => $column) {
            $value = $this->readInteger($attributes[$requestKey] ?? null, 0, 100);
            if ($value === null) {
                $this->error('Invalid hero attribute allocation.');
                return;
            }
            $requested[$column] = $value;
        }

        $db = DB::getInstance();
        $homeKid = 0;
        if (!$db->begin_transaction()) {
            $this->error('Could not save hero attributes.', 503);
            return;
        }

        try {
            $statement = $db->run(
                'SELECT exp, power, offBonus, defBonus, production, kid FROM hero WHERE uid=? FOR UPDATE',
                [(int)$this->session->getPlayerId()]
            );
            $hero = $statement ? $statement->get_result()->fetch_assoc() : null;
            if ($hero === null) {
                $db->rollback();
                $this->error('Hero not found.', 404);
                return;
            }

            $level = Formulas::heroLevel((int)$hero['exp']);
            $budget = min($level, 100) * 4 + ($level < 100 ? 4 : 0);
            // Saved points are permanent; only a Book of Wisdom gives them back.
            foreach ($requested as $column => $value) {
                if ($value < (int)$hero[$column]) {
                    $db->rollback();
                    $this->error('Invalid hero attribute allocation.');
                    return;
                }
            }
            if (array_sum($requested) > $budget) {
                $db->rollback();
                $this->error('Not enough hero points available.');
                return;
            }

            $homeKid = (int)$hero['kid'];
            $saved = $db->run(
                'UPDATE hero SET power=?, offBonus=?, defBonus=?, production=?, productionType=?, hide=? WHERE uid=?',
                [
                    $requested['power'],
                    $requested['offBonus'],
                    $requested['defBonus'],
                    $requested['production'],
                    $resource,
                    $attackBehaviour === 'hide' ? 1 : 0,
                    (int)$this->session->getPlayerId(),
                ]
            );
            if (!$saved || !$db->commit()) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                $this->error('Could not save hero attributes.', 503);
                return;
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $this->error('Could not save hero attributes.', 503);
            return;
        }

        // Dorf1 / stock bar read cached vdata.*p rates. Rebuild the hero home
        // village after attribute or production-target changes so crop and
        // other production update immediately on reload (Support §45/§214).
        if ($homeKid > 0) {
            ResourcesHelper::updateVillageResources($homeKid, false);
        }

        $this->response['reload'] = true;
    }

    /**
     * @param mixed $value
     */
    private function readInteger($value, int $minimum, int $maximum): ?int
    {
        if (!is_scalar($value) || !ctype_digit((string)$value)) {
            return null;
        }

        $integer = (int)$value;
        return $integer >= $minimum && $integer <= $maximum ? $integer : null;
    }
}
