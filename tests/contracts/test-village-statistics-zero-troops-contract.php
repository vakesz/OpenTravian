#!/usr/bin/env php
<?php

declare(strict_types=1);

function failZeroTroopsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2) . '/game/include';
$controller = file_get_contents($root . '/Controller/VillageStatisticsCtrl.php');
$template = file_get_contents($root . '/resources/Templates/village-statistics/vil_troops.php');
if ($controller === false || $template === false) {
    failZeroTroopsContract('Village-statistics troop sources must be readable.');
}

foreach ([
    "'hasUnits' => (bool) array_filter(",
    'static fn($amount): bool => (int)$amount > 0',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        failZeroTroopsContract("VillageStatisticsCtrl must expose the zero-support-troop state ({$needle}).");
    }
}

foreach ([
    "<?php \$hasUnits = !empty(\$vars['hasUnits']); ?>",
    '<?php if ($hasUnits): ?>',
    '<td colspan="<?= $hasUnits ? 12 : 11; ?>" class="empty"></td>',
    '<td colspan="11">',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failZeroTroopsContract("The troop template must keep the compact zero-state branch ({$needle}).");
    }
}

function T(string $group, string $key): string
{
    return $key;
}

function nrToUnitId(int $unit, int $race): string
{
    return 'u' . $unit;
}

function number_format_x(int $value): string
{
    return (string)$value;
}

/** @param array<string, mixed> $vars */
function renderZeroTroopsTemplate(string $templatePath, array $vars): string
{
    ob_start();
    require $templatePath;
    return (string)ob_get_clean();
}

$zero = renderZeroTroopsTemplate($root . '/resources/Templates/village-statistics/vil_troops.php', [
    'kid' => 1,
    'name' => 'Empty village',
    'race' => 1,
    'units' => array_fill(1, 11, 0),
    'hasUnits' => false,
    'upkeep' => 0,
]);
if (str_contains($zero, '<th>Village</th>')
    || str_contains($zero, '<th>Troops</th>')
    || str_contains($zero, '<th>Upkeep</th>')
    || substr_count($zero, 'class="none">0</td>') !== 11
    || !str_contains($zero, '<td colspan="11" class="empty"></td>')
    || preg_match('~<tbody class="upkeep">\s*<tr>\s*<td colspan="11">~s', $zero) !== 1) {
    failZeroTroopsContract('Zero support troops must render the compact eleven-column, unlabeled state.');
}

$populated = renderZeroTroopsTemplate($root . '/resources/Templates/village-statistics/vil_troops.php', [
    'kid' => 1,
    'name' => 'Populated village',
    'race' => 1,
    'units' => [1 => 1] + array_fill(2, 10, 0),
    'hasUnits' => true,
    'upkeep' => 1,
]);
if (!str_contains($populated, '<th>Village</th>')
    || !str_contains($populated, '<th>Troops</th>')
    || !str_contains($populated, '<th>Upkeep</th>')
    || !str_contains($populated, '<td colspan="12" class="empty"></td>')) {
    failZeroTroopsContract('Populated support troops must retain the labelled twelve-column layout.');
}

echo "Village statistics zero-troops contract passed.\n";
