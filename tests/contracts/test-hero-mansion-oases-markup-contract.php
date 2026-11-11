<?php

declare(strict_types=1);

/**
 * Contract: Hero mansion oasis tables keep valid landmarks — empty owned list
 * is a full <tr>, abandon table has a single id and class="under_progress".
 */

function failHeroMansionOasesMarkupContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ctrl = file_get_contents($root . '/game/include/Controller/Build/HeroMansionCtrl.php');
$tpl = file_get_contents($root . '/game/include/resources/Templates/build/building/HeroMansion.php');

if ($ctrl === false || $tpl === false) {
    failHeroMansionOasesMarkupContract('Unable to read Hero mansion sources.');
}

if (!str_contains($ctrl, '<tr><td class="noData" colspan="5">')) {
    failHeroMansionOasesMarkupContract('Empty owned-oasis state must wrap noData cell in <tr>.');
}

if (preg_match('/<td class="noData" colspan="5">[^<]*<\/td>(?!<\/tr>)/', $ctrl)
    && !str_contains($ctrl, '<tr><td class="noData" colspan="5">')) {
    failHeroMansionOasesMarkupContract('Lone noData <td> without <tr> is invalid.');
}

foreach (['id="oasesOwned"', 'id="oasesSurround"', 'id="oasesAbandon"', 'class="under_progress"'] as $needle) {
    if (!str_contains($tpl, $needle)) {
        failHeroMansionOasesMarkupContract('HeroMansion.php missing: ' . $needle);
    }
}

if (preg_match('/id="oasesAbandon"[^>]*id=/', $tpl) || substr_count($tpl, 'id="under_progress"') > 0) {
    failHeroMansionOasesMarkupContract('Abandon table must not keep duplicate/legacy id="under_progress".');
}

fwrite(STDOUT, "Hero mansion oases markup contract passed.\n");
