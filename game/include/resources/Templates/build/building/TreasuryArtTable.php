<table class="show_artefacts" cellpadding="1" cellspacing="1">
    <thead>
    <tr>
        <th></th>
        <th><?=T("Treasury", "Name");?></th>
        <th><?=T("Treasury", "Player");?></th>
        <?php if(isset($vars['dist'])):?>
            <th><?=T("Treasury", "Distance");?></th>
        <?php else:?>
            <th><?=T("Treasury", "Alliance");?></th>
            <th><?=T("Treasury", "Distance");?></th>
        <?php endif;?>
    </tr>
    </thead>
    <tbody>
    <?=$vars['content'];?>
    </tbody>
</table>