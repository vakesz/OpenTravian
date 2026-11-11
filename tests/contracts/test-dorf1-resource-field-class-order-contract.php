<?php

declare(strict_types=1);

/**
 * Contract: dorf1 resource-field anchors use class token order:
 * {color} level colorLayer resourceField gid{N} buildingSlot{N} level{L} (+ underConstruction).
 */

function failDorf1ResourceFieldClassOrderContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$dorf1 = file_get_contents($root . '/game/include/resources/Templates/resource-fields/main.php');
if ($dorf1 === false) {
    failDorf1ResourceFieldClassOrderContract('Unable to read resource-fields/main.php.');
}

$expected = 'class="<?=$map[\'color\']; ?> level colorLayer resourceField gid<?=$map[\'item_id\']; ?> buildingSlot<?=$map_index; ?> level<?=$map[\'level\']; ?><?=$map[\'upgradeState\'] ? \' underConstruction\' : \'\'; ?>"';

if (!str_contains($dorf1, $expected)) {
    failDorf1ResourceFieldClassOrderContract('dorf1 resource field class attribute must match token order.');
}

if (!str_contains($dorf1, 'data-aid="<?=$map_index; ?>"')
    || !str_contains($dorf1, 'data-gid="<?=$map[\'item_id\']; ?>"')
    || !str_contains($dorf1, 'class="labelLayer"')) {
    failDorf1ResourceFieldClassOrderContract('Resource fields must keep data-aid/data-gid and labelLayer.');
}

if (str_contains($dorf1, 'class="buildingSlot<?=$map_index; ?> colorLayer gid')) {
    failDorf1ResourceFieldClassOrderContract('Legacy buildingSlot-first class order must not remain.');
}

fwrite(STDOUT, "Dorf1 resource-field class-order contract passed.\n");
