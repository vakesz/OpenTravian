#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: a free oasis is taken by one won raid or one won normal attack, within the
 * 7x7 square around the village (map wrap included), and starts at loyalty 100. The
 * send form offers the normal attack against free oases.
 */

$root = dirname(__DIR__, 2);

function failOasisCaptureRules(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$battle = (string)file_get_contents($root . '/game/include/Model/BattleModel.php');
$start = strpos($battle, 'private function handleOasisCapture(');
$end = $start === false ? false : strpos($battle, 'private function handleArtefactCapture(', $start);
if ($start === false || $end === false) {
    failOasisCaptureRules('BattleModel::handleOasisCapture was not found.');
}
$capture = substr($battle, $start, $end - $start);

if (!str_contains($capture, "abs(Formulas::delta(\$to['x'], \$from['x'])) <= 3 && abs(Formulas::delta(\$to['y'], \$from['y'])) <= 3")
    || str_contains($capture, 'getDistance(')) {
    failOasisCaptureRules('The capture range must be the 7x7 square (per-axis wrap-around delta of at most 3).');
}
$raid = strpos($capture, 'MovementsModel::ATTACKTYPE_RAID');
$raidCapture = $raid === false ? false : strpos($capture, 'OasesModel::captureOasis(', $raid);
$normal = $raid === false ? false : strpos($capture, '} else {', $raid);
if ($raid === false || $raidCapture === false || $normal === false || $raidCapture > $normal) {
    failOasisCaptureRules('A won raid must capture a free oasis.');
}
if (!str_contains($capture, "(int)\$oasis['did'] === 0\n                                ? \$oasis['loyalty']")) {
    failOasisCaptureRules('One won normal attack must take a free oasis (its whole loyalty in one step).');
}

$oases = (string)file_get_contents($root . '/game/include/Model/OasesModel.php');
if (!str_contains($oases, 'owner=$uid, loyalty=100, conquered_time=')) {
    failOasisCaptureRules('A captured oasis must start at loyalty 100.');
}

$send = (string)file_get_contents($root . '/game/include/Controller/RallyPoint/SendTroops.php');
if (substr_count($send, 'MovementsModel::ATTACKTYPE_NORMAL => ["disabled" => true') !== 0) {
    failOasisCaptureRules('The send form and sendTroops() must allow a normal attack against a free oasis.');
}

echo "Oasis capture rules contract passed.\n";
