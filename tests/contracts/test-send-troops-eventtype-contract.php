<?php

declare(strict_types=1);

/**
 * Contract: send-troops form uses eventType + troop[tN] only
 * (no legacy c= / tN= field names).
 */

function failSendTroopsEventTypeContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sendTpl = file_get_contents($root . '/game/include/resources/Templates/rallypoint/sendTroops.php');
$prepareTpl = file_get_contents($root . '/game/include/resources/Templates/rallypoint/prepareSend.php');
$ctrl = file_get_contents($root . '/game/include/Controller/RallyPoint/SendTroops.php');
$english = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

foreach ([$sendTpl, $prepareTpl, $ctrl, $english] as $source) {
    if ($source === false) {
        failSendTroopsEventTypeContract('Unable to read send-troops sources.');
    }
}

if (!str_contains(
    $english,
    "\$Definition['RallyPoint']['Errors']['heroDeployError'] = 'You can only move your Hero in a reinforcement. Note that the target village must have a Rally Point.';"
)) {
    failSendTroopsEventTypeContract('Hero-home validation must preserve the canonical Rally Point wording.');
}

foreach ([
    'name="eventType"',
    'value="5"',
    'action="/build.php?gid=16&amp;tt=2"',
    "'id' => 'ok'",
] as $needle) {
    if (!str_contains($sendTpl, $needle)) {
        failSendTroopsEventTypeContract('sendTroops template must post eventType / gid=16 / #ok.');
    }
}

// Changing the hero's home village is only a form field. It must not be a
// link or client navigation control that can resolve through the shared base
// to the login front controller.
if (preg_match(
    '/<input class="redeployHeroSetting check".*?name="redeployHero" value="1"\/>/s',
    $sendTpl,
    $heroHomeControl
) !== 1
    || !str_contains($heroHomeControl[0], 'type="checkbox"')
    || str_contains($heroHomeControl[0], 'href=')
    || str_contains($heroHomeControl[0], 'onclick=')) {
    failSendTroopsEventTypeContract(
        'The change-hero-home control must remain a non-navigating checkbox in the root-absolute send form.'
    );
}

foreach (['name="c"', 'name="snd"', 'btn_ok', "name' => 's1'", 'name="t1"'] as $legacy) {
    if (str_contains($sendTpl, $legacy) || preg_match('/name="t\d+"/', $sendTpl) === 1) {
        failSendTroopsEventTypeContract('sendTroops template must not keep legacy form fields or name="snd".');
    }
}

foreach ([
    'name="eventType"',
    'name="troop[t1]"',
    'name="troop[t11]"',
    "'id' => 'ok'",
] as $needle) {
    if (!str_contains($prepareTpl, $needle)) {
        failSendTroopsEventTypeContract('prepareSend must post eventType, troop[tN], and #ok.');
    }
}

foreach (['name="c"', 'btn_ok', "name' => 's1'", 'name="t1"'] as $legacy) {
    if (str_contains($prepareTpl, $legacy) || preg_match('/name="t\d+"/', $prepareTpl) === 1) {
        failSendTroopsEventTypeContract('prepareSend must not keep legacy c=/tN=/s1/btn_ok.');
    }
}

$html = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPointHTML.php');
$rally = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPoint.php');
$base = file_get_contents($root . '/game/include/resources/Templates/rallypoint/baseSend.php');
if ($html === false || $rally === false || $base === false) {
    failSendTroopsEventTypeContract('Unable to read related rally-point sources.');
}

if (!str_contains($html, "name=\"troop[t'")
    || str_contains($html, "name=\"t[")) {
    failSendTroopsEventTypeContract('RallyPointHTML editable units must use troop[tN] only.');
}

if (str_contains($rally, "\$_POST['t']")
    || str_contains($rally, '"btn_ok"')
    || str_contains($rally, '"s1"')) {
    failSendTroopsEventTypeContract('RallyPoint send-home/settlers must not keep legacy t[] / s1 / btn_ok.');
}

if (str_contains($base, 'name="snd"')
    || str_contains($base, 'id=39')
    || !str_contains($base, 'action="/build.php?gid=16&amp;tt=2')) {
    failSendTroopsEventTypeContract('baseSend must post to /build.php?gid=16&tt=2 without name="snd".');
}

foreach ([
    "troop[t",
    'function readPostedAttackType',
    'function readPostedTroopCounts',
    "\$_REQUEST['eventType']",
    "\$_REQUEST['troop']",
] as $needle) {
    if (!str_contains($ctrl, $needle)) {
        failSendTroopsEventTypeContract('Controller must read eventType / troop[tN] only.');
    }
}

if (str_contains($ctrl, "isset(\$_REQUEST['c'])")
    || str_contains($ctrl, "isset(\$_REQUEST['t' . \$i])")
    || str_contains($ctrl, "\$_REQUEST['t' . \$i] =")
    || str_contains($ctrl, 'document.snd')) {
    failSendTroopsEventTypeContract('Remove legacy c=/tN=/document.snd from sendTroops controller.');
}

if (!str_contains($ctrl, "jQuery(\\'table#troops\\').find(\\'input[name=")
    || !str_contains($ctrl, ".trigger(\\'focus\\'); return false")
    || !str_contains($ctrl, '<a href="/build.php?gid=16&amp;tt=2" onclick="')
    || str_contains($ctrl, '<a href="#" onclick="')
    || str_contains($ctrl, 'onclick="this.form.elements')) {
    failSendTroopsEventTypeContract(
        'Rally Point quantity links must fill their input like the reference and have a root-absolute fallback.'
    );
}

echo "Send troops eventType contract passed.\n";
