<?php
/**
 * village statistics, Troops row: training queues per village.
 *
 * $vars: race, buildings (gids), rows, selectedKid
 */
?>
<table class="under_progress" cellspacing="1" cellpadding="1">
	<thead>
	<tr>
		<th><?=T("villageOverview", "Village"); ?></th>
<?php foreach ($vars['buildings'] as $gid): ?>
		<th class="unit">
			<i class="building_small tribe<?=(int)$vars['race']; ?> type<?=$gid; ?>"></i>
		</th>
<?php endforeach; ?>
	</tr>
	</thead>
	<tbody>
<?php foreach ($vars['rows'] as $row): ?>
	<tr class="<?=$row['kid'] === $vars['selectedKid'] ? 'hl' : 'hover'; ?>">
		<td class="villageName">
			<a href="/build.php?newdid=<?=$row['kid']; ?>"><?=$row['name']; ?></a>
		</td>
<?php foreach ($vars['buildings'] as $gid): ?>
		<td>
<?php if (($row['queue'][$gid] ?? 0) > 0): ?>
			<a href="/build.php?newdid=<?=$row['kid']; ?>&amp;gid=<?=$gid; ?>"><span class="dot">&bull;</span></a>
<?php else: ?>
			<span class="none">-</span>
<?php endif; ?>
		</td>
<?php endforeach; ?>
	</tr>
<?php endforeach; ?>
	</tbody>
</table>
<br>
