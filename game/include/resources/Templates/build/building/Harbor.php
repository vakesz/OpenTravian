<h4 class="round"><?= T("inGame", "build_ships"); ?></h4>
<p><?= sprintf(T("inGame", "ships_owned"), number_format_x($vars['owned'] + $vars['queuedShips']), number_format_x($vars['maxShips']), number_format_x($vars['away']), number_format_x($vars['queuedShips'])); ?></p>
<?php if ($vars['room'] <= 0): ?>
    <span class="errorMessage"><?= T("inGame", "harborFull"); ?></span>
<?php endif; ?>
<form method="post" name="snd" action="/build.php?id=<?= $vars['index']; ?>">
    <input type="hidden" name="id" value="<?= $vars['index']; ?>"/>
    <input type="hidden" name="z" value="<?= $vars['checker']; ?>"/>
    <input type="hidden" name="a" value="2"/>
    <div class="buildActionOverview trainUnits">
        <?php foreach ($vars['rows'] as $row): ?>
            <?php
                $title = T("Troops", $row['unitId'] . ".title");
                // Medium/small decoy icons are tribe-less; section art is tribe-keyed for every ship.
                $iconTribe = (int)$row['type'] === 3 ? '' : 'tribe' . (int)$vars['tribe'];
            ?>
            <div class="action<?= $row['type'] == 1 ? ' first' : ''; ?> troop">
                <div class="innerTroopWrapper">
                    <div class="bigUnitSection">
                        <img class="unitSection tribe<?= (int)$vars['tribe']; ?> u<?= (int)$row['unitId']; ?>Section"
                             src="/img/spacer.gif"
                             alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="details">
                        <div class="tit">
                            <i class="unit_small <?= $iconTribe; ?> t<?= (int)$row['unitId']; ?>"></i>
                            <?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8'); ?>
                            <span class="furtherInfo">(<?= T("inGame", "available"); ?> <?= number_format_x($row['home']); ?>)</span>
                        </div>
                        <div class="inlineIconList resourceWrapper">
                            <div class="inlineIcon resource"><i class="r1Big"></i><span class="value value"><?= $row['cost'][0]; ?></span></div>
                            <div class="inlineIcon resource"><i class="r2Big"></i><span class="value value"><?= $row['cost'][1]; ?></span></div>
                            <div class="inlineIcon resource"><i class="r3Big"></i><span class="value value"><?= $row['cost'][2]; ?></span></div>
                            <div class="inlineIcon resource"><i class="r4Big"></i><span class="value value"><?= $row['cost'][3]; ?></span></div>
                        </div>
                        <br/>
                        <div class="inlineIcon duration"><div class="iconWrapper"><i class="clock_medium"></i></div><span class="value"><?= $row['duration']; ?></span></div>
                        <p class="shipCarries">
                            <?php if ($row['type'] == 1): ?>
                                <?= T("inGame", "ship_carries_resources"); ?>
                            <?php elseif ($row['type'] == 2): ?>
                                <?= T("inGame", "ship_carries_any"); ?>
                            <?php else: ?>
                                <?= sprintf(T("inGame", "ship_carries_units"), $row['units']); ?>
                            <?php endif; ?>
                        </p>
                        <div class="cta">
                            <span class="value"><?= T("inGame", "Amount"); ?></span>
                            <input type="text" inputmode="numeric" class="text" name="t<?= $row['type']; ?>" value="0" maxlength="<?= max(strlen((string)$row['max']), 4); ?>"/><span class="value"> / </span>
                            <a href="#" onclick="jQuery(this).closest('div.details').find('input').val(<?= $row['max']; ?>); return false;"><?= number_format_x($row['max']); ?></a>
                        </div>
                    </div>
                    <div class="clear"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?= getButton(
        ['type' => 'submit', 'value' => 'ok', 'name' => 's1', 'id' => 's1', 'class' => 'textButtonV1 green startTraining'],
        ['data' => ['type' => 'submit', 'value' => 'ok', 'name' => 's1']],
        T('inGame', 'train')
    ); ?>
</form>
<?php if ($vars['queue'] !== ''): ?>
    <h4 class="round spacer"><?= T("inGame", "in_building_ships"); ?></h4>
    <table cellpadding="1" cellspacing="1" class="under_progress">
        <thead>
        <tr>
            <td><?= T("inGame", "Amount"); ?></td>
            <td><?= T("Global", "General.duration"); ?></td>
            <td><?= T("Global", "General.endat"); ?></td>
        </tr>
        </thead>
        <tbody>
        <?= $vars['queue']; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php $anyHome = false; foreach ($vars['rows'] as $row) { $anyHome = $anyHome || $row['home'] > 0; } ?>
<?php if ($anyHome): ?>
    <h4 class="round spacer"><?= T("inGame", "destroy_ships"); ?></h4>
    <form method="post" action="/build.php?id=<?= $vars['index']; ?>">
        <input type="hidden" name="id" value="<?= $vars['index']; ?>"/>
        <input type="hidden" name="z" value="<?= $vars['checker']; ?>"/>
        <input type="hidden" name="destroy" value="1"/>
        <div class="shipDestroyAmount">
            <?php foreach ($vars['rows'] as $row): ?>
                <?php if ($row['home'] > 0): ?>
                    <i class="unit_small <?= $row['type'] == 3 ? '' : 'tribe' . $vars['tribe']; ?> t<?= $row['unitId']; ?>" title="<?= T("Troops", $row['unitId'] . ".title"); ?>"></i>
                    <input type="text" class="text" name="d<?= $row['type']; ?>" value="0" maxlength="<?= strlen((string)$row['home']); ?>"/>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="destroyShips" title="<?= T("inGame", "destroy_ships"); ?>">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 4h10l-1 10H4zM6 2h4l1 1H5z"/></svg>
        </button>
    </form>
<?php endif; ?>
