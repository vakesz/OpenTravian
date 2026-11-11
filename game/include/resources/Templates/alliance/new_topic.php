<form method="post" name="post" action="<?= htmlspecialchars((string) $vars['forumRoute'], ENT_QUOTES, 'UTF-8') ?>"><?=\Core\Session::getCheckerInput();?>
    <input type="hidden" name="a" value="1"/>
    <input type="hidden" name="s" value="2"/>
    <input type="hidden" name="aid" value="<?=$vars['aid'];?>"/>
    <input type="hidden" name="fid" value="<?=$vars['fid'];?>"/>
    <input type="hidden" name="ac" value="newtopic"/>
    <input type="hidden" name="checkstr" value="<?=$vars['checkstr'];?>"/>
    <h4 class="round"><?=T("Alliance", "Post new thread");?></h4>
    <table class="transparent" id="new_topic">
        <tbody>
        <tr>
            <th><?=T("Alliance", "Thread");?>:</th>
            <td colspan="2"><input class="text" type="text" name="thema" maxlength="35"
                                   value="<?=htmlspecialchars($vars['thema'], ENT_QUOTES, 'UTF-8');?>"/></td>
        </tr>
        <tr>
            <td colspan="3">

                <div id="text_container" class="bbEditor">
                    <div class="boxes boxesColor gray">
                        <div class="boxes-tl"></div>
                        <div class="boxes-tr"></div>
                        <div class="boxes-tc"></div>
                        <div class="boxes-ml"></div>
                        <div class="boxes-mr"></div>
                        <div class="boxes-mc"></div>
                        <div class="boxes-bl"></div>
                        <div class="boxes-br"></div>
                        <div class="boxes-bc"></div>
                        <div class="boxes-contents cf">
                            <div id="text_toolbar" class="bbToolbar">
                                <button type="button" class="icon bbButton bbBold bbType{d} bbTag{b}" title="bold">
                                    <img src="/img/spacer.gif" class="bbBold" alt="bbBold"/>
                                </button>
                                <button type="button" class="icon bbButton bbItalic bbType{d} bbTag{i}" title="italic">
                                    <img src="/img/spacer.gif" class="bbItalic" alt="bbItalic"/>
                                </button>
                                <button type="button" class="icon bbButton bbUnderscore bbType{d} bbTag{u}"
                                        title="underline">
                                    <img src="/img/spacer.gif" class="bbUnderscore" alt="bbUnderscore"/>
                                </button>
                                <button type="button" class="icon bbButton bbAlliance bbType{d} bbTag{alliance}"
                                        title="<?=T("Alliance", "Alliance");?>">
                                    <img src="/img/spacer.gif" class="bbAlliance" alt="bbAlliance"/>
                                </button>
                                <button type="button" class="icon bbButton bbPlayer bbType{d} bbTag{player}"
                                        title="<?=T("Alliance", "Player");?>">
                                    <img src="/img/spacer.gif" class="bbPlayer" alt="bbPlayer"/>
                                </button>
                                <button type="button" class="icon bbButton bbCoordinate bbType{d} bbTag{x|y}"
                                        title="<?=T("Alliance", "Coordinates");?>">
                                    <img src="/img/spacer.gif" class="bbCoordinate" alt="bbCoordinate"/>
                                </button>
                                <button type="button" class="icon bbButton bbReport bbType{d} bbTag{report}"
                                        title="<?=T("Alliance", "report");?>">
                                    <img src="/img/spacer.gif" class="bbReport" alt="bbReport"/>
                                </button>
                                <button type="button" id="text_resourcesButton"
                                        class="icon bbButton bbResource bbWin{resources}"
                                        title="<?=T("inGame", "resources.resources");?>">
                                    <img src="/img/spacer.gif" class="bbResource" alt="bbResource"/>
                                </button>
                                <button type="button" id="text_smiliesButton"
                                        class="icon bbButton bbSmilies bbWin{smilies}" title="smilies">
                                    <img src="/img/spacer.gif" class="bbSmilies" alt="bbSmilies"/>
                                </button>
                                <button type="button" id="text_troopsButton" class="icon bbButton bbTroops bbWin{troops}"
                                        title="<?=T("Alliance", "Troops");?>">
                                    <img src="/img/spacer.gif" class="bbTroops" alt="bbTroops"/>
                                </button>
                                <button type="button" id="text_previewButton" class="icon bbButton bbPreview"
                                        title="<?=T("Alliance", "preview");?>">
                                    <img src="/img/spacer.gif" class="bbPreview" alt="bbPreview"/>
                                </button>
                                <div class="clear"></div>
                                <div id="text_toolbarWindows" class="bbToolbarWindow">
                                    <div id="text_resources">
                                        <a href="#" class="bbType{o} bbTag{l}">
                                            <img src="/img/spacer.gif" class="r1" title="<?=T("inGame", "resources.r1");?>"
                                                 alt="<?=T("inGame", "resources.r1");?>"/>
                                        </a>
                                        <a href="#" class="bbType{o} bbTag{cl}">
                                            <img src="/img/spacer.gif" class="r2" title="<?=T("inGame", "resources.r2");?>"
                                                 alt="<?=T("inGame", "resources.r2");?>"/>
                                        </a>
                                        <a href="#" class="bbType{o} bbTag{c}">
                                            <img src="/img/spacer.gif" class="r4" title="<?=T("inGame", "resources.r3");?>"
                                                 alt="<?=T("inGame", "resources.r3");?>"/>
                                        </a>
                                        <a href="#" class="bbType{o} bbTag{ir}">
                                            <img src="/img/spacer.gif" class="r3" title="<?=T("inGame", "resources.r4");?>"
                                                 alt="<?=T("inGame", "resources.r4");?>"/>
                                        </a>
                                    </div>
                                    <div id="text_smilies">
                                        <a href="#" class="bbType{s} bbTag{*aha*}">
                                            <img class="smiley aha" src="/img/spacer.gif" alt="*aha*" title="*aha*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*angry*}">
                                            <img class="smiley angry" src="/img/spacer.gif" alt="*angry*" title="*angry*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*cool*}">
                                            <img class="smiley cool" src="/img/spacer.gif" alt="*cool*" title="*cool*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*cry*}">
                                            <img class="smiley cry" src="/img/spacer.gif" alt="*cry*" title="*cry*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*cute*}">
                                            <img class="smiley cute" src="/img/spacer.gif" alt="*cute*" title="*cute*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*depressed*}">
                                            <img class="smiley depressed" src="/img/spacer.gif" alt="*depressed*"
                                                 title="*depressed*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*eek*}">
                                            <img class="smiley eek" src="/img/spacer.gif" alt="*eek*" title="*eek*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*ehem*}">
                                            <img class="smiley ehem" src="/img/spacer.gif" alt="*ehem*" title="*ehem*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*emotional*}">
                                            <img class="smiley emotional" src="/img/spacer.gif" alt="*emotional*"
                                                 title="*emotional*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{:D}">
                                            <img class="smiley grin" src="/img/spacer.gif" alt=":D" title=":D"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{:)}">
                                            <img class="smiley happy" src="/img/spacer.gif" alt=":)" title=":)"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*hit*}">
                                            <img class="smiley hit" src="/img/spacer.gif" alt="*hit*" title="*hit*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*hmm*}">
                                            <img class="smiley hmm" src="/img/spacer.gif" alt="*hmm*" title="*hmm*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*hmpf*}">
                                            <img class="smiley hmpf" src="/img/spacer.gif" alt="*hmpf*" title="*hmpf*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*hrhr*}">
                                            <img class="smiley hrhr" src="/img/spacer.gif" alt="*hrhr*" title="*hrhr*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*huh*}">
                                            <img class="smiley huh" src="/img/spacer.gif" alt="*huh*" title="*huh*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*lazy*}">
                                            <img class="smiley lazy" src="/img/spacer.gif" alt="*lazy*" title="*lazy*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*love*}">
                                            <img class="smiley love" src="/img/spacer.gif" alt="*love*" title="*love*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*nocomment*}">
                                            <img class="smiley nocomment" src="/img/spacer.gif" alt="*nocomment*"
                                                 title="*nocomment*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*noemotion*}">
                                            <img class="smiley noemotion" src="/img/spacer.gif" alt="*noemotion*"
                                                 title="*noemotion*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*notamused*}">
                                            <img class="smiley notamused" src="/img/spacer.gif" alt="*notamused*"
                                                 title="*notamused*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*pout*}">
                                            <img class="smiley pout" src="/img/spacer.gif" alt="*pout*" title="*pout*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*redface*}">
                                            <img class="smiley redface" src="/img/spacer.gif" alt="*redface*"
                                                 title="*redface*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*rolleyes*}">
                                            <img class="smiley rolleyes" src="/img/spacer.gif" alt="*rolleyes*"
                                                 title="*rolleyes*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{:(}">
                                            <img class="smiley sad" src="/img/spacer.gif" alt=":(" title=":("/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*shy*}">
                                            <img class="smiley shy" src="/img/spacer.gif" alt="*shy*" title="*shy*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*smile*}">
                                            <img class="smiley smile" src="/img/spacer.gif" alt="*smile*" title="*smile*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*tongue*}">
                                            <img class="smiley tongue" src="/img/spacer.gif" alt="*tongue*" title="*tongue*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*veryangry*}">
                                            <img class="smiley veryangry" src="/img/spacer.gif" alt="*veryangry*"
                                                 title="*veryangry*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{*veryhappy*}">
                                            <img class="smiley veryhappy" src="/img/spacer.gif" alt="*veryhappy*"
                                                 title="*veryhappy*"/>
                                        </a>
                                        <a href="#" class="bbType{s} bbTag{;)}">
                                            <img class="smiley wink" src="/img/spacer.gif" alt=";)" title=";)"/>
                                        </a>
                                    </div>
                                    <div id="text_troops">
                                        <?php for ($i = 1; $i <= 50; ++$i):?>
                                            <a href="#" class="bbType{o} bbTag{tid<?=$i;?>}">
                                                <img class="unit u<?=$i;?>" src="/img/spacer.gif"
                                                     title="<?=T("Troops", $i . ".title");?>"
                                                     alt="<?=T("Troops", $i . ".title");?>"/>
                                            </a>
                                        <?php endfor;?>
                                        <a href="#" class="bbType{o} bbTag{hero}">
                                            <img class="unit uhero" src="/img/spacer.gif"
                                                 title="<?=T("Troops", "98.title");?>"
                                                 alt="<?=T("Troops", "98.title");?>"/>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="line bbLine"></div>
                    <textarea id="text" name="text" cols="1" rows="1"><?=htmlspecialchars($vars['text'], ENT_QUOTES, 'UTF-8');?></textarea>

                    <div id="text_preview"></div>
                </div>

                <script type="text/javascript">
                    jQuery(function() {
                        new Travian.Game.BBEditor("text");
                    });
                </script>

            </td>
        </tr>
        <tr>
            <th><?=T("Alliance", "Survey");?>:</th>
            <td>
                <script language="JavaScript" type="text/javascript">
                    <!--
                    function vote() {
                        if (document.post.umfrage.checked == true) {
                            document.post.umfrage_thema.disabled = false;
                            document.getElementById('options').className = '';
                            document.post.umfrage_thema.focus();

                        } else {
                            document.post.umfrage_thema.disabled = true;
                            document.getElementById('options').className = 'hide';
                        }
                    }
                    //-->
                </script>
                <input class="text" type="text" name="umfrage_thema" value="<?=htmlspecialchars($vars['Survey'], ENT_QUOTES, 'UTF-8');?>" maxlength="60"
                       disabled="disabled"/></td>
            <td class="sel"><input class="check" type="checkbox" name="umfrage" value="1" onclick="vote();"/>
            </td>
        </tr>
        <tr id="options" class="hide">
            <th><?=T("Alliance", "Vote_options");?></th>
            <td>
                <input class="text" type="text" value="<?=htmlspecialchars($vars['option_1'], ENT_QUOTES, 'UTF-8');?>" name="option_1" maxlength="60"/>
                <input class="text" type="text" value="<?=htmlspecialchars($vars['option_2'], ENT_QUOTES, 'UTF-8');?>" name="option_2" maxlength="60"/>
                <input class="text" type="text" value="<?=htmlspecialchars($vars['option_3'], ENT_QUOTES, 'UTF-8');?>" name="option_3" maxlength="60"/>
                <input class="text" type="text" value="<?=htmlspecialchars($vars['option_4'], ENT_QUOTES, 'UTF-8');?>" name="option_4" maxlength="60"/>
                <input class="text" type="text" value="<?=htmlspecialchars($vars['option_5'], ENT_QUOTES, 'UTF-8');?>" name="option_5" maxlength="60"/>
                <input class="text" type="text" value="<?=htmlspecialchars($vars['option_6'], ENT_QUOTES, 'UTF-8');?>" name="option_6" maxlength="60"/>
                <input class="text" type="text" value="<?=htmlspecialchars($vars['option_7'], ENT_QUOTES, 'UTF-8');?>" name="option_7" maxlength="60"/>
                <input class="text" type="text" value="<?=htmlspecialchars($vars['option_8'], ENT_QUOTES, 'UTF-8');?>" name="option_8" maxlength="60"/>
            </td>
            <td></td>
        </tr>
        <tr>
            <th><?=T("Alliance", "ends on");?></th>
            <td>
                <script language="JavaScript" type="text/javascript">
                    <!--
                    function voteEnd() {
                        if (document.post.umfrage_ende.checked == true) {
                            document.post.month.disabled = false;
                            document.post.day.disabled = false;
                            document.post.year.disabled = false;
                            document.post.hour.disabled = false;
                            document.post.minute.disabled = false;
                        } else {
                            document.post.month.disabled = true;
                            document.post.day.disabled = true;
                            document.post.year.disabled = true;
                            document.post.hour.disabled = true;
                            document.post.minute.disabled = true;
                        }
                    }
                    //-->
                </script>
                <select class="dropdown" name="day" disabled="disabled">
                    <?=$vars['days'];?>
                </select>
                <select class="dropdown" name="month" disabled="disabled">
                    <?=$vars['month'];?>
                </select>
                <select class="dropdown" name="year" disabled="disabled">
                    <?=$vars['year'];?>
                </select>

                &nbsp;&nbsp;&nbsp;
                <select class="dropdown" name="hour" disabled="disabled">
                    <?=$vars['hour'];?>
                </select>
                <select class="dropdown" name="minute" disabled="disabled">
                    <?=$vars['minute'];?>
                </select></td>
            <td class="sel">
                <input class="check" type="checkbox" name="umfrage_ende" onclick="voteEnd();"/>
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
