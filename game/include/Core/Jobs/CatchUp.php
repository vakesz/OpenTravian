<?php

declare(strict_types=1);

namespace Core\Jobs;

use Core\Automation;
use Core\Database\DB;

use function getGame;
use function make_seed;

/**
 * The catch-up pass after a long engine stop. The engine parent runs it once, before any worker exists, when the
 * last stored beat is far enough in the past: every overdue construction, demolition, research, merchant trip,
 * troop movement and training row is completed in one global chronological order (a battle never resolves before
 * a building that was due earlier), at a capped rate so the database is not hit all at once, and with the
 * original due times (nothing is shifted). Each row goes through the same processor the workers use, so the
 * effects, the transaction around each row and the quarantine of a failing row are the workers' own: a row claimed
 * by a web request or another process in the meantime is simply gone when its turn comes. What is still overdue
 * when the pass ends (its time cap, a pause request) is left to the normal workers.
 */
final class CatchUp
{
    public const PROGRESS_INTERVAL = 5.0;

    /** The same unchanged row coming up again after its processor ran: it is skipped for this pass. */
    private const MAX_ATTEMPTS = 3;

    private function __construct()
    {
    }

    /**
     * @return array{after: int, rate: int, max: int}
     */
    public static function settings(): array
    {
        $saved = \Game\OperationsSettings::all();

        return ['after' => $saved['catchupAfter'], 'rate' => $saved['catchupRate'], 'max' => $saved['catchupMax']];
    }

    /** True when the engine was down long enough that its overdue work should be caught up in order. */
    public static function needed(int $lastBeat, int $now): bool
    {
        return $lastBeat > 0 && $now - $lastBeat > self::settings()['after'];
    }

    /**
     * The overdue-row sources, each with the SQL that peeks at its earliest due row, as seconds.
     *
     * With $kid only that village's rows count (tools and tests; the engine's own pass takes the whole world).
     *
     * @return array<string, array{table: string, sql: string, order: string, scale: int}>
     */
    private static function sources(int $cutoff, ?int $kid = null): array
    {
        $own = $kid === null ? '' : " AND kid=$kid";
        $either = $kid === null ? '' : " AND (kid=$kid OR to_kid=$kid)";
        $trainingScale = getGame('useNanoseconds') ? 1000000000 : (getGame('useMilSeconds') ? 1000 : 1);
        $milliseconds = $cutoff * 1000;

        return [
            'building' => ['table' => 'building_upgrade', 'sql' => "SELECT id, isMaster, commence AS due FROM building_upgrade WHERE commence<=$cutoff$own", 'order' => 'commence', 'scale' => 1],
            'demolition' => ['table' => 'demolition', 'sql' => "SELECT id, end_time AS due FROM demolition WHERE end_time<=$cutoff$own", 'order' => 'end_time', 'scale' => 1],
            'research' => ['table' => 'research', 'sql' => "SELECT id, end_time AS due FROM research WHERE end_time<=$cutoff$own", 'order' => 'end_time', 'scale' => 1],
            'market' => ['table' => 'send', 'sql' => "SELECT id, end_time AS due FROM send WHERE end_time<$cutoff$either", 'order' => 'end_time', 'scale' => 1],
            'attack' => ['table' => 'movement', 'sql' => "SELECT id, end_time AS due FROM movement WHERE mode=0 AND end_time<=$milliseconds$either", 'order' => 'end_time', 'scale' => 1000],
            'return' => ['table' => 'movement', 'sql' => "SELECT id, end_time AS due FROM movement WHERE mode=1 AND end_time<=$milliseconds$either", 'order' => 'end_time', 'scale' => 1000],
            'training' => ['table' => 'training', 'sql' => 'SELECT id, commence AS due FROM training WHERE commence<' . ($cutoff * $trainingScale) . $own, 'order' => 'commence', 'scale' => $trainingScale],
        ];
    }

    /** How many rows are overdue at the cutoff, for the progress report and the Engine health page. */
    public static function remaining(int $cutoff, ?int $kid = null): int
    {
        $db = DB::getInstance();
        $total = 0;
        foreach (self::sources($cutoff, $kid) as $source) {
            $sql = preg_replace('/^SELECT .*? FROM /', 'SELECT COUNT(*) FROM ', $source['sql']);
            $total += (int) $db->fetchScalar((string) $sql);
        }

        return $total;
    }

    /**
     * Completes the rows overdue at $cutoff in chronological order.
     *
     * @param callable(): bool $shouldStop checked before every row (a signal or a pause request)
     * @param callable(array{processed: int, remaining: int, elapsed: float}): void|null $progress called every PROGRESS_INTERVAL seconds
     * @param callable(string, int, float): void|null $observer called after each row with its source, id and due time
     * @param float $progressEvery seconds between progress reports
     * @param int|null $kid restricts the pass to the rows of one village
     * @return array{processed: int, remaining: int, stoppedBy: string} stoppedBy is done, cap or stopped
     */
    public static function run(int $cutoff, callable $shouldStop, ?callable $progress = null, ?callable $observer = null, ?int $rate = null, ?int $maxSeconds = null, float $progressEvery = self::PROGRESS_INTERVAL, ?int $kid = null): array
    {
        $settings = self::settings();
        $rate ??= $settings['rate'];
        $maxSeconds ??= $settings['max'];
        $db = DB::getInstance();
        $automation = Automation::getInstance();
        $sources = self::sources($cutoff, $kid);
        $started = microtime(true);
        $nextProgress = $started + $progressEvery;
        $processed = 0;
        $attempts = [];
        $skip = [];
        $stoppedBy = 'done';
        while (true) {
            $now = microtime(true);
            if ($shouldStop()) {
                $stoppedBy = 'stopped';
                break;
            }
            if ($now - $started >= $maxSeconds) {
                $stoppedBy = 'cap';
                break;
            }
            $next = null;
            foreach ($sources as $name => $source) {
                $exclude = $skip[$name] ?? [];
                $row = $db->query($source['sql'] . ($exclude === [] ? '' : ' AND id NOT IN (' . implode(',', $exclude) . ')') . ' ORDER BY ' . $source['order'] . ' ASC, id ASC LIMIT 1')->fetch_assoc();
                if (is_array($row) && ($next === null || (float) $row['due'] / $source['scale'] < $next['due'])) {
                    $next = ['source' => $name, 'row' => $row, 'due' => (float) $row['due'] / $source['scale']];
                }
            }
            if ($next === null) {
                break;
            }
            $id = (int) $next['row']['id'];
            self::process($automation, $next['source'], $next['row']);
            ++$processed;
            // A row that is still there, unchanged, after its processor ran failed (the transaction retries and
            // quarantines it); do not spin on it.
            $key = $next['source'] . ':' . $id;
            $again = $db->query($sources[$next['source']]['sql'] . " AND id=$id")->fetch_assoc();
            if (is_array($again) && $again['due'] === $next['row']['due']) {
                $attempts[$key] = ($attempts[$key] ?? 0) + 1;
                if ($attempts[$key] >= self::MAX_ATTEMPTS) {
                    $skip[$next['source']][] = $id;
                }
            }
            if ($observer !== null) {
                $observer($next['source'], $id, $next['due']);
            }
            $ahead = $processed / $rate - (microtime(true) - $started);
            if ($ahead > 0) {
                usleep((int) ($ahead * 1000000));
            }
            if ($progress !== null && microtime(true) >= $nextProgress) {
                $nextProgress = microtime(true) + $progressEvery;
                $progress(['processed' => $processed, 'remaining' => self::remaining($cutoff, $kid), 'elapsed' => microtime(true) - $started]);
            }
        }

        return ['processed' => $processed, 'remaining' => self::remaining($cutoff, $kid), 'stoppedBy' => $stoppedBy];
    }

    /** @param array<string, mixed> $row */
    private static function process(Automation $automation, string $source, array $row): void
    {
        $id = (int) $row['id'];
        $automation->guardRow("Catch-up $source task $id", function () use ($automation, $source, $row, $id): void {
            match ($source) {
                'building' => $automation->processBuildingRow($id, (bool) $row['isMaster']),
                'demolition' => $automation->processDemolitionTask($id),
                'research' => $automation->processResearchTask($id),
                'market' => $automation->processMarketTask($id),
                'attack', 'return' => self::processMovement($automation, $id),
                'training' => $automation->trainingComplete($id),
            };
        });
    }

    private static function processMovement(Automation $automation, int $id): void
    {
        mt_srand(make_seed());
        $automation->processMovementTask($id);
    }
}
