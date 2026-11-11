<?php
/** @var array<string, mixed> $vars Injected by resources\View\PHPBatchView::display(). */
$aid = (int) ($vars['aid'] ?? 0);
$fn = !empty($vars['fn']) ? 1 : 0;
$filter = (int) ($vars['filter'] ?? -1);
$base = !empty($vars['ownAllianceRoutes'])
    ? '/alliance/reports'
    : ('/alliance/' . $aid . '/attacks');
$reportFilters = [
    1 => 'Won as attacker without losses.',
    2 => 'Won as attacker with losses.',
    3 => 'Lost as attacker.',
    4 => 'Won as defender without losses.',
    5 => 'Won as defender with losses.',
    6 => 'Lost as defender with losses.',
    7 => 'Lost as defender without losses.',
    15 => 'Spying was successful and not detected.',
    16 => 'Spying was successful, but detected.',
    17 => 'Spying failed.',
    18 => 'Spying was stopped successfully.',
    19 => 'Spying could not be stopped.',
];
?>
<div class="filterWrapper">
    <div class="reportFilter offDef">
        <?php foreach ($reportFilters as $type => $alt): ?>
            <?php
            $active = $filter === $type ? ' iconFilterActive' : '';
            $href = htmlspecialchars($base . '?f=' . $type . '&fn=' . $fn, ENT_QUOTES, 'UTF-8');
            ?>
            <button type="submit" class="iconFilter allianceReportFilterButton<?= $active ?>"
                    alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>"
                    title="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>"
                    data-filter="<?= $type ?>" useicon=""
                    onclick="window.location.href = '<?= $href ?>'; return false;">
                <img src="/img/spacer.gif" class="iReport iReport<?= $type ?>"/>
            </button>
        <?php endforeach; ?>
    </div>
    <div class="reportFilter unimportant">
        <?php
        $ownActive = $fn === 1 ? ' iconFilterActive' : '';
        $ownHref = htmlspecialchars($base . '?f=-1&fn=' . ($fn === 1 ? 0 : 1), ENT_QUOTES, 'UTF-8');
        ?>
        <button type="button" class="iconFilter allianceReportOwnFilterButton<?= $ownActive ?>"
                title="<?= htmlspecialchars(T("Alliance", "Don´t show attacks of own alliance (under 100 units, no losses)"), ENT_QUOTES, 'UTF-8') ?>"
                onclick="window.location.href = '<?= $ownHref ?>'; return false;">
            <img src="/img/spacer.gif" class="filterNews" alt="filterNews"/>
        </button>
    </div>
</div>
<table cellpadding="1" cellspacing="1" id="offs">
    <thead>
    <tr>
        <td><?= T("Alliance", "Player"); ?></td>
        <td><?= T("Alliance", "Alliance"); ?></td>
        <td><?= T("Alliance", "Date"); ?></td>
    </tr>
    </thead>
    <tbody>
    <?= $vars['attacks']; ?>
    </tbody>
</table>
