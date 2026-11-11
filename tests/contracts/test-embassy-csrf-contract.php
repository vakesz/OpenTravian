<?php

declare(strict_types=1);

// Embassy invitations: join and refuse change state, so they require POST plus the session
// checker; the join dialog and refuse button submit a form instead of following a GET link.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$controller = (string)file_get_contents($root . '/game/include/Controller/Build/EmbassyCtrl.php');
$template = (string)file_get_contents($root . '/game/include/resources/Templates/build/building/EmbassyJoin.php');

$guard = strpos($controller, 'private function guardInvitationRequest(): string');
if ($guard === false) {
    $fail('guardInvitationRequest() is missing.');
}
$guardBody = substr($controller, $guard, strpos($controller, 'private function handleJoin', $guard) - $guard);
foreach (['WebService::isPost()', 'Session::claimAction(', 'Session::getCheckerName()', 'isSitter()', 'banned()'] as $needle) {
    if (!str_contains($guardBody, $needle)) {
        $fail("guardInvitationRequest() must use {$needle}.");
    }
}
if (!preg_match("/\\(\\\$do === 'join' \\|\\| \\\$do === 'refuse'\\) && isset\\(\\\$_REQUEST\\['d'\\]\\)\\) \\{\\s*\\/\\/[^\\n]*\\n\\s*\\\$joinError = \\\$this->guardInvitationRequest\\(\\);\\s*if \\(\\\$joinError === ''\\)/", $controller)) {
    $fail('join and refuse must both run behind guardInvitationRequest().');
}
if (str_contains($controller, "do=join&d=") || str_contains($controller, "do=refuse&d=")) {
    $fail('GET join/refuse URLs must be removed.');
}
if (!str_contains($template, '<form id="allianceInvitationForm" method="post"')
    || !str_contains($template, 'getCheckerInput()')
    || str_contains($template, 'window.location.href')) {
    $fail('The invitation dialog must submit a POST form with the session checker.');
}
if (substr_count($template, "submitAllianceInvitation(") < 3) {
    $fail('Both Join confirmation and Refuse must use the POST form.');
}
$found = substr($controller, (int)strpos($controller, 'private function found()'));
if (strpos($found, 'banned()') === false || strpos($found, 'banned()') > strpos($found, 'WebService::isPost()')
    || !str_contains($found, 'isSitter()')) {
    $fail('Founding must reject banned players on GET and sitters on POST.');
}

echo "Embassy CSRF contract passed.\n";
