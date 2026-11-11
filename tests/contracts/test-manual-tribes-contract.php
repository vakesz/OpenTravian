#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: the manual renders every tribe's units and the tribe buildings in
 * the markup checked on 2026-10-30 (x2 world): Spartan
 * Hoplite 14:10, Sentinel 10:16, ..., Hospital 0:25:00, Defensive Wall 0:16:40;
 * Nature units show only attack/defence/velocity, Natars have no big unit art
 * and no description, the Spider sits behind the arachnophobia gate.
 */

use Core\Config;
use Game\Manual\ManualContent;

function failManualTribes(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertManualTribes(bool $condition, string $message): void
{
    if (!$condition) {
        failManualTribes($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$originalSpeed = $config->game->speed;
$originalMultiplier = $config->game->extra_training_time_multiplier;
$originalMovement = $config->game->movement_speed_increase;
$originalTribes = $config->dynamic->playableTribes ?? null;
try {
    $config->game->speed = 2;
    $config->game->extra_training_time_multiplier = 1;
    $config->game->movement_speed_increase = 2;
    $manual = new ManualContent();

    // Displayed training time and velocity at x2.
    foreach ([71 => ['0:14:10', 12], 72 => ['0:10:16', 18], 73 => ['0:16:08', 16], 74 => ['0:17:36', 12], 75 => ['0:23:28', 32], 76 => ['0:28:36', 18], 77 => ['0:38:30', 8], 78 => ['1:15:00', 6], 79 => ['10:46:15', 8], 80 => ['4:44:10', 10]] as $unitId => [$time, $velocity]) {
        $html = $manual->renderUnit($unitId);
        assertManualTribes(str_contains($html, '<span class="value ">' . $time . '</span>'), "Unit $unitId must show training time $time.");
        assertManualTribes(str_contains($html, "<td>$velocity fields/hour</td>"), "Unit $unitId must show velocity $velocity.");
        assertManualTribes(str_contains($html, '<div id="t_desc">') && str_contains($html, '<strong>Prerequisites</strong>'), "Unit $unitId must show its description and prerequisites.");
    }
    $marksman = $manual->renderUnit(65);
    assertManualTribes(
        preg_match('/<img class="att_all"[^>]*\/>\s*110\s*<\/td>/', $marksman) === 1
        && str_contains($marksman, '<td>30 fields/hour</td>')
        && str_contains($marksman, '<span class="value ">0:20:40</span>'),
        'Hun Marksman must match the manual capture at x2.'
    );
    foreach ([
        52 => "Academy</a> Level 1, <a onclick=\"Travian.Game.Manual.open('building', 13)\">Smithy</a> Level 1",
        53 => "Academy</a> Level 5, <a onclick=\"Travian.Game.Manual.open('building', 13)\">Smithy</a> Level 1",
        56 => "Stable</a> Level 10, <a onclick=\"Travian.Game.Manual.open('building', 22)\">Academy</a> Level 15",
        57 => "Academy</a> Level 10, <a onclick=\"Travian.Game.Manual.open('building', 21)\">Workshop</a> Level 1",
        62 => "Academy</a> Level 3, <a onclick=\"Travian.Game.Manual.open('building', 13)\">Smithy</a> Level 1",
        63 => "Academy</a> Level 5, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 1",
        64 => "Academy</a> Level 5, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 3",
        65 => "Academy</a> Level 5, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 5",
        66 => "Academy</a> Level 15, <a onclick=\"Travian.Game.Manual.open('building', 20)\">Stable</a> Level 10",
        67 => "Academy</a> Level 10, <a onclick=\"Travian.Game.Manual.open('building', 21)\">Workshop</a> Level 1",
    ] as $unitId => $requirements) {
        assertManualTribes(str_contains($manual->renderUnit($unitId), $requirements), "Hun unit $unitId must retain the prerequisite order.");
    }
    assertManualTribes(
        str_contains($manual->renderUnit(70), "Residence</a> Level 10 or <a onclick=\"Travian.Game.Manual.open('building', 26)\">Palace</a> Level 10 or <a onclick=\"Travian.Game.Manual.open('building', 44)\">Command Center</a> Level 10"),
        'Hun Settler must list the Command Center level-10 training route.'
    );
    $settler = $manual->renderUnit(80);
    assertManualTribes(str_contains($settler, "Residence</a> Level 10 or <a onclick=\"Travian.Game.Manual.open('building', 26)\">Palace</a> Level 10"), 'Settlers need Residence or Palace level 10.');
    assertManualTribes(str_contains($manual->renderUnit(72), "Academy</a> Level 1, <a onclick=\"Travian.Game.Manual.open('building', 13)\">Smithy</a> Level 1"), 'Prerequisites are comma separated.');
    foreach ([81 => 'Thrall', 90 => 'Settler'] as $unitId => $title) {
        $html = $manual->renderUnit($unitId);
        assertManualTribes(
            str_contains($html, '<img class="unit u' . $unitId . '"')
            && str_contains($html, $title . '    <span class="tribe">(Vikings)</span>')
            && str_contains($html, 'class="unitSection u' . $unitId . 'Section"')
            && str_contains($html, '<strong>Prerequisites</strong>'),
            "Viking unit $unitId must retain its native manual heading, art, and prerequisites."
        );
    }

    $rat = $manual->renderUnit(31);
    assertManualTribes(!str_contains($rat, 'r5') && !str_contains($rat, '<th>Can carry') && !str_contains($rat, 'id="t_desc"') && !str_contains($rat, 'id="prereqs"') && str_contains($rat, 'bigUnitSection nature'), 'Nature units show attack, defence and velocity only.');
    $pikeman = $manual->renderUnit(41);
    assertManualTribes(!str_contains($pikeman, 'bigUnitSection') && !str_contains($pikeman, 'id="t_desc"') && !str_contains($pikeman, 'id="prereqs"') && str_contains($pikeman, '<span class="value ">100</span>'), 'Natars show costs but no big unit art, description or prerequisites.');
    $spider = $manual->renderUnit(32);
    assertManualTribes(str_contains($spider, 'arachnophobiaWarning arachnophobiaMode') && str_contains($spider, 'Travian.Game.Manual.toggleArachnophobiaMode()') && str_contains($spider, 'bigUnitSection nature arachnophobiaMode'), 'The Spider is hidden behind the arachnophobia gate.');
    foreach (['game-client.js'] as $clientName) {
        $client = file_get_contents($root . '/game/deployment/public/' . $clientName);
        assertManualTribes(
            $client !== false
            && str_contains($client, 'toggleArachnophobiaMode: function ()')
            && str_contains($client, 'jQuery(".arachnophobiaWarning").removeClass("arachnophobiaMode")')
            && str_contains($client, 'jQuery(".arachnophobiaWarning ~ .bigUnitSection").removeClass("arachnophobiaMode")'),
            "$clientName must reveal the Spider by removing the reference arachnophobia classes."
        );
    }

    $hospital = $manual->renderBuilding(46);
    assertManualTribes(str_contains($hospital, 'class="building big g46 gaul"') && str_contains($hospital, '<i class="building_small tribe3 type46"></i>') && str_contains($hospital, '0:25:00') && str_contains($hospital, "Main Building</a> Level 10, <a onclick=\"Travian.Game.Manual.open('building', 22)\">Academy</a> Level 15"), 'Hospital page.');
    $wall = $manual->renderBuilding(47);
    assertManualTribes(str_contains($wall, 'class="building big g47 spartan"') && str_contains($wall, '0:16:40') && str_contains($wall, '<br/>none</div>'), 'Defensive wall page.');
    $asclepeion = $manual->renderBuilding(48);
    assertManualTribes(str_contains($asclepeion, '60% of the losses can be healed after battle.') && str_contains($asclepeion, 'g48 gaul'), 'Asclepeion page.');
    $worldWonder = $manual->renderBuilding(40);
    assertManualTribes(str_contains($worldWonder, '<span class="value ">1:15:00</span>'), 'World Wonder level 1 must match the x2 and Knowledge Base time.');
    assertManualTribes((int) \Game\Formulas::buildingUpgradeTime(40, 1, 1, false) === 4500, 'World Wonder level 1 must take 1:15:00 at x2.');
    $config->game->speed = 1;
    assertManualTribes(
        (int) \Game\Formulas::buildingUpgradeTime(40, 1, 1, false) === 9000
        && (int) \Game\Formulas::buildingUpgradeTime(40, 2, 1, false) === 9430,
        'World Wonder x1 level 1 and 2 construction times must match the official table.'
    );
    $config->game->speed = 2;
    assertManualTribes((int) \Game\Formulas::buildingUpgradeTime(40, 2, 1, false) === 4720, 'World Wonder x2 level 2 must preserve the official second rounding step.');
    foreach ([
        1 => [
            [26, 12, 14470], [34, 0, 133580], [35, 0, 136950], [52, 5, 34900], [57, 9, 33460],
            [62, 17, 27610], [63, 6, 42150], [64, 4, 46260], [76, 5, 56050], [76, 16, 37450],
            [76, 19, 33550], [86, 0, 388860], [88, 6, 67080], [90, 15, 49950], [94, 10, 64310],
        ],
        2 => [
            [54, 19, 10900], [65, 0, 131640], [76, 6, 27020], [97, 3, 43760], [98, 0, 239460],
        ],
    ] as $speed => $officialRows) {
        $config->game->speed = $speed;
        foreach ($officialRows as [$level, $mainBuildingLevel, $expectedSeconds]) {
            assertManualTribes(
                (int) \Game\Formulas::buildingUpgradeTime(40, $level, $mainBuildingLevel, false) === $expectedSeconds,
                "World Wonder x{$speed} level {$level}, Main Building {$mainBuildingLevel} must match the official calculator rounding."
            );
        }
    }
    $config->game->speed = 10;
    assertManualTribes(
        (int) \Game\Formulas::buildingUpgradeTime(40, 1, 1, false) === 900
        && (int) \Game\Formulas::buildingUpgradeTime(40, 2, 1, false) === 950,
        'World Wonder x10 level 1 and 2 construction times must match the official table.'
    );
    foreach ([
        3 => [3000, 3140],
        5 => [1800, 1890],
    ] as $speed => [$levelOne, $levelTwo]) {
        $config->game->speed = $speed;
        assertManualTribes(
            (int) \Game\Formulas::buildingUpgradeTime(40, 1, 1, false) === $levelOne
            && (int) \Game\Formulas::buildingUpgradeTime(40, 2, 1, false) === $levelTwo,
            "World Wonder x{$speed} levels 1 and 2 must match the official calculator."
        );
    }
    foreach ([
        [11, 1, 1, 820], [501, 1, 1, 20], [1000, 1, 1, 10], [501, 50, 1, 80],
        [1000, 50, 1, 40], [1000, 90, 1, 90], [1500, 90, 1, 60],
    ] as [$speed, $level, $mainBuildingLevel, $expectedSeconds]) {
        $config->game->speed = $speed;
        assertManualTribes(
            (int) \Game\Formulas::buildingUpgradeTime(40, $level, $mainBuildingLevel, false) === $expectedSeconds,
            "World Wonder local x{$speed} high-speed timing must remain unchanged."
        );
    }
    $config->game->speed = 100;
    assertManualTribes((int) \Game\Formulas::buildingUpgradeTime(40, 1, 1, false) === 90, 'World Wonder construction must continue through the supported local 100x band.');
    $config->game->speed = 1000;
    assertManualTribes((int) \Game\Formulas::buildingUpgradeTime(1, 1, 1, false) === 1, 'Ordinary construction must retain a positive queue duration at 1,000x.');
    $config->game->speed = 1000000;
    assertManualTribes(
        (int) \Game\Formulas::buildingUpgradeTime(40, 1, 1, false) === 1
        && (int) \Game\Formulas::buildingUpgradeTime(40, 50, 1, false) === 1
        && (int) \Game\Formulas::buildingUpgradeTime(40, 90, 1, false) === 1
        && (int) \Game\Formulas::buildingUpgradeTime(40, 100, 1, false) === 1
        && (int) \Game\Formulas::buildingUpgradeTime(1, 1, 1, false) === 1,
        'World Wonder and ordinary construction must take the one second minimum at 1,000,000x.'
    );
    $config->game->speed = 2;
    $commandCenter = $manual->renderBuilding(44);
    assertManualTribes(str_contains($commandCenter, "<a onclick=\"Travian.Game.Manual.open('building', 25)\"><strike>Residence</strike></a>") && str_contains($commandCenter, '0:20:50'), 'Command center page (struck prerequisites, Knowledge Base construction time).');
    assertManualTribes(str_contains($manual->renderBuilding(41), "Stable</a> Level 20, <a onclick=\"Travian.Game.Manual.open('building', 16)\">Rally Point</a> Level 10"), 'Prerequisites keep the order.');

    // The overview lists the playable tribes, then Nature and Natars, and the new buildings.
    $config->dynamic->playableTribes = '1,2,3,6,7,8';
    $overview = \resources\View\PHPBatchView::render('manual/overview');
    preg_match_all('/<ul class="troops(\d)">/', $overview, $tabs);
    assertManualTribes($tabs[1] === ['1', '2', '3', '6', '7', '8', '4', '5'], 'Troop tabs: Romans, Teutons, Gauls, Egyptians, Huns, Spartans, Nature, Natars.');
    assertManualTribes(substr_count($overview, 'class="tabContainer') === 11 && substr_count($overview, 'class="instantTabs"') === 2, 'Manual overview uses the instant-tabs structure.');
    foreach ([46, 47, 48] as $gid) {
        assertManualTribes(str_contains($overview, "Travian.Game.Manual.open('building', " . $gid . ')'), "The overview lists building $gid.");
    }
    assertManualTribes(!str_contains($overview, "Travian.Game.Manual.open('building', 50)") && !str_contains($overview, "Travian.Game.Manual.open('troop', 81);"), 'Vikings stay out of the manual while they are not playable.');
    $config->dynamic->playableTribes = '1,2,3,6,7,8,9';
    $overview = \resources\View\PHPBatchView::render('manual/overview');
    assertManualTribes(str_contains($overview, "Travian.Game.Manual.open('troop', 81);") && str_contains($overview, "Travian.Game.Manual.open('building', 50)"), 'Vikings appear in the manual when the world enables them.');
} finally {
    $config->game->speed = $originalSpeed;
    $config->game->extra_training_time_multiplier = $originalMultiplier;
    $config->game->movement_speed_increase = $originalMovement;
    $config->dynamic->playableTribes = $originalTribes;
}

echo "Manual tribes contract passed.\n";
