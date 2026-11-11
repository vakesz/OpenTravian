<div id="info">
<h4 class="roundV2 details"><?=T("Embassy", "Found alliance"); ?></h4>
<form method="post" action="<?= htmlspecialchars($vars['formAction'], ENT_QUOTES, 'UTF-8'); ?>">
    <table cellpadding="1" cellspacing="1" id="found"
           class="transparent">
        <tbody>
        <tr>
            <th><?=T("Buildings", "Tag"); ?></th>
            <td class="tag">
                <input class="tag text " name="newTag"
                       value="<?= htmlspecialchars((string) $vars['tag'], ENT_QUOTES, 'UTF-8'); ?>"
                       maxlength="5">
                <span
                    class="error"><?=$vars['errorTag']; ?></span>
            </td>
        </tr>
        <tr>
            <th><?=T("Buildings", "Name"); ?></th>
            <td class="nam">
                <input class="name text " name="newName"
                       value="<?= htmlspecialchars((string) $vars['name'], ENT_QUOTES, 'UTF-8'); ?>"
                       maxlength="15">
                <span
                    class="error"><?=$vars['errorName']; ?></span>
            </td>
        </tr>
        </tbody>
    </table>
    <p>
        <?= getButton(
            [
                'type' => 'submit',
                'value' => T('Global', 'General.ok'),
                'name' => 's1',
                'id' => 's1',
                'class' => 'textButtonV1 green ',
                'version' => 'textButtonV1',
                'title' => T('Global', 'General.ok'),
            ],
            ['data' => ['type' => 'submit', 'value' => T('Global', 'General.ok'), 'name' => 's1', 'version' => 'textButtonV1']],
            T('Global', 'General.ok')
        ); ?>
    </p>
</form>
</div>
