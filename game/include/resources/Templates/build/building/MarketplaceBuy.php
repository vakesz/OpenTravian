<div id="marketplaceBuy" class="contentV2">
<div class="merchantsInformation">
    <div class="available"><?=T("MarketPlace", "Merchants");?>:&nbsp;<span class="value">&#8237;&#8237;<?=(int)$vars['merchantsAvailable'];?>&#8236;/&#8237;<?=(int)$vars['total_merchants'];?>&#8236;&#8236;</span></div>
    <div class="capacity"><?=T("MarketPlace", "Capacity per merchant");?>:&nbsp;<span class="value"><?=(int)$vars['merchantCapacity'];?></span></div>
</div>
<?php if ($vars['error'] !== ''): ?>
    <p class="error"><?= $vars['error']; ?></p>
<?php endif; ?>
<?php if($vars['offerAccepted']):?>
    <table id="summary" cellpadding="1" cellspacing="1">
        <tbody>
        <tr>
            <td colspan="2" class="desc"><?=$vars['offerAcceptTitle'];?></td>
        </tr>
        <tr>
            <td class="val">
                <img class="r<?=$vars['giveType'];?>" src="img/spacer.gif" alt="<?=T("inGame", "resources.r" . $vars['giveType']);?>">
                <?=$vars['giveValue'];?>
            </td>
            <td class="text"><?=T("MarketPlace", "are on their way to you");?></td>
        </tr>
        <tr>
            <td class="val">
                <img class="r<?=$vars['needType'];?>" src="img/spacer.gif" alt="<?=T("inGame", "resources.r" . $vars['needType']);?>">
                <?=$vars['needValue'];?>
            </td>
            <td class="text"><?=T("MarketPlace", "have been dispatched by your merchants");?></td>
        </tr>
        </tbody>
    </table>
    <div class="clear"></div>
    <br/>
<?php endif;?>
<?php
$plainBaseUrl = '/build.php?id=' . (int)$vars['index'] . '&amp;t=1';
$baseUrl = $plainBaseUrl . (!empty($vars['al']) ? '&amp;al=1' : '');
$resourceClasses = [1 => 'lumber', 2 => 'clay', 3 => 'iron', 4 => 'crop'];
$ratioUrl = $baseUrl . '&amp;page=1&amp;s=' . (int)$vars['s'] . '&amp;b=' . (int)$vars['b'] . ($vars['r'] == '1:1' ? '' : '&amp;v=1:1');
?>
<div class="filterWrapper">
    <div class="label"><?=T("MarketPlace", "I'm searching");?></div>
    <div class="filterGroup">
        <?php foreach ($resourceClasses as $id => $class): ?>
            <button type="button" class="textButtonV2 buttonFramed rectangle withIcon toggle grey<?=$vars['s'] == $id ? ' active' : '';?>"
                    title="<?=T("inGame", "resources.r" . $id);?>"
                    data-option="<?=$id;?>"
                    onclick="window.location.href = '<?=$baseUrl . ($vars['s'] != $id ? '&amp;s=' . $id : '') . '&amp;v=' . $vars['r'] . '&amp;b=' . (int)$vars['b'];?>'; return false;">
                <i class="resource <?=$class;?>_small"></i>
            </button>
        <?php endforeach; ?>
    </div>
    <div class="label"><?=T("MarketPlace", "Ratio");?></div>
    <div class="filterGroup">
        <button type="button" class="textButtonV2 buttonFramed rectangle withIcon toggle grey<?=$vars['r'] == '1:1' ? ' active' : '';?>"
                title="<?=T("MarketPlace", "Show offers with a good ratio");?>"
                onclick="window.location.href = '<?=$baseUrl . '&amp;page=1&amp;s=' . (int)$vars['s'] . '&amp;b=' . (int)$vars['b'] . ($vars['r'] == '1:1' ? '' : '&amp;v=1:1');?>'; return false;">
            <i class="oneToOneFilter"></i>
        </button>
    </div>
    <div class="label"><?=T("MarketPlace", "I'm offering");?></div>
    <div class="filterGroup">
        <?php foreach ($resourceClasses as $id => $class): ?>
            <button type="button" class="textButtonV2 buttonFramed rectangle withIcon toggle grey<?=$vars['b'] == $id ? ' active' : '';?>"
                    title="<?=T("inGame", "resources.r" . $id);?>"
                    data-option="<?=$id;?>"
                    onclick="window.location.href = '<?=$baseUrl . '&amp;v=' . $vars['r'] . ($vars['b'] != $id ? '&amp;b=' . $id : '') . '&amp;s=' . (int)$vars['s'];?>'; return false;">
                <i class="resource <?=$class;?>_small"></i>
            </button>
        <?php endforeach; ?>
    </div>
    <div class="label"><?=T("MarketPlace", "Only alliance");?></div>
    <div class="filterGroup">
        <button type="button" class="textButtonV2 buttonFramed rectangle withIcon toggle grey<?=!empty($vars['al']) ? ' active' : '';?>"
                title="<?=T("MarketPlace", "Only show offers from members of my alliance");?>"
                onclick="window.location.href = '<?=$plainBaseUrl . '&amp;page=1&amp;s=' . (int)$vars['s'] . '&amp;b=' . (int)$vars['b'] . '&amp;v=' . $vars['r'] . (empty($vars['al']) ? '&amp;al=1' : '');?>'; return false;">
            <i class="allianceFilter"></i>
        </button>
    </div>
</div>
<h3><a name="h2"></a><?=T("MarketPlace", "Offers at the marketplace");?></h3>
<table class="borderGap offers">
    <thead class="includedHeader">
    <tr>
        <td class="offered sortable"><div><?=T("MarketPlace", 'Offered to me');?></div></td>
        <td class="ratio sortable" title="<?=T("MarketPlace", "ratioHeaderTooltip");?>"><div></div></td>
        <td class="sortable wanted"><div><?=T("MarketPlace", 'Wanted from me');?></div></td>
        <td class="merchants" title="<?=T("MarketPlace", "Number of merchants required");?>"></td>
        <td class="duration sortable" title="<?=T("MarketPlace", "Travel time");?>"><div></div></td>
        <td></td>
        <td></td>
    </tr>
    </thead>
    <tbody>
    <?=$vars['content'];?>
    </tbody>
</table>
<div style="text-align: right; margin-top: 10px;">
    <?=$vars['nav'];?>
</div>
</div>
