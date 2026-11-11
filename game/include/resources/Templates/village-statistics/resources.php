<?=$vars['subTabs']; ?>
<table cellpadding="1" cellspacing="1" id="ressources">
	<thead>
	<tr>
		<td><?=T("villageOverview", "Village"); ?></td>
		<td><i class="r1"></i></td>
		<td><i class="r2"></i></td>
		<td><i class="r3"></i></td>
		<td><i class="r4"></i></td>
		<td><?=T("villageOverview", "Merchants"); ?></td>
	</tr>
	</thead>
	<tbody>
	<?=$vars['content']; ?>
	<tr>
		<td colspan="6" class="empty"></td>
	</tr>
	<tr class="sum">
		<td class="vil"><?=T("villageOverview", "Sum"); ?></td>
		<td class="lum">&#x202d;<?=$vars['summary']['r1']; ?>&#x202c;</td>
		<td class="clay">&#x202d;<?=$vars['summary']['r2']; ?>&#x202c;</td>
		<td class="iron">&#x202d;<?=$vars['summary']['r3']; ?>&#x202c;</td>
		<td class="crop">&#x202d;<?=$vars['summary']['r4']; ?>&#x202c;</td>
		<td class="tra">&#x202d;&#x202d;<?=$vars['summary']['merchants_available']; ?>&#x202c;/&#x202d;<?=$vars['summary']['merchants_total']; ?>&#x202c;&#x202c;</td>
	</tr>
	</tbody>
</table>