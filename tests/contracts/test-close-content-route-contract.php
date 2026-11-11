#!/usr/bin/env php
<?php

declare(strict_types=1);

function failCloseContentRoute(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$build = file_get_contents($root . '/game/include/Controller/BuildCtrl.php');
$options = file_get_contents($root . '/game/include/Controller/OptionCtrl.php');
$dorf1 = file_get_contents($root . '/game/include/Controller/ResourceFieldsCtrl.php');
$dorf2 = file_get_contents($root . '/game/include/Controller/VillageCenterCtrl.php');
$statistics = file_get_contents($root . '/game/include/Controller/StatisticsCtrl.php');
$map = file_get_contents($root . '/game/include/Controller/MapCtrl.php');
$perspective = file_get_contents($root . '/game/include/Core/Helper/VillagePerspective.php');

if ($layout === false || $build === false || $options === false || $dorf1 === false || $dorf2 === false || $statistics === false || $map === false || $perspective === false) {
    failCloseContentRoute('Unable to read close-button sources.');
}

if (!str_contains($layout, 'VillagePerspective::closeContentHref()')) {
    failCloseContentRoute('Overlay close controls must follow the stored village perspective.');
}
if (!str_contains($dorf1, 'VillagePerspective::rememberResourceView()')
    || !str_contains($dorf2, 'VillagePerspective::rememberBuildingsView()')) {
    failCloseContentRoute('Both village views must persist the close-route origin.');
}
foreach ([
    'rememberResourceView',
    'rememberBuildingsView',
    "SessionVar::setVariable(self::SESSION_KEY",
    "SessionVar::getVariable(self::SESSION_KEY, self::RESOURCE_VIEW)",
    "? '/dorf2.php'",
    ": '/dorf1.php'",
] as $required) {
    if (!str_contains($perspective, $required)) {
        failCloseContentRoute("Village perspective persistence is incomplete: {$required}");
    }
}
if (!str_contains(
    $build,
    '$this->selectedBuildingIndex <= 18 || $this->selectedBuildingIndex == 39 ? "build perspectiveResources" : "build perspectiveBuildings"'
)) {
    failCloseContentRoute('Build slots must continue to explicitly identify their resource/building perspective.');
}
if (!str_contains($options, "\$this->view->vars['showHeaderBar'] = true;")
    || !str_contains($options, "\$this->view->vars['showCloseButton'] = true;")) {
    failCloseContentRoute('Preferences must render the shared title bar and perspective-aware close control.');
}
if (str_contains($statistics . $map, "['closeContentHref']")) {
    failCloseContentRoute('Statistics and the map must follow the stored village view, not a fixed close route.');
}

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$originalSession = $_SESSION ?? [];
try {
    $_SESSION = [];
    \Core\Helper\VillagePerspective::rememberResourceView();
    if (\Core\Helper\VillagePerspective::closeContentHref() !== '/dorf1.php') {
        failCloseContentRoute('A resource-village origin must close overlays to dorf1.');
    }
    \Core\Helper\VillagePerspective::rememberBuildingsView();
    if (\Core\Helper\VillagePerspective::closeContentHref() !== '/dorf2.php') {
        failCloseContentRoute('A buildings-village origin must close overlays to dorf2.');
    }
} finally {
    $_SESSION = $originalSession;
}

echo "Close content route contract passed.\n";
