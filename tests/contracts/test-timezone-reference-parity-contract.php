#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Config;
use Core\Helper\TimezoneHelper;

function failTimezoneReferenceParity(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$config = Config::getInstance();
$capturedAt = (new DateTimeImmutable('2026-11-06T01:17:00+00:00'))->getTimestamp();

foreach ([
    'America/Adak' => '(UTC-10:00) Adak',
    'America/Anchorage' => '(UTC-09:00) Anchorage, Juneau, Metlakatla, Nome, Sitka, Yakutat',
    'America/Nassau' => '(UTC-05:00) Detroit, Havana, New York, Toronto',
    'Atlantic/Bermuda' => '(UTC-04:00) Bermuda',
    'America/Halifax' => '(UTC-04:00) Glace Bay, Goose Bay, Halifax, Moncton, Thule',
    'America/St_Johns' => '(UTC-03:30) St Johns',
    'America/Miquelon' => '(UTC-03:00) Miquelon',
    'Antarctica/Troll' => '(UTC) Troll',
] as $timezone => $expected) {
    $sourceLabel = $config->timezones[$timezone] ?? null;
    if (!is_string($sourceLabel)) {
        failTimezoneReferenceParity("Missing configured zone {$timezone}.");
    }
    $actual = TimezoneHelper::timezoneOptionLabel($timezone, $sourceLabel, $capturedAt);
    if ($actual !== $expected) {
        failTimezoneReferenceParity("{$timezone}: expected {$expected}, got {$actual}.");
    }
}

$optionsTemplate = file_get_contents($root . '/game/include/resources/Templates/options/Game.php');
$gameView = file_get_contents($root . '/game/include/resources/View/GameView.php');
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
if ($optionsTemplate === false || $gameView === false || $layout === false) {
    failTimezoneReferenceParity('Unable to read time-zone UI sources.');
}
if (!str_contains($optionsTemplate, 'TimezoneHelper::timezoneOptions(') || !str_contains((string) file_get_contents($root . '/game/include/Core/Helper/TimezoneHelper.php'), 'self::timezoneOptionLabel(')) {
    failTimezoneReferenceParity('The options select must render dynamic IANA UTC prefixes.');
}
if (!str_contains($gameView, '$this->vars[\'dateTime\'] = time();')) {
    failTimezoneReferenceParity('GameView must keep an absolute server-clock timestamp.');
}
if (!str_contains($layout, " : gmdate('H:i:s');")
    || !str_contains($layout, "TimezoneHelper::date('H:i:s')")) {
    failTimezoneReferenceParity('The HUD must distinguish server UTC from explicitly enabled local time.');
}

echo "Timezone reference parity contract passed.\n";
