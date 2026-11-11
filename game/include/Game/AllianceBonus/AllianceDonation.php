<?php

namespace Game\AllianceBonus;

use Core\Config;
use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Game\ResourcesHelper;
use Model\AllianceBonusModel;
use Model\DailyQuestModel;

use function getCustom;

/**
 * Applies one alliance-bonus donation after locking all affected player,
 * village, alliance, and quest rows.
 */
final class AllianceDonation
{
    private const TYPE_BY_IDENTIFIER = [
        'TroopProductionSpeed' => AllianceBonusModel::TYPE_TRAINING,
        'CPProduction' => AllianceBonusModel::TYPE_CP,
        'SmithyPower' => AllianceBonusModel::TYPE_ARMOR,
        'MerchantCapacity' => AllianceBonusModel::TYPE_TRADE,
    ];

    /**
     * @return array{newTotal: int, limit: int, limitReached: bool, goldChanged: bool}|array{error: string}
     * @param array<mixed, mixed> $parameters
     */
    public function donate(Session $session, array $parameters, bool $triple): array
    {
        if (!$session->isValid()
            || $session->banned()
            || $session->isInVacationMode()
            || Config::getInstance()->dynamic->serverFinished) {
            return ['error' => 'unavailable'];
        }
        if (!$session->checkSitterPermission(Session::SITTER_CAN_CONTRIBUTE_ALLIANCE)) {
            return ['error' => 'sitter'];
        }

        $type = self::typeFor($parameters['bid'] ?? null);
        $kid = self::positiveInteger($parameters['did'] ?? null);
        $resources = self::resourcesFrom($parameters);
        if ($type === null || $kid === null || $kid !== $session->getKid() || $resources === null) {
            return ['error' => 'invalid'];
        }

        $amount = array_sum($resources);
        if ($amount <= 0) {
            return ['error' => 'invalid'];
        }
        $creditedAmount = $triple ? $amount * 3 : $amount;
        if ($creditedAmount > 1000000000) {
            return ['error' => 'invalid'];
        }

        // Persist elapsed production before the locked balance check.
        ResourcesHelper::updateVillageResources($kid, true);

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return ['error' => 'unavailable'];
        }

        try {
            $uid = $session->getPlayerId();
            $userStatement = $db->run(
                'SELECT aid, alliance_contributions, bought_gold, gift_gold
                 FROM users WHERE id=? FOR UPDATE',
                [$uid]
            );
            $user = $userStatement ? $userStatement->get_result()->fetch_assoc() : null;
            if ($user === null || (int)$user['aid'] <= 0 || (int)$user['aid'] !== $session->getAllianceId()) {
                throw new \RuntimeException('unavailable');
            }

            $aid = (int)$user['aid'];
            $fields = [
                AllianceBonusModel::TYPE_TRAINING => ['training_bonus_level', 'training_bonus_contributions'],
                AllianceBonusModel::TYPE_CP => ['cp_bonus_level', 'cp_bonus_contributions'],
                AllianceBonusModel::TYPE_ARMOR => ['armor_bonus_level', 'armor_bonus_contributions'],
                AllianceBonusModel::TYPE_TRADE => ['trade_bonus_level', 'trade_bonus_contributions'],
            ];
            [$levelField, $contributionField] = $fields[$type];
            $allianceStatement = $db->run(
                "SELECT $levelField AS level, $contributionField AS contributions,
                        training_bonus_level, armor_bonus_level, cp_bonus_level, trade_bonus_level
                 FROM alidata WHERE id=? FOR UPDATE",
                [$aid]
            );
            $alliance = $allianceStatement ? $allianceStatement->get_result()->fetch_assoc() : null;
            if ($alliance === null || (int)$alliance['level'] >= 5) {
                throw new \RuntimeException('unavailable');
            }

            $maximumLevel = max(
                (int)$alliance['training_bonus_level'],
                (int)$alliance['armor_bonus_level'],
                (int)$alliance['cp_bonus_level'],
                (int)$alliance['trade_bonus_level']
            );
            $limit = Formulas::getAllianceBonusDonationLimit($maximumLevel);
            $newTotal = (int)$user['alliance_contributions'] + $creditedAmount;
            if ($newTotal > $limit) {
                throw new \RuntimeException('limit');
            }

            $queueStatement = $db->run(
                'SELECT id FROM alliance_bonus_upgrade_queue WHERE aid=? AND type=? FOR UPDATE',
                [$aid, $type]
            );
            if (!$queueStatement || $queueStatement->get_result()->num_rows !== 0) {
                throw new \RuntimeException('unavailable');
            }

            $villageStatement = $db->run(
                'SELECT wood, clay, iron, crop FROM vdata WHERE kid=? AND owner=? FOR UPDATE',
                [$kid, $uid]
            );
            $village = $villageStatement ? $villageStatement->get_result()->fetch_assoc() : null;
            if ($village === null
                || (int)$village['wood'] < $resources[0]
                || (int)$village['clay'] < $resources[1]
                || (int)$village['iron'] < $resources[2]
                || (int)$village['crop'] < $resources[3]) {
                throw new \RuntimeException('resources');
            }

            $boughtGold = (int)$user['bought_gold'];
            $giftGold = (int)$user['gift_gold'];
            $goldChanged = false;
            if ($triple && !getCustom('serverIsFreeGold')) {
                $cost = (int)Config::getProperty('gold', 'allianceBonus3xGold');
                if ($giftGold + $boughtGold < $cost) {
                    throw new \RuntimeException('gold');
                }
                $fromGift = min($giftGold, $cost);
                $giftGold -= $fromGift;
                $boughtGold -= $cost - $fromGift;
                $goldChanged = true;
                $gold = $db->run('UPDATE users SET bought_gold=?, gift_gold=? WHERE id=?', [$boughtGold, $giftGold, $uid]);
                if (!$gold || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('unavailable');
                }
            }

            $paid = $db->run(
                'UPDATE vdata SET wood=wood-?, clay=clay-?, iron=iron-?, crop=crop-? WHERE kid=? AND owner=?',
                [$resources[0], $resources[1], $resources[2], $resources[3], $kid, $uid]
            );
            if (!$paid || $db->affectedRows() !== 1) {
                throw new \RuntimeException('unavailable');
            }

            if (!(new AllianceBonusModel())->contribute($aid, $uid, $type, $creditedAmount)) {
                throw new \RuntimeException('unavailable');
            }

            $target = Formulas::getAllianceBonusContributesNeededForLevel((int)$alliance['level'] + 1);
            if ((int)$alliance['contributions'] + $creditedAmount >= $target) {
                $unlockAt = time() + Formulas::getAllianceBonusUpgradeDuration((int)$alliance['level'] + 1);
                $queue = $db->run(
                    'INSERT INTO alliance_bonus_upgrade_queue (aid, type, time) VALUES (?, ?, ?)',
                    [$aid, $type, $unlockAt]
                );
                if (!$queue) {
                    throw new \RuntimeException('unavailable');
                }
            }

            if ($triple) {
                (new DailyQuestModel())->setQuestAsCompleted($uid, 5);
            }
            if (!$db->commit()) {
                throw new \RuntimeException('unavailable');
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            return ['error' => in_array($exception->getMessage(), ['invalid', 'sitter', 'resources', 'limit', 'gold'], true)
                ? $exception->getMessage()
                : 'unavailable'];
        }

        $session->set('alliance_contributions', $newTotal);
        if ($goldChanged) {
            $session->set('bought_gold', $boughtGold);
            $session->set('gift_gold', $giftGold);
        }

        return [
            'newTotal' => $newTotal,
            'limit' => $limit,
            'limitReached' => $newTotal >= $limit,
            'goldChanged' => $goldChanged,
        ];
    }

    /**
     * The reference posts {bonus, resources: {lumber, clay, iron, crop}}; the donation itself
     * works on {bid, did, r1..r4}.
     *
     * @param array<mixed, mixed> $body
     * @return array<string, mixed>
     */
    public static function parametersFromRequest(array $body, int $kid): array
    {
        $resources = is_array($body['resources'] ?? null) ? $body['resources'] : [];

        return [
            'bid' => $body['bonus'] ?? null,
            'did' => $kid,
            'r1' => $resources['lumber'] ?? null,
            'r2' => $resources['clay'] ?? null,
            'r3' => $resources['iron'] ?? null,
            'r4' => $resources['crop'] ?? null,
        ];
    }

    /**
     * @param mixed $value
     */
    public static function typeFor($value): ?int
    {
        return is_string($value) && isset(self::TYPE_BY_IDENTIFIER[$value])
            ? self::TYPE_BY_IDENTIFIER[$value]
            : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     *
     * @param array<mixed> $parameters
     */
    public static function resourcesFor(array $parameters): ?array
    {
        return self::resourcesFrom($parameters);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     *
     * @param array<mixed> $parameters
     */
    private static function resourcesFrom(array $parameters): ?array
    {
        $resources = [];
        for ($index = 1; $index <= 4; ++$index) {
            $resource = self::nonNegativeInteger($parameters['r' . $index] ?? null);
            if ($resource === null) {
                return null;
            }
            $resources[] = $resource;
        }
        return $resources;
    }

    /**
     * @param mixed $value
     */
    private static function positiveInteger($value): ?int
    {
        $integer = self::nonNegativeInteger($value);
        return $integer !== null && $integer > 0 ? $integer : null;
    }

    /**
     * @param mixed $value
     */
    private static function nonNegativeInteger($value): ?int
    {
        if (!is_scalar($value) || !preg_match('/^(?:0|[1-9][0-9]{0,8})$/', (string)$value)) {
            return null;
        }
        return (int)$value;
    }
}
