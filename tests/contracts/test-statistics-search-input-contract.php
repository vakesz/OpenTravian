<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/StatisticsCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/statistics/table.php');

if ($controller === false || $template === false) {
    fwrite(STDERR, "Could not read the statistics search sources.\n");
    exit(1);
}

if (str_contains($controller, 'FILTER_SANITIZE_STRING')
    || !str_contains($controller, "return is_string(\$name) ? trim(strip_tags(\$name)) : '';")) {
    fwrite(STDERR, "Statistics searches must safely normalize scalar names without deprecated filters.\n");
    exit(1);
}

if (!str_contains($controller, "return htmlspecialchars(\$name, ENT_QUOTES, 'UTF-8');")
    || preg_match('/htmlspecialchars\\(\\(string\\)\\s*\\$vars\\[\'selectedName\'\\], ENT_QUOTES, \'UTF-8\'\\)/', $template) !== 1
    || preg_match('/htmlspecialchars\\(\\(string\\)\\s*\\$vars\\[\'selectedRank\'\\], ENT_QUOTES, \'UTF-8\'\\)/', $template) !== 1) {
    fwrite(STDERR, "Statistics search values must be escaped at every reflected HTML boundary.\n");
    exit(1);
}

echo "Statistics search input contract passed.\n";
