<?php

declare(strict_types=1);

namespace Tests\Stub {
    final class DailyQuestPremiumDurationConfigStub
    {
        public static int $speed = 2;

        public static function getProperty(string ...$properties): mixed
        {
            return match ($properties) {
                ['quest', 'multiplyRewards'] => true,
                ['game', 'speed'] => self::$speed,
                ['gold', 'plusAccountDurationSeconds'],
                ['gold', 'productionBoostDurationSeconds'] => 10800,
                default => null,
            };
        }
    }
}

namespace {
    // The game code resolves Core\Config; the stand-in keeps a test-local name so
    // static analysis never confuses it with the real class.
    class_alias(\Tests\Stub\DailyQuestPremiumDurationConfigStub::class, 'Core\\Config');

    function getGameSpeed(): int
    {
        return \Tests\Stub\DailyQuestPremiumDurationConfigStub::$speed;
    }

    function failDailyQuestPremiumDuration(string $message): never
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    require dirname(__DIR__, 2) . '/game/include/Game/PremiumDuration.php';
    require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';
    require dirname(__DIR__, 2) . '/game/include/Model/Quest.php';

    foreach ([1, 2, 3, 5, 10] as $speed) {
        \Tests\Stub\DailyQuestPremiumDurationConfigStub::$speed = $speed;
        foreach (['plus', 'productionBoost'] as $type) {
            if (\Model\Quest::calcEffect(86400, $type, false, true) !== 86400) {
                failDailyQuestPremiumDuration("Published x{$speed} daily-quest {$type} rewards must add exactly one day.");
            }
        }
        if (\Model\Quest::calcEffect(200, 'res', false, true) !== 200) {
            failDailyQuestPremiumDuration("Published x{$speed} daily-quest resource rewards must not scale.");
        }
    }

    \Tests\Stub\DailyQuestPremiumDurationConfigStub::$speed = 10;
    if (\Model\Quest::calcEffect(200, 'res') != 400) {
        failDailyQuestPremiumDuration('Non-daily quest resource scaling must be preserved.');
    }
    \Tests\Stub\DailyQuestPremiumDurationConfigStub::$speed = 11;
    if (\Model\Quest::calcEffect(200, 'res', false, true) != 400
        || \Model\Quest::calcEffect(86400, 'plus', false, true) != 86400
        || \Model\Quest::calcEffect(86400, 'productionBoost', false, true) != 86400) {
        failDailyQuestPremiumDuration('Unlisted-speed daily rewards keep the local formula but never last under one day.');
    }
    // A reward below one day is lifted to one day, a longer one is kept (user rule 2026-11-08).
    if (\Model\Quest::calcEffect(3600, 'plus', false, true) != 86400 || \Model\Quest::calcEffect(3600, 'productionBoost') != 86400) {
        failDailyQuestPremiumDuration('Plus and production-boost rewards must last at least one day.');
    }

    echo "Daily-quest premium duration contract passed.\n";
}
