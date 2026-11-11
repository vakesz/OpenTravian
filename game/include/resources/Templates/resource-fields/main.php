<div id="resourceFieldContainer" class="resourceField<?=$vars['fieldType']; ?> tribe<?=$vars['race']; ?>">
	<?php
        use Core\Session;

$productionBoostArrow = static fn (bool $boosted): string => \Game\ProductionBoostArrow::forResourceBar($boosted);

require SVG_FIELD_TEMPLATES;
?>
    <a href="/dorf2.php" class="villageCenter"></a>
    <?php foreach ($vars['areas'] as $index => $area): ?>
        <?php $map = $vars['maps'][$index]; ?>
        <?php $map_index = $index + 1; ?>
		<a href="/build.php?id=<?=$map_index; ?>"
			class="<?=$map['color']; ?> level colorLayer resourceField gid<?=$map['item_id']; ?> buildingSlot<?=$map_index; ?> level<?=$map['level']; ?><?=$map['upgradeState'] ? ' underConstruction' : ''; ?>"
			data-aid="<?=$map_index; ?>" data-gid="<?=$map['item_id']; ?>"
			title="<?=htmlspecialchars($area['title']); ?>">
			<div class="labelLayer"><?=$map['level']; ?></div>
		</a>
    <?php endforeach; ?>
	<?php if (isset($resourceField['resourceField'.$vars['fieldType']]['fields'])) {?>
		<svg class="resourceField resourceField<?=$vars['fieldType']; ?>" viewBox="0 0 473 304">
		  <?=$resourceField['resourceField'.$vars['fieldType']]['fields'];?>
		</svg>
	  <?php } ?>
	<svg class="villageCenter" viewBox="0 0 473 304">
		<?=$resourceField['villageCenter']; ?>
	</svg>
</div>
<div class="villageInfoWrapper">
	<?php $groupedNumber = static fn (int|float $value): string => number_format((int) round($value), 0, '.', ' '); ?>
	<?=$vars['onLoadBuildings']; ?>
	<?=$vars['movements']; ?>
	<div class="villageInfobox production">
	   <table id="production" cellpadding="1" cellspacing="1">
		  <thead>
			 <tr>
				<th colspan="4"><?=T("Dorf1", "production.production per hour"); ?>:</th>
			 </tr>
		  </thead>
		  <tbody>
			 <tr>
				<td class="ico">
				   <div> <i class="r1" title="<?= T('inGame', 'resources.r1'); ?>"></i> <?=$productionBoostArrow((bool) $vars['productionBoost'][0]); ?> </div>
				</td>
				<td class="res"> <?=T("Dorf1", "production.resources.1"); ?>: </td>
				<td class="num"> ‭<?=$groupedNumber($vars['production'][0]); ?>‬ </td>
			 </tr>
			 <tr>
				<td class="ico">
				   <div> <i class="r2" title="<?= T('inGame', 'resources.r2'); ?>"></i> <?=$productionBoostArrow((bool) $vars['productionBoost'][1]); ?> </div>
				</td>
				<td class="res"> <?=T("Dorf1", "production.resources.2"); ?>: </td>
				<td class="num"> ‭<?=$groupedNumber($vars['production'][1]); ?>‬ </td>
			 </tr>
			 <tr>
				<td class="ico">
				   <div> <i class="r3" title="<?= T('inGame', 'resources.r3'); ?>"></i> <?=$productionBoostArrow((bool) $vars['productionBoost'][2]); ?> </div>
				</td>
				<td class="res"> <?=T("Dorf1", "production.resources.3"); ?>: </td>
				<td class="num"> ‭<?=$groupedNumber($vars['production'][2]); ?>‬ </td>
			 </tr>
			 <tr>
				<td class="ico">
				   <div> <i class="r4" title="<?= T('inGame', 'resources.r4'); ?>"></i> <?=$productionBoostArrow((bool) $vars['productionBoost'][3]); ?> </div>
				</td>
				<td class="res"> <?=T("Dorf1", "production.resources.4"); ?>: </td>
				<td class="num"> ‭<?=$groupedNumber($vars['production'][3]); ?>‬ </td>
			 </tr>
		  </tbody>
	   </table>
	   <div>
		  <?=$vars['goldProductionBoostButton']; ?>
	   </div>
	</div>
	<script type="text/javascript" data-cmp-info="6">
		jQuery(function () {
			jQuery('input#showTroopsToggleMobile').prop(
				'checked', Travian.Game.Preferences.get('mobileUnitDisplay') === 'expanded'
			).on('change', function () {
				Travian.Game.Village.toggleMobileUnitDisplay();
			});
		});
	</script>
	<input type="checkbox" name="showTroopsToggleMobile" id="showTroopsToggleMobile">
	<label for="showTroopsToggleMobile" class="showTroopsToggleMobile<?= $vars['unitsSummary'] <= 0 ? ' noTroops' : ' '; ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 50" class="troopIcon"><g class="outline"><path d="M21 2H9l5 6-1 2h3.9L16 8zM29 20c0-4-2.4-6.4-4-8-1-1-5-1-5-1l-3 4h-4l-3-4s-4 0-5 1c-1.6 1.6-4 4-4 8v11l1 2c.3.6.5 1.1.5 1.8.1 2.5.5 7.2.5 7.2v1c.8 1.8 6.1 5.3 7 6 0-.3-2-16-2-16l-4-4-1-1v-6l11 5-2 5v1l2 3h2l2-3v-1l-2-5 11-5v6l-1 1-4 4s-2 15.7-2 16c.9-.7 6.2-4.2 7-6v-1s.4-4.7.4-7.2c0-.7.2-1.2.6-1.8.3-.6.8-2 .8-2l.2-1V20z"/></g><g class="icon"><path d="M21 2H9l5 6-1 2h3.9L16 8zM29 20c0-4-2.4-6.4-4-8-1-1-5-1-5-1l-3 4h-4l-3-4s-4 0-5 1c-1.6 1.6-4 4-4 8v11l1 2c.3.6.5 1.1.5 1.8.1 2.5.5 7.2.5 7.2v1c.8 1.8 6.1 5.3 7 6 0-.3-2-16-2-16l-4-4-1-1v-6l11 5-2 5v1l2 3h2l2-3v-1l-2-5 11-5v6l-1 1-4 4s-2 15.7-2 16c.9-.7 6.2-4.2 7-6v-1s.4-4.7.4-7.2c0-.7.2-1.2.6-1.8.3-.6.8-2 .8-2l.2-1V20z"/></g></svg></label>
	<div class="villageInfobox units">
	   <table id="troops" cellpadding="1" cellspacing="1">
		  <thead>
			 <tr>
				<th colspan="3"><?=T("Dorf1", "units"); ?>:</th>
			 </tr>
		  </thead>
		  <tbody>
			 <?php if ($vars['unitsSummary'] <= 0): ?>
                    <tr>
                        <td class="noTroops"><?=T("Dorf1", "none"); ?>.</td>
                    </tr>
			 <?php else: ?>
				<?php foreach ($vars['units'] as $key => $num): ?>
					<tr>
						<td class="ico">
							<a href="/build.php?id=39#td">
								<img class="unit u<?=$key == 98 ? 'hero' : $key; ?>" src="/img/spacer.gif"
									 title="<?=T("Troops", ($key == 98 ? 'hero' : $key) . '.title'); ?>" alt="<?=T("Troops", ($key == 98 ? 'hero' : $key) . '.title'); ?>">
							</a>
						</td>
						<td class="num"><?=$groupedNumber($num); ?></td>
						<td class="un"><?= T("Troops", ($key == 98 ? 'hero' : $key) . '.title'); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		  </tbody>
	   </table>
	</div>
</div>
