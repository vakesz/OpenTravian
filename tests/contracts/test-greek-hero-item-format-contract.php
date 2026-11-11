#!/usr/bin/env php
<?php

declare(strict_types=1);

function failGreekHeroItemFormatContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$heroItems = file_get_contents($root . '/game/include/Game/Hero/HeroItems.php');
if ($heroItems === false) {
    failGreekHeroItemFormatContract('Unable to read HeroItems.');
}
if (!str_contains($heroItems, "else if (\$type <= 12)")
    || !str_contains($heroItems, "else if (\$type <= 15)")
    || !str_contains($heroItems, "sprintf(\$result['attributes'], \$result['cav'])")
    || !str_contains($heroItems, "sprintf(\$result['attributes'], \$result['inf'])")) {
    failGreekHeroItemFormatContract('Horseman and infantry helmet titles must remain runtime-formatted.');
}

$GLOBALS['Definition'] = [];
$catalog = require $root . '/game/include/resources/Translation/el-GR.php';

$expected = [
    10 => 'Μείωση χρόνου εκπαίδευσης στον στάβλο κατά %s&#37;.',
    11 => 'Μείωση χρόνου εκπαίδευσης στον στάβλο κατά %s&#37;.',
    12 => 'Μείωση χρόνου εκπαίδευσης στον στάβλο κατά %s&#37;.',
    13 => 'Η στρατολόγηση στο στρατόπεδο μειώνεται κατά %s&#37;.',
    14 => 'Η στρατολόγηση στο στρατόπεδο μειώνεται κατά %s&#37;.',
    15 => 'Η στρατολόγηση στο στρατόπεδο μειώνεται κατά %s&#37;.',
];

foreach ($expected as $type => $copy) {
    $format = $catalog['HeroItems'][1][$type]['title'] ?? null;
    if ($format !== $copy) {
        failGreekHeroItemFormatContract("Greek helmet {$type} must retain its verified player copy and a single runtime percentage placeholder.");
    }
    try {
        $rendered = sprintf($format, 10);
    } catch (ValueError $error) {
        failGreekHeroItemFormatContract("Greek helmet {$type} title cannot be rendered: {$error->getMessage()}");
    }
    if (!str_contains($rendered, '10&#37;')) {
        failGreekHeroItemFormatContract("Greek helmet {$type} title did not render its percentage.");
    }
}

echo "Greek hero item format contract passed.\n";
