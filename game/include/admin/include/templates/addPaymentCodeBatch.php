<?php

declare(strict_types=1);

use Game\AdminUi;

/** @var array $params */
$isGift = !empty($params['isGift']);
$action = $isGift ? 'giftGoldPackageCodeGenerator' : 'goldPackageCodeGenerator';
$selected = $params['selectedGoldProductId'] ?? null;
$packageOptions = ['' => 'Select a gold package'];
foreach (($params['packages'] ?? []) as $package) {
    $packageOptions[(string) $package['goldProductId']] = $package['goldProductName']
        . ' - ' . $package['goldProductPrice'] . ' ' . ($params['currency'] ?? '')
        . ' (' . $package['goldProductGold'] . ' Gold)';
}

$generated = '';
if (!empty($params['generatedCodes'])) {
    $generated = AdminUi::box(
        'Generated codes',
        '<label class="textarea fixed"><textarea rows="16" readonly>' . AdminUi::esc((string) $params['generatedCodes']) . '</textarea>'
        . '<div class="label">Copy these codes now</div></label>'
    );
}

$list = '';
if (!empty($params['content'])) {
    $list = AdminUi::box(
        'Unused codes',
        '<table class="adminTable"><thead><tr>'
        . '<th></th><th>#</th><th>Code</th><th>Product</th><th>Price</th>'
        . '</tr></thead><tbody>' . $params['content'] . '</tbody>'
        . '<tfoot><tr><td colspan="5" class="navi adminMuted">' . ($params['navigator'] ?? '') . '</td></tr></tfoot>'
        . '</table>'
    );
}

$body = '<script type="text/javascript">function packageSelectChanged(select){window.location.href="?action='
    . AdminUi::esc($action) . '&package="+encodeURIComponent(select.options[select.selectedIndex].value);}</script>'
    . '<form method="post" action="admin.php?action=' . AdminUi::esc($action)
    . ($selected ? '&package=' . (int) $selected : '') . '">'
    . getCheckerInput()
    . AdminUi::select('package', 'Package', $packageOptions, (string) ($selected ?? ''), ['onchange' => 'packageSelectChanged(this);'])
    . AdminUi::input('count', 'Number of codes', (string) ($params['count'] ?? '0'), ['type' => 'number', 'min' => '1', 'max' => '1000'])
    . AdminUi::button('Generate', 'green')
    . '</form>'
    . $generated
    . $list;

echo AdminUi::page(
    $isGift ? 'Gift code generator' : 'Code generator',
    $isGift
        ? 'Generate gift redeem codes for gold packages.'
        : 'Generate payment redeem codes for gold packages.',
    $body
);
