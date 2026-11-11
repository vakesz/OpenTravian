<?php

declare(strict_types=1);

/**
 * The block that replaces the adventure list once the hero is on its way.
 *
 * @var array<string, mixed> $vars place (class of the adventure place), timer (countdown markup), arrivalAt, rallyPointUrl
 */
$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
?>
<div class="adventureStarted <?= $e((string) $vars['place']); ?>">
    <div class="heroState">
        <i class="statusRunning_medium" aria-hidden="true"></i>
        <div>
            <span><?= $e((string) T('HeroAdventure', 'heroOnWayToAdventure', 'Your hero is on its way to an adventure.')); ?></span>&nbsp;
            <span><?= $e((string) T('HeroAdventure', 'Arrival in', 'Arrival in')); ?> <?= $vars['timer']; ?> <?= $e((string) T('HeroAdventure', 'at', 'at')); ?> <?= $e((string) $vars['arrivalAt']); ?></span>
            <br>
            <span class="hint">(<?= $e((string) T('HeroAdventure', 'adventureProgressHintPrefix', "The adventure's progress can be seen in the")); ?> <a href="<?= $e((string) $vars['rallyPointUrl']); ?>"><?= $e((string) T('HeroAdventure', 'Rally point', 'Rally point')); ?></a>.)</span>
        </div>
    </div>
    <button type="button" class="textButtonV2 buttonFramed continue rectangle withText green">
        <div><?= $e((string) T('HeroAdventure', 'Continue', 'Continue')); ?></div>
    </button>
</div>
