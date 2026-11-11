<style type="text/css">
    .reportButton{
        width:16px;
        height:16px;
    }
    .warsim{background-position:0 -78px;}
    .raidList{background-position:0 -130px;}
    td.buttons {
        padding: 2px 0 2px 2px;
        text-align: <?=(getDirection() == 'RTL' ? 'left' : 'right');?>;
        white-space: nowrap;
        width: 1%;
    }
</style>
<h4 class="round"><?=T("Statistics", "tabs.farm"); ?></h4>
<table cellpadding="1" cellspacing="1" id="wonder">
    <thead>
    <tr>
        <td style="width: 3%;"></td>
        <td class="name" style="width: 25%;"><?=T("Profile", "Name"); ?></td>
        <td class="inhabitants" style="width: 10%;"><?=T("Profile", "Inhabitants"); ?></td>
        <td class="coords" style="width: 10%;"><?=T("Profile", "Coordinates"); ?></td>
        <td style="width: 5%;"></td>
    </tr>
    </thead>
    <tbody>
    <?=$vars['farms'];?>
    </tbody>
</table>
<div id="search_navi">
    <?=$vars['navigator'];?>
    <div class="clear"></div>
</div>
