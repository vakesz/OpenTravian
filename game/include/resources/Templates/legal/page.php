<?php

declare(strict_types=1);

/**
 * @var array{title: string, html: string} $vars
 */
?>
<div class="supportCard legalPage">
    <div class="legalPageBody"><?= $vars['html'] /* LegalMarkdown output: escaped, constrained markup */ ?></div>
</div>
