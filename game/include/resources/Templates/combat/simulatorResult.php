<?php

declare(strict_types=1);

/**
 * the native Rally Point result cards. Values have already been computed
 * by Controller\RallyPoint\Combat from Simulator's canonical payload.
 *
 * @var array{
 *     cards:list<array{kind:string,tribe:int,label:string,units:list<array{unitId:int,initial:int,loss:int,wounded:int,result:int}>,hero:array{initial:int,loss:int,wounded:int,result:int},supplyBefore:int,supplyLost:int}>,
 *     messages:list<string>,
 *     statistics:array{attacker:array{strength:int,supplyBefore:int,supplyLost:int},defender:array{strength:int,supplyBefore:int,supplyLost:int}}|null
 * } $vars
 */

$esc = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$number = static fn(int $value): string => $esc(number_format_x($value));
$svg = static function (string $filename, string $class): string {
    $source = file_get_contents(PUBLIC_INTERNAL_PATH . 'img/svg/combat/' . $filename);
    if ($source === false) {
        return '';
    }

    return preg_replace('/class="[^"]+"/', 'class="' . $class . '"', $source, 1) ?? '';
};
$attackRoleIcon = $svg('svg-attack.svg', 'svgAttack roleIcon');
$defendRoleIcon = $svg('svg-defend.svg', 'svgDefend roleIcon');
$attackOutcomeIcon = $svg('svg-attack.svg', 'svgAttack');
$defendOutcomeIcon = $svg('svg-defend.svg', 'svgDefend');
$row = static function (string $class, string $icon, array $values) use ($number): string {
    $html = '<tr class="' . $class . '"><th>' . $icon . '</th>';
    foreach ($values as $index => $value) {
        $html .= '<td class="unit' . ($index + 1) . '"><span>' . $number((int)$value) . '</span></td>';
    }

    return $html . '</tr>';
};
?>
<div class="separator long"><span class="title"><?= $esc(T('combat', 'results', 'Results')); ?></span></div>
<?php foreach ($vars['cards'] as $card): ?>
    <?php
    $isAttack = $card['kind'] === 'attack';
    $roleIcon = $isAttack ? $attackRoleIcon : $defendRoleIcon;
    $outcomeIcon = $isAttack ? $attackOutcomeIcon : $defendOutcomeIcon;
    $classes = 'result ' . ($isAttack ? 'attack' : 'defence');
    if ($card['kind'] === 'reinforcement') {
        $classes .= ' reinforcement';
    }
    $classes .= ' tribe' . $card['tribe'];
    $initial = array_column($card['units'], 'initial');
    $losses = array_column($card['units'], 'loss');
    $wounded = array_column($card['units'], 'wounded');
    $remaining = array_column($card['units'], 'result');
    $unitIds = array_column($card['units'], 'unitId');
    $initial[] = $card['hero']['initial'];
    $losses[] = $card['hero']['loss'];
    $wounded[] = $card['hero']['wounded'];
    $remaining[] = $card['hero']['result'];
    ?>
    <div class="<?= $esc($classes); ?>">
        <div class="header">
            <div class="avatar"><i class="tribeIcon bigTribe<?= (int)$card['tribe']; ?>"></i><?= $roleIcon; ?></div>
            <h2><?= $esc($card['label']); ?></h2>
            <div class="outcome"><div class="arrowWrapper"><svg class="outcomeArrow" viewBox="0 0 20 20" preserveAspectRatio="none"><path d="M0 0L20 10L0 20z"></path></svg></div><div class="outcomeIcons"><?= $outcomeIcon; ?></div></div>
        </div>
        <div class="content preventMobileSwipeNavigation tribe<?= (int)$card['tribe']; ?>">
            <table class="transparent"><tbody>
                <tr class="troopIcons"><th></th><?php foreach ($unitIds as $unitId): ?><td><i class="unit u<?= (int)$unitId; ?>"></i></td><?php endforeach; ?><td><i class="unit uhero"></i></td></tr>
                <?= $row('troopsInitial', '<i class="troopCount_small"></i>', $initial); ?>
                <?= $row('troopsLoss', '<i class="troopDead_small"></i>', $losses); ?>
                <?= $row('troopsWounded', '<i class="troopWounded_small"></i>', $wounded); ?>
                <?= $row('troopsResult', '', $remaining); ?>
            </tbody></table>
        </div>
    </div>
<?php endforeach; ?>
<?php if ($vars['messages'] !== []): ?>
    <div class="additionalInfo"><table class="transparent"><tbody><?php foreach ($vars['messages'] as $message): ?><tr class="combatReportLine"><td><span><?= $esc($message); ?></span></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php if ($vars['statistics'] !== null): ?>
    <div class="combatStatistics">
        <div class="header"><h2><?= $esc(T('Statistics', 'titleInHeader')); ?></h2></div>
        <table class="transparent"><thead><tr><th></th><th><?= $esc(T('combat', 'attacker')); ?></th><th><?= $esc(T('combat', 'defender')); ?></th></tr></thead><tbody>
            <tr><th><?= $esc(T('combat', 'combatStrength', 'Combat strength')); ?></th><td><div class="inlineIcon"><i class="offence_medium"></i><span class="value"><?= $number($vars['statistics']['attacker']['strength']); ?></span></div></td><td><div class="inlineIcon"><i class="defence_medium"></i><span class="value"><?= $number($vars['statistics']['defender']['strength']); ?></span></div></td></tr>
            <tr><th><?= $esc(T('combat', 'supplyBefore', 'Supply before')); ?></th><td><div class="inlineIcon"><i class="r5Big"></i><span class="value"><?= $number($vars['statistics']['attacker']['supplyBefore']); ?></span></div></td><td><div class="inlineIcon"><i class="r5Big"></i><span class="value"><?= $number($vars['statistics']['defender']['supplyBefore']); ?></span></div></td></tr>
            <tr><th><?= $esc(T('combat', 'supplyLost', 'Supply lost')); ?></th><td><div class="inlineIcon"><i class="r5Big"></i><span class="value"><?= $number($vars['statistics']['attacker']['supplyLost']); ?></span></div></td><td><div class="inlineIcon"><i class="r5Big"></i><span class="value"><?= $number($vars['statistics']['defender']['supplyLost']); ?></span></div></td></tr>
            <tr><th><?= $esc(T('combat', 'resourcesLost', 'Resources lost')); ?></th><td><div class="inlineIcon"><i class="resources_medium"></i><span class="value">0</span></div></td><td><div class="inlineIcon"><i class="resources_medium"></i><span class="value">0</span></div></td></tr>
        </tbody></table>
    </div>
<?php endif; ?>
