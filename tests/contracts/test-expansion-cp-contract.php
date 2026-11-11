<?php

declare(strict_types=1);

namespace Tests\Stub {
    final class ExpansionCpConfigStub
    {
        public static int $speed = 1;
    }
}

namespace {
    // The game code resolves Core\Config; the stand-in keeps a test-local name so
    // static analysis never confuses it with the real class.
    class_alias(\Tests\Stub\ExpansionCpConfigStub::class, 'Core\\Config');

    function getGameSpeed(): int
    {
        return \Tests\Stub\ExpansionCpConfigStub::$speed;
    }

    function getGame(string $name): int
    {
        if ($name !== 'movement_speed_increase') {
            throw new \LogicException('Unexpected game setting.');
        }
        return \Game\WorldSpeedProfile::movementSpeedMultiplier(getGameSpeed());
    }

    require dirname(__DIR__, 2) . '/game/include/Game/WorldSpeedProfile.php';
    require dirname(__DIR__, 2) . '/game/include/Game/Formulas.php';

    $requirements = [
        1 => [0, 2000, 8000, 20000, 39000, 65000, 99000, 141000, 191000, 251000, 319000, 397000, 486000, 584000, 692000, 811000, 941000, 1082000, 1234000, 1397000],
        2 => [0, 800, 3900, 10000, 19400, 32400, 49300, 70300, 95500, 125300, 159600, 198700, 242800, 291800, 346100, 405600, 470500, 540900, 616900, 698600],
        3 => [0, 500, 2600, 6700, 12900, 21600, 32900, 46900, 63700, 83500, 106400, 132500, 161900, 194600, 230700, 270400, 313700, 360600, 411300, 465700],
        5 => [0, 300, 1600, 4000, 7800, 13000, 19700, 28100, 38200, 50100, 63800, 79500, 97100, 116700, 138400, 162200, 188200, 216400, 246800, 279400],
        10 => [0, 200, 800, 2000, 3900, 6500, 9900, 14100, 19100, 25100, 31900, 39700, 48600, 58400, 69200, 81100, 94100, 108200, 123400, 139700],
    ];
    $laterRequirements = [
        [1572000, 786100, 524000, 314400, 157200],
        [1759000, 879400, 586300, 351800, 175900],
        [1957000, 978700, 652500, 391500, 195700],
        [2168000, 1084100, 722700, 433600, 216800],
        [2391000, 1195600, 797000, 478200, 239100],
        [2627000, 1313300, 875500, 525300, 262700],
        [2874000, 1437200, 958200, 574900, 287400],
        [3135000, 1567600, 1045000, 627000, 313500],
        [3409000, 1704300, 1136200, 681700, 340900],
        [3695000, 1847600, 1231700, 739000, 369500],
        [3995000, 1997400, 1331600, 799000, 399500],
        [4308000, 2153900, 1435900, 861600, 430800],
        [4634000, 2317000, 1544700, 926800, 463400],
        [4974000, 2487000, 1658000, 994800, 497400],
        [5327000, 2663700, 1775800, 1065500, 532700],
        [5695000, 2847400, 1898300, 1139000, 569500],
        [6076000, 3038000, 2025300, 1215200, 607600],
        [6471000, 3235600, 2157100, 1294200, 647100],
        [6881000, 3440300, 2293500, 1376100, 688100],
        [7304000, 3652100, 2434700, 1460800, 730400],
        [7742000, 3871000, 2580700, 1548400, 774200],
        [8195000, 4097300, 2731500, 1638900, 819500],
        [8662000, 4330800, 2887200, 1732300, 866200],
        [9143000, 4571600, 3047700, 1828600, 914300],
        [9640000, 4819800, 3213200, 1927900, 964000],
        [10151000, 5075500, 3383700, 2030200, 1015100],
        [10677000, 5338700, 3559100, 2135500, 1067700],
        [11219000, 5609400, 3739600, 2243800, 1121900],
        [11775000, 5887700, 3925100, 2355100, 1177500],
        [12347000, 6173600, 4115800, 2469500, 1234700],
    ];
    foreach ($laterRequirements as $row) {
        foreach ([1, 2, 3, 5, 10] as $index => $speed) {
            $requirements[$speed][] = $row[$index];
        }
    }
    foreach ($requirements as $speed => $points) {
        \Tests\Stub\ExpansionCpConfigStub::$speed = $speed;
        if (\Game\Formulas::startingCulturePoints() !== (int)ceil(500 / $speed)) {
            fwrite(STDERR, "Starting CP must follow the published x$speed value.\n");
            exit(1);
        }
        foreach ($points as $index => $expected) {
            $villageCount = $index + 1;
            if ((int)\Game\Formulas::newVillageCP($villageCount) !== $expected) {
                fwrite(STDERR, "Wrong CP threshold for village $villageCount on x$speed.\n");
                exit(1);
            }
            if ((int)\Game\Formulas::countCPVillages($expected) !== $villageCount
                || ($index > 0 && (int)\Game\Formulas::countCPVillages($expected - 1) !== $index)) {
                fwrite(STDERR, "CP slot count disagrees with threshold $expected on x$speed.\n");
                exit(1);
            }
        }
    }
    foreach ([4, 6, 20, 100, 1000] as $speed) {
        \Tests\Stub\ExpansionCpConfigStub::$speed = $speed;
        if (\Game\Formulas::startingCulturePoints() !== (int)ceil(500 / min($speed, 8))) {
            fwrite(STDERR, "Custom-speed starting CP must not change.\n");
            exit(1);
        }
        for ($count = 1; $count <= 100; ++$count) {
            $cappedSpeed = min($speed, 10);
            $expected = ceil(getGame('movement_speed_increase') / 50)
                * round(1600 * pow($count - 1, 2.3) / $cappedSpeed, $cappedSpeed >= 3 ? -2 : -3);
            if (\Game\Formulas::newVillageCP($count) !== $expected) {
                fwrite(STDERR, "Custom-speed CP thresholds must not change.\n");
                exit(1);
            }
        }
    }
    echo "Expansion CP published tables, slot boundaries, and custom-speed thresholds passed.\n";
}
