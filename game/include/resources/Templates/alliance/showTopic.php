<?php $forumRoute = (string) $vars['forumRoute']; ?>
<h4>
    <a href="<?= htmlspecialchars($forumRoute, ENT_QUOTES, 'UTF-8') ?>"><?=$vars['areaName'];?></a>
    <a class="arrowForum" href="<?= htmlspecialchars($forumRoute . '?fid=' . (int) $vars['fid'], ENT_QUOTES, 'UTF-8') ?>"><?=$vars['forumName'];?></a>
</h4>
<h4 class="round"><?=$vars['thread'];?></h4>
<?php if($vars['showVotes']):?>
<table cellpadding="1" cellspacing="1" id="posts">
    <thead>
    <tr>
        <td colspan="2" class="poll">
            <form action="<?= htmlspecialchars($forumRoute, ENT_QUOTES, 'UTF-8') ?>" method="post"><?=\Core\Session::getCheckerInput();?>
                <input type="hidden" name="s" value="2"/>
                <input type="hidden" name="aid" value="<?=$vars['aid'];?>"/>
                <input type="hidden" name="seite" value="1"/>
                <input type="hidden" name="tid" value="<?=$vars['tid'];?>"/>
                <table cellpadding="0" cellspacing="0" id="poll">
                    <thead>
                    <tr>
                        <th colspan="3">
                            <?=T("Alliance", "Survey");?>: <?=$vars['SurveyName'];?> (<?=$vars['SurveyDate'];?>)
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?=$vars['options'];?>
                    <?php if($vars['ends'] and $vars['finished']==0):?>
                        <tr>
                            <td colspan="3"><?=$vars['end_time'];?><span></span></td>
                        </tr>
                    <?php elseif($vars['finished']==1 and $vars['ends']):?>
                        <tr>
                            <td colspan="3"><?=T("Alliance", "voting finished");?><span></span></td>
                        </tr>
                    <?php endif;?>
                    <tr>

                        <td colspan="3">

                            <?php if($vars['finished']==0 and $vars['close']==0 and $vars['results']==0 and $vars['voted']==0):?>
                                <button type="submit" value="1" name="vote" id="fbtn_vote" class="textButtonV1 green " version="textButtonV1"><?=T("Alliance", "vote");?></button>
                                <script type="text/javascript">
                                    jQuery(function() {
                                        if (jQuery('#fbtn_vote').length > 0) {
                                            jQuery('#fbtn_vote').click(function (event) {
                                                jQuery(window).trigger('buttonClicked', [this, {
                                                    "type": "submit",
                                                    "value": 1,
                                                    "name": "vote",
                                                    "id": "fbtn_vote",
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
                            <?php endif;?>
                            <?php if( ($vars['finished']==0 and $vars['close']==0) and $vars['results']==0):?>
                                <button type="submit" value="1" name="vote_ergebnis" id="fbtn_result" class="textButtonV1 green " version="textButtonV1"><?=T("Alliance", "to result");?></button>
                                <script type="text/javascript">
                                    jQuery(function() {
                                        if (jQuery('#fbtn_result').length > 0) {
                                            jQuery('#fbtn_result').click(function (event) {
                                                jQuery(window).trigger('buttonClicked', [this, {
                                                    "type": "submit",
                                                    "value": 1,
                                                    "name": "vote_ergebnis",
                                                    "id": "fbtn_result",
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
                            <?php endif;?>
                            <?php if( ($vars['finished']==1 or $vars['close']==1) and $vars['results']==0 and $vars['voted']==0):?>
                                <button type="submit" value="1" name="vote_ergebnis" id="fbtn_result" class="textButtonV1 green " version="textButtonV1"><?=T("Alliance", "to result");?></button>
                                <script type="text/javascript">
                                    jQuery(function() {
                                        if (jQuery('#fbtn_result').length > 0) {
                                            jQuery('#fbtn_result').click(function (event) {
                                                jQuery(window).trigger('buttonClicked', [this, {
                                                    "type": "submit",
                                                    "value": 1,
                                                    "name": "vote_ergebnis",
                                                    "id": "fbtn_result",
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
                            <?php endif;?>
                            <?php if($vars['close']==0 and $vars['results']==1 and $vars['finished']==0 and $vars['voted']==0):?>
                                <button type="submit" value="1" name="vote_abstimmung" id="fbtn_voting" class="textButtonV1 green " version="textButtonV1"><?=T("Alliance", "to survey");?></button>
                                <script type="text/javascript">
                                    jQuery(function() {
                                        if (jQuery('#fbtn_voting').length > 0) {
                                            jQuery('#fbtn_voting').click(function (event) {
                                                jQuery(window).trigger('buttonClicked', [this, {
                                                    "type": "submit",
                                                    "value": 1,
                                                    "name": "vote_abstimmung",
                                                    "id": "fbtn_voting",
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
                            <?php endif;?>

                        </td>
                    </tr>
                </table>
            </form>
        </td>
    </tr>
    <tr>
        <?php else:?>
        <table cellpadding="1" cellspacing="1" id="posts">
            <thead>
            <tr>
                <?php endif;?>
                <td><?=T("Alliance", "author");?></td>
                <td><?=T("Alliance", "messages");?></td>
            </tr>
            </thead>
            <tbody>
            <?=$vars['content'];?>
            </tbody>
        </table>
        <div class="spacer"></div>
        <?php if($vars['showReplyButton']):?>
            <div class="buttonBox">
                <button type="button" value="<?=T("Alliance", "reply");?>" id="fbtn_reply" class="textButtonV1 green " version="textButtonV1"
                        onclick="window.location.href = '<?= $forumRoute ?>?tid=<?=$vars['tid'];?>&amp;ac=newpost'; return false;"><?=T("Alliance", "reply");?></button>
                <script type="text/javascript">
                    jQuery(function() {
                        if (jQuery('#fbtn_reply').length > 0) {
                            jQuery('#fbtn_reply').click(function (event) {
                                jQuery(window).trigger('buttonClicked', [this, {
                                    "type": "button",
                                    "value": "<?=T("Alliance", "reply");?>",
                                    "name": "",
                                    "id": "fbtn_reply",
                                    "class": "textButtonV1 green ",
                                    "title": "",
                                    "confirm": "",
                                    "onclick": "window.location.href = \u0027<?= $forumRoute ?>?tid=<?=$vars['tid'];?>\u0026amp;ac=newpost\u0027; return false;",
                                    "version": "textButtonV1"
                                }]);
                            });
                        }
                    });
                </script>
            </div>
        <?php endif;?>
        <?=$vars['adminSwitchButton'];?>
<?=$vars['nav'];?>
