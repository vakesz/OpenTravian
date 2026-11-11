#!/usr/bin/env php
<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool may only run from the command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/game/deployment/include/env.php';
require_once dirname(__DIR__) . '/game/include/bootstrap.php';

$db = \Core\Database\DB::getInstance();
$villages = (int)$db->fetchScalar('SELECT COUNT(*) FROM vdata');
$storageMultiplier = (int)\Core\Config::getProperty('game', 'storage_multiplier');
$currentSpeed = max(1, (int)getGameSpeed());
$fromSpeed = null;
$toSpeed = null;

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--from-speed=')) {
        $candidate = substr($argument, strlen('--from-speed='));
        if (!ctype_digit($candidate) || (int)$candidate < 1) {
            fwrite(STDERR, "--from-speed must be a positive integer.\n");
            exit(2);
        }
        $fromSpeed = (int)$candidate;
    } elseif (str_starts_with($argument, '--to-speed=')) {
        $candidate = substr($argument, strlen('--to-speed='));
        if (!ctype_digit($candidate) || (int)$candidate < 1) {
            fwrite(STDERR, "--to-speed must be a positive integer.\n");
            exit(2);
        }
        $toSpeed = (int)$candidate;
    } elseif ($argument !== '--apply' && $argument !== '--force') {
        fwrite(STDERR, "Usage: php tools/reconcile-world-speed-profile.php [--from-speed=<old speed>] [--to-speed=<new speed, dry run only>] [--apply] [--force]\n");
        exit(2);
    }
}


function reconcileInFlightTimers(int $from, int $to, bool $apply): void
{
    if ($from === $to) {
        printf("In-flight timers: speed is unchanged (%dx); nothing to rescale.\n", $to);
        return;
    }
    $db = \Core\Database\DB::getInstance();
    $plans = \Game\WorldSpeedTimers::plans($from, $to, time());
    foreach ($plans as $plan) {
        $count = (int)$db->fetchScalar(sprintf('SELECT COUNT(*) FROM %s WHERE %s', $plan['table'], \Game\WorldSpeedTimers::where($plan)));
        printf(
            "  %-34s %6d row(s), remaining time x%s%s\n",
            $plan['label'] . ':',
            $count,
            rtrim(rtrim(\Game\WorldSpeedTimers::float($plan['ratio']), '0'), '.'),
            isset($plan['from_unit']) && $plan['from_unit'] !== $plan['unit'] ? sprintf(' (timestamps converted from 1/%d s to 1/%d s)', $plan['from_unit'], $plan['unit']) : ''
        );
    }
    if (!$apply) {
        return;
    }
    if (!$db->begin_transaction()) {
        fwrite(STDERR, "Could not start the in-flight timer transaction.\n");
        exit(1);
    }
    try {
        foreach ($plans as $plan) {
            if (!$db->query(\Game\WorldSpeedTimers::statement($plan))) {
                throw new \RuntimeException('Could not rescale ' . $plan['label'] . '.');
            }
        }
        if (!$db->commit()) {
            throw new \RuntimeException('Could not commit the in-flight timer rescale.');
        }
    } catch (\Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        fwrite(STDERR, 'In-flight timer rescale failed: ' . $exception->getMessage() . "\n");
        exit(1);
    }
    echo "Rescaled the in-flight timers listed above.\n";
}

$targetSpeed = $toSpeed ?? $currentSpeed;
$heroCount = (int)$db->fetchScalar('SELECT COUNT(*) FROM hero');
$oldHeroMultiplier = $fromSpeed === null
    ? null
    : \Game\Formulas::heroExpLevelMultiplierForSpeed($fromSpeed);
$newHeroMultiplier = \Game\Formulas::heroExpLevelMultiplierForSpeed($currentSpeed);

if (!in_array('--apply', $argv, true)) {
    $summary = sprintf(
        "Dry run: %d village storage, production, and upkeep rows would be recalculated for storage multiplier %d.",
        $villages,
        $storageMultiplier
    );
    if ($fromSpeed !== null) {
        $summary .= sprintf(
            " %d hero experience rows would be rescaled from %dx (multiplier %d) to %dx (multiplier %d) without changing their level.",
            $heroCount,
            $fromSpeed,
            $oldHeroMultiplier,
            $currentSpeed,
            $newHeroMultiplier
        );
    } else {
        $summary .= ' Existing hero experience is left unchanged; pass --from-speed=<old speed> after a speed change to preserve hero levels.';
    }
    printf("%s Re-run with --apply.\n", $summary);
    if ($fromSpeed !== null) {
        printf("In-flight timers that would be rescaled from %dx to %dx:\n", $fromSpeed, $targetSpeed);
        reconcileInFlightTimers($fromSpeed, $targetSpeed, false);
    }
    exit(0);
}

/**
 * Re-run guard: the last applied from->to pair and the phases already done are
 * kept in the generated state directory, so a repeated apply cannot rescale hero
 * experience or in-flight timers twice. --force starts over.
 *
 * @return array{from: int|null, to: int|null, phases: list<string>, complete: bool}
 */
function worldSpeedGuardLoad(string $file): array
{
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;

    return [
        'from' => is_array($data) && isset($data['from']) ? (int)$data['from'] : null,
        'to' => is_array($data) && isset($data['to']) ? (int)$data['to'] : null,
        'phases' => is_array($data) && is_array($data['phases'] ?? null) ? array_values($data['phases']) : [],
        'complete' => is_array($data) && !empty($data['complete']),
    ];
}

function worldSpeedGuardSave(string $file, array $guard): void
{
    file_put_contents($file, json_encode($guard, JSON_THROW_ON_ERROR), LOCK_EX);
}

$guardFile = rtrim(STATE_DIR, '/') . '/world-speed-reconcile.json';
$guard = ['from' => null, 'to' => null, 'phases' => [], 'complete' => false];
if ($fromSpeed !== null && $fromSpeed !== $currentSpeed) {
    $guard = worldSpeedGuardLoad($guardFile);
    $sameChange = $guard['from'] === $fromSpeed && $guard['to'] === $currentSpeed;
    $forced = in_array('--force', $argv, true);
    if ($sameChange && $guard['complete'] && !$forced) {
        fwrite(STDERR, sprintf("The %dx -> %dx reconciliation was already applied. Re-run with --force to repeat it.\n", $fromSpeed, $currentSpeed));
        exit(1);
    }
    if (!$sameChange || $forced) {
        $guard = ['from' => $fromSpeed, 'to' => $currentSpeed, 'phases' => [], 'complete' => false];
    }
    worldSpeedGuardSave($guardFile, $guard);
}
$phaseDone = static fn (string $phase): bool => in_array($phase, $guard['phases'], true);

$reconciledHeroes = 0;
if ($fromSpeed !== null && !$phaseDone('hero') && $oldHeroMultiplier !== $newHeroMultiplier) {
    if (!$db->begin_transaction()) {
        fwrite(STDERR, "Could not start the hero experience reconciliation transaction.\n");
        exit(1);
    }

    try {
        $result = $db->query('SELECT uid, exp FROM hero FOR UPDATE');
        if (!$result) {
            throw new \RuntimeException('Could not load hero experience rows.');
        }
        while ($hero = $result->fetch_assoc()) {
            $scaledExperience = (int)round(
                ((float)$hero['exp'] * $newHeroMultiplier) / $oldHeroMultiplier
            );
            $statement = $db->run('UPDATE hero SET exp=? WHERE uid=?', [
                $scaledExperience,
                (int)$hero['uid'],
            ]);
            if (!$statement || ($scaledExperience !== (int)$hero['exp'] && $statement->affected_rows !== 1)) {
                throw new \RuntimeException('Could not update a hero experience row.');
            }
            ++$reconciledHeroes;
        }
        $result->free();
        if (!$db->commit()) {
            throw new \RuntimeException('Could not commit hero experience reconciliation.');
        }
    } catch (\Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        fwrite(STDERR, 'Hero experience reconciliation failed: ' . $exception->getMessage() . "\n");
        exit(1);
    }
    $guard['phases'][] = 'hero';
    worldSpeedGuardSave($guardFile, $guard);
}

// Existing villages persist both capacity and hourly production in vdata, so
// applying a changed speed profile needs the established level-aware repairs
// rather than only updating the configuration. The storage repair includes
// extra-storage bonuses; the resource repair also refreshes upkeep. Run these
// only after the independent hero transaction has committed successfully.
require_once INCLUDE_PATH . 'fixes/fixStorages.php';
if ($fromSpeed === null) {
    require_once INCLUDE_PATH . 'fixes/fixAllResources.php';
} else {
    $productionRows = $db->query('SELECT kid FROM vdata');
    if (!$productionRows) {
        fwrite(STDERR, "Could not load villages for production reconciliation.\n");
        exit(1);
    }
    while ($village = $productionRows->fetch_assoc()) {
        \Game\ResourcesHelper::recalculateVillageProduction((int)$village['kid']);
    }
    $productionRows->free();
}

if ($fromSpeed !== null) {
    echo "In-flight timers:\n";
    if ($phaseDone('timers')) {
        echo "  already rescaled by an earlier run of this change; skipped.\n";
    } else {
        reconcileInFlightTimers($fromSpeed, $currentSpeed, true);
        if ($fromSpeed !== $currentSpeed) {
            $guard['phases'][] = 'timers';
            worldSpeedGuardSave($guardFile, $guard);
        }
    }
}
if ($fromSpeed !== null && $fromSpeed !== $currentSpeed) {
    $guard['complete'] = true;
    worldSpeedGuardSave($guardFile, $guard);
}

printf(
    "Reconciled storage, granary capacity, and resource production for %d villages at multiplier %d.\n",
    $villages,
    $storageMultiplier
);
if ($fromSpeed !== null) {
    printf(
        "Reconciled %d hero experience rows from %dx (multiplier %d) to %dx (multiplier %d).\n",
        $reconciledHeroes,
        $fromSpeed,
        $oldHeroMultiplier,
        $currentSpeed,
        $newHeroMultiplier
    );
}
