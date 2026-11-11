<?php if ($vars['hasWounded']): ?>
    <h4 class="round"><?=T("inGame", "heal_troops");?></h4>
    <form method="post" name="snd" action="/build.php?id=<?=$vars['index'];?>">
        <input type="hidden" name="id" value="<?=$vars['index'];?>"/>
        <input type="hidden" name="z" value="<?=$vars['checker'];?>"/>
        <input type="hidden" name="a" value="2"/>
        <input type="hidden" name="s" value="1"/>
        <div class="buildActionOverview trainUnits">
            <?=$vars['wrappers'];?>
        </div>
        <?= getButton(
            ['type' => 'submit', 'value' => 'ok', 'name' => 's1', 'id' => 's1', 'class' => 'textButtonV1 green startTraining'],
            ['data' => ['type' => 'submit', 'value' => 'ok', 'name' => 's1']],
            T('inGame', 'heal')
        ); ?>
    </form>
<?php else: ?>
    <h4 class="round"><?=T("inGame", "heal_troops");?></h4>
    <span class="errorMessage"><?=T("inGame", "no_wounded");?></span>
<?php endif;?>
<?php if ($vars['queue'] !== ''): ?>
    <h4 class="round spacer"><?=T("inGame", "in_healing");?></h4>
    <table cellpadding="1" cellspacing="1" class="under_progress">
        <thead>
        <tr>
            <td><?=T("inGame", "Amount");?></td>
            <td><?=T("Global", "General.duration");?></td>
            <td><?=T("Global", "General.endat");?></td>
        </tr>
        </thead>
        <tbody>
        <?=$vars['queue'];?>
        </tbody>
    </table>
<?php endif;?>
