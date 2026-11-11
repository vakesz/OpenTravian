<?php

declare(strict_types=1);

/**
 * Contract: Rally Point arrival row + adventure return headline match.
 * - th text key ArrivalIn resolves to "Arrival"
 * - timer uses data-value + "&nbsp;hrs."
 * - adventure return links to /karte.php?d=0 without coords
 */
function failRallyPointArrivalMarkupContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$html = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPointHTML.php');
$rally = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPoint.php');
$en = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$overview = file_get_contents($root . '/game/include/resources/Templates/rallypoint/overview.php');

if ($html === false || $rally === false || $en === false || $overview === false) {
    failRallyPointArrivalMarkupContract('Unable to read Rally Point arrival sources.');
}

if (!str_contains($en, "\$Definition['RallyPoint']['ArrivalIn'] = 'Arrival'")
    || !str_contains($en, "\$Definition['RallyPoint']['adventure'] = 'adventure'")
    || !str_contains($en, "\$Definition['RallyPoint']['showAll'] = 'Show all'")
    || !str_contains($en, "\$Definition['RallyPoint']['SubFilters'][3] = 'Incoming reinforcements'")
    || !str_contains($en, "\$Definition['RallyPoint']['filters'][3] = 'Troops in this village'")
    || !str_contains($en, "\$Definition['RallyPoint']['filters'][4] = 'Troops in other villages'")
    || !str_contains($en, "\$Definition['RallyPoint']['SubFilters'][1] = 'Incoming attacks/raids'")
    || !str_contains($en, "\$Definition['RallyPoint']['SubFilters'][2] = 'Returning troops'")
    || !str_contains($en, "\$Definition['RallyPoint']['SubFilters'][5] = 'Outgoing reinforcements'")
    || !str_contains($en, "\$Definition['RallyPoint']['SubFilters'][6] = 'Sort descending from last sent to earliest'")
    || !str_contains($en, "\$Definition['RallyPoint']['changeHeroHomeVillage'] = 'Change hero home village'")
    || !str_contains($en, "\$Definition['RallyPoint']['sendBack'] = 'send back'")
    || !str_contains($en, "\$Definition['RallyPoint']['reinforcementForVillageName'] = 'Reinforcement for %s'")
    || !str_contains($en, "\$Definition['RallyPoint']['reinforcementForPlayerName'] = 'Reinforcement for %s'")
    || !str_contains($en, "\$Definition['RallyPoint']['imprisendInVillage'] = 'Imprisoned in %s'")
    || !str_contains($en, "\$Definition['RallyPoint']['imprisendPlayer'] = 'Imprisoned troops from %s'")
    || !str_contains($en, "\$Definition['RallyPoint']['no_incoming_troops_error'] = 'There are no incoming troops currently.'")
    || !str_contains($en, "\$Definition['RallyPoint']['no_outgoing_troops_error'] = 'There are no outgoing troops currently.'")
    || !str_contains($en, "\$Definition['RallyPoint']['no_outvillage_troops_error'] = 'There are no troops from this village in other villages or oases.'")
    || !str_contains($en, "\$Definition['inGame']['finishNowWWDisabled'] = 'Not available in wonder of the world village.'")
    || !str_contains($en, "\$Definition['Global']['General']['hour'] = 'hrs'")) {
    failRallyPointArrivalMarkupContract('en-US RallyPoint Arrival, filter, adventure, and hrs copy must match.');
}

if (!str_contains($html, 'data-value="')
    || !str_contains($html, "T(\"Global\", \"General.hour\")")
    || !str_contains($html, "'&nbsp;' . T(\"Global\", \"General.hour\")")
    || !str_contains($html, '<div class="at"><span>')
    || !str_contains($html, "T(\"Global\", \"General.at\") . '&nbsp;'")) {
    failRallyPointArrivalMarkupContract('RallyPointHTML Arrival markup must match timer/at structure.');
}

if (!str_contains($rally, 'ATTACKTYPE_ADVENTURE')
    || !str_contains($rally, 'href="/karte.php?d=0"')
    || !preg_match(
        '/ATTACKTYPE_ADVENTURE[\s\S]{0,400}?href="\/karte\.php\?d=0"[\s\S]{0,200}?"adventure"\) \. \'<\/a>\';/',
        $rally
    )) {
    failRallyPointArrivalMarkupContract('Adventure return headline must link /karte.php?d=0 without coords.');
}

if (!str_contains($overview, 'useicon=""')
    || !str_contains($overview, 'this.disabled = true;')
    || !str_contains($overview, 'src="/img/spacer.gif"')
    || !str_contains($overview, "\$rallyPointQuery = '/build.php?gid=16&amp;tt=1'")
    || str_contains($overview, "\$rallyPointQuery = '?gid=16&amp;tt=1'")
    || !str_contains($overview, '? 0 : 1')) {
    failRallyPointArrivalMarkupContract('Overview filters must use root-absolute Rally Point URLs, useicon, /img/spacer.gif, and filter=0 clear.');
}

echo "Rally Point arrival markup contract passed.\n";
