<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$action = (string) ($params['action'] ?? 'publicInfobox');
$isPrivate = $action === 'privateInfobox';
$title = $isPrivate ? 'Private info box' : 'Public info box';
$lead = $isPrivate
    ? 'Shows the same notice inside every player\'s info box on this world. HTML is allowed.'
    : 'Shows the same notice inside every player\'s info box on every running world. HTML is allowed.';

$form = '<form method="post" action="admin.php?action=' . AdminUi::esc($action) . '">'
    . getCheckerInput()
    . AdminUi::input('expire', 'Expires', (string) ($params['expire'] ?? ''), [], 'Local time, for example 2026-11-11 13:35.')
    . AdminUi::textarea('html', 'Content (HTML)', (string) ($params['html'] ?? ''), ['rows' => 16])
    . AdminUi::button('Save', 'green')
    . '</form>';

$list = '<table class="adminTable"><thead><tr>'
    . '<th>Content</th><th>Expires</th><th>Action</th>'
    . '</tr></thead><tbody>'
    . (string) ($params['content'] ?? '')
    . '</tbody></table>';

echo AdminUi::page($title, $lead, $form . AdminUi::box('Current info boxes', $list));
