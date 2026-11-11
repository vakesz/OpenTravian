<?php

declare(strict_types=1);

namespace Tests\Stub {
    final class HeroItemTierTimingConfigStub
    {
        public static int $speed = 1;
        public static int $elapsed = 0;
        public static int $roundLength = 100;

        public static function getProperty(string ...$properties): int
        {
            if ($properties !== ['game', 'round_length']) {
                throw new \LogicException('Unexpected configuration lookup.');
            }
            return self::$roundLength;
        }
    }
}

namespace {
    // The game code resolves Core\Config; the stand-in keeps a test-local name so
    // static analysis never confuses it with the real class.
    class_alias(\Tests\Stub\HeroItemTierTimingConfigStub::class, 'Core\\Config');

    function getGameSpeed(): int
    {
        return \Tests\Stub\HeroItemTierTimingConfigStub::$speed;
    }

    function getGameElapsedSeconds(): int
    {
        return \Tests\Stub\HeroItemTierTimingConfigStub::$elapsed;
    }

    require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';
    require dirname(__DIR__, 2) . '/game/include/Game/Formulas.php';

    foreach ([1, 2, 3, 5, 10] as $speed) {
        \Tests\Stub\HeroItemTierTimingConfigStub::$speed = $speed;
        foreach ([100, 160, 250] as $roundLength) {
            \Tests\Stub\HeroItemTierTimingConfigStub::$roundLength = $roundLength;
            $tier2 = 70 * 86400 / $speed;
            $tier3 = 140 * 86400 / $speed;
            foreach ([0 => 1, (int)$tier2 - 1 => 1, (int)$tier2 => 2, (int)$tier3 - 1 => 2, (int)$tier3 => 3] as $elapsed => $expected) {
                \Tests\Stub\HeroItemTierTimingConfigStub::$elapsed = $elapsed;
                if (\Game\Formulas::getCurrentHeroItemsTier() !== $expected) {
                    fwrite(STDERR, "Wrong hero tier at elapsed $elapsed on x$speed (round $roundLength).\n");
                    exit(1);
                }
            }
        }
    }
    foreach ([4, 6, 20, 1000] as $speed) {
        \Tests\Stub\HeroItemTierTimingConfigStub::$speed = $speed;
        \Tests\Stub\HeroItemTierTimingConfigStub::$roundLength = 35;
        foreach ([0 => 1, 648000 => 1, 648001 => 2, 1425600 => 2, 1425601 => 3] as $elapsed => $expected) {
            \Tests\Stub\HeroItemTierTimingConfigStub::$elapsed = $elapsed;
            if (\Game\Formulas::getCurrentHeroItemsTier() !== $expected) {
                fwrite(STDERR, "Custom-speed item-tier timing must retain round-length scaling.\n");
                exit(1);
            }
        }
    }
    echo "Hero item-tier published unlock boundaries and custom-speed fallback passed.\n";
}
