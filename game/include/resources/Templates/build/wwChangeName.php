<h4><?=T("WWChangeName", "Wonder of the world name"); ?></h4>
<form method="post" action="/build.php">
    <table cellpadding="1" cellspacing="1" id="rename" class="transparent">
        <tbody>
        <tr>
            <td colspan="2"><?=T("WWChangeName", "What do you want Wonder of the world name to be?");?></td>
        </tr>
        <tr>
            <th><?=T("WWChangeName", "Name");?>:</th>
            <td>
                <?php
                $disabled = !getDisplay("allowWWUnusualRename") && ($vars['level'] > 10);
                ?>
                <input type="text" class="text" name="ww_name" <?=($disabled ? 'disabled="disabled"' : '');?> value="<?=$vars['WWName']; ?>" maxlength="20">
            </td>
        </tr>
    </table>
    <p>
        <?php if(!$disabled):?>
        <input type="hidden" name="id" value="99">
        <?= getButton(
            ['type' => 'submit', 'value' => 'ok', 'name' => 's1', 'id' => 's1', 'class' => 'textButtonV1 green', 'title' => T('Global', 'General.save')],
            ['data' => ['type' => 'submit', 'value' => 'ok', 'name' => 's1']],
            T('Global', 'General.save')
        ); ?>
        <?php endif;?>
        <span class="info">(<?=T("WWChangeName", "Can be changed until level 11");?></span>
    </p>
</form>
