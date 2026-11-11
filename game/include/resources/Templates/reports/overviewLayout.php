<?php
$statusLabel = mb_strtolower(T("Reports", $vars['viewed'] ? "Read" : "Unread"));
// The row icon carries the reference's report_icon_N title (the filter titles), which is what the reference prints for every type it lists.
$typeTitle = T("Reports", "reportFilterTitles." . $vars['type'], T("Reports", "reportTypes." . $vars['type']));
?>
<tr>
    <td class="sel">
        <?php if($vars['hasPermission']):?>
            <input class="check report" type="checkbox" name="ids[]" value="<?=$vars['noticeId'];?>" onclick="Travian.Game.Reports.updateSelected()"/>
        <?php endif;?>
    </td>
    <td class="sub <?=$vars['viewed'] ? '' : ' newMessage';?>">
        <a href="#" onclick="Travian.Game.Reports.toggleReadStatus('<?=$vars['noticeId'];?>', <?=$vars['viewed'] ? 1 : 0;?>); return false;">
            <img src="/img/spacer.gif" class="messageStatus messageStatus<?=$vars['viewed'] ? "Read" : "Unread";?>" title="<?=$statusLabel;?>" alt="<?=$statusLabel;?>"/>
        </a>
        <img src="/img/spacer.gif" class="iReport iReport<?=$vars['type'];?> " title="<?=$typeTitle;?>" alt="<?=$typeTitle;?>"/>
        <?=$vars['reportIcon'];?>
        <div class="">
            <a<?=$vars['linkClass'] !== '' ? ' class="' . $vars['linkClass'] . '"' : '';?> href="<?= htmlspecialchars($vars['reportUrl'], ENT_QUOTES, 'UTF-8'); ?>"><?=$vars['subject'];?></a>
        </div>
        <div class="clear"></div>
    </td>
    <td class="dat"><?=$vars['date'];?></td>
</tr>
