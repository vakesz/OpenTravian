<?php

declare(strict_types=1);

function failCombatSimulatorLocalizationContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/combat/main.php');
if ($template === false) {
    failCombatSimulatorLocalizationContract('Unable to read the combat simulator template.');
}

if (!str_contains($template, "json_encode(T('Troops', 'hero.title')")) {
    failCombatSimulatorLocalizationContract('The simulator hero editor must use the canonical translated troop label.');
}

if (str_contains($template, "title.textContent = 'Hero'")) {
    failCombatSimulatorLocalizationContract('The simulator hero editor must not hard-code English Hero text.');
}

foreach (['ar-AE.php', 'el-GR.php', 'en-US.php', 'fa-IR.php'] as $locale) {
    $translation = file_get_contents($root . '/game/include/resources/Translation/' . $locale);
    if ($translation === false || !str_contains($translation, "\$Definition['Troops']['hero']['title']")) {
        failCombatSimulatorLocalizationContract("The {$locale} troop translation must provide the hero title.");
    }
}

echo "Combat simulator localization contract passed.\n";
