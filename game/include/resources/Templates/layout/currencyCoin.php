<?php

declare(strict_types=1);

/**
 * Single header currency coin SVG in the Gpack.
 * Expects $currencyCoinKind = 'gold'|'silver'.
 */

$kind = ($currencyCoinKind ?? '') === 'silver' ? 'silver' : 'gold';
$interactive = $currencyCoinInteractive ?? true;
$class = $kind === 'silver' ? 'silverCoin' : 'goldCoin';
$relative = gpack_image_path('hud/top-bar/currency/' . $kind . '-coin.svg');

$root = getenv('TRAVIAN_ROOT') ?: dirname(__DIR__, 5);
$path = rtrim((string) $root, '/') . '/assets/gpack/travian/mainPage/' . $relative;

if (!is_readable($path)) {
    throw new RuntimeException('Missing currency coin SVG: ' . $relative);
}

$svg = file_get_contents($path);
if ($svg === false) {
    throw new RuntimeException('Unable to read currency coin SVG: ' . $relative);
}

$openPayment = "Travian.React.openPaymentWizard({activeTab:'advantages'}); return false;";
$onclick = htmlspecialchars($openPayment, ENT_QUOTES, 'UTF-8');

// Drop standalone xmlns so inline SVG matches reference DOM; inject click handler.
$svg = preg_replace('/\sxmlns="[^"]*"/', '', $svg, 1) ?? $svg;
if ($interactive && preg_match('/<svg\b[^>]*>/', $svg, $m) === 1) {
    $tag = $m[0];
    if (!str_contains($tag, 'onclick=')) {
        $tagWithClick = rtrim(substr($tag, 0, -1)) . ' onclick="' . $onclick . '">';
        $svg = str_replace($tag, $tagWithClick, $svg);
    }
}

echo $svg;
