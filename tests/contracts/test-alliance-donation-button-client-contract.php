<?php

declare(strict_types=1);

$template = file_get_contents(
    dirname(__DIR__, 2) . '/game/include/resources/Templates/alliance/bonuses/donateForm.php',
);

if (!is_string($template)) {
    fwrite(STDERR, "Unable to read the alliance donation template.\n");
    exit(1);
}

if (str_contains($template, 'DOMEvent')) {
    fwrite(STDERR, "Alliance donation buttons must not require Prototype's DOMEvent.\n");
    exit(1);
}

$contracts = [
    "event.stopPropagation(); return false;} else {Travian.Game.AllianceBonus.showContributeForGoldDialog()}",
    "event.stopPropagation(); return false;} else {Travian.Game.AllianceBonus.contribute(false)}",
];
foreach ($contracts as $contract) {
    if (substr_count($template, $contract) !== 1) {
        fwrite(STDERR, "Alliance donation buttons must safely handle disabled clicks and run the reference handlers.\n");
        exit(1);
    }
}

echo "Alliance donation button client contract passed.\n";
