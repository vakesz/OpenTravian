<div class="buildingList">
	<?php if($vars['normal'] > 0):?>
		<h5><?=T("Buildings", "Buildings");?>:</h5>
		<?=$vars['finishNowButton'];?>
	<?php endif;?>
	<?=$vars['buildings'];?>
</div>
<script type="text/javascript" data-cmp-info="6">var bld =<?=$vars['buildsJson'];?>;</script>
<script type="text/javascript">
	function showCancelBuildingDialog(eventId, title, text) {
		new Travian.Dialog.Confirmation({
			title: title,
			message: text,
			confirmText: '<?=T("Buildings", "abortConstruction.confirm");?>',
			cancelText: '<?=T("Buildings", "abortConstruction.continue");?>',
			confirmClass: 'grey negativeAction',
			cancelClass: 'grey',
			buttonCancel: true,
			context: 'cancelBuilding',
			onCancel: function () {
				Travian.WindowManager.closeByContext('cancelBuilding');
			},
			onConfirm: function () {
				window.location.href = '<?=$vars['cancelUrl'];?>';
			}
		}).show();
	}
</script>
