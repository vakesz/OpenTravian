<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Game/MentorSilhouettes.php';

use Game\MentorSilhouettes;

function failMentorContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$gaulReference = trim((string)file_get_contents($root . '/tests/fixtures/mentor-gaul-reference.txt'));
$template = (string)file_get_contents($root . '/game/include/resources/Templates/layout/sidebarBoxQuestmaster.php');

$expectedRaces = [1, 2, 3, 6, 7, 8, 9];
if (MentorSilhouettes::races() !== $expectedRaces) {
    failMentorContract('MentorSilhouettes must provide exactly the mentor races 1,2,3,6,7,8,9.');
}
if (MentorSilhouettes::path(3) !== $gaulReference || !str_starts_with($gaulReference, 'M0 122c1.52-7.85')) {
    failMentorContract('The Gaul path must remain the exact reference string.');
}
$seen = [];
foreach ($expectedRaces as $race) {
    $path = MentorSilhouettes::path($race);
    if (preg_match('/^M[-.\d ]+(?:[clCLsS][-.\d ]+)+(?:H0)?Z$/', $path) !== 1) {
        failMentorContract("Race $race path is not a closed SVG path.");
    }
    if (isset($seen[$path])) {
        failMentorContract("Race $race reuses another tribe's silhouette.");
    }
    $seen[$path] = true;
}
if (!str_contains($template, 'MentorSilhouettes::path($race)') || preg_match('/ d="M\d/', $template) === 1) {
    failMentorContract('The questmaster template must choose its path per tribe and embed no literal path.');
}

echo "Mentor silhouette contract passed.\n";
