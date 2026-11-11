<?php

declare(strict_types=1);

namespace Tests\Stub {
    final class VacationAllowanceConfigStub
    {
        public static int $speed = 1;
        public static int $baseDays = 15;

        public static function getProperty(string ...$properties): int
        {
            if ($properties !== ['game', 'vacationDays']) {
                throw new \LogicException('Unexpected configuration lookup.');
            }
            return self::$baseDays;
        }
    }
}

namespace {
    // The game code resolves Core\Config; the stand-in keeps a test-local name so
    // static analysis never confuses it with the real class.
    class_alias(\Tests\Stub\VacationAllowanceConfigStub::class, 'Core\\Config');

    function getGameSpeed(): int
    {
        return \Tests\Stub\VacationAllowanceConfigStub::$speed;
    }

    require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';
    require dirname(__DIR__, 2) . '/game/include/Game/Formulas.php';
    require dirname(__DIR__, 2) . '/game/include/Game/WorldOverrides.php';

    function expectVacationDays(int $expected, string $message): void
    {
        if (\Game\Formulas::maxVacationDays() !== $expected) {
            fwrite(STDERR, $message . PHP_EOL);
            exit(1);
        }
    }

    foreach ([1 => 15, 2 => 8, 3 => 5, 5 => 3, 10 => 2] as $speed => $expected) {
        \Tests\Stub\VacationAllowanceConfigStub::$speed = $speed;
        \Tests\Stub\VacationAllowanceConfigStub::$baseDays = 15;
        expectVacationDays($expected, "Official x$speed vacation allowance is incorrect.");
        foreach ([0, 1, $expected, $expected + 3] as $used) {
            if (\Game\Formulas::remainingVacationDays($used) !== max(0, $expected - $used)) {
                fwrite(STDERR, "Vacation balance must subtract used days without going negative.\n");
                exit(1);
            }
        }
        $config = (object)['game' => (object)['vacationDays' => 15]];
        \Game\WorldOverrides::apply($config, ['vacationDays' => 30]);
        \Tests\Stub\VacationAllowanceConfigStub::$baseDays = $config->game->vacationDays;
        expectVacationDays((int)round(30 / $speed), 'Explicit operator base-day overrides must retain speed scaling.');
        \Game\WorldOverrides::apply($config, ['vacationDays' => 0]);
        \Tests\Stub\VacationAllowanceConfigStub::$baseDays = $config->game->vacationDays;
        expectVacationDays(0, 'Zero must continue to disable vacation.');
    }

    foreach ([4, 6, 20, 100, 1000] as $speed) {
        \Tests\Stub\VacationAllowanceConfigStub::$speed = $speed;
        \Tests\Stub\VacationAllowanceConfigStub::$baseDays = 21;
        expectVacationDays(max((int)round(21 / $speed), 3), 'Unlisted speeds must retain their local vacation policy.');
    }

    $source = file_get_contents(dirname(__DIR__, 2) . '/game/include/config.php');
    if ($source === false || !str_contains($source, "'vacationDays'                         => \\Game\\WorldSpeedProfile::isOfficialLegendsSpeed((int)\$connection['speed']) ? 15 : 21")) {
        fwrite(STDERR, "Vacation defaults must select the official base only at published speeds.\n");
        exit(1);
    }

    echo "Official vacation allowances, overrides, disabled state, and remaining balances passed.\n";
}
