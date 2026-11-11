<?php
/** @var array{ignoreList: array<int, string>} $vars */
?>
<table cellpadding="1" cellspacing="1" class="transparent">
    <tbody>
    <?php for ($index = 1; $index <= 20; $index += 2): ?>
        <tr>
            <?=$vars['ignoreList'][$index]; ?>
            <?=$vars['ignoreList'][$index + 1]; ?>
        </tr>
    <?php endfor; ?>
    </tbody>
</table>
