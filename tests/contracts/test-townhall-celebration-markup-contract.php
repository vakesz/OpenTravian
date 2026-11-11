<?php

declare(strict_types=1);

/**
 * Contract: Townhall big celebration uses celebrationBig (not celebrationSmall),
 * and celebration progress tables use class="under_progress" (Academy/Smithy/Barracks).
 */

function failTownhallCelebrationMarkupContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$townhall = file_get_contents($root . '/game/include/resources/Templates/build/building/Townhall.php');
$brewery = file_get_contents($root . '/game/include/resources/Templates/build/building/Brewery.php');

if ($townhall === false || $brewery === false) {
    failTownhallCelebrationMarkupContract('Unable to read Townhall/Brewery templates.');
}

if (!str_contains($townhall, 'class="celebration celebrationBig"')) {
    failTownhallCelebrationMarkupContract('Big celebration image must use celebrationBig.');
}

if (!str_contains($townhall, 'class="celebration celebrationSmall"')) {
    failTownhallCelebrationMarkupContract('Small celebration image must keep celebrationSmall.');
}

// The big-celebration block must not reuse celebrationSmall on its img.
if (preg_match(
    '/Big celebration[\s\S]{0,400}class="celebration celebrationSmall"/',
    $townhall
) || preg_match(
    '/class="celebration celebrationSmall"[\s\S]{0,400}Big celebration/',
    $townhall
)) {
    failTownhallCelebrationMarkupContract('Big celebration block still references celebrationSmall.');
}

foreach ([$townhall => 'Townhall', $brewery => 'Brewery'] as $source => $label) {
    if (str_contains($source, 'id="under_progress"')) {
        failTownhallCelebrationMarkupContract($label . ' must not use id="under_progress".');
    }
    if (!str_contains($source, 'class="under_progress"') && str_contains($source, 'celebrationRunning')) {
        failTownhallCelebrationMarkupContract($label . ' progress table must use class="under_progress".');
    }
}

if (str_contains($townhall, 'Travian.Game.iPopup')) {
    failTownhallCelebrationMarkupContract('Townhall must use Manual.open instead of iPopup.');
}
if (!str_contains($townhall, "Travian.Game.Manual.open('building', 24)")) {
    failTownhallCelebrationMarkupContract('Townhall celebration links must use Manual.open(\'building\', 24).');
}

if (str_contains($brewery, 'Travian.Game.iPopup')) {
    failTownhallCelebrationMarkupContract('Brewery must use Manual.open instead of iPopup.');
}
if (!str_contains($brewery, "Travian.Game.Manual.open('building', 35)")) {
    failTownhallCelebrationMarkupContract('Brewery festival links must use Manual.open(\'building\', 35).');
}
if (str_contains($brewery, 'src="img/spacer.gif"')) {
    failTownhallCelebrationMarkupContract('Brewery must not use relative src="img/spacer.gif".');
}
if (!str_contains($brewery, 'src="/img/spacer.gif"')) {
    failTownhallCelebrationMarkupContract('Brewery must use src="/img/spacer.gif".');
}
if (!str_contains($brewery, 'class="round"') || !str_contains($brewery, 'Hold a celebration')) {
    failTownhallCelebrationMarkupContract('Brewery must use the Townhall-style Hold a celebration h4.round heading.');
}
if (!str_contains($brewery, 'build_details researches')) {
    failTownhallCelebrationMarkupContract('Brewery festival block must use build_details researches wrapper.');
}

fwrite(STDOUT, "Townhall celebration markup contract passed.\n");


