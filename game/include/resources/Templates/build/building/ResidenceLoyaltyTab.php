<div id="loyaltyTab">
    <?=T(
        "ResidencePalace",
        "A palace or residence protect a village from being conquered If the seat of government has been destroyed, a village`s loyalty can be lowered by attacks with chieftains, chiefs and senators If the loyalty is lowered to zero, the village will join the attacker`s empire An ongoing great celebration in the village of either attacker or defender will increase or lower the rate at which each attacking administrator will lower the loyalty Each level of the seat of government increases the speed at which the loyalty of a village increases to 100% again A hero stationed in the village can use tablets of law to additionally increase loyalty"
    ); ?>

    <br>
    <br>
    <?=T("ResidencePalace", "Loyalty in the current village"); ?>: <span
        class="currentLoyalty">&#x202d;&#x202d;<?=$vars['loyalty']; ?>&#x202c;&#37;&#x202c;</span>
    <br>
    <br>
    <h4 class="round"><?= T('ResidencePalace', 'Alliance / Confederacy'); ?></h4>
    <?= T('ResidencePalace', 'Attacking administrators of your alliance/confederacy cannot reduce loyalty, except when there is an artifact in this village or you\'ve given permission'); ?>
    <br>
    <br>
    <div class="allowLoyaltyDecrease">
        <input class="check" id="allowLoyaltyDecrease" name="allowLoyaltyDecrease" type="checkbox" value="true"<?= $vars['allowLoyaltyDecrease'] ? ' checked="checked"' : ''; ?>>
        <label for="allowLoyaltyDecrease"><?= T('ResidencePalace', 'Allow attacking administrators of your alliance/confederacy to lower loyalty'); ?></label>
    </div>
    <br>
    <br>
    <h4 class="round"><?=T(
            "ResidencePalace", "Loyalty overview"
        ); ?></h4>
    <table cellpadding="1" cellspacing="1">
        <thead>
        <tr>
            <th><?=T("ResidencePalace", "village"); ?></th>
            <td><?=T("ResidencePalace", "Inhabitants"); ?></td>
            <td><?=T("ResidencePalace", "Loyalty"); ?></td>
        </tr>
        </thead>
        <tbody>
        <?=$vars['content']; ?>
        </tbody>
    </table>
</div>
<script type="text/javascript">
    jQuery(function () {
        jQuery('#allowLoyaltyDecrease').on('change', function () {
            Travian.api('allowLoyaltyDecrease', {
                data: {
                    villageId: <?= (int) $vars['villageId']; ?>,
                    state: this.checked
                }
            });
        });
    });
</script>
