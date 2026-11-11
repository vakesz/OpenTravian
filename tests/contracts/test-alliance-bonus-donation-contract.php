<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/game/include/Model/AllianceBonusModel.php';
require dirname(__DIR__, 2) . '/game/include/Game/AllianceBonus/AllianceDonation.php';

use Game\AllianceBonus\AllianceDonation;
use Model\AllianceBonusModel;

function assertAllianceDonationValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

assertAllianceDonationValue(
    AllianceBonusModel::TYPE_TRAINING,
    AllianceDonation::typeFor('TroopProductionSpeed'),
    'Training donations must map to the training alliance bonus.'
);
assertAllianceDonationValue(
    AllianceBonusModel::TYPE_CP,
    AllianceDonation::typeFor('CPProduction'),
    'Culture-point donations must map to the culture-point alliance bonus.'
);
assertAllianceDonationValue(
    AllianceBonusModel::TYPE_ARMOR,
    AllianceDonation::typeFor('SmithyPower'),
    'Smithy donations must map to the armour alliance bonus.'
);
assertAllianceDonationValue(
    AllianceBonusModel::TYPE_TRADE,
    AllianceDonation::typeFor('MerchantCapacity'),
    'Merchant donations must map to the trade alliance bonus.'
);
assertAllianceDonationValue(
    ['bid' => 'SmithyPower', 'did' => 7, 'r1' => 1, 'r2' => 2, 'r3' => 3, 'r4' => 4],
    AllianceDonation::parametersFromRequest(['bonus' => 'SmithyPower', 'resources' => ['lumber' => 1, 'clay' => 2, 'iron' => 3, 'crop' => 4]], 7),
    'The reference request body must map onto the donation parameters.'
);
assertAllianceDonationValue(null, AllianceDonation::typeFor('unknown'), 'Unknown bonus identifiers must be rejected.');
assertAllianceDonationValue(
    [1, 2, 3, 4],
    AllianceDonation::resourcesFor(['r1' => '1', 'r2' => '2', 'r3' => '3', 'r4' => '4']),
    'A complete non-negative resource payload must be accepted.'
);
assertAllianceDonationValue(
    null,
    AllianceDonation::resourcesFor(['r1' => '1', 'r2' => '-2', 'r3' => '3', 'r4' => '4']),
    'Negative resources must be rejected.'
);
assertAllianceDonationValue(
    null,
    AllianceDonation::resourcesFor(['r1' => '1e2', 'r2' => '0', 'r3' => '0', 'r4' => '0']),
    'Scientific notation must not bypass resource validation.'
);

$model = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/AllianceBonusModel.php');
if ($model === false
    || !str_contains($model, 'qst11=LEAST(FLOOR((alliance_contribution+?)/?), 6)')
    || !str_contains($model, 'WHERE id=? AND aid=?')) {
    fwrite(STDERR, "Alliance donations must advance daily quest 11 and update only the donating member.\n");
    exit(1);
}

foreach (['bonusContribute.php', 'bonusFormGold.php', 'bonusFormScaleDown.php', 'bonusOverview.php'] as $controller) {
    $path = dirname(__DIR__, 2) . '/game/include/Controller/Ajax/alliance/' . $controller;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing alliance donation controller: {$controller}\n");
        exit(1);
    }
}

echo "Alliance bonus donation contract passed.\n";
