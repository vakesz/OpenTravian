<form method="post" action="<?= htmlspecialchars((string) $vars['forumRoute'], ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
    <input type="hidden" name="s" value="2"/>
    <input type="hidden" name="tid" value="<?=$vars['tid'];?>"/>
    <input type="hidden" name="edittopic" value="1"/>
    <input type="hidden" name="admin" value="1"/>
    <h4 class="round"><?=T("Alliance", "edit topic");?></h4>
    <table cellpadding="1" cellspacing="1" class="transparent" id="edit_topic">
        <tbody>
        <tr>
            <th><?=T("Alliance", "Thread");?>:</th>
            <td><input class="text" type="text" name="thema" value="<?=htmlspecialchars($vars['name'], ENT_QUOTES, 'UTF-8');?>" maxlength="35"/></td>
        </tr>
        <tr>
            <th><?=T("Alliance", "move topic");?>:</th>
            <td>
                <select class="dropdown" name="fid">
                    <?=$vars['options'];?>
                </select>
            </td>
        </tr>
        </tbody>
    </table>
    <div class="spacer"></div>
    <button type="submit" value="<?=T("Global", "General.ok");?>" name="s1" id="btn_ok" class="textButtonV1 green " version="textButtonV1"><?=T("Global", "General.ok");?></button>
    <script type="text/javascript">
        jQuery(function() {
            if (jQuery('#btn_ok').length > 0) {
                jQuery('#btn_ok').click(function (event) {
                    jQuery(window).trigger('buttonClicked', [this, {
                        "type": "submit",
                        "value": "<?=T("Global", "General.ok");?>",
                        "name": "s1",
                        "id": "btn_ok",
                        "class": "textButtonV1 green ",
                        "title": "",
                        "confirm": "",
                        "onclick": "",
                        "version": "textButtonV1"
                    }]);
                });
            }
        });
    </script>
</form>
