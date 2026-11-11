<?php

declare(strict_types=1);

$nr = (int) ($vars['nr'] ?? $vars['index'] ?? 0);
$troopKey = (string) ($vars['troopKey'] ?? ('t' . $nr));
$unitId = (int) $vars['unitId'];
$unitTitle = T('Troops', $unitId . '.title');
$smithyLevel = (int) ($vars['smithyLevel'] ?? 0);
$showFavourite = !empty($vars['showFavourite']);
$expansion = !empty($vars['expansion']);
$locked = !empty($vars['notResearched']) || ($vars['slotNeeded'] ?? null) !== null;
$showLevel = array_key_exists('showLevel', $vars) ? !empty($vars['showLevel']) : ($unitId !== 99);
?>
<div class="action troop troop<?= htmlspecialchars($troopKey, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="innerTroopWrapper troop<?= htmlspecialchars($troopKey, ENT_QUOTES, 'UTF-8'); ?>" data-troopID="<?= htmlspecialchars($troopKey, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($showFavourite): ?>
            <button type="button" class="favourite " data-troopID="<?= htmlspecialchars($troopKey, ENT_QUOTES, 'UTF-8'); ?>"><i></i></button>
        <?php endif; ?>
        <?php if ($nr !== 99): ?>
            <div class="bigUnitSection">
                <a class="unitZoom"
                   onclick="return Travian.Game.unitZoom(<?= $unitId; ?>, '<?= htmlspecialchars(addslashes($unitTitle), ENT_QUOTES, 'UTF-8'); ?>'<?= $expansion ? '' : ', '; ?>);"
                   title="<?= htmlspecialchars($unitTitle . '||' . T('Manual', 'zoomImage'), ENT_QUOTES, 'UTF-8'); ?>">
                    <img class="unitSection <?= $expansion ? '' : 'tribe '; ?>u<?= $unitId; ?>Section"
                         src="/img/spacer.gif"
                         alt="<?= htmlspecialchars($unitTitle, ENT_QUOTES, 'UTF-8'); ?>">
                </a>
            </div>
        <?php endif; ?>
        <div class="details">
            <div class="tit">
                <a onclick="Travian.Game.Manual.open('troop', <?= $unitId === 99 ? 36 : $unitId; ?>);"><img
                            class="unit u<?= $unitId; ?>" src="/img/spacer.gif"
                            alt="<?= htmlspecialchars($unitTitle, ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars($unitTitle, ENT_QUOTES, 'UTF-8'); ?>"></a>
                <a onclick="Travian.Game.Manual.open('troop', <?= $unitId === 99 ? 36 : $unitId; ?>);"><?= $unitTitle; ?></a>
                <?php if ($showLevel): ?>
                    <span class="level"><?= T('Buildings', 'level'); ?> <?= $smithyLevel; ?></span>
                <?php endif; ?>
                <span class="furtherInfo">(<?= T('inGame', 'present'); ?> <?= number_format_x($vars['available']); ?>)</span>
            </div>
            <div class="inlineIconList resourceWrapper charges">
                <div class="inlineIcon resource" title="<?= T('inGame', 'resources.r1'); ?>"><i class="r1Big"></i><span class="value value">&#8237;<?= number_format_x($vars['cost'][0]); ?>&#8236;</span></div>
                <div class="inlineIcon resource" title="<?= T('inGame', 'resources.r2'); ?>"><i class="r2Big"></i><span class="value value">&#8237;<?= number_format_x($vars['cost'][1]); ?>&#8236;</span></div>
                <div class="inlineIcon resource" title="<?= T('inGame', 'resources.r3'); ?>"><i class="r3Big"></i><span class="value value">&#8237;<?= number_format_x($vars['cost'][2]); ?>&#8236;</span></div>
                <div class="inlineIcon resource" title="<?= T('inGame', 'resources.r4'); ?>"><i class="r4Big"></i><span class="value value">&#8237;<?= number_format_x($vars['cost'][3]); ?>&#8236;</span></div>
                <div class="inlineIcon resource" title="<?= T('Manual', 'freeCrop'); ?>"><i class="cropConsumptionBig"></i><span class="value value">&#8237;<?= $vars['upkeep']; ?>&#8236;</span></div>
            </div>
            <br>
            <div class="inlineIcon duration" title="<?= htmlspecialchars((string)T('Manual', 'duration'), ENT_QUOTES, 'UTF-8'); ?>"><div class="iconWrapper"><i class="<?= htmlspecialchars((string) $vars['durationClass'], ENT_QUOTES, 'UTF-8'); ?>"></i></div><span class="value "><?= $vars['duration']; ?></span></div>
            <?php if ($vars['notResearched'] ?? false): ?>
                <div class="notResearched">
                    <?= sprintf((string) T('ResidencePalace', 'researchIn'), '<span class="noAcademy">' . T('Buildings', '22.title') . '</span>'); ?>
                </div>
            <?php endif; ?>
            <?php if (($vars['slotNeeded'] ?? null) !== null): ?>
                <div class="noExpansionSlot">
                    <?= $vars['slotNeeded']; ?>
                </div>
            <?php endif; ?>
            <?php if (!$locked): ?>
                <div class="cta">
                    <?php if ($vars['none_status'] !== ''): ?>
                        <div class="status none"><?= $vars['none_status']; ?></div>
                    <?php endif; ?>
                    <span class="value"><?= T('inGame', 'Amount'); ?></span>
                    <input type="text" inputmode="numeric" class="text" name="t<?= (int) $vars['index']; ?>" value="0"
                           maxlength="<?= max(strlen((string) $vars['max']), 4); ?>"/><span class="value"> / </span>
                    <a href="#"
                       onclick="jQuery(this).closest('div.details').find('input').val(<?= (int) $vars['max']; ?>); return false;"><?= number_format_x($vars['max']); ?></a>
                    <?= $vars['npc']; ?>
                </div>
                <?php if (!empty($vars['maximumShortcut'])): ?>
                    <div class="cta">
                        <?= getButton(
                            ['type' => 'submit', 'name' => 'trainMaximum', 'value' => (string) $nr, 'class' => 'textButtonV1 green' . ((int) $vars['max'] > 0 ? '' : ' disabled')],
                            ['data' => ['type' => 'submit', 'name' => 'trainMaximum', 'value' => (string) $nr]],
                            T('SpeedShortcuts', 'trainMaximum')
                        ); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
