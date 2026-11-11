<?php
/**
 * Shared message list (inbox, sent, archive).
 *
 * $vars: hasPermission, tab (0|2|3), basePath, dateLabel, recursive,
 *        nav, content, buttons (list of id/name/label)
 */
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$basePath = $escape($vars['basePath']);
$pageIndex = str_contains((string) $vars['content'], 'class="noData"') ? 0 : max(1, (int) ($_GET['page'] ?? 1));
$sortHref = $basePath . '?order=' . ($vars['recursive'] ? 'DESC' : 'ASC') . '&amp;page=' . $pageIndex;
?>
<form id="messagesForm" method="post" action="<?= $basePath; ?>?page=<?= $pageIndex; ?>" name="msg" autocomplete="off">
<?php foreach ([1, 2] as $position): ?>
<?php if ($position === 2): ?>
    <table cellpadding="1" cellspacing="1" id="overview" class="index">
        <thead>
        <tr>
            <th colspan="2"><?= T('Messages', 'Subject'); ?></th>
            <th class="send"></th>
            <th class="dat">
                <a href="<?= $sortHref; ?>" class="sorting <?= $vars['recursive'] ? 'asc' : 'desc'; ?>">
                    <?= $escape($vars['dateLabel']); ?>&nbsp;
                    <img src="/img/spacer.gif" alt="sort"/>
                </a>
            </th>
        </tr>
        </thead>
        <tbody>
        <?= $vars['content']; ?>
        </tbody>
    </table>
<?php endif; ?>
    <div class="footer">
        <?php if ($vars['hasPermission']): ?>
            <div id="markAll">
                <input class="check" type="checkbox" id="sAll<?= $position === 1 ? 1 : 2; ?>" onclick="messagesFormSelectAll(this);">
                <span><label for="sAll<?= $position === 1 ? 1 : 2; ?>"><?= T('Messages', 'select all'); ?></label></span>
            </div>
        <?php endif; ?>
        <?= $vars['nav']; ?>
        <div class="clear"></div>
    </div>
<?php endforeach; ?>
    <div class="administration">
        <div class="buttons">
            <?php if ($vars['hasPermission']): ?>
                <?php foreach ($vars['buttons'] as $button): ?>
                    <button type="submit" value="<?= $escape($button['value'] ?? $button['label']); ?>" name="<?= $escape($button['name']); ?>" id="<?= $escape($button['id']); ?>" class="textButtonV1 green " version="textButtonV1"><?= $escape($button['label']); ?></button>
                    <script type="text/javascript" id="<?= $escape($button['id']); ?>_script">
                        jQuery(function () {
                            jQuery('#<?= $escape($button['id']); ?>').click(function (event) {
                                jQuery(window).trigger('buttonClicked', [this, {
                                    "type": "submit",
                                    "value": <?= json_encode($button['label']); ?>,
                                    "name": <?= json_encode($button['name']); ?>,
                                    "id": <?= json_encode($button['id']); ?>,
                                    "class": "textButtonV1 green ",
                                    "title": "",
                                    "confirm": "",
                                    "onclick": "",
                                    "version": "textButtonV1"
                                }]);
                            });
                        });
                    </script>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</form>
