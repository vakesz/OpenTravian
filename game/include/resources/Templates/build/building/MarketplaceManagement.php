<h4 class="round"><?=T("MarketPlace", "Own merchants and NPC");?></h4>
<div class="whereAreMyMerchants">
    <?=T("MarketPlace", "Free merchants");?>: &#8237;&#8237;<?=$vars['total_available'];?>&#8236;/&#8237;<?=$vars['total'];?>&#8236;&#8236;<br/>
    <?=T("MarketPlace", "Merchants offering resources");?>: &#8237;&#8237;<?=$vars['total_offering'];?>&#8236;/&#8237;<?=$vars['total'];?>&#8236;&#8236;<br/>
    <?=T("MarketPlace", "Merchants underway");?>: &#8237;&#8237;<?=$vars['total_on_the_way'];?>&#8236;/&#8237;<?=$vars['total'];?>&#8236;&#8236;
</div>

<div class="npcMerchant">
    <?=T("MarketPlace", "Trade your village's resources immediately with NPC merchant 1:1");?>.<br/>
    <?=$vars['exchangeButton'];?>
</div>
<div class="clear"></div>