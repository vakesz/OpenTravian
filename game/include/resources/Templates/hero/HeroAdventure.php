<?php

$heroStatusMessage = (string)($vars['heroStatusMessage'] ?? '');
$heroStatusIcon = htmlspecialchars((string)($vars['heroStatusIcon'] ?? 'statusHome_medium'), ENT_QUOTES, 'UTF-8');

?>
<script type="text/javascript">
    var adventureList = new Travian.AdventureList();
    jQuery(function () {
        Travian.Game.HeroAdventure.initialize();
    });
</script>

<div id="heroAdventure" class="contentV2">
    <div class="heroState">
        <i class="<?= $heroStatusIcon; ?>" aria-hidden="true"></i>
        <div><?= $heroStatusMessage; ?></div>
    </div>

    <table class="borderGap adventureList">
        <thead>
            <tr>
                <th class="place"><?= htmlspecialchars((string)T('HeroAdventure', 'location'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th class="distance"><?= htmlspecialchars((string)T('map', 'Distance'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th class="duration"><?= htmlspecialchars((string)T('HeroAdventure', 'moveTime'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th class="difficulty"><?= htmlspecialchars((string)T('HeroAdventure', 'difficulty'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th class="button"></th>
            </tr>
        </thead>
        <tbody>
            <?= $vars['tbody']; ?>
        </tbody>
    </table>
</div>
