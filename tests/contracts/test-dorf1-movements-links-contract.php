<?php

declare(strict_types=1);

/**
 * Contract: dorf1 movement rows use root-absolute rally links
 * (/build.php?gid=16&tt=1&filter=&subfilters=) and /img/spacer.gif.
 */

function failDorf1MovementsLinksContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ctrl = file_get_contents($root . '/game/include/Controller/ResourceFieldsCtrl.php');
$queue = file_get_contents($root . '/game/include/Controller/OnLoadBuildingsDorfCtrl.php');
$fixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');

if ($ctrl === false || $queue === false || $fixes === false) {
    failDorf1MovementsLinksContract('Unable to read dorf1 movement sources.');
}

foreach ([
    '/build.php?gid=16&amp;tt=1&amp;filter=',
    'src="/img/spacer.gif"',
] as $needle) {
    if (!str_contains($ctrl, $needle)) {
        failDorf1MovementsLinksContract('getMovementHTML must emit gid=16 filter links, and /img/spacer.gif.');
    }
}

if (str_contains($ctrl, 'href="build.php?id=39')
    || str_contains($ctrl, 'src="img/spacer.gif"')) {
    failDorf1MovementsLinksContract('Remove relative build.php / img/spacer.gif movement links.');
}

if (str_contains($queue, 'src="img/spacer.gif"')) {
    failDorf1MovementsLinksContract('Build-queue cancel icons must use /img/spacer.gif.');
}

if (str_contains($fixes, 'table#movements div.mov')) {
    failDorf1MovementsLinksContract('Do not keep a local #movements .mov font-size override; keep the 13px size.');
}

echo "Dorf1 movements links contract passed.\n";
