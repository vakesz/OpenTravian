<?php

declare(strict_types=1);

/**
 * Contract: Treasury artefact list theads use <th> (Loyalty/Expansion pattern).
 */

function failTreasuryTheadContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$paths = [
    'TreasuryStoredArtifact' => $root . '/game/include/resources/Templates/build/building/TreasuryStoredArtifact.php',
    'TreasuryArtTable' => $root . '/game/include/resources/Templates/build/building/TreasuryArtTable.php',
];

foreach ($paths as $label => $path) {
    $template = file_get_contents($path);
    if ($template === false) {
        failTreasuryTheadContract("Unable to read {$label}.");
    }
    if (!preg_match('/<thead>\s*<tr>([\s\S]*?)<\/tr>\s*<\/thead>/', $template, $matches)) {
        failTreasuryTheadContract("{$label} must define a thead row.");
    }
    if (preg_match('/<td\b/', $matches[1])) {
        failTreasuryTheadContract("{$label} thead must not contain <td> cells.");
    }
    if (!str_contains($matches[1], '<th')) {
        failTreasuryTheadContract("{$label} thead must use <th> cells.");
    }
}

fwrite(STDOUT, "Treasury thead contract passed.\n");
