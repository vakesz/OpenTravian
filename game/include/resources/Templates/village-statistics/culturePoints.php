<?php if (!empty($vars['nextSlotDate'])): ?>
<div id="expansionSettlements">
	<div class="infoBox">
		<strong><?=T("villageOverview", "AboutSettlementSlots"); ?></strong>
		<ul>
			<li><?=sprintf(T("villageOverview", "SettlementSlotsEstimate"), htmlspecialchars($vars['nextSlotDate'], ENT_QUOTES, 'UTF-8')); ?></li>
		</ul>
	</div>
</div>
<?php endif; ?>
<table cellpadding="1" cellspacing="1" id="culture_points">
	<thead>
	<tr>
		<td><?=T("villageOverview", "Village"); ?></td>
		<td><?=T("villageOverview", "CPs/Day"); ?></td>
		<td><?=T("villageOverview", "Celebrations"); ?></td>
		<td><?=T("villageOverview", "Troops"); ?></td>
		<td><?=T("villageOverview", "Slots"); ?></td>
	</tr>
	</thead>
	<tbody>
	<?=$vars['content']; ?>
	<tr>
		<td colspan="5" class="empty"></td>
	</tr>
	<tr class="sum">
		<td class="vil"><?=T("villageOverview", "Sum"); ?></td>
		<td class="cps"><?=$vars['summary']['totalCP']; ?></td>
		<?php
		$vars['summary']['totalCelebrationTime'] = max($vars['summary']['totalCelebrationTime']);
		$none = $vars['summary']['totalCelebrationTime'] == 0;
		$value = $none ? '-' : appendTimer($vars['summary']['totalCelebrationTime']-time());
		?>
		<td class="cel <?=$none ? 'none' : ''; ?>"><?=$value; ?></td>
		<td class="tro"><?=array_sum($vars['summary']['units']); ?></td>
		<td class="slo">&#x202d;&#x202d;<?=$vars['summary']['slots']['filled']; ?>&#x202c;/&#x202d;<?=$vars['summary']['slots']['total']; ?>&#x202c;&#x202c;</td>
	</tr>
	</tbody>
</table>