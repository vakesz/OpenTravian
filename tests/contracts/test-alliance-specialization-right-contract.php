<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/include/Model/AllianceModel.php';

use Model\AllianceModel;

$root = dirname(__DIR__, 2);
function failAllianceSpecializationContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (AllianceModel::MANAGE_SPECIALIZATION !== 256) {
    failAllianceSpecializationContract('The specialization right is permission bit 256.');
}
$all = (new ReflectionClassConstant(AllianceModel::class, 'ALL_RIGHTS'))->getValue();
if (($all & AllianceModel::MANAGE_SPECIALIZATION) === 0 || $all !== 511) {
    failAllianceSpecializationContract('Founders must hold every right, specialization included (511).');
}

$controller = (string) file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
$template = (string) file_get_contents($root . '/game/include/resources/Templates/alliance/optionAssignPosition.php');
$schema = (string) file_get_contents($root . '/database/world.sql');
foreach ([
    [$controller, "AllianceModel::MANAGE_SPECIALIZATION;\n                    }", 'saving the right'],
    [$controller, '[e10]', 'rendering the checkbox'],
    [$template, 'allyRightSpecialization', 'the header icon'],
    [$template, 'Manage members specializations', 'the reference tooltip'],
    [$schema, '256 specializations', 'the schema comment'],
] as [$source, $needle, $what]) {
    if (!str_contains($source, $needle)) {
        failAllianceSpecializationContract("The rights form is missing {$what}.");
    }
}

echo "Alliance specialization right contract passed.\n";
