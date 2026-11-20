#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: sending troops locks the village's unit row inside the send transaction before it
 * clamps the troops, and the debit only succeeds when every sent unit is still there. Two
 * concurrent sends of the same troops then cannot both subtract them.
 */

$root = dirname(__DIR__, 2);

function failTroopSendLock(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$send = (string)file_get_contents($root . '/game/include/Controller/RallyPoint/SendTroops.php');
$start = strpos($send, 'private function sendTroops(');
$body = $start === false ? '' : substr($send, $start, 6000);
if (!str_contains($body, '$row = $db->query("SELECT * FROM units WHERE kid=" . (int)Village::getInstance()->getKid() . " FOR UPDATE")->fetch_assoc();')) {
    failTroopSendLock('sendTroops() must read the troops it clamps with SELECT ... FOR UPDATE.');
}
if (!str_contains($send, 'if ($db->mysqli->begin_transaction()) {') || !str_contains($send, 'if ($this->sendTroops($a2b)) {')) {
    failTroopSendLock('sendTroops() must run inside the send transaction.');
}

$units = (string)file_get_contents($root . '/game/include/Model/Units.php');
if (!str_contains($units, '$enough[] = "u{$i}>=" . (int) $units[$i];')
    || !str_contains($units, "\" WHERE kid=\$kid AND \" . implode(' AND ', \$enough)) && \$db->affectedRows() === 1;")) {
    failTroopSendLock('Units::modifyUnits() must only subtract troops that are all still present.');
}

echo "Troop send lock contract passed.\n";
