<?php
// The shared <base href="/"> resolves a query-only location to the login
// front controller. Keep every filter destination rooted at the Rally Point.
$rallyPointQuery = '/build.php?gid=16&amp;tt=1';
?>
<div class="bulkTroopActionContainer hide"></div>
<div class="data rallyPointOverviewContainer">
    <div class="filterWrapper">
        <?php if ($vars['filter'] > 0 && $vars['filter'] < 3): ?>
        <div class="filterContainer">
            <?php if ($vars['filter'] == 1): ?>
                <button type="button" class="iconFilter <?= $vars['subFilters'][1] > 0 ? "iconFilterActive" : ''; ?>"
                        title="<?= T("RallyPoint", "SubFilters.1"); ?>"
                        onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=1&amp;subfilters=<?= \Game\RallyPointSubFilters::toggle(1, $vars['subFilters']); ?>'; return false;"
                        useicon=""><img src="/img/spacer.gif" class="filterCategory subFilterCategory1"></button>
                <button type="button" class="iconFilter <?= $vars['subFilters'][2] > 0 ? "iconFilterActive" : ''; ?>"
                        title="<?= T("RallyPoint", "SubFilters.2"); ?>"
                        onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=1&amp;subfilters=<?= \Game\RallyPointSubFilters::toggle(2, $vars['subFilters']); ?>'; return false;"
                        useicon=""><img src="/img/spacer.gif" class="filterCategory subFilterCategory2"></button>
                <button type="button" class="iconFilter <?= $vars['subFilters'][3] > 0 ? "iconFilterActive" : ''; ?>"
                        title="<?= T("RallyPoint", "SubFilters.3"); ?>"
                        onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=1&amp;subfilters=<?= \Game\RallyPointSubFilters::toggle(3, $vars['subFilters']); ?>'; return false;"
                        useicon=""><img src="/img/spacer.gif" class="filterCategory subFilterCategory3"></button>
            <?php else: ?>
                <button type="button" class="iconFilter <?= $vars['subFilters'][4] > 0 ? "iconFilterActive" : ''; ?>"
                        title="<?= T("RallyPoint", "SubFilters.4"); ?>"
                        onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=2&amp;subfilters=<?= \Game\RallyPointSubFilters::toggle(4, $vars['subFilters']); ?>&amp;sortOrder=<?= $vars['sortOrder']; ?>'; return false;"
                        useicon=""><img src="/img/spacer.gif" class="filterCategory subFilterCategory4"></button>
                <button type="button" class="iconFilter <?= $vars['subFilters'][5] > 0 ? "iconFilterActive" : ''; ?>"
                        title="<?= T("RallyPoint", "SubFilters.5"); ?>"
                        onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=2&amp;subfilters=<?= \Game\RallyPointSubFilters::toggle(5, $vars['subFilters']); ?>&amp;sortOrder=<?= $vars['sortOrder']; ?>'; return false;"
                        useicon=""><img src="/img/spacer.gif" class="filterCategory subFilterCategory5"></button>
                <?php $latestFirst = $vars['sortOrder'] === \Model\RallyPoint\RallyPointModel::SORT_LATEST_SENT_FIRST; ?>
                <button type="button" class="iconFilter <?= $latestFirst ? "iconFilterActive" : ''; ?>"
                        title="<?= T("RallyPoint", "SubFilters.6"); ?>"
                        onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=2&amp;sortOrder=<?= $latestFirst ? \Model\RallyPoint\RallyPointModel::SORT_EARLIEST_ARRIVING_FIRST : \Model\RallyPoint\RallyPointModel::SORT_LATEST_SENT_FIRST; ?>'; return false;"
                        useicon=""><img src="/img/spacer.gif" class="filterCategory sortOrder"></button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="filterContainer">
            <button type="button" class="iconFilter <?= $vars['filter'] == 1 ? "iconFilterActive" : ''; ?>"
                    title="<?= T("RallyPoint", "filters.1"); ?>"
                    onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=<?= $vars['filter'] == 1 ? 0 : 1; ?>'; return false;"
                    useicon=""><img src="/img/spacer.gif" class="filterCategory filterCategory1"></button>
            <button type="button" class="iconFilter <?= $vars['filter'] == 2 ? "iconFilterActive" : ''; ?>"
                    title="<?= T("RallyPoint", "filters.2"); ?>"
                    onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=<?= $vars['filter'] == 2 ? 0 : 2; ?>'; return false;"
                    useicon=""><img src="/img/spacer.gif" class="filterCategory filterCategory2"></button>
            <button type="button" class="iconFilter <?= $vars['filter'] == 3 ? "iconFilterActive" : ''; ?>"
                    title="<?= T("RallyPoint", "filters.3"); ?>"
                    onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=<?= $vars['filter'] == 3 ? 0 : 3; ?>'; return false;"
                    useicon=""><img src="/img/spacer.gif" class="filterCategory filterCategory3"></button>
            <button type="button" class="iconFilter <?= $vars['filter'] == 4 ? "iconFilterActive" : ''; ?>"
                    title="<?= T("RallyPoint", "filters.4"); ?>"
                    onclick="this.disabled = true; window.location.href = '<?= $rallyPointQuery; ?>&amp;filter=<?= $vars['filter'] == 4 ? 0 : 4; ?>'; return false;"
                    useicon=""><img src="/img/spacer.gif" class="filterCategory filterCategory4"></button>
        </div>
    </div>
    <?= $vars['content']; ?>
</div>
