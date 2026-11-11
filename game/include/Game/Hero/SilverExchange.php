<?php

namespace Game\Hero;

use Core\Database\DB;
use Core\Session;
use Model\DailyQuestModel;

/**
 * Atomically exchanges hero-auction currency and keeps the current session's
 * balance cache in sync with the locked user row.
 */
final class SilverExchange
{
    public const GOLD_TO_SILVER = 'GoldToSilver';
    public const SILVER_TO_GOLD = 'SilverToGold';

    private const MAX_AMOUNT = 9999999;
    private const SILVER_PER_GOLD = 200;
    private const SILVER_PER_GOLD_SPENT = 100;

    /**
     * @return array{oldGold: int, oldSilver: int, newGold: int, newSilver: int, exchangedFrom: int, exchangedTo: int}|array{error: string}
     * @param mixed $direction
     * @param mixed $amount
     */
    public function exchange(Session $session, $direction, $amount): array
    {
        if (!in_array($direction, [self::GOLD_TO_SILVER, self::SILVER_TO_GOLD], true)) {
            return ['error' => 'invalid'];
        }

        if (!is_scalar($amount) || !preg_match('/^[1-9][0-9]{0,6}$/', (string)$amount)) {
            return ['error' => 'invalid'];
        }

        $amount = (int)$amount;
        if ($amount > self::MAX_AMOUNT) {
            return ['error' => 'invalid'];
        }

        if ($direction === self::SILVER_TO_GOLD && $amount < self::SILVER_PER_GOLD) {
            return ['error' => 'invalid'];
        }

        $db = DB::getInstance();
        if (!$db->begin_transaction()) {
            return ['error' => 'unavailable'];
        }

        try {
            $userStatement = $db->run(
                'SELECT bought_gold, gift_gold, silver FROM users WHERE id=? FOR UPDATE',
                [(int)$session->getPlayerId()]
            );
            $user = $userStatement ? $userStatement->get_result()->fetch_assoc() : null;
            if ($user === null) {
                $db->rollback();
                return ['error' => 'unavailable'];
            }

            $reservedResult = $db->query(
                'SELECT maxSilver FROM auction WHERE finish=0 AND activeUid=' . (int)$session->getPlayerId() . ' FOR UPDATE'
            );
            if ($reservedResult === false) {
                $db->rollback();
                return ['error' => 'unavailable'];
            }
            $reservedSilver = 0;
            while ($bid = $reservedResult->fetch_assoc()) {
                $reservedSilver += (int)$bid['maxSilver'];
            }
            $reservedResult->free();
            $boughtGold = (int)$user['bought_gold'];
            $giftGold = (int)$user['gift_gold'];
            $silver = (int)$user['silver'];
            $oldGold = getCustom('serverIsFreeGold') ? (int)$session->getAvailableGold() : $boughtGold + $giftGold;
            $oldSilver = max(0, $silver - $reservedSilver);

            if ($direction === self::GOLD_TO_SILVER) {
                if (!getCustom('serverIsFreeGold') && $amount > $oldGold) {
                    $db->rollback();
                    return ['error' => 'notEnoughGold'];
                }

                if (!getCustom('serverIsFreeGold')) {
                    $giftSpent = min($giftGold, $amount);
                    $giftGold -= $giftSpent;
                    $boughtGold -= $amount - $giftSpent;
                }
                $silver += $amount * self::SILVER_PER_GOLD_SPENT;
            } else {
                if ($amount > $oldSilver) {
                    $db->rollback();
                    return ['error' => 'notEnoughSilver'];
                }

                $gold = intdiv($amount, self::SILVER_PER_GOLD);
                $silver -= $gold * self::SILVER_PER_GOLD;
                $giftGold += $gold;
            }

            $saved = $db->run(
                'UPDATE users SET bought_gold=?, gift_gold=?, silver=? WHERE id=?',
                [$boughtGold, $giftGold, $silver, (int)$session->getPlayerId()]
            );
            if (!$saved || !$db->commit()) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }
                return ['error' => 'unavailable'];
            }
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            return ['error' => 'unavailable'];
        }

        if (!getCustom('serverIsFreeGold')) {
            $session->set('bought_gold', $boughtGold);
            $session->set('gift_gold', $giftGold);
        }
        $session->set('silver', $silver);

        if ($direction === self::GOLD_TO_SILVER) {
            $exchangedFrom = $amount;
            $exchangedTo = $amount * self::SILVER_PER_GOLD_SPENT;
            (new DailyQuestModel())->setQuestAsCompleted($session->getPlayerId(), 5);
        } else {
            $exchangedFrom = $gold * self::SILVER_PER_GOLD;
            $exchangedTo = $gold;
        }

        return [
            'oldGold' => $oldGold,
            'oldSilver' => $oldSilver,
            'newGold' => getCustom('serverIsFreeGold') ? $oldGold : $boughtGold + $giftGold,
            'newSilver' => max(0, $silver - $reservedSilver),
            'exchangedFrom' => $exchangedFrom,
            'exchangedTo' => $exchangedTo,
        ];
    }
}
