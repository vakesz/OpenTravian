<?php
$gid = (int)$vars['building']['gid'];
$title = T("Buildings", $gid . ".title");
$charge = static fn ($value): string => '&#x202d;' . number_format_x($value) . '&#x202c;';
?>
<div class="cf">
    <p>
        <a class="manualBack arrow back" onclick="Travian.Game.Manual.open()"><?=T("Manual", "toOverview");?></a>
    </p>

    <h1 class="titleInHeader"><i class="building_small tribe3 type<?=$gid;?>"></i> <?=$title;?></h1>
    <img class="building big g<?=$gid;?> <?=$vars['building']['artTribe'];?>" src="/img/spacer.gif" alt="<?=$title;?>" title="<?=$title;?>"/>
    <p class="description"><?=T("Buildings", $gid . ".desc");?></p>
    <p class="costsHeader"><b><?=T("Buildings", "costs");?></b> <?=T("Manual", "and");?>
        <b><?=mb_strtolower(T("Manual", "construction_time"));?></b> <?=T("Manual", "for");?> <?=T("Manual", "level");?> 1:<br />
    <div class="inlineIconList resourceWrapper charges">
        <div class="inlineIcon resource" title="<?=T("inGame", "resources.r1");?>"><i class="r1Big"></i><span class="value value"><?=$charge($vars['building']['cost'][0]);?></span></div>
        <div class="inlineIcon resource" title="<?=T("inGame", "resources.r2");?>"><i class="r2Big"></i><span class="value value"><?=$charge($vars['building']['cost'][1]);?></span></div>
        <div class="inlineIcon resource" title="<?=T("inGame", "resources.r3");?>"><i class="r3Big"></i><span class="value value"><?=$charge($vars['building']['cost'][2]);?></span></div>
        <div class="inlineIcon resource" title="<?=T("inGame", "resources.r4");?>"><i class="r4Big"></i><span class="value value"><?=$charge($vars['building']['cost'][3]);?></span></div>
        <div class="inlineIcon resource" title="<?=T("Manual", "freeCrop");?>"><i class="cropConsumptionBig"></i><span class="value value"><?=$charge($vars['building']['cu']);?></span></div>
    </div><br />
    <div class="inlineIcon duration" title="<?=T("Manual", "duration");?>"><div class="iconWrapper"><i class="clock_medium"></i></div><span class="value "><?=secondsToString($vars['building']['upTime']);?></span></div>
    <div id="prereqs"><b><?=T("Manual", "preRequests");?></b><br/><?=$vars['preRequests'];?></div>
</div>
