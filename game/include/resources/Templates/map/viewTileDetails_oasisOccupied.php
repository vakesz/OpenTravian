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
        <table cellpadding="1" cellspacing="1" id="village_info" class="transparent">
            <tr class="first">
                <th><?= T("map", "Tribe"); ?></th>
                <td><?= T("Global", "races." . $vars['Tribe']); ?></td>
            </tr>
            <tr>
                <th><?= T("map", "alliance"); ?></th>
                <td><a href="/alliance/<?= (int) $vars['aid']; ?>"><?= $vars['allianceTag'] ?? ''; ?></a></td>
            </tr>
            <tr>
                <th><?= T("map", "owner"); ?></th>
                <td><a href="<?= profileUrl((int) $vars['uid']); ?>"><?= $vars['playerName']; ?></a></td>
            </tr>
            <tr>
                <th><?= T("map", "Village"); ?></th>
                <td><a href="/karte.php?x=<?= $vars['village']['x']; ?>&y=<?= $vars['village']['y']; ?>"><?= $vars['village']['name']; ?></a></td>
            </tr>
        </table>
        <h4><?= T("map", "Bonus"); ?></h4>
        <table cellpadding="1" cellspacing="1" id="distribution" class="transparent">
            <tbody>
            <?= $vars['distribution']; ?>
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
        <?= \resources\View\PHPBatchView::render('map/tileTabs', ['id' => 'oasis2InstantTabs', 'reports' => $vars['top5Attacks'], 'surrounding' => $vars['top5Surrounding']]); ?>
    </div>
    <div class="clear"></div>
</div>
