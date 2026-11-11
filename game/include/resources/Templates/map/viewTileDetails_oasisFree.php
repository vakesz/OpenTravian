<div id="tileDetails" class="oasis oasis-<?= $vars['oasisType']; ?>">
    <?= $vars['heading']; ?>
    <div class="detailImage">
        <div class="options">
            <?= $vars['options']; ?>
        </div>
        <?php if (isset($vars['farmOptions'])): ?>
        <br/>
        <div class="options">
            <?= $vars['farmOptions']; ?>
        </div>
        <?php endif; ?>
    </div>
    <div id="map_details">
        <h4><?= T("map", "Bonus"); ?></h4>
        <table cellpadding="1" cellspacing="1" id="distribution" class="transparent">
            <tbody>
            <?= $vars['distribution']; ?>
            </tbody>
        </table>
        <h4><?= T("map", "units"); ?></h4>
        <table cellpadding="1" cellspacing="1" id="troop_info" class="transparent">
            <tbody>
            <?= $vars['troops_info']; ?>
            </tbody>
        </table>
        <table cellpadding="1" cellspacing="1" id="distance" class="transparent">
            <tbody>
            <tr>
                <td class="desc"><?= T("map", "Distance"); ?></td>
                <td class="bold"><?= $vars['distance']; ?> <?= T("map", "fields"); ?></td>
            </tr>
            </tbody>
        </table>
        <?= \resources\View\PHPBatchView::render('map/tileTabs', ['id' => 'oasis1InstantTabs', 'reportsId' => 'mapReports', 'reports' => $vars['top5Attacks'], 'surrounding' => $vars['top5Surrounding']]); ?>
    </div>
    <div class="clear"></div>
</div>
