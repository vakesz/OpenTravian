<h4 class="round"><?=T("inGame", "Hold a celebration"); ?></h4>
<div class="build_details researches">
    <div class="research">
        <div class="information">
            <div class="title">
                <a href="#" onclick="Travian.Game.Manual.open('building', 35); return false;">
                    <img class="celebration celebrationSmall" src="/img/spacer.gif"
                         alt="<?=T("inGame", "run celebration");?>"
                         title="<?=T("inGame", "run celebration");?>">
                </a>
                <a href="#" onclick="Travian.Game.Manual.open('building', 35); return false;"><?=T("inGame", "run celebration");?></a>
            </div>
            <div class="inlineIconList resourceWrapper">
                <div class="inlineIcon resource"><i class="r1Big"></i><span class="value value"><?=number_format_x($vars['festivalResources'][0]); ?></span></div>
                <div class="inlineIcon resource"><i class="r2Big"></i><span class="value value"><?=number_format_x($vars['festivalResources'][1]); ?></span></div>
                <div class="inlineIcon resource"><i class="r3Big"></i><span class="value value"><?=number_format_x($vars['festivalResources'][2]); ?></span></div>
                <div class="inlineIcon resource"><i class="r4Big"></i><span class="value value"><?=number_format_x($vars['festivalResources'][3]); ?></span></div>
            </div>
            <div class="cta">
                <div class="inlineIcon duration"><i class="clock_medium"></i><span class="value "><?=secondsToString($vars['festivalDuration']); ?></span></div>
                <?=$vars['npcButton']; ?>
                <br />
                <?=$vars['contractLinkButton']; ?>
            </div>
        </div>
        <div class="clear"></div>
    </div>
</div>
<?php if ($vars['isFestival']): ?>
    <h4 class="round spacer"><?=T(
            "inGame", 'celebrationRunning'
        ); ?></h4>
    <table class="under_progress" cellpadding="1" cellspacing="1">
        <thead>
        <tr>
            <td><?=T("inGame", "festival");?></td>
            <td><?=T("Global", "General.duration"); ?></td>
            <td><?=T("Global", "General.endat"); ?></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td class="desc"><?=T("inGame", "run celebration");?></td>
            <td class="dur"><?=$vars['timeLeft']; ?></td>
            <td class="fin"><?=$vars['endat']; ?></td>
        </tr>
        </tbody>
    </table>
<?php endif; ?>
