<?php

declare(strict_types=1);

namespace Game;

use Core\Database\DB;

/**
 * Rescales the in-flight timers of a world whose speed changed (tools/reconcile-world-speed-profile.php). Each row's
 * remaining time is multiplied by the ratio of the old to the new duration of the same mechanic, so queues finish when a
 * fresh queue at the new speed would. Timestamps are subtracted as signed numbers: a movement that started in the past,
 * or a training batch that already began, has a column below "now", which is negative as an unsigned BIGINT.
 */
final class WorldSpeedTimers
{
    private function __construct()
    {
    }

    /**
     * @return list<array{label: string, table: string, columns: list<string>, ratio: float, unit: int}>
     */
    public static function plans(int $from, int $to, int $nowSeconds): array
    {
        $nowMs = $nowSeconds * 1000;
        $economy = $from / $to;
        $movement = WorldSpeedProfile::movementSpeedMultiplier($from) / WorldSpeedProfile::movementSpeedMultiplier($to);
        $oldUnit = WorldSpeedProfile::trainingTimeUnit($from);
        $newUnit = WorldSpeedProfile::trainingTimeUnit($to);
        $auction = WorldSpeedProfile::auctionDuration($to) / WorldSpeedProfile::auctionDuration($from);
        $abandon = max(6 * 3600 / $to, 600) / max(6 * 3600 / $from, 600);

        $plans = [
            ['label' => 'building queue', 'table' => 'building_upgrade', 'columns' => ['commence'], 'ratio' => $economy, 'unit' => 1, 'now' => $nowSeconds],
            ['label' => 'demolition', 'table' => 'demolition', 'columns' => ['end_time'], 'ratio' => $economy, 'unit' => 1, 'now' => $nowSeconds],
            ['label' => 'academy and smithy research', 'table' => 'research', 'columns' => ['end_time'], 'ratio' => $economy, 'unit' => 1, 'now' => $nowSeconds],
            ['label' => 'troop movements', 'table' => 'movement', 'columns' => ['start_time', 'end_time'], 'ratio' => $movement, 'unit' => 1000, 'now' => $nowMs, 'guard' => 'end_time'],
            ['label' => 'auctions', 'table' => 'auction', 'columns' => ['time'], 'ratio' => $auction, 'unit' => 1, 'now' => $nowSeconds, 'extra' => 'finish=0 AND cancel=0'],
            ['label' => 'oasis abandonment', 'table' => 'odelete', 'columns' => ['end_time'], 'ratio' => $abandon, 'unit' => 1, 'now' => $nowSeconds],
            ['label' => 'alliance bonus upgrades', 'table' => 'alliance_bonus_upgrade_queue', 'columns' => ['time'], 'ratio' => $economy, 'unit' => 1, 'now' => $nowSeconds],
        ];
        // Bought OpenTravian Plus and production boosts are paid wall-clock time and are never rescaled.
        // Training, hero revival and healing queues store timestamps in the
        // speed-dependent unit (seconds, milliseconds, nanoseconds), so a speed
        // change across 20x or 20000x also converts the unit.
        $plans[] = [
            'label' => 'troop training and hero revival',
            'table' => 'training',
            'columns' => ['commence', 'end_time'],
            'ratio' => $economy,
            'unit' => $newUnit,
            'from_unit' => $oldUnit,
            'now' => $nowSeconds * $newUnit,
            'durations' => ['training_time'],
            'guard' => 'end_time',
        ];

        return $plans;
    }

    /** @param array<string, mixed> $plan */
    public static function where(array $plan): string
    {
        $guard = $plan['guard'] ?? $plan['columns'][0];
        $fromUnit = (int)($plan['from_unit'] ?? $plan['unit']);
        $nowInOldUnit = (int)(($plan['now'] / $plan['unit']) * $fromUnit);

        return sprintf('%s > %d%s', $guard, $nowInOldUnit, isset($plan['extra']) ? ' AND ' . $plan['extra'] : '');
    }

    public static function float(float $value): string
    {
        return number_format($value, 12, '.', '');
    }

    /** @param array<string, mixed> $plan */
    public static function statement(array $plan): string
    {
        $fromUnit = (int)($plan['from_unit'] ?? $plan['unit']);
        $factor = $plan['unit'] / $fromUnit;
        $nowOld = (int)(($plan['now'] / $plan['unit']) * $fromUnit);
        $sets = [];
        foreach ($plan['columns'] as $column) {
            // Signed arithmetic, clamped at zero: the column may be below "now" and is often an unsigned BIGINT.
            $sets[] = sprintf(
                '%1$s = GREATEST(0, %2$d + ROUND((CAST(%1$s AS SIGNED) - %3$d) * %4$s))',
                $column,
                $plan['now'],
                $nowOld,
                self::float($plan['ratio'] * $factor)
            );
        }
        foreach ($plan['durations'] ?? [] as $column) {
            $sets[] = sprintf('%1$s = GREATEST(1, ROUND(%1$s * %2$s))', $column, self::float($plan['ratio'] * $factor));
        }

        return sprintf('UPDATE %s SET %s WHERE %s', $plan['table'], implode(', ', $sets), self::where($plan));
    }
}
