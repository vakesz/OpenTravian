<?php

declare(strict_types=1);

/*
 * Content of the "Sell item" dialog. A stackable item asks for the size of the stack to sell; the button stays
 * disabled until one is chosen unless there is only one possible size.
 */

$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
$amounts = $vars['amounts'];
?>
<div class="contentV2">
    <h3><?= $e((string)T('HeroInventory', 'sellItem')); ?></h3>
    <div id="sellItemForm" class="formV2">
        <?= $vars['information']; ?>
        <div class="actionButtons">
            <button class="textButtonV2 buttonFramed cancel rectangle withText grey" type="button"><div><?= $e((string)T('HeroInventory', 'buttonCancel')); ?></div></button>
            <?php if ($amounts !== []): ?>
                <label class="select batchSize" data-spacer="<?= $e((string)T('Auction', 'Batch size')); ?>">
                    <select size="1" name="batchSize">
                        <?php if (!$vars['autoSelect']): ?><option></option><?php endif; ?>
                        <?php foreach ($amounts as $amount): ?><option value="<?= (int)$amount; ?>"><?= (int)$amount; ?></option><?php endforeach; ?>
                    </select>
                    <div class="label"><?= $e((string)T('Auction', 'Batch size')); ?></div>
                    <div class="validation" data-rule="notEmpty"></div>
                </label>
            <?php endif; ?>
            <button class="textButtonV2 buttonFramed sell<?= $amounts !== [] && !$vars['autoSelect'] ? ' disabled' : ''; ?> rectangle withText green" type="submit"><div><?= $e((string)T('HeroInventory', 'sellItem')); ?></div></button>
        </div>
    </div>
</div>
