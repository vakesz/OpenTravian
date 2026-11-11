<tr>
    <td class="sub">
        <div class="reportIcon <?=$vars['reportIcon'];?>"></div>
        <div class="reportText"><?=$vars['reportText'];?></div>
    </td>
    <td class="coords"><a class="" href="/karte.php?x=<?=$vars['x'];?>&amp;y=<?=$vars['y'];?>" title="<?=htmlspecialchars(sprintf(T('HeroGlobal', 'distanceTooltip'), $vars['distance']), ENT_QUOTES, 'UTF-8');?>">&#x202d;<span class="coordinates coordinatesWrapper coordinatesAligned coordinates<?=strtolower(getDirection());?>"><span class="coordinateX">(<?=coordinateNumberHTML($vars['x'])?></span><span class="coordinatePipe">|</span><span class="coordinateY"><?=coordinateNumberHTML($vars['y'])?>)</span></span>&#x202c;</a></td>
    <td class="dist"><?=$vars['distance'];?></td>
    <td class="dat"><?=$vars['date'];?></td>
</tr>
