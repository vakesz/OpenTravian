<br />
<hr />
<form method="GET" action="/build.php">
    <input type="hidden" name="id" value="<?=$vars['id'];?>">
    <table class="row_table_data" cellpadding="1" cellspacing="1">
        <thead>
            <tr>
                <th colspan="2"><?=T("Global", "Building settings");?></th>
            </tr>
        </thead>
        <tr>
            <td><?=T("Global", "General.level");?>:</td>
            <td><input name="new_building_level" type="number" class="text" value="<?=$vars['level'];?>"></td>
        </tr>
        <tr>
            <td></td>
            <td>
                <?= getButton(
                    ['type' => 'submit', 'class' => 'textButtonV1 green build'],
                    ['data' => ['type' => 'submit']],
                    T('Global', 'General.save')
                ); ?>
            </td>
        </tr>
    </table>
</form>
