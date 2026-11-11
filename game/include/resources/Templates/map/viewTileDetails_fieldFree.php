<div id="tileDetails" class="village village-<?=$vars['fieldType']; ?>">
	<?= $vars['heading']; ?>
	<div class="detailImage<?= !empty($vars['shore']) ? ' shore' : ''; ?> vid<?= $vars['vid']; ?> ">
		<div class="options">
			<?=$vars['options']; ?>
		</div>
		<?php if (isset($vars['farmOptions'])): ?>
		<br/>
		<div class="options">
			<?= $vars['farmOptions']; ?>
		</div>
		<?php endif; ?>
	</div>

	<div id="map_details">
		<h4><?=T("map", "distribution"); ?></h4>
		<table cellpadding="1" cellspacing="1" id="distribution" class="transparent">
			<tbody>
			<?=$vars['distribution']; ?>
			</tbody>
		</table>
        <h4><?=T("map", "Other Information");?></h4>
        <table cellpadding="1" cellspacing="1" class="transparent">
            <tbody>
            <tr>
                <td class="desc"><?=T("map", "Distance");?></td>
                <td class="bold"><?=$vars['distance'];?> <?=T("map", "fields");?></td>
            </tr>
            </tbody>
        </table>
	</div>
	<div class="clear"></div>
</div>
