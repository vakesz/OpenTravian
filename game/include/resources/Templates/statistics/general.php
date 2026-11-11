<?php

use Core\Helper\RechartsMarkup;
use Core\Helper\TimezoneHelper;

/** @var array<string, mixed> $vars Injected by resources\View\PHPBatchView::display(). */
$data = $vars['dashboard'];
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$lre = "\u{202D}";
$pdf = "\u{202C}";
$plain = static fn ($n): string => $lre . $n . $pdf;
$short = static function ($n) use ($lre, $pdf): string {
    $n = max(0, (float)$n);
    if ($n >= 1000) {
        return $lre . $lre . number_format(floor($n / 100) / 10, 1, '.', '') . $pdf . 'k' . $pdf;
    }

    return $lre . number_format(floor($n * 10) / 10, 1, '.', '') . $pdf;
};
$rank = static function ($n) use ($lre, $pdf): string {
    $n = (int)$n;

    return $n >= 1000 ? $lre . $lre . intdiv($n, 1000) . $pdf . 'k' . $pdf : $lre . $n . $pdf;
};
$sig = static function (float $value): string {
    if ($value <= 0) {
        return '0';
    }
    $digits = max(0, 5 - (int)floor(log10($value)));

    return rtrim(rtrim(number_format($value, $digits, '.', ''), '0'), '.');
};
$gpack = static fn (string $file): string => get_gpack_cdn_mainPage_url() . gpack_image_path('global/' . $file);
$date = static fn (int $time): string => TimezoneHelper::date(TimezoneHelper::getDateFormat(), $time);
$tr = static fn (string $key, string $fallback): string => T('Statistics', 'general.' . $key, $fallback);
$tribeNames = [1 => 'Romans', 2 => 'Teutons', 3 => 'Gauls', 6 => 'Egyptians', 7 => 'Huns', 8 => 'Spartans', 9 => 'Vikings'];
$tribeImages = [1 => 'roman', 2 => 'teuton', 3 => 'gaul', 6 => 'egyptian', 7 => 'hun', 8 => 'spartan', 9 => 'viking'];
$tribeColors = [1 => '#96323d', 2 => '#428291', 3 => '#de7e1b', 6 => '#3e559a', 7 => '#674876', 8 => '#4E4545', 9 => '#2f6f4e'];
$checkmark = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 20 20" class="checkmark invisible"><path d="M.3 8.5s5 4.4 6.3 8.1C8.5 7.8 14.3.3 14.3.3"></path></svg>';
$wrapperOpen = static fn (int $w, int $h): string => '<div width="' . $w . '" height="' . $h . '" class="recharts-wrapper" style="position: relative; cursor: default; width: ' . $w . 'px; height: ' . $h . 'px;">';
$tooltip = '<div xmlns="http://www.w3.org/1999/xhtml" tabindex="-1" class="recharts-tooltip-wrapper" style="visibility: hidden; pointer-events: none; position: absolute; top: 0px; left: 0px;"><div class="recharts-default-tooltip" role="status" aria-live="assertive" style="margin: 0px; padding: 10px; background-color: rgb(255, 255, 255); border: 1px solid rgb(204, 204, 204); white-space: nowrap;"><p class="recharts-tooltip-label" style="margin: 0px;"></p></div></div>';
$serverAllianceRank = static function (string $class, int $server, ?int $alliance) use ($rank, $plain): string {
    $html = '<div class="ServerAllianceRank"><div class="typeWrapper"><div class="buttonWrapper"><div>' . T('Statistics', 'general.gameWorld', 'Game world:') . '</div><i class="gameworld_large"></i></div><div class="rankDisplay ' . $class . '">' . $rank($server) . '</div></div>';
    if ($alliance !== null) {
        $html .= '<div class="typeWrapper"><div class="buttonWrapper"><div>' . T('Statistics', 'tabs.alliances', 'Alliance') . '</div><i class="alliance_large"></i></div><div class="rankDisplay ' . $class . '">' . $rank($alliance) . '</div></div>';
    }

    return $html . '</div>';
};
$legendTabs = static function (string $firstIcon, string $firstLabel, string $secondIcon, string $secondLabel, string $attribute, string $firstValue, string $secondValue): string {
    return '<div class="legendTabs"><div class="tab inlineIcon active" ' . $attribute . '="' . $firstValue . '"><i class="' . $firstIcon . '"></i><span>' . $firstLabel . '</span></div><div class="tab inlineIcon" ' . $attribute . '="' . $secondValue . '"><i class="' . $secondIcon . '"></i><span>' . $secondLabel . '</span></div></div>';
};
$perDayLabel = T('Statistics', 'general.perDay', 'Per day');
$soFarLabel = T('Statistics', 'general.soFar', 'So far');

$progress = $data['serverProgression'];
$achieved = count(array_filter($progress['steps'], static fn ($s) => $s['achieved']));
?>
<div id="statisticsV2" class="contentV2"><div>
<div id="serverProgression"><h4><i class="serverProgression"></i> <?= $tr('worldProgression', 'Game world progression'); ?></h4>
<div id="serverProgressionTimeLine"><div id="timeLine"><div class="serverNumbers"><div class="counter serverTime"><i class="serverTime"></i><span><?= (int)$progress['ageInDays']; ?></span></div><div class="counter activatedPlayers"><i class="player_large"></i><span><?= (int)$progress['activatedPlayers']; ?></span></div></div>
<div class="timeLine itemCount<?= count($progress['steps']); ?> unlocked<?= $achieved; ?>"><?php foreach ($progress['steps'] as $step): ?><div class="step <?= $step['id']; ?> <?= $step['achieved'] ? 'achieved' : 'locked'; ?>"><i class="<?= $step['id']; ?> tribe<?= (int)$step['tribe']; ?>"></i><div class="description"><?= $step['text'] !== null ? $escape($step['text']) : ($step['time'] > 0 ? $date((int)$step['time']) : '-'); ?></div></div><?php endforeach; ?></div></div></div>
<div id="serverProgressionTribeDistribution"><div class="chartWithLegend"><div class="legendContainer"><div class="simpleLegend"><table class="transparent"><thead><tr><td><i class="distribution"></i></td><td colspan="2"><?= $tr('tribeDistribution', 'Current tribe distribution'); ?></td></tr></thead><tbody>
<?php foreach ($data['tribeDistribution'] as $row): ?><tr><td><i class="tribe<?= $row['tribe']; ?>_medium"></i> </td><td><?= T('Statistics', 'General.' . $tribeNames[$row['tribe']], $tribeNames[$row['tribe']]); ?></td><td><span class="v<?= $row['tribe']; ?>"><?= $lre . $lre . number_format($row['percent'], 1, '.', '') . $pdf . '%' . $pdf; ?></span></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="chartContainer"><?= $wrapperOpen(320, 270); ?><?php
$slices = array_map(static fn ($r) => ['value' => $r['percent'], 'fill' => $tribeColors[$r['tribe']] ?? '#888888', 'tribe' => $r['tribe']], $data['tribeDistribution']);
echo RechartsMarkup::pie(1, 0, $slices, 0.0, static function (int $i, array $slice, float $mid, float $share) use ($gpack, $tribeImages, $plain, $lre, $pdf): string {
    [$cx, $cy] = RechartsMarkup::labelCenter($mid);
    $imageX = round($cx) - 13;
    $imageY = round($cy) - 13;
    $near = abs(cos(deg2rad($mid)));
    if ($near > 0.9) {
        $anchor = 'middle';
        $textX = $cx;
        $textY = cos(deg2rad($mid)) > 0 ? $cy - 25 : $cy + 25;
    } elseif (sin(deg2rad($mid)) > 0) {
        $anchor = 'start';
        $textX = $cx + 16;
        $textY = $cy;
    } else {
        $anchor = 'end';
        $textX = $cx - 16;
        $textY = $cy;
    }
    $tribe = (int)$slice['tribe'];

    return '<g class="recharts-layer"><g><image xlink:href="' . $gpack('tribes/' . $tribeImages[$tribe] . '-medium.png') . '" x="' . RechartsMarkup::num($imageX) . '" y="' . RechartsMarkup::num($imageY) . '" width="26" height="26"></image>'
        . '<text x="' . RechartsMarkup::num($textX, 14) . '" y="' . RechartsMarkup::num($textY, 14) . '" width="150" fill="black" text-anchor="' . $anchor . '" dominant-baseline="central" class="nation nation' . $tribe . '">'
        . $lre . $lre . number_format($slice['value'], 1, '.', '') . $pdf . '%' . $pdf . '</text></g></g>';
});
?></div></div></div></div></div>

<?php $population = $data['populationRank']; ?>
<div id="populationRank" class="expanded"><div class="openedClosedSwitch switchOpened"></div><h4><i class="populationRank"></i> <?= $tr('populationRank', 'Population rank'); ?></h4>
<div class="chartWithLegend"><div class="legendContainer"><div class="simpleLegend"><?= $serverAllianceRank('Population', (int)$population['rank'], $population['hasAlliance'] ? (int)$population['allianceCurrent'] : null); ?></div></div>
<div class="chartContainer"><?= $wrapperOpen(342, 228); ?><?= RechartsMarkup::area(2, 1, $population['serverRank'], max(4, (int)(ceil(($population['ageInDays'] + 1) / 4) * 4))); ?></div></div></div></div>

<?php
$resource = $data['resourceRank'];
$positiveSum = array_sum($resource['positive']);
$negativeSum = array_sum($resource['negative']);
$barBase = max($positiveSum, $negativeSum);
$width = static fn ($part) => ($barBase > 0 ? $sig($part / $barBase * 100) : '0') . '%';
?>
<div id="resourceRank"><div class="openedClosedSwitch switchOpened"></div><h4><i class="resourceRank"></i> <?= $tr('resourceRank', 'Resource rank'); ?></h4>
    <div><div class="resourceBarChartContainer"><div class="resourceBarChart animated"><div class="barContainer"><div class="positive animated"> <span class="raid" style="width: <?= $width($resource['positive']['raid']); ?>;"></span><span class="trade" style="width: <?= $width($resource['positive']['trade']); ?>;"></span><span class="production" style="width: <?= $width($resource['positive']['production']); ?>;"></span></div><div class="negative"><span class="trade" style="width: <?= $width($resource['negative']['trade']); ?>;"></span><span class="raid" style="width: <?= $width($resource['negative']['raid']); ?>;"></span></div></div>
        <div class="balance"><div class="positive"><?= $short($positiveSum); ?></div><div class="negative<?= $negativeSum == 0 ? ' isZero' : ''; ?>"><?= $short($negativeSum); ?></div><div class="total <?= $resource['balance'] >= 0 ? 'positive' : 'negative'; ?>"><?= $short(abs($resource['balance'])); ?></div></div></div>
        <table class="resourceBarChartLegend transparent"><tbody>
        <?php foreach ([
            ['positive raid', $tr('bountyWon', 'Bounty won:'), $resource['positive']['raid']],
            ['positive trade', $tr('tradeIn', 'Incoming trading volume:'), $resource['positive']['trade']],
            ['positive production', $tr('production', 'Production:'), $resource['positive']['production']],
            ['negative raid', $tr('bountyLost', 'Bounty lost:'), $resource['negative']['raid']],
            ['negative trade', $tr('tradeOut', 'Outgoing trading volume:'), $resource['negative']['trade']],
        ] as [$class, $label, $value]): ?><tr class="<?= $class; ?>"><td><?= $label; ?></td><td class="value"><?= $short($value); ?></td><td class="indicators"><div class="cellInner"><span class="resourceIndicator"></span><?= $checkmark; ?></div></td></tr>
        <?php endforeach; ?></tbody></table></div>
<?php
$resourceColors = ['#f37800', '#f09e32', '#b1cacf', '#ffde00'];
$resourceImages = ['lumber', 'clay', 'iron', 'crop'];
// Intrinsic sizes of global/resources/*-small.png.
$iconSizes = ['lumber' => [26, 19], 'clay' => [26, 18], 'iron' => [26, 18], 'crop' => [26, 24]];
$iconSize = static fn (string $name): array => $iconSizes[$name];
$resourceProductionChart = static function (array $amounts, int $surface, int $react) use ($resourceColors, $resourceImages, $iconSize, $gpack, $short): string {
    $slices = [];
    foreach ($amounts as $i => $amount) {
        $slices[] = ['value' => $amount, 'fill' => $resourceColors[$i], 'index' => $i, 'amount' => $amount];
    }

    return RechartsMarkup::pie($surface, $react, $slices, 0.0, static function (int $i, array $slice, float $mid, float $share) use ($gpack, $resourceImages, $iconSize, $short): string {
    [$cx, $cy] = RechartsMarkup::labelCenter($mid);
    $cx = round($cx);
    $cy = round($cy);
    [$w, $h] = $iconSize($resourceImages[$slice['index']]);
    $textY = cos(deg2rad($mid)) > 0 ? $cy - 26 : $cy + 26;

    return '<g class="recharts-layer"><g><image xlink:href="' . $gpack('resources/' . $resourceImages[$slice['index']] . '-small.png') . '" x="' . RechartsMarkup::num($cx - $w / 2) . '" y="' . RechartsMarkup::num($cy - $h / 2)
        . '" width="' . $w . '" height="' . $h . '"></image><text x="' . RechartsMarkup::num($cx) . '" y="' . RechartsMarkup::num($textY) . '" width="150" fill="black" text-anchor="middle" dominant-baseline="central">' . $short($slice['amount']) . '</text></g></g>';
    });
};
$resourceSwitches = [
    'bounty' => ['icon' => 'bounty_small', 'title' => $tr('bounty', 'Bounty')],
    'trade' => ['icon' => 'trade_small', 'title' => $tr('trade', 'Trading volume')],
    'production' => ['icon' => 'resources_small', 'title' => $tr('productionTitle', 'Production')],
];
?>
    <div class="chartWithLegend"><div class="legendContainer"><?= $legendTabs('clock_medium', $perDayLabel, 'resources_medium', $soFarLabel, 'data-statistics-resource-period', 'per-day', 'so-far'); ?>
        <?php foreach (['per-day', 'so-far'] as $period): $rankData = $period === 'per-day' ? $resource['rankDaily'] : $resource['rank']; ?><div class="simpleLegend" data-statistics-resource-rank="<?= $period; ?>"<?= $period === 'per-day' ? '' : ' hidden'; ?>><?= $serverAllianceRank($period === 'per-day' ? 'Production' : 'Resource', (int)$rankData['server'], $resource['hasAlliance'] ? (int)$rankData['alliance'] : null); ?></div><?php endforeach; ?>
        </div><div class="chartContainer"><div class="dataSwitchButtons"><?php foreach ($resourceSwitches as $mode => $switch): ?><a href="#" class="iconButtonV2<?= $mode === 'production' ? ' active' : ' disabled'; ?>" data-statistics-resource-mode="<?= $mode; ?>" title="<?= $escape($switch['title']); ?>"><i class="<?= $switch['icon']; ?>"></i></a><?php endforeach; ?></div>
        <?php foreach ([['per-day', 'production'], ['so-far', 'bounty'], ['so-far', 'trade'], ['so-far', 'production']] as [$period, $mode]):
            $visible = $period === 'per-day' && $mode === 'production';
            $title = $resourceSwitches[$mode]['title'];
        ?><div data-statistics-resource-panel="<?= $period . ':' . $mode; ?>"<?= $visible ? '' : ' hidden'; ?>><div class="chartTitle"><?= $title; ?></div><?= $wrapperOpen(320, 270); ?><?= $tooltip; ?><?php
            if ($mode === 'production') {
                echo $resourceProductionChart($resource['production'][$period === 'per-day' ? 'perDay' : 'soFar'], $period === 'per-day' ? 3 : 6, $period === 'per-day' ? 2 : 5);
            } elseif ($mode === 'bounty') {
                echo RechartsMarkup::pie(7, 6, [
                    ['value' => $resource['bounty']['won'], 'fill' => '#608cbc'],
                    ['value' => $resource['bounty']['lost'], 'fill' => '#ea866e'],
                ], 0.0);
            } else {
                echo RechartsMarkup::pie(8, 7, [
                    ['value' => $resource['trade']['in'], 'fill' => '#608cbc'],
                    ['value' => $resource['trade']['out'], 'fill' => '#ea866e'],
                ], 0.0);
            }
        ?></div></div><?php endforeach; ?></div></div>
    </div>
</div>

<?php
$strength = $data['villageStrength'];
$strengthModes = [
    'offence' => [
        'icon' => 'offence_medium',
        'label' => $tr('offensive', 'Offensive'),
        'rankClass' => 'Offensive',
        'title' => $tr('offensiveComparison', 'Offensive strength comparison'),
        'detail' => $tr('offensiveOfSelected', 'Offensive strength of selected village'),
    ],
    'defence' => [
        'icon' => 'defence_medium',
        'label' => $tr('defensive', 'Defensive'),
        'rankClass' => 'Defensive',
        'title' => $tr('defensiveComparison', 'Defensive strength comparison'),
        'detail' => $tr('defensiveOfSelected', "Defensive strength of selected village"),
    ],
];
?>
<div id="militaryStrengthRank"><div class="openedClosedSwitch switchOpened"></div><h4><i class="militaryStrengthRank"></i> <?= $tr('militaryStrengthRank', 'Military strength rank'); ?></h4>
    <div><?php foreach ($strengthModes as $mode => $meta): ?>
        <div data-statistics-strength-mode-panel="<?= $mode; ?>"<?= $mode === 'offence' ? '' : ' hidden'; ?>><div class="chartWithLegend"><div class="legendContainer"><?= $legendTabs('offence_medium', $tr('offensive', 'Offensive'), 'defence_medium', $tr('defensive', 'Defensive'), 'data-statistics-strength-mode', 'offence', 'defence'); ?>
            <?php foreach ($strength['villages'] as $selectedIndex => $selected): $rankData = $selected['rankings'][$mode]; ?>
                <div class="simpleLegend" data-statistics-strength-rank="<?= $selected['kid']; ?>"<?= $selectedIndex === 0 ? '' : ' hidden'; ?>><?= $serverAllianceRank($meta['rankClass'], (int)$rankData['serverRank'], $strength['hasAlliance'] ? (int)$rankData['allianceRank'] : null); ?>
                    <table class="villageList transparent"><thead><tr><th></th><th><i class="village_medium"></i></th><th><i class="<?= $meta['icon']; ?>"></i></th></tr></thead><tbody>
                    <?php foreach ($strength['villages'] as $index => $village): ?><tr><td class="rank"><a class="iconButtonV2<?= $index === $selectedIndex ? ' active' : ''; ?>" href="" data-statistics-strength-village="<?= $village['kid']; ?>"><span><?= $index + 1; ?></span></a></td><td class="name"><?= $escape($village['name']); ?></td><td class="strength"><span><?= $plain($village[$mode]); ?></span></td></tr><?php endforeach; ?>
                    </tbody></table>
                </div>
            <?php endforeach; ?>
            </div><div class="chartContainer">
            <?php foreach ($strength['villages'] as $selectedIndex => $selected): $rankData = $selected['rankings'][$mode]; ?>
                <div data-statistics-strength-village-panel="<?= $selected['kid']; ?>"<?= $selectedIndex === 0 ? '' : ' hidden'; ?>><div class="strengthRankChart"><div class="chartTitle"><?= $meta['title']; ?></div>
                    <div><div class="worse centeredText" title="<?= $escape(sprintf($tr('villagesWorse', 'Number of villages that rank worse than %s'), $selected['name'])); ?>"><p class="inlineIcon"><i class="village_medium"></i><span><?= (int)$rankData['worse']; ?></span></p><p><?= $tr('worse', 'worse'); ?></p></div><div class="better centeredText" title="<?= $escape(sprintf($tr('villagesBetter', 'Number of villages that rank better than %s'), $selected['name'])); ?>"><p class="inlineIcon"><i class="village_medium"></i><span><?= (int)$rankData['better']; ?></span></p><p><?= $tr('better', 'better'); ?></p></div></div>
                    <?= $wrapperOpen(320, 270); ?><?= $tooltip; ?><?= RechartsMarkup::pie(4 + $selectedIndex + ($mode === 'defence' ? 20 : 0), 3 + $selectedIndex + ($mode === 'defence' ? 20 : 0), [['value' => $rankData['worse'], 'fill' => '#608cbc'], ['value' => $rankData['better'], 'fill' => '#ea866e']], 29.9); ?></div></div>
                    <div class="chartDetails"><div><div class="strength"><div class="inlineIcon"><i class="<?= $meta['icon']; ?>"></i><span><?= $plain($selected[$mode]); ?></span></div></div><div class="name"><div class="inlineIcon"><i class="village_medium"></i><span><?= $escape($selected['name']); ?></span></div></div><?= $meta['detail']; ?><div class="rankDisplay"><?= $rank($rankData['serverRank']); ?></div></div></div>
                </div>
            <?php endforeach; ?>
            </div></div>
        </div>
    <?php endforeach; ?></div>
</div>

<?php $cp = $data['culturePointsRank']; ?>
<div id="culturePointsRank">
    <div class="culturePointsHeader"><div class="openedClosedSwitch switchOpened"></div><h4><i class="culturePointsRank"></i> <?= $tr('culturePointsRank', 'Culture points rank'); ?></h4>
        <div class="dataSwitchButtons"><a href="#" class="iconButtonV2 active" data-statistics-cp-mode="comparison"><i class="culturePoints_small"></i></a><a href="#" class="iconButtonV2" data-statistics-cp-mode="distribution"><i class="distribution"></i></a><a href="#" class="iconButtonV2" data-statistics-cp-mode="progression"><i class="progression"></i></a></div>
    </div>
    <div class="chartWithLegend" id="cpRankComparison" data-statistics-cp-mode-panel="comparison">
        <div class="legendContainer"><?= $legendTabs('clock_medium', $perDayLabel, 'culturePoints_medium', $soFarLabel, 'data-statistics-cp-period', 'per-day', 'so-far'); ?><?php foreach (['per-day', 'so-far'] as $period):
            $rankData = $cp['comparison'][$period === 'per-day' ? 'perDay' : 'soFar'];
            $production = $period === 'per-day' ? (int)$cp['cpProduction'] : (int)$cp['cpProduced'];
            $productionLabel = $period === 'per-day' ? $tr('cpProduction', 'CP production:') : $tr('cpProduced', 'CP produced so far:');
        ?><div class="simpleLegend" data-statistics-cp-period-panel="<?= $period; ?>"<?= $period === 'per-day' ? '' : ' hidden'; ?>><?= $serverAllianceRank($period === 'per-day' ? 'CPperDay' : 'CulturePoints', (int)$rankData['serverRank'], $cp['hasAlliance'] ? (int)$rankData['allianceRank'] : null); ?><div class="cpProduction"><?= $productionLabel; ?> <span><?= $plain($production); ?></span></div></div><?php endforeach; ?></div>
        <div class="chartContainer"><?php foreach (['per-day', 'so-far'] as $period):
            $rankData = $cp['comparison'][$period === 'per-day' ? 'perDay' : 'soFar'];
        ?><div data-statistics-cp-period-panel="<?= $period; ?>"<?= $period === 'per-day' ? '' : ' hidden'; ?>><div class="rankDisplay"><?= $rank($rankData['serverRank']); ?></div><div class="culturePointsRankChart"><div class="chartTitle"><?= $tr('cpComparison', 'CP rank comparison'); ?></div>
            <div><div class="worse centeredText"><p title="<?= T('Statistics', 'tabs.players', 'Player(s)'); ?>" class="inlineIcon"><i class="player_medium"></i><span><?= (int)$rankData['worse']; ?></span></p><p title="<?= T('Statistics', 'tabs.players', 'Player(s)'); ?>"><?= $tr('worse', 'worse'); ?></p></div><div class="better centeredText"><p title="<?= T('Statistics', 'tabs.players', 'Player(s)'); ?>" class="inlineIcon"><i class="player_medium"></i><span><?= (int)$rankData['better']; ?></span></p><p title="<?= T('Statistics', 'tabs.players', 'Player(s)'); ?>"><?= $tr('better', 'better'); ?></p></div></div>
            <?= $wrapperOpen(320, 270); ?><?= $tooltip; ?><?= RechartsMarkup::pie($period === 'per-day' ? 5 : 6, $period === 'per-day' ? 4 : 5, $cp['known'] ? [['value' => $rankData['worse'], 'fill' => '#608cbc'], ['value' => $rankData['better'], 'fill' => '#ea866e']] : [], 29.9); ?></div></div></div><?php endforeach; ?></div>
    </div>
    <div id="cpProgressionChart" data-statistics-cp-mode-panel="progression" hidden><div class="chartTitle"><?= $tr('cpProgression', 'CP progression'); ?></div><div class="chartWithLegend chartIncluded"><div class="legendContainer"><?= $legendTabs('clock_medium', $perDayLabel, 'culturePoints_medium', $soFarLabel, 'data-statistics-cp-period', 'per-day', 'so-far'); ?><?php foreach (['per-day', 'so-far'] as $period):
            $points = $cp['progression'][$period === 'per-day' ? 'perDay' : 'soFar'];
            $lastPoint = $points[count($points) - 1] ?? ['day' => 0];
            $xMax = max(4, (int)(ceil(((int)$lastPoint['day']) / 4) * 4));
    ?><div class="simpleLegend" data-statistics-cp-period-panel="<?= $period; ?>"<?= $period === 'per-day' ? '' : ' hidden'; ?>><?= $wrapperOpen(535, 220); ?><?= RechartsMarkup::areaValues($period === 'per-day' ? 30 : 31, $period === 'per-day' ? 29 : 30, $points, $xMax); ?></div></div><?php endforeach; ?></div>
    </div></div>
</div>
<script type="text/javascript" src="/statistics-general.js?v=<?= filemtime(PUBLIC_INTERNAL_PATH . 'statistics-general.js'); ?>"></script>
