<div class="statusCard statusCard--<?=htmlspecialchars((string)$vars['tone'], ENT_QUOTES, 'UTF-8');?>" role="<?=$vars['tone'] === 'error' ? 'alert' : 'status';?>">
    <h1 class="statusCardHeading"><?=htmlspecialchars((string)$vars['heading'], ENT_QUOTES, 'UTF-8');?></h1>
    <p class="statusCardMessage"><?=htmlspecialchars((string)$vars['message'], ENT_QUOTES, 'UTF-8');?></p>
    <?php if ($vars['actionHref'] !== ''): ?>
        <p class="statusCardAction"><a href="<?=htmlspecialchars((string)$vars['actionHref'], ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars((string)$vars['actionLabel'], ENT_QUOTES, 'UTF-8');?></a></p>
    <?php endif; ?>
</div>
