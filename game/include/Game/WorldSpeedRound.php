<?php

declare(strict_types=1);

namespace Game;

use RuntimeException;

/**
 * The round clock of a world that changes speed (tools/set-world-speed.php). The round timers (artefacts, plans, the
 * Wonder and the end of the round) are derived from the stored round start, and a faster speed has a shorter round, so a
 * world older than the new round length would end its round on the engine's next cycle and delete every queue. The tool
 * refuses such a change unless it also moves the round start to now.
 */
final class WorldSpeedRound
{
    private function __construct()
    {
    }

    public static function ageDays(int $startTime, int $now): float
    {
        return max(0, $now - $startTime) / 86400;
    }

    /**
     * Days from the round start to the engine's end of the round at this speed: the Natar Wonder finish of an official
     * speed, otherwise the plans, the Wonder and its 100 levels (about 350 days divided by the speed), but never more
     * than the round length the profile advertises.
     */
    public static function finishDays(int $speed): float
    {
        $timeline = WorldSpeedProfile::officialRoundTimeline($speed);
        if ($timeline !== null) {
            return $timeline['natarWwFinishAfter'] / 86400;
        }

        return min(350 / max(1, $speed), (float)WorldSpeedProfile::roundLengthDays($speed));
    }

    /** The reason a change to `$speed` must not run, or null when the world is younger than that speed's round. */
    public static function refusal(int $startTime, int $now, int $speed): ?string
    {
        $age = self::ageDays($startTime, $now);
        $round = self::finishDays($speed);
        if ($age < $round) {
            return null;
        }

        return sprintf(
            'The world is %.1f days old and a round at %dx lasts %.1f days: the engine would end the round on its next cycle and delete every queue. Re-run with --reset-start to move the round start to now.',
            $age,
            $speed,
            $round
        );
    }

    /** Config columns that mark a round-progress step as done and are replayed when the round start moves. */
    private const PROGRESS_FLAGS = ['ArtifactsReleased', 'WWPlansReleased', 'WWAlertSent'];

    /**
     * Moves the round start to `$now`: the world's config row (round start and installation time, the downtime delay a
     * restore added to the old round's timers) and the account database's server row. Everything derived from the
     * start (artefact, plan, Wonder and end-of-round timers) follows, so the flags that say those steps already
     * happened are cleared and the new round replays them: the artefacts are released again at the new release time
     * (the current ones are replaced), then the Wonder plans, then the Wonder alert.
     *
     * `$world` and `$account` are connections with query() and real_escape_string() (mysqli or the game's DB wrapper).
     *
     * @return array<string, int> the values of the columns it replaced, for restoreStart()
     */
    public static function resetStart(object $world, object $account, string $worldId, int $now): array
    {
        $columns = array_merge(['startTime', 'installationTime', 'delayTime'], self::PROGRESS_FLAGS);
        $previous = $world->query('SELECT ' . implode(', ', $columns) . ' FROM config LIMIT 1');
        $row = $previous === false ? null : $previous->fetch_assoc();
        if (!is_array($row)) {
            throw new RuntimeException('The world has no config row.');
        }
        $sets = sprintf('startTime=%d, installationTime=%d, delayTime=0, ', $now, $now)
            . implode(', ', array_map(static fn (string $flag): string => $flag . '=0', self::PROGRESS_FLAGS));
        if ($world->query('UPDATE config SET ' . $sets) === false) {
            throw new RuntimeException('Could not move the round start in the world database.');
        }
        $name = $account->real_escape_string($worldId);
        if ($account->query(sprintf("UPDATE gameServers SET startTime=%d WHERE worldId='%s'", $now, $name)) === false) {
            throw new RuntimeException('Could not move the round start in the account database.');
        }

        return array_map('intval', $row);
    }

    /** Puts the columns resetStart() replaced back. @param array<string, int> $previous */
    public static function restoreStart(object $world, array $previous): void
    {
        $sets = [];
        foreach ($previous as $column => $value) {
            $sets[] = $column . '=' . (int)$value;
        }
        $world->query('UPDATE config SET ' . implode(', ', $sets));
    }
}
