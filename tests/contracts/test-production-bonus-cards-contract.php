<?php

declare(strict_types=1);

use Game\GoldHelper;

/**
 * The production bonus cards of the shop (advantages tab). The Gpack lays `.bonusText` out as a grid of a 16 px
 * icon column and a text column: the reference puts the arrow svg first and one span after it, so the line reads
 * "+25% for 72 hours". Without the arrow the span falls into the 16 px column and wraps word by word. The reference
 * writes durations in hours; the "in days" button titles carry a number.
 */
function failProductionBonusCardsContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$template = (string)file_get_contents($root . '/game/include/resources/Templates/payment/pros.php');
if (!str_contains($template, '<div class="bonusInfo bonusText"><?=\Game\ProductionBoostArrow::svg(\'advantageBonusArrow premiumFeature\'')
    || !str_contains($template, '?><span><strong>')) {
    failProductionBonusCardsContract('Each production bonus card starts its bonusInfo with the arrow svg, then one span.');
}
$arrow = \Game\ProductionBoostArrow::svg('advantageBonusArrow premiumFeature', 'svg-abc123');
if (!str_starts_with($arrow, '<svg viewBox="0 0 160 180" class="advantageBonusArrow premiumFeature" id="svg-abc123">')
    || substr_count($arrow, '<path') !== 5) {
    failProductionBonusCardsContract('The arrow is the reference svg with its five paths.');
}
foreach (['layout/Layout.php', 'resource-fields/main.php'] as $file) {
    $source = (string)file_get_contents($root . '/game/include/resources/Templates/' . $file);
    if (str_contains($source, 'M158.78 106.45') || !str_contains($source, 'ProductionBoostArrow::forResourceBar')) {
        failProductionBonusCardsContract("{$file} must use the shared arrow, not a copy.");
    }
}

$helper = new GoldHelper();
$bidi = static fn (string $text): string => "\u{202D}" . $text . "\u{202C}";
foreach ([86400 => '24 hours', 3 * 86400 => '72 hours', 7 * 86400 => '168 hours', 90000 => '25 hours'] as $seconds => $text) {
    if ($helper->getDurationText($seconds) !== $bidi($text)) {
        failProductionBonusCardsContract("{$seconds} s must read {$text}, got: " . $helper->getDurationText($seconds));
    }
    if (str_contains($helper->getDurationText($seconds), 'Days')) {
        failProductionBonusCardsContract('A duration is written in hours, never "Days".');
    }
}
foreach ([86400 => '1', 3 * 86400 => '3', 7 * 86400 => '7', 129600 => '1.5'] as $seconds => $days) {
    if ($helper->getDurationDays($seconds) !== $days) {
        failProductionBonusCardsContract("{$seconds} s must read {$days} days in a title, got: " . $helper->getDurationDays($seconds));
    }
}

echo "Production bonus cards contract passed.\n";
