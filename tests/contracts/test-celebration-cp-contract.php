<?php

declare(strict_types=1);

/**
 * Contract: celebration culture-point caps use the published speed points
 * (x1, x2, x3, x5, x10), interpolate linearly for unpublished speeds, and hold
 * the x10 value above x10 (README world-speed profile). No speed may yield 0.
 */

function failCelebrationCpContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$formulas = file_get_contents(dirname(__DIR__, 2) . '/game/include/Game/Formulas.php');
if ($formulas === false || !str_contains($formulas, 'function celebrationCpAtSpeed(')
    || str_contains($formulas, '20 => 125') || str_contains($formulas, '1000, 0, 1000')) {
    failCelebrationCpContract('Formulas must use celebrationCpAtSpeed() without the legacy per-speed table.');
}

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Celebration CP contract passed (static).\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['QUERY_STRING'] = '';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Game\Formulas;

$expected = [
    'small' => [1 => 500, 2 => 500, 3 => 250, 4 => 250, 5 => 250, 6 => 225, 7 => 200, 8 => 175, 9 => 150, 10 => 125, 11 => 125, 20 => 125, 100 => 125],
    'big' => [1 => 2000, 2 => 2000, 3 => 1000, 4 => 1000, 5 => 1000, 6 => 900, 9 => 600, 10 => 500, 20 => 500, 100 => 500],
];
foreach ($expected as $size => $table) {
    foreach ($table as $speed => $cp) {
        $actual = Formulas::celebrationCpAtSpeed($size === 'big', $speed);
        if ($actual != $cp) {
            failCelebrationCpContract("{$size} celebration at x{$speed}: expected {$cp}, got {$actual}.");
        }
    }
}
// Check interpolation between differing neighbouring published speeds.
if (Formulas::celebrationCpAtSpeed(true, 2.5) != 1500) {
    failCelebrationCpContract('Unpublished speeds must interpolate linearly (big x2.5 should be 1500).');
}

echo "Celebration CP contract passed.\n";
