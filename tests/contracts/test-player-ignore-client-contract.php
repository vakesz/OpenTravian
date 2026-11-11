<?php

declare(strict_types=1);

function failPlayerIgnoreClientContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$ignoreList = file_get_contents($root . '/game/include/resources/Templates/messages/ignoreList.php');
$profile = file_get_contents($root . '/game/include/resources/Templates/profile/Profile.php');

if ($ignoreList === false || $profile === false) {
    failPlayerIgnoreClientContract('Player-ignore templates must be readable.');
}

foreach ([$ignoreList, $profile] as $template) {
    if (!str_contains($template, 'new Travian.Dialog.Dialog({preventFormSubmit: true})')
        || !str_contains($template, '.setContent(error.message)')
        || !str_contains($template, '.show();')) {
        failPlayerIgnoreClientContract('Player-ignore request failures must be shown in an in-game dialog.');
    }
}

if (substr_count($ignoreList, 'error: showIgnoreListError') !== 2
    || substr_count($profile, 'error: showProfileActionError') !== 3) {
    failPlayerIgnoreClientContract('Every player-ignore request must handle rejected responses.');
}

echo "Player-ignore client contract passed.\n";
