<?php
$unit = $vars['unit'];
$unitTitle = T("Troops", $unit['id'] . ".title");
$isNature = $unit['race'] === 4;
$isSpider = $unit['id'] === 32;
$resourceNames = [T("inGame", "resources.r1"), T("inGame", "resources.r2"), T("inGame", "resources.r3"), T("inGame", "resources.r4")];
?>
    <p>
        <a class="manualBack arrow back" onclick="Travian.Game.Manual.open()"><?=T("Manual", "toOverview");?></a>
    </p>

    <h1 class="titleInHeader">
        <img class="unit u<?=$unit['id'];?>" src="/img/spacer.gif" alt="<?=$unitTitle;?>" title="<?=$unitTitle;?>" />
        <?=$unitTitle;?>    <span class="tribe">(<?=T("Global", "races." . $unit['race']);?>)</span>
    </h1>

<?php if ($isSpider): ?>
        <div class="arachnophobiaWarning arachnophobiaMode">
            <div onclick="Travian.Game.Manual.toggleArachnophobiaMode();">
                <svg viewBox="0 0 19 18">
  <path d="M12.6 2.8c-1-.5-2.1-.7-3.1-.7-2.2 0-4.4 1.1-6.6 3.2-.9.9-1.8 2-2.5 3L0 9l.4.7c.7 1 1.5 2 2.3 2.9l.9-.9c-1.2-1.4-2-2.7-2-2.7s3.5-5.6 7.9-5.6c.7 0 1.4.1 2.1.4l1-1zm-1.9 1.9c-.6-.2-1.3-.2-2-.1-.8.2-1.6.6-2.2 1.2s-1 1.3-1.2 2.1c-.1.7-.1 1.4.1 2l5.3-5.2zm-2.4 8 5.3-5.3c.1.4.2.9.2 1.3 0 1.1-.4 2.2-1.2 2.9-.8.8-1.9 1.2-3 1.2-.5.1-.9 0-1.3-.1zM7 14c.8.4 1.6.6 2.5.6 4.4 0 7.9-5.6 7.9-5.6s-.9-1.5-2.4-3l.9-.9.2.2c.9.9 1.8 2 2.5 3l.4.7-.4.7c-.7 1.1-1.6 2.1-2.5 3-2.2 2.1-4.4 3.2-6.6 3.2-1.2 0-2.3-.3-3.5-.9l1-1zm-5 4L.2 16.2l16-16L18 2 2 18z"></path>
</svg>
<br />
                <?=T("Manual", "arachnophobiaActive");?><br />
                <span><?=T("Manual", "clickToReveal");?></span>
            </div>
        </div>
<?php endif; ?>
<?php if ($unit['race'] !== 5): ?>
    <div class="bigUnitSection <?=$isNature ? 'nature' : '';?> <?=$isSpider ? 'arachnophobiaMode' : '';?>">
        <a class="unitZoom" onclick="return Travian.Game.unitZoom(<?=$unit['id'];?>, '<?=htmlspecialchars(addslashes($unitTitle), ENT_QUOTES, 'UTF-8');?>');" title="<?=$unitTitle;?>||<?=T("Manual", "zoomImage");?>">
            <img class="unitSection u<?=$unit['id'];?>Section" src="/img/spacer.gif" alt="<?=$unitTitle;?>" />
        </a>
    </div>
<?php endif; ?>

    <div class="troopInfoWrapper">
        <table id="troop_info" cellpadding="1" cellspacing="1">
            <tbody>
<?php if (!$isNature): ?>
                <tr>
<?php foreach ($unit['cost'] as $resource => $amount): ?>
                    <td>
                        <div class="inlineIconList resourceWrapper"><div class="inlineIcon resources" title="<?=$resourceNames[$resource];?>"><i class="r<?=$resource + 1;?>"></i><span class="value "><?=$amount;?></span></div></div>
                    </td>
<?php endforeach; ?>
                </tr>
<?php endif; ?>
                <tr>
                    <td>
                        <img class="att_all" src="/img/spacer.gif" alt="<?=T("Manual", "attackValue");?>" title="<?=T("Manual", "attackValue");?>" /><?=$unit['attack_power'];?>
                    </td>
                    <td>
                        <img class="def_i" src="/img/spacer.gif" alt="<?=T("Manual", "defenceInfantry");?>" title="<?=T("Manual", "defenceInfantry");?>" /><?=$unit['def_inf'];?>
                    </td>
                    <td>
                        <img class="def_c" src="/img/spacer.gif" alt="<?=T("Manual", "defenceCavalry");?>" title="<?=T("Manual", "defenceCavalry");?>" /><?=$unit['def_cav'];?>
                    </td>
                    <td>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="troopDataWrapper">
        <table class="troopData" cellpadding="1" cellspacing="1">
            <tbody>
                <tr>
                    <th><?=T("Manual", "velocity");?></th>
                    <td><?=$unit['speed'];?> <?=T("Manual", "fieldsHour");?></td>
                </tr>
<?php if (!$isNature): ?>
                <tr>
                    <th><?=T("Manual", "canCarry");?></th>
                    <td><?=$unit['carry'];?> <?=T("Manual", "carryResources");?></td>
                </tr>
                <tr>
                    <th><?=T("Manual", "upkeep");?></th>
                    <td><div class="inlineIconList resourceWrapper"><div class="inlineIcon resources" title="<?=T("Manual", "freeCrop");?>"><i class="r5"></i><span class="value "><?=$unit['cu'];?></span></div></div></td>
                </tr>
                <tr>
                    <th><?=T("Manual", "trainingDuration");?></th>
                    <td><div class="inlineIcon clocks" title="<?=T("Manual", "duration");?>"><i class="clock"></i><span class="value "><?=secondsToString($unit['trainingTime'], true);?></span></div></td>
                </tr>
<?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="clear"></div>

<?php if ($vars['description'] !== ''): ?>
    <div id="t_desc"><?=$vars['description'];?></div>
<?php endif; ?>
<?php if ($vars['preRequests'] !== ''): ?>
    <div id="prereqs"><strong><?=T("Manual", "preRequests");?></strong><br/><?=$vars['preRequests'];?></div>
<?php endif; ?>
