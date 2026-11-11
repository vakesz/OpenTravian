<a name="troopEscape"></a>
<h4 class="round"><?= T("RallyPoint", "EvasionSettings"); ?></h4>
<?php if ($vars['goldClub']): ?>
    <form class="troopEscape" action="/build.php#troopEscape" method="post">
        <div class="troopEscape">
            <input type="hidden" name="id" value="39"/>
            <input type="hidden" name="troopEscape" value="<?=$vars['evasion']; ?>"/>
            <input type="hidden" name="tt" value="0"/>
            <input class="check" id="troop_escape_active" name="troop_escape_active"
                   type="checkbox"<?=$vars['evasion'] == 1 ? "checked" : ''; ?>
                   value="<?=$vars['evasion'] == 1 ? "true" : "false"; ?>"/>
            <label for="troop_escape_active"><?=T("RallyPoint", "EvasionDesc"); ?></label>
        </div>
        <p><?=T("RallyPoint", $vars['heroEvasion'] == 0 ? "HeroShowDesc" : "HeroHideDesc"); ?></p>
        <div>
            <?=$vars['evasionSaveButton']; ?>
        </div>
    </form>
<?php else: ?>
    <div class="build_desc"><p><?=$vars['goldClubEvasionDesc']; ?></p>
        <?=$vars['goldClubButton']; ?>
    </div>
<?php endif; ?>
