<?php
use Game\Hero\SessionHero;
use Game\Hero\HeroImage;

$healthPercent = $vars['regenerating'] ? $vars['regeneratedHealth'] : $vars['health'];
$healthPercent = max(0.0, min(100.0, (float)$healthPercent));
$experiencePercent = max(0.0, min(100.0, (float)$vars['expPercent']));
// Arc geometry lives in HeroHud so the HUD refresh endpoints and this
// first render emit identical mask and title paths.
$healthPaths = \Game\Hero\HeroHud::healthPaths($healthPercent);
$experiencePaths = \Game\Hero\HeroHud::experiencePaths($experiencePercent);
$heroGpackUrl = htmlspecialchars(get_gpack_cdn_mainPage_url(), ENT_QUOTES, 'UTF-8');
$healthLabel = T('HeroGlobal', 'health') . ': ' . number_format($healthPercent, 0) . '%';
$experienceLabel = T('HeroGlobal', 'Experience') . ': ' . number_format($experiencePercent, 1) . '%';
?>

<div id="topBarHero" class="heroV2">
	<svg class="health" viewBox="0 0 110 110" role="img" aria-label="<?=htmlspecialchars($healthLabel, ENT_QUOTES, 'UTF-8'); ?>">
		<defs>
			<clipPath id="healthMask" clipPathUnits="userSpaceOnUse">
				<path d="<?=$healthPaths['mask']; ?>" fill="white"></path>
			</clipPath>
		</defs>
		<image href="<?=$heroGpackUrl; ?><?=gpack_image_path('hud/top-bar/hero/frame/health.png'); ?>"
		       xlink:href="<?=$heroGpackUrl; ?><?=gpack_image_path('hud/top-bar/hero/frame/health.png'); ?>"
		       x="0" y="0" width="110" height="110" style="clip-path:url(#healthMask);"></image>
		<path class="title" d="<?=$healthPaths['title']; ?>" fill="transparent">
			<title><?=htmlspecialchars($healthLabel, ENT_QUOTES, 'UTF-8'); ?></title>
		</path>
	</svg>
	<svg class="experience" viewBox="0 0 110 110" role="img" aria-label="<?=htmlspecialchars($experienceLabel, ENT_QUOTES, 'UTF-8'); ?>">
		<defs>
			<clipPath id="experienceMask" clipPathUnits="userSpaceOnUse">
				<path d="<?=$experiencePaths['mask']; ?>" fill="white"></path>
			</clipPath>
		</defs>
		<image href="<?=$heroGpackUrl; ?><?=gpack_image_path('hud/top-bar/hero/frame/experience.png'); ?>"
		       xlink:href="<?=$heroGpackUrl; ?><?=gpack_image_path('hud/top-bar/hero/frame/experience.png'); ?>"
		       x="0" y="0" width="110" height="110" style="clip-path:url(#experienceMask);"></image>
		<path class="title" d="<?=$experiencePaths['title']; ?>" fill="transparent">
			<title><?=htmlspecialchars($experienceLabel, ENT_QUOTES, 'UTF-8'); ?></title>
		</path>
	</svg>
	<a id="heroImageButton" href="/hero" class="heroImageButton" title="<?=T("HeroGlobal", "HeroOverview"); ?>||<?=htmlspecialchars($vars['longStatus']); ?>">
        <div class="heroImageHover">  
			<img class="heroImage <?=$vars['gender'] === 'female' ? 'heroImageFemale' : 'heroImageMale'; ?>" src="<?=HeroImage::headUrl((int)$vars['uid']); ?>" title="Hero" alt="Hero" data-cmp-info="9">
</div>
	</a>
	
	<div class="heroStatus">
		<a href="<?=htmlspecialchars((string)$vars['heroRallyPointUrl'], ENT_QUOTES, 'UTF-8'); ?>" title="" class="">
			<i class="<?=$vars['heroStatusClass']; ?>"></i>
		</a>
		<span class="hide"><i class="<?=$vars['heroStatusClass']; ?>" aria-hidden="true"></i></span>
	</div>
	<i class="levelUp <?=$vars['lvlUp'] ? 'show' : ''?>"></i>
	<a id="<?=$vars['auctionWhiteButton']['id'];?>" class="layoutButton buttonFramed withIcon round auction green" href="/auctions" data-load-tooltip="layoutButton" data-load-tooltip-data='{"boxId":"hero","buttonId":"auction"}'
		title="<?=T("HeroGlobal", "Auctions"); ?>||<?=T("HeroGlobal", "Tooltip loading"); ?>">
		<svg viewBox="0 0 20.18 19.44" class="auction"><g class="outline">
		  <path d="M20 9.44l-6.14 6.16a.54.54 0 0 1-.78 0L11 13.5a.56.56 0 0 1 0-.78l1.64-1.64-.64-.64h-1.24l-7.38 8.7L0 15.76l8.67-7.41V7.13l-.57-.57-.74.75a.49.49 0 0 1-.69 0L4.19 4.83a.49.49 0 0 1 0-.69l4-4a.49.49 0 0 1 .69 0l2.45 2.45a.52.52 0 0 1 0 .74l-.45.46.65.65h3.14v3.14l.73.73 1.75-1.75a.54.54 0 0 1 .78 0L20 8.66a.54.54 0 0 1 0 .78zm-9.35 7v3h9v-3z"></path>
		</g><g class="icon">
		  <path d="M20 9.44l-6.14 6.16a.54.54 0 0 1-.78 0L11 13.5a.56.56 0 0 1 0-.78l1.64-1.64-.64-.64h-1.24l-7.38 8.7L0 15.76l8.67-7.41V7.13l-.57-.57-.74.75a.49.49 0 0 1-.69 0L4.19 4.83a.49.49 0 0 1 0-.69l4-4a.49.49 0 0 1 .69 0l2.45 2.45a.52.52 0 0 1 0 .74l-.45.46.65.65h3.14v3.14l.73.73 1.75-1.75a.54.54 0 0 1 .78 0L20 8.66a.54.54 0 0 1 0 .78zm-9.35 7v3h9v-3z"></path>
		</g></svg>
	</a>
	<script type="text/javascript" data-cmp-info="6">
		jQuery('#<?=$vars['auctionWhiteButton']['id'];?>').click(function (event) {
			jQuery(window).trigger('buttonClicked', [event.delegateTarget, {
				"type":"green",
				"loadTitle":true,
				"boxId":"hero",
				"disabled":false,
				"attention":false,
				"colorBlind":false,"class":"",
				"id":"<?=$vars['auctionWhiteButton']['id'];?>",
				"redirectUrl":"/auctions",
				"redirectUrlExternal":"",
				"svg":"topBar\/auction.svg",
				"content":"",
				"title":"Auctions||Tooltip loading..."}]);
		});
	</script>
	<?php $adventureAttention = (int)($vars['adventureWhiteButton']['adventureCount'] ?? 0) > 0; ?>
	<a id="<?=$vars['adventureWhiteButton']['id']; ?>" class="layoutButton buttonFramed withIcon round adventure green<?=$adventureAttention ? ' attention' : ''; ?>" href="/hero/adventures" data-load-tooltip="layoutButton" data-load-tooltip-data='{"boxId":"hero","buttonId":"adventure"}'
		title="<?=T("HeroGlobal", "Adventure"); ?>||<?=T("HeroGlobal", "Tooltip loading"); ?>">
		<div class="content"><?=$vars['adventureWhiteButton']['adventureCount']; ?></div>
	</a>
	<script type="text/javascript" data-cmp-info="6">
		jQuery('#<?=$vars['adventureWhiteButton']['id']; ?>').click(function (event) {
			jQuery(window).trigger('buttonClicked', [event.delegateTarget, 
			{
				"type":"green",
				"loadTitle":true,
				"boxId":"hero",
				"disabled":false,
				"attention":<?=$adventureAttention ? 'true' : 'false'; ?>,
				"colorBlind":false,
				"class":"",
				"id":"<?=$vars['adventureWhiteButton']['id'];?>",
				"redirectUrl":"/hero/adventures",
				"redirectUrlExternal":"",
				"svg":false,
				"content":"<?=$vars['adventureWhiteButton']['adventureCount'];?>",
				"title":"Adventure||Tooltip loading..."}]);
		});
	</script>
</div>
