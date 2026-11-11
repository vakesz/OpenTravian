<?php

declare(strict_types=1);

/**
 * Contract: marketplace send composer uses field names (lumber/clay/iron/crop),
 * nameless village input, and deliveries radios without value=1|2|3.
 */

function failMarketplaceSendFormNamesContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/build/building/MarketplaceSendResources.php');
$prepare = file_get_contents($root . '/game/include/Controller/Ajax/marketplace/prepare.php');

if ($template === false || $prepare === false) {
    failMarketplaceSendFormNamesContract('Unable to read marketplace send sources.');
}

if (!str_contains($template, "\$resourceFieldNames = ['lumber', 'clay', 'iron', 'crop']")
    || !str_contains($template, 'name="<?= $fieldName; ?>"')) {
    failMarketplaceSendFormNamesContract('Send form must emit lumber/clay/iron/crop resource input names.');
}

foreach (['name="r1"', 'name="r2"', 'name="r3"', 'name="r4"', 'name="dname"', 'id="x2"', 'name="x2"'] as $legacy) {
    if (str_contains($template, $legacy)) {
        failMarketplaceSendFormNamesContract("Send form still uses legacy field {$legacy}.");
    }
}

if (preg_match('/<input[^>]*id="enterVillageName"[^>]*\sname=/', $template) === 1) {
    failMarketplaceSendFormNamesContract('Village name input must be nameless.');
}

if (!str_contains($template, 'id="enterVillageName"')
    || !str_contains($template, 'data-repeat="')
    || !str_contains($template, "attr('data-repeat')")
    || !str_contains($template, 'resourcePayload()')
    || !str_contains($template, 'lumber: state.amounts[0]')) {
    failMarketplaceSendFormNamesContract('Send composer JS must post lumber/clay/iron/crop and read data-repeat.');
}

if (!str_contains($prepare, "['lumber', 'clay', 'iron', 'crop']")
    || str_contains($prepare, "['r1', 'r2', 'r3', 'r4']")) {
    failMarketplaceSendFormNamesContract('prepare API must accept lumber/clay/iron/crop only.');
}

echo "Marketplace send form names contract passed.\n";
