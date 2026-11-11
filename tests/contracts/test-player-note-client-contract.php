<?php

declare(strict_types=1);

function failPlayerNoteClientContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$profile = file_get_contents($root . '/game/include/resources/Templates/profile/Profile.php');
$standardBundle = file_get_contents($root . '/game/deployment/public/game-client.js');

if (in_array(false, [$profile, $standardBundle], true)) {
    failPlayerNoteClientContract('Player-note sources must be readable.');
}

if (
    str_contains($profile, 'affectedPlayerID: 6185')
    || !str_contains($profile, 'affectedPlayerID: <?= (int) $vars["uid"] ?>')
) {
    failPlayerNoteClientContract('Profile notes must target the viewed player rather than a fixed account.');
}

foreach ([$standardBundle] as $bundle) {
    if (!str_contains($bundle, 'jQuery("#playerNotePopupError").text(e.message || "");')) {
        failPlayerNoteClientContract('Player-note failures must be rendered in the open note dialog.');
    }
}

echo "Player-note client contract passed.\n";
