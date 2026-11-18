<?php

declare(strict_types=1);

use Game\AdminUi;
use Game\Formulas;

/** @var array $params */
$fieldOptions = [];
for ($i = 1; $i <= 12; ++$i) {
    $fieldOptions[(string) $i] = $i . ' - ' . implode('-', Formulas::getFieldTypeResourceArr($i));
}
$sectorOptions = ['ne' => 'NE', 'nw' => 'NW', 'se' => 'SE', 'sw' => 'SW'];
$notice = is_string($params['result'] ?? null) && $params['result'] !== ''
    ? AdminUi::notice($params['result'], str_contains($params['result'], 'success') ? 'success' : 'error')
    : '';

$body = $notice
    . '<form method="post" action="admin.php?action=addVillage">'
    . getCheckerInput()
    . AdminUi::input('uid', 'Player id', (string) ($params['uid'] ?? ''), ['inputmode' => 'numeric', 'required' => true])
    . AdminUi::select('fieldtype', 'Field type', $fieldOptions, (string) ($params['fieldtype'] ?? '1'))
    . AdminUi::select('sector', 'Side', $sectorOptions, (string) ($params['sector'] ?? 'ne'))
    . AdminUi::button('Add village', 'green')
    . '</form>';

echo AdminUi::page(
    'Add village',
    'Create a new village for a player in the chosen map sector and resource layout.',
    $body
);
