<?php

declare(strict_types=1);

function failVillageNameClientContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/layout/sidebarBoxActiveVillages.php');
if ($template === false) {
    failVillageNameClientContract('Village-name sidebar template must be readable.');
}

if (!str_contains($template, "Travian.api('village/change-names'")) {
    failVillageNameClientContract('The village-name editor must use the change-names API.');
}
if (!str_contains($template, "body && body.message ? body.message : 'Request failed.'")) {
    failVillageNameClientContract('The village-name editor must display the API error message with a fallback.');
}
if (str_contains($template, 'setContent(body.errorMsg)')) {
    failVillageNameClientContract('The village-name editor must not read the retired errorMsg field.');
}

if (!str_contains($template, "}, 'PUT');") || !str_contains($template, "jQuery('.listEntry.village .name[data-did=")) {
    failVillageNameClientContract('The rename is a PUT like the reference and updates the village list without reloading the page.');
}
$handler = (string)file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/village/changeNames.php');
foreach (["T('Dorf1', 'villageNotFromUser')", "T('Global', 'errors.cant_be_empty')"] as $needle) {
    if (!str_contains($handler, $needle)) {
        failVillageNameClientContract("The rename handler must answer with the reference error text: $needle");
    }
}
foreach (["const MAX_NAME_LENGTH = 20;", "mb_substr(clean_string_from_white(strip_tags(\$entry['name'])), 0, self::MAX_NAME_LENGTH)", "T('Dorf1', 'villageNameUnavailable')", "file_get_contents('php://input')"] as $needle) {
    if (!str_contains($handler, $needle)) {
        failVillageNameClientContract("A long name is cut to 20 characters and a name with tags is refused, like the reference: $needle");
    }
}
if (str_contains($handler, 'Error: very long input')) {
    failVillageNameClientContract('The reference does not refuse a long village name.');
}
if (str_contains($handler, "['reload']")) {
    failVillageNameClientContract('A successful rename answers without a reload flag, like the reference.');
}

echo "Village-name client contract passed.\n";
