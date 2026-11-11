<h4 class="round"><?=T("Options", "Vacation mode is active"); ?></h4>
<div>
	<?=T("Options", "Your are not able to"); ?>
	<ul>
		<li><?=T("Options", "upgrade buildings"); ?></li>
		<li><?=T("Options", "train troops"); ?></li>
		<li><?=T("Options", "send troops"); ?></li>
		<li><?=T("Options", "send merchants"); ?></li>
		<li><?=T("Options", "delete your account"); ?></li>
	</ul>
</div>

<div class="vacationModeActive">
	<div>
		<?=T("Options", "Remaining days in vacation mode"); ?>
		: <?=$vars['vacationDays']; ?> <?=T("Options", "day(s)"); ?></div>
	<div>
		<?=T("Options", "Vacation mode runs til"); ?>
		: <?=$vars['vacationTil']; ?></div>
	<div>
		<?=T("Options", "You can abort vacation mode now"); ?></div>
	<br/>

	<div>
		<?php
		$button_id = get_button_id();
		$abortLabel = T("Options", "abort vacation mode");
		?>
		<button type="button"
		        value="<?= htmlspecialchars($abortLabel, ENT_QUOTES, 'UTF-8'); ?>"
		        id="<?= $button_id; ?>"
		        class="textButtonV1 gold "
		        version="textButtonV1"
		        title="<?= htmlspecialchars($abortLabel, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($abortLabel, ENT_QUOTES, 'UTF-8'); ?></button>
		<script type="text/javascript">
			jQuery(function() {
				if (jQuery('#<?= $button_id; ?>').length > 0) {
					jQuery('#<?= $button_id; ?>').click(function (event) {
						jQuery(window).trigger('buttonClicked', [this, {
							"type": "button",
							"value": "<?= htmlspecialchars($abortLabel, ENT_QUOTES, 'UTF-8'); ?>",
							"id": "<?= $button_id; ?>",
							"class": "textButtonV1 gold ",
							"title": "<?= htmlspecialchars($abortLabel, ENT_QUOTES, 'UTF-8'); ?>",
							"version": "textButtonV1",
							"wayOfPayment": {"featureKey": "VacationModeAbort"}
						}]);
					});
				}
			});
		</script>
	</div>
</div>
