<?php
/**
 * village statistics, Troops row: wounded troops per village.
 *
 * $vars: race, building (gid), units (nr => id/title), rows, selectedKid
 */
?>
<table class="under_progress" cellspacing="1" cellpadding="1">
	<thead>
	<tr>
		<th rowspan="2" class="villageName"><i class="tribe<?=(int)$vars['race']; ?>_medium"></i></th>
		<th rowspan="2" class="inProgress"><?=T("villageOverview", "in progress"); ?></th>
		<th colspan="<?=count($vars['units']); ?>"><?=T("villageOverview", "Wounded troops"); ?></th>
	</tr>
	<tr>
<?php foreach ($vars['units'] as $unit): ?>
		<th class="unit">
			<img class="unit u<?=$unit['id']; ?>" src="/img/spacer.gif" alt="<?=$unit['title']; ?>">
		</th>
<?php endforeach; ?>
	</tr>
	</thead>
	<tbody>
<?php foreach ($vars['rows'] as $row): ?>
	<tr class="<?=$row['kid'] === $vars['selectedKid'] ? 'hl' : 'hover'; ?>">
		<td class="villageName">
			<a href="/build.php?newdid=<?=$row['kid']; ?>&amp;gid=<?=$vars['building']; ?>"><?=$row['name']; ?></a>
		</td>
		<td class="inProgress">
			<?php if ($row['healing'] > 0): ?><?=number_format($row['healing'], 0, '.', ','); ?><?php else: ?><span class="none">-</span><?php endif; ?>
		</td>
<?php foreach ($vars['units'] as $nr => $unit):
    $count = $row['wounded'][$nr] ?? 0;
    ?>
		<td<?= $count > 0 ? '' : ' class="none"'; ?>><?=number_format($count, 0, '.', ','); ?></td>
<?php endforeach; ?>
	</tr>
<?php endforeach; ?>
	</tbody>
</table>
<br>
