<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $vars */
$sections = '';

if (!empty($vars['resourcesEnabled'])) {
    $sections .= AdminUi::box(
        'Free resources for everyone',
        '<form method="post" action="admin.php?action=gifts">'
        . getCheckerInput()
        . '<input type="hidden" name="section" value="giftResources">'
        . AdminUi::input('wood', 'Wood', '1', ['inputmode' => 'numeric'])
        . AdminUi::input('clay', 'Clay', '1', ['inputmode' => 'numeric'])
        . AdminUi::input('iron', 'Iron', '1', ['inputmode' => 'numeric'])
        . AdminUi::input('crop', 'Crop', '1', ['inputmode' => 'numeric'])
        . AdminUi::confirmButton('Fill every village', 'Fill every village warehouse and granary to capacity?', 'green', ['name' => 'fullResources', 'value' => '1'])
        . ' '
        . AdminUi::confirmButton('Give amounts', 'Add these resource amounts to every village?', 'green')
        . '</form>'
    );
}

if (!empty($vars['goldGrantsEnabled'])) {
    $sections .= AdminUi::box(
        'Free gold for everyone',
        '<form method="post" action="admin.php?action=gifts">'
        . getCheckerInput()
        . '<input type="hidden" name="section" value="giftGold">'
        . AdminUi::input('give_gold', 'Gold', '20', ['inputmode' => 'numeric', 'maxlength' => '40'])
        . AdminUi::confirmButton('Give gold', 'Give this gold amount to every player?', 'green')
        . '</form>'
    );
    $sections .= AdminUi::box(
        'Free gold for one player',
        '<form method="post" action="admin.php?action=gifts">'
        . getCheckerInput()
        . '<input type="hidden" name="section" value="giftUidGold">'
        . AdminUi::input('giftUidGold', 'Gold', (string) ($vars['giftUidGold'] ?? ''), ['inputmode' => 'numeric', 'maxlength' => '9'])
        . AdminUi::input('giftUid', 'Player id', (string) ($vars['giftUid'] ?? ''), ['inputmode' => 'numeric', 'required' => true])
        . AdminUi::button('Give gold', 'green')
        . '</form>'
    );
}

$message = trim((string) ($vars['error'] ?? ''));
$lower = strtolower($message);
$tone = $message === ''
    ? ''
    : (str_contains($lower, 'disabled')
        || str_contains($lower, 'unavailable')
        || str_contains($lower, 'expired')
        || str_contains($lower, 'unknown')
        || str_contains($lower, 'does not exist')
        || str_contains($lower, 'enter a valid')
        ? 'error'
        : (str_contains($lower, 'gift') || str_contains($lower, 'fill') || str_contains($lower, 'granted')
            ? 'success'
            : 'error'));
$error = $tone === '' ? '' : AdminUi::notice($message, $tone);

if ($sections === '' && $error === '') {
    $sections = AdminUi::notice('Resource and gold gifts are disabled for this world.', 'info');
}

echo AdminUi::page(
    'Gifts',
    'Grant resources or gold to everyone, or gold to one player by id.',
    $error . $sections
);
