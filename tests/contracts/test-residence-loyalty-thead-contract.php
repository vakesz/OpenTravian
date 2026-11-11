<?php

declare(strict_types=1);

/**
 * Contract: Residence loyalty overview thead has the reference's cells: a <th> for the village column and <td> for the others.
 */

function failResidenceLoyaltyTheadContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/build/building/ResidenceLoyaltyTab.php');

if ($template === false) {
    failResidenceLoyaltyTheadContract('Unable to read ResidenceLoyaltyTab template.');
}

if (!preg_match('/<thead>\s*<tr>([\s\S]*?)<\/tr>\s*<\/thead>/', $template, $matches)) {
    failResidenceLoyaltyTheadContract('Loyalty tab must define a thead row.');
}

$theadRow = $matches[1];

if (substr_count($theadRow, '<th') !== 1 || substr_count($theadRow, '<td') !== 2) {
    failResidenceLoyaltyTheadContract('Loyalty overview thead must have one <th> (village) and two <td> cells, like the reference.');
}

foreach (['village', 'Inhabitants', 'Loyalty'] as $label) {
    if (!str_contains($theadRow, $label)) {
        failResidenceLoyaltyTheadContract('Loyalty overview thead missing label: ' . $label);
    }
}

fwrite(STDOUT, "Residence loyalty thead contract passed.\n");
