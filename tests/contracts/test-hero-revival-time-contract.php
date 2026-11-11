<?php

declare(strict_types=1);

namespace Tests\Stub {
    final class HeroRevivalTimeConfigStub
    {
        public static int $speed = 1;
        public static float $regenerationMultiplier = 1;

        public static function getProperty(string ...$properties): mixed
        {
            return match ($properties) {
                ['game', 'useNanoseconds'], ['game', 'useMilSeconds'] => false,
                ['game', 'heroRegenerationMultiplier'] => self::$regenerationMultiplier,
                default => null,
            };
        }
    }
}

namespace {
    // The game code resolves Core\Config; the stand-in keeps a test-local name so
    // static analysis never confuses it with the real class.
    class_alias(\Tests\Stub\HeroRevivalTimeConfigStub::class, 'Core\\Config');

    function getGameSpeed(): int
    {
        return \Tests\Stub\HeroRevivalTimeConfigStub::$speed;
    }

    function assertHeroRevivalTime(bool $condition, string $message): void
    {
        if ($condition) {
            return;
        }

        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';
    require dirname(__DIR__, 2) . '/game/include/Game/Formulas.php';

    foreach ([1 => 1, 2 => 1, 3 => 2, 5 => 2, 10 => 4] as $speed => $speedDivisor) {
        \Tests\Stub\HeroRevivalTimeConfigStub::$speed = $speed;
        foreach ([0, 5, 23, 98] as $level) {
            $expectedSeconds = (int)round(min($level + 1, 24) / $speedDivisor * 3600);
            assertHeroRevivalTime(
                (int)\Game\Formulas::heroRegenerationTime($level) === $expectedSeconds,
                sprintf('Level %d revival time must use the official x%d speed formula.', $level, $speed)
            );
        }

        foreach ([99, 100, 120] as $level) {
            $expectedSeconds = (int)round(24 * 99 / (99 + ($level - 99) * 5) * 3600);
            assertHeroRevivalTime(
                (int)\Game\Formulas::heroRegenerationTime($level) === $expectedSeconds,
                sprintf('Level %d revival time must use the official level-99+ formula.', $level)
            );
        }
    }

    foreach ([20 => 7, 21 => 8, 100 => 16, 1000 => 50] as $speed => $speedDivisor) {
        \Tests\Stub\HeroRevivalTimeConfigStub::$speed = $speed;
        assertHeroRevivalTime(
            (int)\Game\Formulas::heroRegenerationTime(5) === (int)round(6 / $speedDivisor * 3600),
            sprintf('Level 5 revival time at x%d must follow the continuous revival divisor %d.', $speed, $speedDivisor)
        );
    }

    echo "Hero revival time contract passed.\n";
}
