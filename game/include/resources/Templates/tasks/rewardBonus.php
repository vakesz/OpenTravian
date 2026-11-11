<?php

$e = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$bonus = $vars['heroBonus'];
// The reference isolates the number and then the whole string: LRO LRO n PDF, then the percent sign, then PDF.
$percentText = static fn (string $format, int $percent): string => sprintf($format, "\u{202D}\u{202D}" . $percent . "\u{202C}") . "\u{202C}";
?>
<div class="rewardBonus">
    <div class="heroLevel">
        <div class="level"><?= (int)$bonus['level']; ?></div>
        <div class="description"><?= $e(T('HeroInventory', 'heroLevel')); ?></div>
    </div>
    <div class="rewardInfo">
        <h4><?= $e($percentText(T('Tasks', 'bonusRewards'), (int)$bonus['bonusPercent'])); ?></h4>
        <div><?= $e(T('Tasks', 'bonusDescription')); ?></div>
    </div>
    <div class="nextLevel">
        <div class="level"><?= (int)$bonus['level'] + 1; ?></div>
        <div class="description"><?= $e($percentText(T('Tasks', 'nextLevel'), (int)$bonus['nextBonusPercent'])); ?></div>
    </div>
</div>
