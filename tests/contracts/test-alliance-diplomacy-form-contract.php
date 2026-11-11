<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$template = (string) file_get_contents($root . '/game/include/resources/Templates/alliance/diplomacy.php');
$controller = (string) file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');

foreach (['name="toAlliance"', 'name="diplomacy"', "1 => [4, 'offer a confederacy']", "2 => [2, 'offer a NAP']", "3 => [3, 'declare war']", 'id="dipl<?= $radio ?>"'] as $needle) {
    if (!str_contains($template, $needle)) {
        fwrite(STDERR, "The diplomacy form must post the reference fields: missing {$needle}\n");
        exit(1);
    }
}
foreach (["getPostInt('diplomacy')", '[4 => 1, 2 => 2, 3 => 3]', "getPostPlainText('toAlliance')"] as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "The controller must map the reference diplomacy values to the stored types: missing {$needle}\n");
        exit(1);
    }
}

echo "Alliance diplomacy form contract passed.\n";
