<?php if($vars['numIncomingTroops'] > 0 || $vars['numOutGoingTroops'] > 0):?>
<div class="villageInfobox movements">
	<table id="movements" cellspacing="1" cellpadding="1">
		<?php if($vars['numIncomingTroops'] > 0):?>
			<tr>
				<th class="troopMovements header" colspan="3"><?=T("Dorf1", "movements.incoming");?>:</th>
			</tr>
			<?=$vars['inComingContent'];?>
		<?php endif;?>
		<?php if($vars['numOutGoingTroops'] > 0):?>
			<tr>
				<th class="troopMovements header" colspan="3"><?=T("Dorf1", "movements.outgoing");?>:</th>
			</tr>
			<?=$vars['outGoingContent'];?>
		<?php endif;?>
	</table>
</div>
<?php endif;?>