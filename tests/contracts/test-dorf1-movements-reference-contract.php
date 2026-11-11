<?php

declare(strict_types=1);

// Contract: the dorf1 troop movement box has the reference markup (one table inside div.villageInfobox, no
// inner div, an image title per movement type, `Attack` or `Attacks` by count, a timer with only a value
// attribute) and the production boost button carries the reference's isolated "+25%" label as its value.

$root = dirname(__DIR__, 2);
$template = (string)file_get_contents($root . '/game/include/resources/Templates/resource-fields/movements.php');
$controller = (string)file_get_contents($root . '/game/include/Controller/ResourceFieldsCtrl.php');
$catalog = (string)file_get_contents($root . '/game/include/resources/Translation/en-US.php');

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (str_contains($template, '<div class="movements">') || !str_contains($template, '<table id="movements" cellspacing="1" cellpadding="1">')) {
    $fail('The movement box must be one table directly inside div.villageInfobox.movements.');
}
foreach ([
    'title="{$title}"',
    "T('Dorf1', 'movementTitles.'",
    "(int)\$count === 1 ? 'movements.attack' : 'movements.attacks'",
    "\"value\" => \$boostLabel",
    '"\\u{202D}+\\u{202D}25\\u{202C}%\\u{202C}"',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        $fail("ResourceFieldsCtrl must contain: $needle");
    }
}
if (str_contains($controller, 'data-value="{$seconds}"')) {
    $fail('The movement timer carries only a value attribute, like the reference.');
}
foreach ([
    "['Dorf1']['movements']['attack'] = 'Attack';",
    "['Dorf1']['movements']['attacks'] = 'Attacks';",
    "['Dorf1']['movementTitles']['att1'] = 'Arriving attacking Troops';",
    "['Dorf1']['movementTitles']['settler'] = 'Own settlers on the way';",
] as $needle) {
    if (!str_contains($catalog, $needle)) {
        $fail("en-US is missing: $needle");
    }
}

echo "Dorf1 movements reference contract passed.\n";
