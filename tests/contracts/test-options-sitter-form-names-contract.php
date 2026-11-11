<?php

declare(strict_types=1);

/**
 * Contract: Options Sitter tab form field names match reference DOM.
 */

function failOptionsSitterFormNamesContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/options/Sitters.php');
$ctrl = file_get_contents($root . '/game/include/Controller/OptionCtrl.php');
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
if ($template === false || $ctrl === false || $translation === false) {
    failOptionsSitterFormNamesContract('Unable to read Options Sitter sources.');
}

foreach ([
    'name="sitter[0][name]"',
    'name="sitter[1][name]"',
    'name="sitter[0][id]"',
    'name="sitter[1][id]"',
    'name="sitter[0][flags][]"',
    'name="sitter[1][flags][]"',
    'class="textButtonV1 green "',
] as $fragment) {
    if (!str_contains($template, $fragment)) {
        failOptionsSitterFormNamesContract("Sitters template missing fragment: {$fragment}");
    }
}

if (!str_contains($template, '#sitterName0') || !str_contains($template, '#sitterName1')) {
    failOptionsSitterFormNamesContract('Sitters cloneName must target #sitterName0 / #sitterName1.');
}

if (!str_contains($template, 'obj.value.replace(/<\\/?[^>]+(>|$)/g, "")')) {
    failOptionsSitterFormNamesContract('Sitters cloneName must strip markup before mirroring the input preview.');
}

foreach ([
    "\$Definition['Options']['sitter'] = 'sitter';",
    "\$Definition['Options']['no entry'] = 'no entry';",
    "\$Definition['Options']['This player is sitter for 2 players'] = 'This player is already sitting two avatars.';",
] as $fragment) {
    if (!str_contains($translation, $fragment)) {
        failOptionsSitterFormNamesContract("Sitters translation must match copy: {$fragment}");
    }
}

foreach ([
    'name="sitter_flag[0][]"',
    'name="sitter_flag[-1][]"',
    'name="sitter_flag[][]"',
    'sitter_flag_posted',
    'name="sitter[-1]"',
    'button-container addHoverClick',
] as $fragment) {
    if (str_contains($template, $fragment)) {
        failOptionsSitterFormNamesContract("Sitters template still uses legacy fragment: {$fragment}");
    }
}

if (preg_match('/name="sitter\\[[01]\\]"/', $template) === 1) {
    failOptionsSitterFormNamesContract('Sitters must use sitter[N][name], not bare sitter[N].');
}

if (!str_contains($ctrl, 'mergeSitter(0, 1)') || !str_contains($ctrl, 'mergeSitter(1, 2)')) {
    failOptionsSitterFormNamesContract('OptionCtrl must merge sitter[0]/sitter[1] slots.');
}

if (!str_contains($ctrl, "\$posted['flags']") || str_contains($ctrl, "\$_POST['sitter_flag']")) {
    failOptionsSitterFormNamesContract('OptionCtrl must read sitter[N][flags][] and drop legacy sitter_flag.');
}

if (!str_contains($ctrl, "Session::matchesChecker(\$_POST['a'] ?? null)")
    || !str_contains($ctrl, "Session::matchesChecker(\$_GET['a'] ?? null)")) {
    failOptionsSitterFormNamesContract('Sitter changes and removals must validate the current native a checker.');
}

echo "Options Sitter form names contract passed.\n";
