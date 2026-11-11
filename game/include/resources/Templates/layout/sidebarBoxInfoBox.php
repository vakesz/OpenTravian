<div id="sidebarBoxInfobox" class="sidebarBox toggleable <?= htmlspecialchars((string) $vars['toggle'], ENT_QUOTES, 'UTF-8'); ?>">
 <div class="header"></div>
 <div class="content">
	<div class="boxTitleWrapper">
		<div class="boxTitle"><?= T("inGame", "InfoBox"); ?></div>
		<div class="inlineIcon messageShortInfo" title="<?= htmlspecialchars((string) $vars['title'], ENT_QUOTES, 'UTF-8'); ?>">
		   <svg viewBox="0 0 20 14.28" class="message<?= htmlspecialchars((string) $vars['unread'], ENT_QUOTES, 'UTF-8'); ?>">
			  <path d="M.72.93A1.69 1.69 0 0 1 .33.21L.54 0h19a1.56 1.56 0 0 1 .16.2 1.73 1.73 0 0 1-.39.73l-7.2 7.78a2.79 2.79 0 0 1-4.18 0zm13.59 7.89l3.55 2.5-4.92-1.06a4.31 4.31 0 0 1-5.84 0l-4.89 1.06 3.53-2.49L0 2.62v10.06a1.54 1.54 0 0 0 1.47 1.6h17.06a1.54 1.54 0 0 0 1.47-1.6v-10z"></path>
		   </svg>
		   <span class="value ">‭‭<?= $vars['unreadCount'] ? $vars['unreadCount'] : $vars['total']; ?>×‬</span>
		</div>
	</div>

	<ul>
		<?= $vars['content']; ?>
	</ul>
	<script type="text/javascript" data-cmp-info="6">
	   jQuery(function() {
		Travian.Game.Layout.setupInfoboxItemsDeletionWithMessage('<?= T("inGame", "Delete this message permanently?"); ?>', '<?= T("inGame", "Confirm"); ?>');
	   });
	</script>
 </div>
 <div class="toggle">
	<button type="button" class="toggleBox" onclick="" title="<?= T("inGame", $vars['toggle'] == 'expanded' ? "collapseInfoBox" : "expandInfoBox"); ?>">
	   <svg class="toggleArrow" viewBox="0 0 18 11">
		  <filter id="insetShadow">
			 <feFlood flood-color="#a2e25e"></feFlood>
			 <feComposite in2="SourceAlpha" operator="out"></feComposite>
			 <feGaussianBlur stdDeviation="2" result="blur"></feGaussianBlur>
			 <feComposite operator="atop" in2="SourceGraphic"></feComposite>
		  </filter>
		  <path class="caret" d="M1 10H17L9 1z"></path>
		  <path class="glow" d="M1 10H17L9 1z"></path>
	   </svg>
	</button>
 </div>
<script type="text/javascript" data-cmp-info="6">
jQuery(function() {
	Travian.Translation.add({
		'infobox_collapsed': '<?= T("inGame", "expandInfoBox"); ?>',
		'infobox_expanded': '<?= T("inGame", "collapseInfoBox"); ?>'
	});

	var box = jQuery('#sidebarBoxInfobox');
	box.find('button.toggleBox').click(function(e) {
		Travian.Game.Layout.toggleBox(box, 'travian_toggle', 'infobox');
	});
});
</script>
</div>
