<?php

declare(strict_types=1);

if ($vars['canTrain']): ?>
    <?php if ($vars['expansion']): ?>
        <div class="trainExpansionUnits">
    <?php endif; ?>
    <?php if ((int) $vars['item_id'] !== 36): ?>
        <h4 class="round"><?= T('inGame', 'train_troops'); ?></h4>
    <?php else: ?>
        <?= $vars['trap_desc']; ?>
    <?php endif; ?>
    <form method="post" name="snd" action="/build.php?id=<?= (int) $vars['index']; ?>&amp;gid=<?= (int) $vars['item_id']; ?>">
        <div class="buildActionOverview trainUnits">
            <input type="hidden" name="action" value="trainTroops"/>
            <input type="hidden" name="checksum" value="<?= htmlspecialchars((string) $vars['checker'], ENT_QUOTES, 'UTF-8'); ?>"/>
            <?php if ((int) $vars['item_id'] !== 36): ?>
                <input type="hidden" name="s" value="1"/>
            <?php endif; ?>
            <input type="hidden" name="did" value="<?= (int) $vars['did']; ?>"/>
            <?php if ($vars['expansion']): ?>
                <?= $vars['wrappers']; ?>
            <?php elseif ((int) $vars['item_id'] !== 36): ?>
                <div id="favouriteTroops">
                    <?php foreach ($vars['favouriteSlots'] as $troopKey): ?>
                        <div class="action troop troop<?= htmlspecialchars((string) $troopKey, ENT_QUOTES, 'UTF-8'); ?> empty"></div>
                    <?php endforeach; ?>
                </div>
                <div id="nonFavouriteTroops">
                    <?= $vars['wrappers']; ?>
                    <?php foreach ($vars['emptySlots'] as $troopKey): ?>
                        <div class="action troop troop<?= htmlspecialchars((string) $troopKey, ENT_QUOTES, 'UTF-8'); ?> empty"></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= $vars['wrappers']; ?>
            <?php endif; ?>
        </div>
        <?= getButton(
            ['type' => 'submit', 'value' => 'ok', 'name' => 's1', 'id' => 's1', 'class' => 'textButtonV1 green startTraining' . ($vars['expansion'] && !$vars['canSubmit'] ? ' disabled' : '')],
            ['data' => ['type' => 'submit', 'value' => 'ok', 'name' => 's1']],
            T('inGame', 'train')
        ); ?>
        <?= $vars['finishTraining_button']; ?>
    </form>
    <?php if ($vars['expansion']): ?>
        </div>
    <?php endif; ?>
    <?php if ((int) $vars['item_id'] !== 36): ?>
        <script type="text/javascript">
            jQuery(function () {
                Travian.Game.TrainingTroops.initialize();
            });
        </script>
    <?php endif; ?>
<?php else: ?>
    <h4 class="round"><?= T('inGame', 'train_troops'); ?></h4>
    <span class="errorMessage"><?= T('inGame', 'noTroops'); ?></span>
<?php endif; ?>
<?php if ($vars['isTraining']): ?>
    <h4 class="round spacer"><?= T('inGame', (int) $vars['item_id'] === 36 ? 'in_creating' : 'in_training'); ?></h4>
    <table cellpadding="1" cellspacing="1" class="under_progress">
        <thead>
        <tr>
            <td><?= T('inGame', 'Amount'); ?></td>
            <td><?= T('Global', 'General.duration'); ?></td>
            <td><?= T('Global', 'General.endat'); ?></td>
        </tr>
        </thead>
        <tbody>
        <?= $vars['training']; ?>
        </tbody>
    </table>
<?php endif; ?>
