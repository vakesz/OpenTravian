<h4 class="round"><?php
    use Core\Session;

    $m = new \Model\OptionModel();
	echo T("Options", "Sitter(s) for this account"); ?></h4>
<?php
if(isset($vars['error'])){
    echo '<span class="warning">'.$vars['error'].'</span>';
}
?>
<form id="settings" action="/options" method="post">
	<input type="hidden" name="e" value="3"/>
	<input type="hidden" name="s" value="3"/>
	<input type="hidden" name="a"
	       value="<?=Session::getInstance()->getChecker(); ?>"/>

	<div class="text">
		<?=T("Options", "MySittersDesc"); ?>
	</div>

	<script type="text/javascript">
		function cloneName(obj, id) {
			jQuery(id).text(obj.value.replace(/<\/?[^>]+(>|$)/g, ""));
			Travian.Form.UnloadHelper.updateSubmitButtons(jQuery('#settings'));
		}
	</script>
	<?php
	$sitter = $m->getMySitters(Session::getInstance()->getSittersId(1), Session::getInstance()->getSittersPermissions(1), Session::getInstance()->getSittersId(2), Session::getInstance()->getSittersPermissions(2));
	?>
	<table class="sitters transparent">
		<tr>
			<th>
				<div class="lightGreen"><?=T("Options", "sitter"); ?> I</div>
			</th>
			<td>
				<?php
				if($sitter[1]['uid'] == 0) {
					echo '<input onkeyup="cloneName(this, \'#sitterName0\')" class="text" type="text" name="sitter[0][name]" maxlength="15" value="" />';
					echo '<input type="hidden" name="sitter[0][id]" value="" />';
				} else {
					echo '<button type="button" class="icon " onclick="window.location.href = \'/options/sitter?e=3&amp;id='.$sitter[1]['uid'].'&amp;a='.Session::getInstance()->getChecker().'&amp;type=1\'; return false;"><img src="/img/spacer.gif" class="del" alt="del"></button>&nbsp;'.$sitter[1]['name'];
					echo '<input type="hidden" name="sitter[0][id]" value="'.(int)$sitter[1]['uid'].'" />';
					echo '<input type="hidden" name="sitter[0][name]" value="'.htmlspecialchars((string)$sitter[1]['name'], ENT_QUOTES, 'UTF-8').'" />';
				}
				?>
			</td>
		</tr>
		<tr>
			<th>
				<div class="orange"><?=T("Options", "sitter"); ?> II</div>
			</th>
			<td>
				<?php
				if($sitter[2]['uid'] == 0) {
					echo '<input onkeyup="cloneName(this, \'#sitterName1\')" class="text" type="text" name="sitter[1][name]" maxlength="15" value="" />';
					echo '<input type="hidden" name="sitter[1][id]" value="" />';
				} else {
					echo '<button type="button" class="icon " onclick="window.location.href = \'/options/sitter?e=3&amp;id='.$sitter[2]['uid'].'&amp;a='.Session::getInstance()->getChecker().'&amp;type=1\'; return false;"><img src="/img/spacer.gif" class="del" alt="del"></button>&nbsp;'.$sitter[2]['name'];
					echo '<input type="hidden" name="sitter[1][id]" value="'.(int)$sitter[2]['uid'].'" />';
					echo '<input type="hidden" name="sitter[1][name]" value="'.htmlspecialchars((string)$sitter[2]['name'], ENT_QUOTES, 'UTF-8').'" />';
				}
				?>
			</td>
		</tr>
	</table>

	<table cellpadding="1" cellspacing="1" class="sitters2 spacer">
		<tbody>
		<tr class="sitterHead">
			<th><?=T("Options", "player name"); ?></th>

			<td class="name"
			    id="sitterName0"><?=$sitter[1]['name']; ?></td>


			<td class="name"
			    id="sitterName1"><?=$sitter[2]['name']; ?></td>

		</tr>
		<tr>
			<th><?=T("Options", "Send raids"); ?></th>
			<td>
				<input type="checkbox" value="1"
				       name="sitter[0][flags][]" <?=$sitter[1]['perm'] & 1 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
			<td>
				<input type="checkbox" value="1"
				       name="sitter[1][flags][]" <?=$sitter[2]['perm'] & 1 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
		</tr>
		<tr>
			<th><?=T("Options", "Send reinforcements to other players"); ?></th>
			<td>
				<input type="checkbox" value="2"
				       name="sitter[0][flags][]" <?=$sitter[1]['perm'] & 2 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
			<td>
				<input type="checkbox" value="2"
				       name="sitter[1][flags][]" <?=$sitter[2]['perm'] & 2 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
		</tr>
		<tr>
			<th><?=T("Options", "Send resources to other players"); ?></th>
			<td>
				<input type="checkbox" value="4"
				       name="sitter[0][flags][]" <?=$sitter[1]['perm'] & 4 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
			<td>
				<input type="checkbox" value="4"
				       name="sitter[1][flags][]" <?=$sitter[2]['perm'] & 4 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
		</tr>
		<tr>
			<th><?=T("Options", "Buy and spend Gold"); ?></th>
			<td>
				<input type="checkbox" value="8"
				       name="sitter[0][flags][]" <?=$sitter[1]['perm'] & 8 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
			<td>
				<input type="checkbox" value="8"
				       name="sitter[1][flags][]" <?=$sitter[2]['perm'] & 8 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
		</tr>
		<tr>
			<th><?=T("Options", "Read and send messages"); ?></th>
			<td>
				<input type="checkbox" value="16"
				       name="sitter[0][flags][]" <?=$sitter[1]['perm'] & 16 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
			<td>
				<input type="checkbox" value="16"
				       name="sitter[1][flags][]" <?=$sitter[2]['perm'] & 16 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
		</tr>
		<tr>
			<th><?=T("Options", "Delete and archive messages and reports"); ?></th>
			<td>
				<input type="checkbox" value="32"
				       name="sitter[0][flags][]" <?=$sitter[1]['perm'] & 32 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
			<td>
				<input type="checkbox" value="32"
				       name="sitter[1][flags][]" <?=$sitter[2]['perm'] & 32 ? ' checked="checked"' : ''; ?>
				       class="check"/>
			</td>
		</tr>
        <tr>
            <th><?=T("Options", "Contribute resources to alliance bonuses"); ?></th>
            <td>
                <input type="checkbox" value="64"
                       name="sitter[0][flags][]" <?=$sitter[1]['perm'] & 64 ? ' checked="checked"' : ''; ?>
                       class="check"/>
            </td>
            <td>
                <input type="checkbox" value="64"
                       name="sitter[1][flags][]" <?=$sitter[2]['perm'] & 64 ? ' checked="checked"' : ''; ?>
                       class="check"/>
            </td>
        </tr>
		</tbody>
	</table>
	<div class="text"><?= T("Options", "SitterInactiveDaysNote"); ?></div>

	<h4 class="round spacer"><?=T("Options", "Sitting for other account(s)"); ?></h4>

	<div class="text">
		<?=T("Options", "Sitting for other account(s)Desc"); ?>
	</div>
	<?php
	$sitter = $m->getSitters(Session::getInstance()->getPlayerId());
	?>
	<table class="sitters transparent">
		<tr>
			<th>
				<div class="lightGreen"><?=T("Options", "sitter"); ?> I</div>
			</th>
			<td>
				<?php
				if($sitter[1]['uid'] == 0) {
					echo '<span class="none">'.T("Options", "no entry").'</span>';
				} else {
					echo '<button type="button" class="icon " onclick="window.location.href = \'/options/sitter?e=3&amp;id='.$sitter[1]['uid'].'&amp;a='.Session::getInstance()->getChecker().'&amp;type=2\'; return false;"><img src="/img/spacer.gif" class="del" alt="del"></button>&nbsp;'.$sitter[1]['name'];
				}
				?>
			</td>
		</tr>
		<tr>
			<th>
				<div class="orange"><?=T("Options", "sitter"); ?> II</div>
			</th>
			<td>
				<?php
				if($sitter[2]['uid'] == 0) {
					echo '<span class="none">'.T("Options", "no entry").'</span>';
				} else {
					echo '<button type="button" class="icon " onclick="window.location.href = \'/options/sitter?e=3&amp;id='.$sitter[2]['uid'].'&amp;a='.Session::getInstance()->getChecker().'&amp;type=2\'; return false;"><img src="/img/spacer.gif" class="del" alt="del"></button>&nbsp;'.$sitter[2]['name'];
				}
				?>
			</td>
		</tr>
	</table>

	<table cellpadding="1" cellspacing="1" class="sitters2 spacer">
		<tbody>
		<tr class="sitterHead">
			<th><?=T("Options", "player name"); ?></th>

			<td id="sittingsName0"
			    class="name"><?=$sitter[1]['name']; ?></td>


			<td id="sittingsName1"
			    class="name"><?=$sitter[2]['name']; ?></td>

		</tr>

		<tr>

			<th><?=T("Options", "Send raids"); ?></th>
			<td>
				<input type="checkbox" value="1"
				       disabled="disabled"
				       class="check" <?=$sitter[1]['perm'] & 1 ? ' checked="checked"' : ''; ?> />
			</td>
			<td>
				<input type="checkbox" value="1"
				       disabled="disabled"
				       class="check" <?=$sitter[2]['perm'] & 1 ? ' checked="checked"' : ''; ?> />
			</td>
		</tr>
		<tr>

			<th><?=T("Options", "Send reinforcements to other players"); ?></th>
			<td>
				<input type="checkbox" value="2"
				       disabled="disabled"
				       class="check" <?=$sitter[1]['perm'] & 2 ? ' checked="checked"' : ''; ?> />
			</td>
			<td>
				<input type="checkbox" value="2"
				       disabled="disabled"
				       class="check" <?=$sitter[2]['perm'] & 2 ? ' checked="checked"' : ''; ?> />
			</td>
		</tr>
		<tr>

			<th><?=T("Options", "Send resources to other players"); ?></th>
			<td>
				<input type="checkbox" value="4"
				       disabled="disabled"
				       class="check" <?=$sitter[1]['perm'] & 4 ? ' checked="checked"' : ''; ?> />
			</td>
			<td>
				<input type="checkbox" value="4"
				       disabled="disabled"
				       class="check" <?=$sitter[2]['perm'] & 4 ? ' checked="checked"' : ''; ?> />
			</td>
		</tr>
		<tr>

			<th><?=T("Options", "Buy and spend Gold"); ?></th>
			<td>
				<input type="checkbox" value="8"
				       disabled="disabled"
				       class="check" <?=$sitter[1]['perm'] & 8 ? ' checked="checked"' : ''; ?> />
			</td>
			<td>
				<input type="checkbox" value="8"
				       disabled="disabled"
				       class="check" <?=$sitter[2]['perm'] & 8 ? ' checked="checked"' : ''; ?> />
			</td>
		</tr>
		<tr>

			<th><?=T("Options", "Read and send messages"); ?></th>
			<td>
				<input type="checkbox" value="16"
				       disabled="disabled"
				       class="check" <?=$sitter[1]['perm'] & 16 ? ' checked="checked"' : ''; ?> />
			</td>
			<td>
				<input type="checkbox" value="16"
				       disabled="disabled"
				       class="check" <?=$sitter[2]['perm'] & 16 ? ' checked="checked"' : ''; ?> />
			</td>
		</tr>
		<tr>

			<th><?=T("Options", "Delete and archive messages and reports"); ?></th>
			<td>
				<input type="checkbox" value="32"
				       disabled="disabled"
				       class="check" <?=$sitter[1]['perm'] & 32 ? ' checked="checked"' : ''; ?> />
			</td>
			<td>
				<input type="checkbox" value="32"
				       disabled="disabled"
				       class="check" <?=$sitter[2]['perm'] & 32 ? ' checked="checked"' : ''; ?> />
			</td>
		</tr>
        <tr>
            <th><?=T("Options", "Contribute resources to alliance bonuses"); ?></th>
            <td>
                <input type="checkbox" value="64"
                       disabled="disabled"
                       class="check" <?=$sitter[1]['perm'] & 64 ? ' checked="checked"' : ''; ?> />
            </td>
            <td>
                <input type="checkbox" value="64"
                       disabled="disabled"
                       class="check" <?=$sitter[2]['perm'] & 64 ? ' checked="checked"' : ''; ?> />
            </td>
        </tr>

		</tbody>
	</table>

	<div class="submitButtonContainer">
		<button type="submit" value="<?=T("Global", "General.save"); ?>" name="s1" id="btn_ok" class="textButtonV1 green " version="textButtonV1"><?=T("Global", "General.save"); ?></button>
		<script type="text/javascript">
			jQuery(function() {
				if (jQuery('#btn_ok').length > 0) {
					jQuery('#btn_ok').click(function (event) {
						jQuery(window).trigger('buttonClicked', [this, {
							"type": "submit",
							"value": "<?=T("Global", "General.save");?>",
							"name": "s1",
							"id": "btn_ok",
							"class": "textButtonV1 green ",
							"title": "",
							"confirm": "",
							"onclick": "",
							"version": "textButtonV1"
						}]);
					});
				}
			});
		</script>
	</div>
</form>
<script type="text/javascript">
	jQuery(function() {
		Travian.Form.UnloadHelper.watchHtmlForm(jQuery('#settings'));
	});
</script>
