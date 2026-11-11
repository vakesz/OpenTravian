<?php
/** @var array<string, mixed> $vars Injected by resources\View\PHPBatchView::display(). */
?>
<div class="top10Wrapper">
    <h4 class="round small  top top10_offs"><?= T("Alliance", "PvP of the week"); ?></h4>
    <table cellpadding="1" cellspacing="1" id="top10_offs" class="top10 row_table_data borderGap">
        <thead>
        <tr>
            <td class="ra"><?= T("Statistics", "top10.No"); ?>.</td>
            <td></td>
            <td class="pla"><?= T("Alliance", "Player"); ?>(s)</td>
            <td class="val"><?= T("Alliance", "points"); ?></td>
        </tr>
        </thead>
        <tbody class="hoverable">
        <?= $vars['pvp']; ?>
        </tbody>
    </table>

    <h4 class="round small  top top10_offs"><?= T("Alliance", "PvE of the week"); ?></h4>
    <table cellpadding="1" cellspacing="1" id="top10_pve" class="top10 row_table_data borderGap">
        <thead>
        <tr>
            <td class="ra"><?= T("Statistics", "top10.No"); ?>.</td>
            <td></td>
            <td class="pla"><?= T("Alliance", "Player"); ?>(s)</td>
            <td class="val"><?= T("Alliance", "points"); ?></td>
        </tr>
        </thead>
        <tbody class="hoverable">
        <?= $vars['pve']; ?>
        </tbody>
    </table>

    <h4 class="round small spacer top top10_defs"><?= T("Alliance", "Defenders of the week"); ?></h4>
    <table cellpadding="1" cellspacing="1" id="top10_defs" class="top10 row_table_data borderGap">
        <thead>
        <tr>
            <td class="ra"><?= T("Statistics", "top10.No"); ?>.</td>
            <td></td>
            <td class="pla"><?= T("Alliance", "Player"); ?>(s)</td>
            <td class="val"><?= T("Alliance", "points"); ?></td>
        </tr>
        </thead>
        <tbody class="hoverable">
        <?= $vars['defs']; ?>
        </tbody>
    </table>

    <h4 class="round small spacer top top10_raiders"><?= T("Alliance", "Robbers of the week"); ?></h4>
    <table cellpadding="1" cellspacing="1" id="top10_raiders" class="top10 row_table_data borderGap">
        <thead>
        <tr>
            <td class="ra"><?= T("Statistics", "top10.No"); ?>.</td>
            <td></td>
            <td class="pla"><?= T("Alliance", "Player"); ?>(s)</td>
            <td class="val"><?= T("Alliance", "points"); ?></td>
        </tr>
        </thead>
        <tbody class="hoverable">
        <?= $vars['raiders']; ?>
        </tbody>
    </table>
</div>
