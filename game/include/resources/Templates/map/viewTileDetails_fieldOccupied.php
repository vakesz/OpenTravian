<div id="tileDetails" class="village village-<?= $vars['fieldType']; ?>">
    <?= $vars['heading']; ?>
    <div class="detailImage<?= !empty($vars['shore']) ? ' shore' : ''; ?> vid<?= $vars['vid']; ?> ">
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
        <h4><?= T("map", "distribution"); ?></h4>
        <table cellpadding="1" cellspacing="1" id="distribution" class="transparent">
            <tbody>
            <tr><?= $vars['distribution']; ?></tr>
            </tbody>
        </table>
        <table cellpadding="1" cellspacing="1" id="village_info" class="transparent">
            <tbody>
            <tr class="first">
                <th><?= T("map", "Tribe"); ?></th>
                <td><?= T("Global", "races." . $vars['Tribe']); ?></td>
            </tr>
            <tr>
                <th><?= T("map", "alliance"); ?></th>
                <td class="alliance"><a href="/alliance/<?= (int) $vars['aid']; ?>"><?= $vars['allianceTag'] ?? ''; ?></a></td>
            </tr>
            <tr>
                <th><?= T("map", "owner"); ?></th>
                <td class="player"><a href="<?= profileUrl((int) $vars['uid']); ?>"><?= $vars['playerName']; ?></a></td>
            </tr>
            <tr>
                <th><?= T("map", "pop"); ?></th>
                <td><?= $vars['pop']; ?></td>
            </tr>
            <tr>
                <th><?= T("map", "Distance"); ?></th>
                <td><?= $vars['distance']; ?> <?= T("map", "fields"); ?></td>
            </tr>
            </tbody>
        </table>
        <?= \resources\View\PHPBatchView::render('map/tileTabs', ['id' => 'villageInstantTabs', 'reports' => $vars['top5Attacks'], 'surrounding' => $vars['top5Surrounding']]); ?>
    </div>
    <div class="clear"></div>
</div>
