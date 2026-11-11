<?php

declare(strict_types=1);

function failAllianceReportsRendererContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function T(string $group, string $key): string
{
    return $key;
}

/** @param array<string, mixed> $templateVars */
function renderAllianceReports(array $templateVars, string $template): string
{
    $vars = $templateVars;
    ob_start();
    require $template;
    $output = ob_get_clean();

    if (!is_string($output)) {
        failAllianceReportsRendererContract('Alliance reports template did not render.');
    }

    return $output;
}

$root = dirname(__DIR__, 2);
$template = $root . '/game/include/resources/Templates/alliance/attacks.php';
$controller = file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
$translations = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
if (!is_file($template) || $controller === false || $translations === false) {
    failAllianceReportsRendererContract('Alliance reports renderer sources must be readable.');
}

$baseVars = [
    'aid' => 42,
    'fn' => 0,
    'filter' => -1,
    'attacks' => '',
];
$own = renderAllianceReports($baseVars + ['ownAllianceRoutes' => true], $template);
$outsider = renderAllianceReports($baseVars + ['ownAllianceRoutes' => false], $template);

if (!str_contains($own, "window.location.href = '/alliance/reports?f=1&amp;fn=0'; return false;")
    || str_contains($own, '/alliance/42/attacks?f=')) {
    failAllianceReportsRendererContract('Own-alliance report filter icons must render /alliance/reports query routes.');
}

if (!str_contains($outsider, "window.location.href = '/alliance/42/attacks?f=1&amp;fn=0'; return false;")) {
    failAllianceReportsRendererContract('Outsider-alliance report filter icons must retain their selected-alliance route.');
}

if (!str_contains($controller, "in_array((int) \$row['type'], \$attack_types_32, true)")) {
    failAllianceReportsRendererContract('Defense report rows must select their player from the individual report type.');
}

foreach ([
    "1 => 'Won as attacker without losses.'",
    "2 => 'Won as attacker with losses.'",
    "3 => 'Lost as attacker.'",
    "4 => 'Won as defender without losses.'",
    "5 => 'Won as defender with losses.'",
    "6 => 'Lost as defender with losses.'",
    "7 => 'Lost as defender without losses.'",
    "15 => 'Spying was successful and not detected.'",
    "16 => 'Spying was successful, but detected.'",
    "17 => 'Spying failed.'",
    "18 => 'Spying was stopped successfully.'",
    "19 => 'Spying could not be stopped.'",
] as $copy) {
    if (!str_contains($translations, $copy)) {
        failAllianceReportsRendererContract("Alliance report row copy must match the report icon text: {$copy}");
    }
}

echo "Alliance reports renderer contract passed.\n";
