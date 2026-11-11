<?php
/**
 * village statistics, Resources row: warehouse (fill and time to full),
 * production and capacity tables.
 *
 * $vars: subTabs (html), tab (1 warehouse, 2 production, 3 capacity), selectedKid, rows
 */
/** @var array{subTabs: string, tab: int, selectedKid: int, rows: list<array<string, mixed>>} $vars */
$ltr = static fn (string $text): string => '&#x202d;' . $text . '&#x202c;';
$num = static fn ($value): string => $ltr(number_format((int)$value, 0, '.', ','));
$rowClass = static fn (int $kid): string => $kid === $vars['selectedKid'] ? 'hl' : 'hover';
$duration = static fn (?int $seconds): string => $seconds === null ? '<span class="none">-</span>' : secondsToString($seconds);
$village = static fn (array $row): string => '<td class="vil fc"><a href="/dorf1.php?newdid=' . (int)$row['kid'] . '">' . $row['name'] . '</a></td>';
echo $vars['subTabs'];
?>
<?php if ($vars['tab'] === 1): ?>
<table cellpadding="1" cellspacing="1" id="warehouse">
	<thead>
	<tr>
		<td onclick="sortByColumnOrder('sortIndex')"><?=T("villageOverview", "Village"); ?></td>
		<td onclick="sortByColumnOrder('r1')"><i class="r1"></i></td>
		<td onclick="sortByColumnOrder('r2')"><i class="r2"></i></td>
		<td onclick="sortByColumnOrder('r3')"><i class="r3"></i></td>
		<td onclick="sortByColumnOrder('warehouse')"><img class="clock" src="/img/spacer.gif" alt="<?=T("villageOverview", "Duration"); ?>"></td>
		<td onclick="sortByColumnOrder('r4')"><i class="r4"></i></td>
		<td onclick="sortByColumnOrder('granary')"><img class="clock" src="/img/spacer.gif" alt="<?=T("villageOverview", "Duration"); ?>"></td>
	</tr>
	</thead>
	<tbody>
<?php foreach ($vars['rows'] as $row):
    $cell = static fn (string $class, int $percent) => '<td class="' . $class . ($percent >= 90 ? ' crit' : '') . '">' . $ltr($ltr((string)$percent) . '%') . '</td>';
    ?>
	<tr class="<?=$rowClass($row['kid']); ?>">
		<?=$village($row); ?>
		<?=$cell('lum', $row['percent'][0]); ?>
		<?=$cell('clay', $row['percent'][1]); ?>
		<?=$cell('iron', $row['percent'][2]); ?>
		<td class="max123"><?=$duration($row['warehouseFull']); ?></td>
		<?=$cell('crop', $row['percent'][3]); ?>
		<td class="max4 lc"><?=$duration($row['granaryFull']); ?></td>
	</tr>
<?php endforeach; ?>
	</tbody>
</table>
<?php elseif ($vars['tab'] === 2):
    $sum = [0, 0, 0, 0];
    ?>
<table cellpadding="1" cellspacing="1" id="production">
	<thead><tr>
		<td><?=T("villageOverview", "Village"); ?></td>
		<td><i class="r1"></i></td>
		<td><i class="r2"></i></td>
		<td><i class="r3"></i></td>
		<td><i class="r4"></i></td>
	</tr></thead>
	<tbody>
<?php foreach ($vars['rows'] as $row):
    foreach ($row['production'] as $i => $value) {
        $sum[$i] += $value;
    }
    ?>
	<tr class="<?=$rowClass($row['kid']); ?>">
		<?=$village($row); ?>
		<td class="lum"><?=$num($row['production'][0]); ?></td>
		<td class="clay"><?=$num($row['production'][1]); ?></td>
		<td class="iron"><?=$num($row['production'][2]); ?></td>
		<td class="crop"><?=$num($row['production'][3]); ?></td>
	</tr>
<?php endforeach; ?>
	<tr><td colspan="5" class="empty"></td></tr>
	<tr class="sum">
		<td class="vil"><?=T("villageOverview", "Sum"); ?>: <span class="total"><?=$num(array_sum($sum)); ?></span></td>
		<td class="lum"><?=$num($sum[0]); ?></td>
		<td class="clay"><?=$num($sum[1]); ?></td>
		<td class="iron"><?=$num($sum[2]); ?></td>
		<td class="crop"><?=$num($sum[3]); ?></td>
	</tr>
	</tbody>
</table>
<?php else:
    $store = 0;
    $crop = 0;
    ?>
<table cellpadding="1" cellspacing="1" id="capacity">
	<thead><tr>
		<td><?=T("villageOverview", "Village"); ?></td>
		<td><?=T("villageOverview", "Warehouse"); ?></td>
		<td><?=T("villageOverview", "Granary"); ?></td>
	</tr></thead>
	<tbody>
<?php foreach ($vars['rows'] as $row):
    $store += $row['capacity'][0];
    $crop += $row['capacity'][3];
    ?>
	<tr class="<?=$rowClass($row['kid']); ?>">
		<?=$village($row); ?>
		<td class="max123"><?=$num($row['capacity'][0]); ?></td>
		<td class="max4"><?=$num($row['capacity'][3]); ?></td>
	</tr>
<?php endforeach; ?>
	<tr><td colspan="3" class="empty"></td></tr>
	<tr class="sum">
		<td class="vil"><?=T("villageOverview", "Sum"); ?></td>
		<td class="max123"><?=$num($store); ?></td>
		<td class="max4"><?=$num($crop); ?></td>
	</tr>
	</tbody>
</table>
<?php endif; ?>
