<?php

declare(strict_types=1);

namespace Game;

use Core\Config;
use Core\Helper\TimezoneHelper;

use function appendTimer;
use function getDisplay;
use function number_format_x;
use function secondsToString;

/**
 * The rows of the "in training" table of a troop building, trap Trapper, Hospital and Harbor: one row per batch (the
 * amount, a batch countdown that reloads the page when the batch is done, and the finish time) and the closing
 * "next unit in" row.
 *
 * The next-unit element is deliberately not a `.timer`: the generic timers reload the page when they reach zero, which
 * would reload once per unit. `Travian.Game.TrainingQueue` (game-client.js) counts it down and updates each row's
 * remaining amount in place from the data attributes written here, so only the end of a batch reloads.
 */
final class TrainingQueueView
{
    private function __construct()
    {
    }

    /**
     * @param iterable<array<string, mixed>> $rows `training` rows (ticks of the world's queue clock)
     * @param callable(array<string, mixed>, string): string $descHtml the amount cell of a row from its row and the amount
     *        markup (the icon, the amount, the unit name)
     * @param string $nextKey the `inGame` translation key of the closing row; it takes the countdown as its one argument
     */
    public static function render(iterable $rows, callable $descHtml, string $nextKey): string
    {
        $rate = self::ticksPerSecond();
        $nowTicks = self::nowTicks($rate);
        $now = time();
        $tomorrow = TimezoneHelper::real_strtotime('tomorrow 00:00');
        $group = getDisplay('separateNumbers3By3') ? ' data-group="1"' : '';
        $html = '';
        $next = null;
        foreach ($rows as $row) {
            $end = (int)ceil((float)$row['end_time'] / $rate);
            $commence = (int)ceil((float)$row['commence'] / $rate);
            $next = $next === null ? $commence - $now : min($next, $commence - $now);
            $finish = $end >= $tomorrow ? TimezoneHelper::autoDateString($end, true) : TimezoneHelper::date('H:i', $end);
            $remainingMs = max(0.0, ((float)$row['end_time'] - $nowTicks) * 1000 / $rate);
            $periodMs = (float)$row['training_time'] * 1000 / $rate;
            $count = '<span class="trainingCount"' . $group
                . ' data-num="' . (int)$row['num'] . '"'
                . ' data-remaining-ms="' . self::decimal($remainingMs) . '"'
                . ' data-period-ms="' . self::decimal($periodMs) . '">' . number_format_x($row['num']) . '</span>';
            $html .= '<tr><td class="desc">' . $descHtml($row, $count) . '</td><td class="dur">' . appendTimer(max(0, $end - $now)) . '</td>'
                . '<td class="fin"><span>' . $finish . '</span><span> </span></td></tr>';
        }
        if ($next === null) {
            return '';
        }

        return $html . '<tr class="next"><td colspan="3">'
            . sprintf((string)T('inGame', $nextKey), '<span class="trainingNext">' . secondsToString(max(0, $next)) . '</span>')
            . '</td></tr>';
    }

    private static function ticksPerSecond(): int
    {
        return Config::getProperty('game', 'useNanoseconds') ? 1_000_000_000 : (Config::getProperty('game', 'useMilSeconds') ? 1_000 : 1);
    }

    private static function nowTicks(int $rate): float
    {
        return match ($rate) {
            1_000_000_000 => (float)\nanoseconds(),
            1_000 => (float)\miliseconds(),
            default => (float)time(),
        };
    }

    private static function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
