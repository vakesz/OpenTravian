<?php

declare(strict_types=1);

/** @var array{title: string, done: bool, all: bool, action: string} $vars */
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="supportCard unsubscribePage">
    <?php if ($vars['done']): ?>
        <p class="unsubscribed"><?= $e((string) T('Mail', 'You have been unsubscribed.')); ?></p>
    <?php else: ?>
        <form method="post" action="<?= $e($vars['action']); ?>">
            <button class="textButtonV2 buttonFramed rectangle withText green" type="submit" name="confirm" value="1"><div><?= $e((string) T('Mail', 'Confirm')); ?></div></button>
            <?php if (!$vars['all']): ?>
                <button class="textButtonV2 buttonFramed rectangle withText grey" type="submit" name="all" value="1"><div><?= $e((string) T('Mail', 'All email')); ?></div></button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
