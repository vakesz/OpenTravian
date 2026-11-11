<?php $forumRoute = (string) $vars['forumRoute']; ?>
<h4>
    <a href="<?= htmlspecialchars($forumRoute, ENT_QUOTES, 'UTF-8') ?>"><?=$vars['areaName'];?></a>
    <a class="arrowForum" href="<?= htmlspecialchars($forumRoute . '?fid=' . (int) $vars['fid'], ENT_QUOTES, 'UTF-8') ?>"><?=$vars['forumName'];?></a>
</h4>
<table cellpadding="1" cellspacing="1" id="topics">
    <thead>
    <tr>
        <th></th>
        <th><?=T("Alliance", "Threads");?></th>
        <th><?=T("Alliance", "answer(s)");?></th>
        <th><?=T("Alliance", "Last post");?></th>
    </tr>
    </thead>
    <tbody>
    <?=$vars['content'];?>
    </tbody>
</table>
