<?php

declare(strict_types=1);

$en = (string)file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Translation/en-US.php');
$join = (string)file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/build/building/EmbassyJoin.php');

$expected = [
    ['Embassy', 'Join', 'Join'],
    ['Embassy', 'Join alliance dialog title', 'Join alliance: [NAME]'],
    ['Embassy', 'Joining cooldown note', 'After joining an alliance, there is a 24-hour cooldown before you can leave.'],
    ['Embassy', 'Villages Nearby', 'Villages Nearby'],
    ['Embassy', 'Invitations', 'Invitations'],
    ['Embassy', 'General Information', 'General Information'],
    ['Embassy', 'Leave alliance', 'Leave alliance'],
    ['Embassy', 'Please type', 'Please type:'],
    ['Embassy', 'Please type leave to confirm', 'Please type "leave" to confirm.'],
    ['Embassy', 'Leave failed', 'Could not leave the alliance. Please try again.'],
    ['Global', 'Knowledge Base', 'Knowledge Base'],
    ['inGame', 'localTime', 'Local time'],
];
foreach ($expected as [$section, $key, $text]) {
    $line = "\$Definition['$section']['$key'] = '" . str_replace("'", "\\'", $text) . "';";
    if (!str_contains($en, $line)) {
        fwrite(STDERR, "en-US.php is missing: $line\n");
        exit(1);
    }
}
if (!str_contains($join, '"Join alliance dialog title", "Join alliance: [NAME]"')) {
    fwrite(STDERR, "EmbassyJoin.php default must match the allianz.joinDialogTitle string.\n");
    exit(1);
}
echo "Embassy translation contract passed.\n";
