<?php
/** @var array<string, mixed> $vars Injected by resources\View\PHPBatchView::display(). */
$aid = (int) $vars['aid'];
$isMy = !empty($vars['isMyAlliance']);
$prefix = $isMy ? '/alliance' : ('/alliance/' . $aid);
$favorKeys = [7 => 'overview', 1 => 'profile', 3 => 'reports', 8 => 'bonuses', 2 => 'forum', 5 => 'options'];
$attacksPath = $isMy ? ($prefix . '/reports') : ($prefix . '/attacks');
?>
<?php if ($isMy): ?>
<a type="button" title="<?=T("inGame", "Set tab");?>" id="tabFavorButton" class="icon contentTitleButton  buttonFramed green withIcon rectangle"
onclick="Travian.api('favourite-tab', {data: {name: 'alliance',key: '<?=$favorKeys[(int) $vars['selectedTabId']] ?? 'overview';?>'},success: function(data) {jQuery('.subNavi .favor').removeClass('favorActive');jQuery('.favor.favorKey<?=$favorKeys[(int) $vars['selectedTabId']] ?? '';?>').addClass('favorActive');}});return false;" useicon="">
<img src="/img/spacer.gif" class="&nbsp;" alt="" data-cmp-info="9"></a>
<?php endif; ?>

<div class="contentNavi subNavi">
	<button type="button" class="scrollFrom" disabled="disabled"></button>
    <div class="scrollingContainer">
		<?php if ($isMy): ?>
		<div class="content favor <?=$vars['favorTabId']==7 ? "favorActive" : ''; ?> favorKeyoverview" > 
			<a id="<?=$button_id = get_button_id();?>" href="<?= htmlspecialchars($prefix . '/overview', ENT_QUOTES, 'UTF-8') ?>" class="tabItem <?=$vars['selectedTabId'] == 7  ? 'active' : 'normal'; ?>"> 
				<?=T("Alliance", "Overview");?>
			</a> 
		</div>
		<script type="text/javascript">
		if (jQuery('#<?=$button_id;?>')){
			jQuery('#<?=$button_id;?>').click(
				function (event){
					jQuery(window).trigger('tabClicked', [this,{
						"class": "<?=$vars['selectedTabId'] == 7 ? 'active' : 'normal';?>", 
						"title": false, 
						"target": false, 
						"id": "<?=$button_id;?>", 
						"href": "<?=$prefix?>/overview", 
						"onclick": false, 
						"enabled": true, 
						"text": "<?=T("Alliance", "Overview");?>", 
						"dialog": false, 
						"plusDialog": false, 
						"goldclubDialog": false, 
						"containerId": "", 
						"buttonIdentifier": "<?=$button_id;?>"
						}]);
					});
				}
		</script>
		<?php endif; ?>
		<div class="content<?= $isMy ? ' favor ' . ($vars['favorTabId']==1 ? "favorActive" : '') . ' favorKeyprofile' : '' ?>" > 
			<a id="<?=$button_id = get_button_id();?>" href="<?= htmlspecialchars($prefix . '/profile', ENT_QUOTES, 'UTF-8') ?>" class="tabItem <?=$vars['selectedTabId'] == 1 ? 'active' : 'normal'; ?>" > 
				<?=T("Alliance", "Profile");?>
			</a> 
		</div>
		<script type="text/javascript">if (jQuery('#<?=$button_id;?>')){jQuery('#<?=$button_id;?>').click(function (event){jQuery(window).trigger('tabClicked', [this,{"class": "<?=$vars['selectedTabId'] == 1 ? 'active' : 'normal';?>", "title": false, "target": false, "id": "<?=$button_id;?>", "href": "<?=$prefix?>/profile", "onclick": false, "enabled": true, "text": "<?=T("Alliance", "Profile");?>", "dialog": false, "plusDialog": false, "goldclubDialog": false, "containerId": "", "buttonIdentifier": "<?=$button_id;?>"}]);});}</script> 
		
		<?php if ($isMy): ?>
		<div class="content favor <?=$vars['favorTabId']==3 ? "favorActive" : ''; ?> favorKeyreports" > 
			<a id="<?=$button_id = get_button_id();?>" href="<?= htmlspecialchars($attacksPath, ENT_QUOTES, 'UTF-8') ?>" class="tabItem <?=$vars['selectedTabId'] == 3 ? 'active' : 'normal'; ?>" > 
				<?=T("Alliance", "Attacks");?>
			</a> 
		</div>
		<script type="text/javascript">if (jQuery('#<?=$button_id;?>')){jQuery('#<?=$button_id;?>').click(function (event){jQuery(window).trigger('tabClicked', [this,{"class": "<?=$vars['selectedTabId'] == 3 ? 'active' : 'normal';?>", "title": false, "target": false, "id": "<?=$button_id;?>", "href": "<?=$attacksPath?>", "onclick": false, "enabled": true, "text": "<?=T("Alliance", "Attacks");?>", "dialog": false, "plusDialog": false, "goldclubDialog": false, "containerId": "", "buttonIdentifier": "<?=$button_id;?>"}]);});}</script> 
		
		<div class="content favor <?=$vars['favorTabId']==8 ? "favorActive" : ''; ?> favorKeybonuses" > 
			<a id="<?=$button_id = get_button_id();?>" href="<?= htmlspecialchars($prefix . '/bonuses', ENT_QUOTES, 'UTF-8') ?>" class="tabItem <?=$vars['selectedTabId'] == 8 ? 'active' : 'normal'; ?>" > 
				<?=T("Alliance", "Bonuses");?>
			</a> 
		</div>
		<script type="text/javascript">if (jQuery('#<?=$button_id;?>')){jQuery('#<?=$button_id;?>').click(function (event){jQuery(window).trigger('tabClicked', [this,{"class": "<?=$vars['selectedTabId'] == 8 ? 'active' : 'normal';?>", "title": false, "target": false, "id": "<?=$button_id;?>", "href": "<?=$prefix?>/bonuses", "onclick": false, "enabled": true, "text": "<?=T("Alliance", "Bonuses");?>", "dialog": false, "plusDialog": false, "goldclubDialog": false, "containerId": "", "buttonIdentifier": "<?=$button_id;?>"}]);});}</script> 
		<?php endif; ?>
		
		<div class="content<?= $isMy ? ' favor ' . ($vars['favorTabId']==2 ? "favorActive" : '') . ' favorKeyforum' : '' ?>" > 
			<a id="<?=$button_id = get_button_id();?>" href="<?= htmlspecialchars(!empty($vars['ForumLink']) ? (string) $vars['ForumLink'] : ($prefix . '/forum'), ENT_QUOTES, 'UTF-8') ?>" class="tabItem <?=$vars['selectedTabId'] == 2 ? 'active' : 'normal'; ?>">
				<?=T("Alliance", "Forum");?>
			</a> 
		</div>
		<script type="text/javascript">if (jQuery('#<?=$button_id;?>')){jQuery('#<?=$button_id;?>').click(function (event){jQuery(window).trigger('tabClicked', [this,{"class": "<?=$vars['selectedTabId'] == 2 ? 'active' : 'normal';?>", "title": false, "target": false, "id": "<?=$button_id;?>", "href": "<?=$prefix?>/forum", "onclick": false, "enabled": true, "text": "<?=T("Alliance", "Forum");?>", "dialog": false, "plusDialog": false, "goldclubDialog": false, "containerId": "", "buttonIdentifier": "<?=$button_id;?>"}]);});}</script> 
		
		<?php if ($isMy): ?>
		<div class="content favor <?=$vars['favorTabId']==5 ? "favorActive" : ''; ?> favorKeyoptions" > 
			<a id="<?=$button_id = get_button_id();?>" href="<?= htmlspecialchars($prefix . '/options', ENT_QUOTES, 'UTF-8') ?>" class="tabItem <?=$vars['selectedTabId'] == 5 ? 'active' : 'normal'; ?>" > 
				<?=T("Alliance", "Options");?>
			</a> 
		</div>
		<script type="text/javascript">if (jQuery('#<?=$button_id;?>')){jQuery('#<?=$button_id;?>').click(function (event){jQuery(window).trigger('tabClicked', [this,{"class": "<?=$vars['selectedTabId'] == 5 ? 'active' : 'normal';?>", "title": false, "target": false, "id": "<?=$button_id;?>", "href": "<?=$prefix?>/options", "onclick": false, "enabled": true, "text": "<?=T("Alliance", "Options");?>", "dialog": false, "plusDialog": false, "goldclubDialog": false, "containerId": "", "buttonIdentifier": "<?=$button_id;?>"}]);});}</script> 
		<?php endif; ?>
		
	</div>
	<button type="button" class="scrollTo" disabled="disabled"></button>
</div>
<div class="navigationSpacer"></div>

<script type="text/javascript" data-cmp-info="6">
	jQuery(function() {
		Travian.Game.TabScrollNavigation();
	});
</script>
