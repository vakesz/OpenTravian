<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $vars */
$notice = '';
if (isset($vars['success'])) {
    $notice = AdminUi::notice((string) $vars['success'], 'success');
} elseif (isset($vars['error'])) {
    $notice = AdminUi::notice((string) $vars['error'], 'error');
}

$body = $notice
    . '<form method="post" action="admin.php?action=addNatarsFarm">'
    . getCheckerInput()
    . AdminUi::input('resources_level', 'Resources level', (string) ($vars['resources_level'] ?? ''), ['type' => 'number', 'max' => '10'])
    . AdminUi::input('extra_storage', 'Extra storage count', (string) ($vars['extra_storage'] ?? ''), ['type' => 'number', 'min' => '0', 'max' => '10'])
    . AdminUi::input('village_count', 'Villages on each side', (string) ($vars['village_count'] ?? ''), ['type' => 'number', 'min' => '1'])
    . '<fieldset><legend>Sides</legend>'
    . AdminUi::checkbox('deploy_se', 'SE', !empty($vars['deploy_se']))
    . AdminUi::checkbox('deploy_sw', 'SW', !empty($vars['deploy_sw']))
    . AdminUi::checkbox('deploy_ne', 'NE', !empty($vars['deploy_ne']))
    . AdminUi::checkbox('deploy_nw', 'NW', !empty($vars['deploy_nw']))
    . '</fieldset>'
    . AdminUi::confirmButton('Add Natars farms', 'Create Natars farm villages on the selected sides?', 'green')
    . '</form>';

echo AdminUi::page(
    'Add Natars Farm',
    'Seed Natars farm villages on chosen map sides for end-game pressure.',
    $body
);
