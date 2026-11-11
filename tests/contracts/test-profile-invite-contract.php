<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
function failProfileInviteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$routes = require $root . '/game/deployment/include/api_legacy_routes.php';
if (($routes['routes']['alliance/invite'] ?? null) !== ['alliance', 'invite']) {
    failProfileInviteContract('alliance/invite must be a routed browser endpoint.');
}

$actions = $read('game/include/Game/ProfileActions.php');
foreach ([
    'AllianceModel::INVITE_PLAYER',
    '!$session->isSitter()',
    "'SELECT aid FROM users WHERE id=?'",
    "getGame('disallowAllianceInviteAfterWWRelease')",
    "T('Alliance', 'sentInvitation')",
] as $needle) {
    if (!str_contains($actions, $needle)) {
        failProfileInviteContract("The profile invite rules are missing {$needle}.");
    }
}

$ajax = $read('game/include/Controller/Ajax/alliance/invite.php');
if (!str_contains($ajax, 'ProfileActions::invite($target)') || !str_contains($ajax, '->sendInvite(')) {
    failProfileInviteContract('The invite endpoint must re-check the rights and use the existing invite flow.');
}

$buttons = $read('game/include/resources/Templates/profile/actionButtons.php');
$profile = $read('game/include/resources/Templates/profile/Profile.php');
if (!str_contains($buttons, 'buttonFramed invite rectangle withIcon green') || !str_contains($profile, "Travian.api('alliance/invite'")) {
    failProfileInviteContract('The profile must render the Invite button and post it to alliance/invite.');
}
if (!str_contains($read('game/include/resources/Translation/en-US.php'), "['Alliance']['sentInvitation'] = 'An invitation has been sent to %s. To revoke this, go to the %sinvitation page%s'")) {
    failProfileInviteContract('The sent-invitation notice must use the reference wording.');
}

echo "Profile invite contract passed.\n";
