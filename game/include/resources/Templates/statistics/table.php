<?php

use Core\Session;

$formAction = htmlspecialchars((string)($vars['formAction'] ?? '/statistics/player/overview'), ENT_QUOTES, 'UTF-8');
?>
<h4 class="roundV2"><?= $vars['headerRound']; ?></h4>
<?php if (Session::getInstance()->isAdmin()): ?>
<style type="text/css">
    div.statistics td.pla {
        max-width: none;
    }
</style>
<?php endif; ?>
<table cellpadding="1" cellspacing="1" id="<?= htmlspecialchars((string) ($vars['tableId'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="row_table_data borderGap">
    <thead>
    <?= $vars['tableColumns']; ?>
    </thead>
    <tbody class="hoverable">
    <?= $vars['tableBody']; ?>
    </tbody>
</table>
<div id="search_navi">
    <div class="searchWrapper">
    <form method="post" action="<?= $formAction; ?>">
			<table class="transparent">
				<tr>
					<td><span>
								<?= T("Statistics", "rank", "Rank"); ?> <input type="text" inputmode="numeric" class="text ra" maxlength="5" name="rank" value="<?= htmlspecialchars((string) $vars['selectedRank'], ENT_QUOTES, 'UTF-8'); ?>"/>
							</span></td>
					<td><span>
								<?= T("Global", "General.or", "or"); ?> <?= mb_strtolower(T("Statistics", "name", "Name")); ?>: <input type="text" class="text name" maxlength="20" name="name" value="<?= htmlspecialchars((string) $vars['selectedName'], ENT_QUOTES, 'UTF-8'); ?>"/>
							</span></td>
					<td>
                        <button type="submit" value="<?= T("Global", "General.ok", "OK"); ?>" name="submit" id="submit" class="textButtonV1 green" version="textButtonV1"><?= T("Global", "General.ok", "OK"); ?></button>
                        <script type="text/javascript">
                            jQuery(function() {
                                if (jQuery('#submit')) {
                                    jQuery('#submit').click(function (event) {
                                        jQuery(window).trigger('buttonClicked', [this, {
                                            "type": "submit",
                                            "value": "<?= T("Global", "General.ok", "OK"); ?>",
                                            "name": "submit",
                                            "id": "submit",
                                            "class": "textButtonV1 green",
                                            "title": "",
                                            "confirm": "",
                                            "onclick": "",
                                            "version": "textButtonV1"
                                        }]);
                                    });
                                }
                            });
                        </script>
                    </td>
				</tr>
			</table>
    </form>
    </div>
    <?= $vars['Navigator']; ?>
</div>
