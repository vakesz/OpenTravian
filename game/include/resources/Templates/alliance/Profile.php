<?php
/** @var array<string, mixed> $vars Injected by resources\View\PHPBatchView::display(). */
$aid = (int) $vars['aid'];
$isMy = !empty($vars['isMyAlliance']);
$base = $isMy ? '/alliance' : ('/alliance/' . $aid);
?>
<div class="allianceDetails">
	<h4 class="roundV2 details"><?= T("Alliance", "Details"); ?></h4>
	<table cellpadding="1" cellspacing="1" class="transparent details">
		<tbody>
			<tr>
				<th><?= T("Alliance", "Tag"); ?>:</th>
				<td colspan="2"><?= htmlspecialchars((string) $vars['tag'], ENT_QUOTES, 'UTF-8') ?></td>
			</tr>
			<tr>
				<th><?= T("Alliance", "Name"); ?>:</th>
				<td class="name" colspan="2"><?= htmlspecialchars((string) $vars['name'], ENT_QUOTES, 'UTF-8') ?></td>
			</tr>
			<tr>
				<th><?= T("Alliance", "Members"); ?></th>
				<td colspan="2"><?= (int) $vars['Members'] ?></td>
			</tr>
			<tr>
				<th colspan="3"><strong><?= T("Alliance", "Ranks and level"); ?></strong></th>
			</tr>
			<tr>
				<th><?= T("Alliance", "Ranking"); ?></th>
				<td><?= (int) $vars['rank'] ?></td>
				<td class="value"><?= (int) $vars['points'] ?> <?= T("Alliance", "points") ?></td>
			</tr>
			<tr>
				<th><?= T("Alliance", "Attacker"); ?></th>
				<td><?= (int) $vars['attackerRank'] ?></td>
				<td class="value"><?= (int) $vars['attackerPoints'] ?> <?= T("Alliance", "points") ?></td>
			</tr>
			<tr>
				<th><?= T("Alliance", "Defender"); ?></th>
				<td><?= (int) $vars['defenderRank'] ?></td>
				<td class="value"><?= (int) $vars['defenderPoints'] ?> <?= T("Alliance", "points") ?></td>
			</tr>
			<?php if (!empty($vars['hasForum'])): ?>
			<tr>
				<td colspan="3">
					<a class="a arrow" href="<?= htmlspecialchars($base . '/forum', ENT_QUOTES, 'UTF-8') ?>"><?= T("Alliance", "toTheForum"); ?></a>
				</td>
			</tr>
			<?php endif; ?>
			<?php if (empty($vars['isMyAlliance'])): ?>
			<?php
			$accuseReasons = [
				'multiAccount' => T('Alliance', 'Multiaccount(s)'),
				'passwordSharing' => T('Alliance', 'Password sharing'),
				'botScript' => T('Alliance', 'Scripts & bots'),
				'inappropriateContent' => T('Alliance', 'Inappropriate content'),
				'privateFarm' => T('Alliance', 'Private farm'),
				'other' => T('Alliance', 'Other'),
			];
			?>
			<tr>
				<td colspan="3">
					<div class="accuseAlliance">
						<a href="#" class="button icon" title="<?= T('Alliance', 'Accuse alliance'); ?>"
						   onclick='Travian.Game.AccuseAlliance({accusationReasons: <?= json_encode($accuseReasons, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) ?>, allianceId: <?= (int) $aid; ?>}); return false;'>
							<svg viewBox="0 0 121.68 98.77" aria-hidden="true">
								<path d="M119.54 76.74L72.11 8.7C66.74 1 54.39 1 49.07 8.75l-47 68c-5.7 8.28.82 19 11.55 19H108c10.78.09 17.3-10.75 11.54-19.01zm-67-45.2a6.93 6.93 0 0 1 6.93-6.94h2.78a6.94 6.94 0 0 1 6.94 6.94v26.12a6.94 6.94 0 0 1-6.94 6.94h-2.8a6.93 6.93 0 0 1-6.93-6.94zm8.32 55.89A8.41 8.41 0 1 1 69.25 79a8.41 8.41 0 0 1-8.41 8.43z"></path>
							</svg>
						</a>
					</div>
					<script>
					Travian.Game = Travian.Game || {};
					if (typeof Travian.Game.AccuseAlliance !== 'function') {
						Travian.Game.AccuseAlliance = function (opts) {
							opts = opts || {};
							var reasons = opts.accusationReasons || {};
							var html = '<div class="accuseAllianceDialog"><p><?= T('Alliance', 'Why do you want to accuse this alliance?'); ?></p><ul class="options">';
							Object.keys(reasons).forEach(function (key) {
								html += '<li><label><input type="radio" name="accusationReason" value="' + key + '"> ' + reasons[key] + '</label></li>';
							});
							html += '</ul></div>';
							new Travian.Dialog.Dialog({preventFormSubmit: true}).setContent(html).show();
						};
					}
					</script>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<h4 class="roundV2 allianceBanner"><?= T("Alliance", "Alliance banner"); ?></h4>
	<?= !empty($vars['bannerTemplateSvg']) ? $vars['bannerTemplateSvg'] : '<svg id="bannerTemplateSVG" width="0" height="0" aria-hidden="true"><defs></defs></svg>'; ?>
	<?php if (!empty($vars['bannerSvg'])): ?>
		<?= $vars['bannerSvg']; ?>
	<?php endif; ?>
	<?php if (!empty($vars['hasPosition'])): ?>
		<h4 class="roundV2 members"><?= T("Alliance", "Position"); ?></h4>
		<div class="members">
		<table cellpadding="1" cellspacing="1" class="transparent">
			<tbody>
			<?= $vars['position']; ?>
			</tbody>
		</table>
		</div>
	<?php endif; ?>
</div>
<div class="contentNavi tabNavi">
    <div title="" class="container <?= $vars['action'] == 'description' ? 'active' : 'normal'; ?>">
        <div class="background-start">&nbsp;</div>
        <div class="background-end">&nbsp;</div>
        <div class="content favor <?= $vars['favorActionId'] == 'description' ? 'favorActive' : ''; ?> favorKeydescription">
            <a id="<?= $button_id = get_button_id(); ?>" href="<?= htmlspecialchars($base . '/profile/description', ENT_QUOTES, 'UTF-8') ?>" class="tabItem">
                <?= T("Alliance", "desc"); ?>
                    <?php if ($vars['favorActionId'] === 'description'): ?><img src="/img/spacer.gif" class="favorIcon" alt="<?= T("inGame", "This tab is selected as your fav tab"); ?>"><?php endif; ?>
            </a>
        </div>
    </div>

    <script type="text/javascript">
            jQuery('#<?= $button_id; ?>').click(function (event)
            {
                jQuery(window).trigger('tabClicked', [this, {"class":"active","title":false,"target":false,"id":"<?= $button_id; ?>","href":"<?= $base ?>/profile/description","onclick":false,"enabled":true,"text":"Description","dialog":false,"plusDialog":false,"goldclubDialog":false,"containerId":"","buttonIdentifier":"<?= $button_id; ?>"}]);

            });
    </script>

    <div title="" class="container <?= $vars['action'] == 'members' ? 'active' : 'normal'; ?>">
        <div class="background-start">&nbsp;</div>
        <div class="background-end">&nbsp;</div>
        <div class="content favor <?= $vars['favorActionId'] == 'members' ? 'favorActive' : ''; ?> favorKeymembers">

            <a id="<?= $button_id = get_button_id(); ?>" href="<?= htmlspecialchars($base . '/profile/members', ENT_QUOTES, 'UTF-8') ?>" class="tabItem">
                <?= T("Alliance", "Members"); ?>
                    <?php if ($vars['favorActionId'] === 'members'): ?><img src="/img/spacer.gif" class="favorIcon" alt="<?= T("inGame", "This tab is selected as your fav tab"); ?>"><?php endif; ?>
            </a>
        </div>
    </div>

    <script type="text/javascript">
        if(jQuery('#<?= $button_id; ?>'))
        {
            jQuery('#<?= $button_id; ?>').click(function (event)
            {
                jQuery(window).trigger('tabClicked', [this, {"class":"normal","title":false,"target":false,"id":"<?= $button_id; ?>","href":"<?= $base ?>/profile/members","onclick":false,"enabled":true,"text":"Members","dialog":false,"plusDialog":false,"goldclubDialog":false,"containerId":"","buttonIdentifier":"<?= $button_id; ?>"}]);

            });
        }
    </script>
    <a type="submit" id="nestedTabFavorButton" class="icon nestedTabRowButton " title="<?= T("inGame", "Set tab"); ?>" onclick="Travian.api('favourite-tab', {data: {name: 'allyPageProfile',key: '<?= $vars['action']; ?>'},success: function(data) {jQuery('.tabNavi .favor').removeClass('favorActive');jQuery('.favor.favorKey<?= $vars['action']; ?>').addClass('favorActive');}});return false;" useicon=""><img src="/img/spacer.gif" class="&nbsp;" alt="" /></a>
    <div class="clear"></div>
</div>
<?php if ($vars['action'] === 'description'): ?>
<div class="description description1">
    <?= $vars['desc1']; ?>
</div>
<div class="description description2">
    <?= $vars['desc2']; ?>
</div>
<?php endif; ?>
<div class="clear"></div>
<?php if ($vars['action'] === 'members'): ?>
    <table cellspacing='1' cellpadding='2' class='allianceMembers'>
		<thead>
			<tr>
				<td class="counter"></td>
				<td class="tribe"></td>
				<td class="player"><i class="player_medium"></i></td>
				<td class="population"><i class="population_medium"></i></td>
				<td class="villages"><i class="village_medium"></i></td>
				<td class="buttons"></td>
			</tr>
		</thead>
		<tbody>
			<?= $vars['MembersHTML']; ?>
		</tbody>
	</table>
    <script type="text/javascript">
        AllianceMembersButtonsDoubleClickPreventer = new Travian.DoubleClickPreventer();
        AllianceMembersButtonsDoubleClickPreventer.timeout = 1000;
        jQuery(function () {

            jQuery('button.editNote').on("click", function(e) {
                e.preventDefault();

                if (!AllianceMembersButtonsDoubleClickPreventer.check()) {
                    return false;
                }
                new Travian.Game.AllianceMembers(
                    {
                        data: {
                            cmd: 'playerNote',
                            affectedPlayerID: this.value,
                            hasNote: this.getAttribute('data-note'),
                            hasSpecialization: this.getAttribute('data-spec'),
                            action: 'edit'
                        },
                        context: 'playerNote',
                        buttonOk: false,
                        darkOverlay: true
                    }
                );

            });
        });
    </script>
<?php endif; ?>
