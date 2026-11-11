<?php if (!empty($vars['rearrange'])) { require __DIR__ . '/rearrange.php'; } ?>
<div id="villageContent"<?= !empty($vars['rearrange']) ? ' class="rearrangeMode"' : ''; ?>>
	<?php
        require SVG_BUILDING_TEMPLATES;

        // The reference draws every clickable building outline twice: a hoverShape group
        // that receives the pointer and a highlightShape group that the Gpack fills on hover.
        $shapeGroups = static function (string $d, string $url, string $title, string $attributes = ''): string {
            $path = '<path' . $attributes . ' d="' . $d . '" onclick="window.location.href=\'' . $url . '\'"><title>' . $title . '</title></path>';

            return '<g class="hoverShape">' . $path . '</g><g class="highlightShape">' . $path . '</g>';
        };
	?>
	<?php foreach ($vars['maps'] as $index => $map):++$index;
		$buildingUrl = '/build.php?id=' . (int)$map['index'] . '&gid=' . (int)$map['item_id'];
		$buildingHref = str_replace('&', '&amp;', $buildingUrl);
	?>
	<?php if ($map['index'] == 40):?>
		<?php if ($map['item_id'] == 0):?>
			<div class="buildingSlot a<?=$map['index'];?> g<?=$map['item_id'];?> <?=$vars['raceName']; ?>" data-aid="<?=$map['index'];?>" data-gid="<?=$map['item_id'];?>" data-building-id="<?= \Game\Buildings\BuildingRearranger::buildingId((int)$map['index']); ?>" data-name="">
				<a href="/build.php?id=40" class="emptyBuildingSlot"></a>
				<?php if (isset($buildingField['g00'])) {?>
					<svg width="794" height="540" viewBox="0 0 794 540" class="buildingShape emptyWall">
					  <path d="<?=$buildingField['g00'];?>" onclick="window.location.href='/build.php?id=<?= $map['index']; ?>'"><title><?=$map['title'];?></title></path>
					</svg>
				<?php } ?>
			</div>
		<?php else:?>
			<?php $wallShore = !empty($vars['isShore']) ? 'shore ' : ''; ?>
			<?php foreach (['bottom' => '', 'top' => \Game\Buildings\BuildingRearranger::buildingId((int)$map['index'])] as $part => $buildingId):
				$partClass = ucfirst($part);
			?>
			<div class="buildingSlot a<?=$map['index'];?> g<?=$map['item_id'];?> <?=$part;?><?= !empty($vars['isShore']) ? ' shore' : ''; ?> <?=$vars['raceName']; ?>" data-aid="<?=$map['index'];?>" data-gid="<?=$map['item_id'];?>" data-building-id="<?=$buildingId;?>" data-name="<?=$map['name'];?>">
				<a href="" class="level <?=$map['levelColor'];?> <?=$vars['raceName']; ?>" title="" data-level="<?=$map['level'];?>">
					<div class="labelLayer"><?=$map['level'];?></div>
				</a>
				<img src="/img/spacer.gif" class="<?=$wallShore;?>g<?=$map['item_id'];?><?=$partClass;?> <?=$vars['raceName']; ?>" alt="">
				<?php if (isset($buildingField['g'.$map['item_id']][$part])) {?>
					<svg width="794" height="540" viewBox="0 0 794 540" class="buildingShape g<?=$map['item_id'];?><?=$partClass;?>">
					  <?= $shapeGroups($buildingField['g'.$map['item_id']][$part], $buildingUrl, $map['title']); ?>
					</svg>
				<?php } ?>
			</div>
			<?php endforeach; ?>
		<?php endif;?>
	<?php else:?>
	<div class="buildingSlot a<?=$map['index'];?> g<?=$map['item_id'];?> aid<?=$map['index'];?> <?=$vars['raceName']; ?><?= !empty($vars['rearrange']) && !empty($map['movable']) ? ' movable' : ''; ?>" data-aid="<?=$map['index'];?>" data-gid="<?=$map['item_id'];?>" data-building-id="<?= \Game\Buildings\BuildingRearranger::buildingId((int)$map['index']); ?>" data-name="<?=$map['name'];?>">
		<?php if ($map['item_id'] == 0):?>
			<a href="/build.php?id=<?=$map['index'];?>" class="emptyBuildingSlot"></a>
		<?php endif;?>
		<?php if ($map['item_id'] > 0):?>
			<a href="<?=$buildingHref;?>" class="level <?=$map['levelColor'];?> <?=$vars['raceName']; ?>" title="<?=$map['title'];?>" data-level="<?=$map['level'];?>">
				<div class="labelLayer"><?=$map['level'];?></div>
			</a>
		<?php endif;?>
		<?php if ((int)$map['item_id'] !== 49):?>
		<img src="/img/spacer.gif" class="building <?=$map['class'];?> <?=$vars['raceName']; ?>" alt="">
		<?php endif;?>
		<?php if ($map['item_id'] == 0):?>
			<?php if ($map['index'] == 39):?>
				<svg width="120" height="180" viewBox="0 0 120 180" class="buildingShape g16e">
					<path d="<?=$buildingField['g16e'] ?? $buildingField['g0'];?>" fill="#606060" onclick="window.location.href='/build.php?id=<?= $map['index']; ?>'"><title><?=$map['title'];?></title></path>
				</svg>
			<?php else:?>
				<svg width="120" height="120" viewBox="0 0 120 120" class="buildingShape iso">
					<path d="<?=$buildingField['g0'];?>" onclick="window.location.href='/build.php?id=<?= $map['index']; ?>'"><title><?=$map['title'];?></title></path>
				</svg>
			<?php endif;?>
		<?php elseif ($map['item_id'] == 16):?>
			<?php if ($vars['isWW']):?>
				<?php if (isset($buildingField['g'.$map['item_id'].'_ww'])) {?>
					<svg width="125" height="160" viewBox="0 0 125 160" class="buildingShape g16_ww">
					  <?= $shapeGroups($buildingField['g'.$map['item_id'].'_ww'], $buildingUrl, $map['title']); ?>
					</svg>
				<?php } ?>
			<?php else:?>
				<?php if (isset($buildingField[$vars['race']]['g'.$map['item_id']])) {?>
					<svg width="125" height="160" viewBox="0 0 125 160" class="buildingShape g<?=$map['item_id'];?>">
					  <?= $shapeGroups($buildingField[$vars['race']]['g'.$map['item_id']], $buildingUrl, $map['title']); ?>
					</svg>
				<?php } ?>
			<?php endif;?>
			
		
		<?php elseif ($map['item_id'] == 40):?>
			<?php if ($map['level'] >= 0 && $map['level'] <= 19):?>
			<svg width="298" height="318" viewBox="0 0 298 318" class="buildingShape g40_0">
				<?= $shapeGroups($buildingField['g'.$map['item_id'][0]], $buildingUrl, $map['title']); ?>
			</svg>
			<?php elseif ($map['level'] >= 20 && $map['level'] <= 39):?>
			<svg width="298" height="318" viewBox="0 0 298 318" class="buildingShape g40_1">
				<?= $shapeGroups($buildingField['g'.$map['item_id'][1]], $buildingUrl, $map['title']); ?>
			</svg>
			<?php elseif ($map['level'] >= 40 && $map['level'] <= 59):?>
			<svg width="298" height="318" viewBox="0 0 298 318" class="buildingShape g40_2">
				<?= $shapeGroups($buildingField['g'.$map['item_id'][2]], $buildingUrl, $map['title']); ?>
			</svg>
			<?php elseif ($map['level'] >= 60 && $map['level'] <= 79):?>
			<svg width="298" height="318" viewBox="0 0 298 318" class="buildingShape g40_3">
				<?= $shapeGroups($buildingField['g'.$map['item_id'][3]], $buildingUrl, $map['title']); ?>
			</svg>
			<?php elseif ($map['level'] >= 80 && $map['level'] <= 89):?>
			<svg width="298" height="318" viewBox="0 0 298 318" class="buildingShape g40_4">
				<?= $shapeGroups($buildingField['g'.$map['item_id'][4]], $buildingUrl, $map['title']); ?>
			</svg>
			<?php elseif ($map['level'] >= 90):?>
			<svg width="298" height="318" viewBox="0 0 298 318" class="buildingShape g40_5">
				<?= $shapeGroups($buildingField['g'.$map['item_id'][5]], $buildingUrl, $map['title']); ?>
			</svg>
			<?php endif;?>
		<?php elseif ((int)$map['item_id'] === 49):?>
			<?php
				// The Gpack paints the Harbor sprite once, in #background .behindContent .harbour
				// (710/591), while the slot stays the reference's 120x120 box at 669/483. The
				// click shape is drawn in the 255x183 sprite space and placed with an SVG
				// transform: sprite offset (41, 108) from the slot. The Gpack RTL stylesheet already
				// mirrors svg.buildingShape, so the same LTR-authored path is correct in RTL.
				$harborPath = $buildingField[$vars['race']]['g49']
					?? $buildingField[9]['g49']
					?? $buildingField[8]['g49']
					?? null;
				$harborScale = 328 / 255;
				$harborTransform = 'translate(41 108) scale(' . $harborScale . ')';
			?>
			<?php if ($harborPath !== null): ?>
				<svg width="120" height="120" viewBox="0 0 120 120" style="overflow:visible" class="buildingShape g49">
					<?= $shapeGroups($harborPath, $buildingUrl, $map['title'], ' transform="' . $harborTransform . '"'); ?>
				</svg>
			<?php endif; ?>
		<?php else:?>
			<?php
				// Empty-string paths still isset(); treat them as missing so we do
				// not emit a dead SVG that blocks neighboring buildings' clicks.
				$shapePath = $buildingField[$vars['race']]['g' . $map['item_id']] ?? '';
				if ($shapePath === '' && (int)$map['item_id'] === 35) {
					$shapePath = $buildingField[2]['g35'] ?? '';
				}
			?>
			<?php if ($shapePath !== ''): ?>
				<svg width="120" height="120" viewBox="0 0 120 120" class="buildingShape g<?=$map['item_id'];?>">
				  <?= $shapeGroups($shapePath, $buildingUrl, $map['title']); ?>
				</svg>
			<?php endif; ?>
		<?php endif;?>
	</div>
	<?php endif;?>
	<?php endforeach; ?>
	<?php if (!empty($vars['rearrange'])):?>
		<div class="animationContainer buildingFrom buildingSlot"></div>
		<div class="animationContainer buildingTo buildingSlot"></div>
		<div class="dragContainer buildingSlot"></div>
	<?php endif;?>
</div>
<?= $vars['onLoadBuildings']; ?>
<?php if (!empty($vars['rearrange'])) { require __DIR__ . '/rearrangeScript.php'; } ?>
