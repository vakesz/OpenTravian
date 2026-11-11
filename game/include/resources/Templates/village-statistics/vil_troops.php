<?php $hasUnits = !empty($vars['hasUnits']); ?>
<div class="troops_wrapper">
    <table cellpadding="1" cellspacing="1" class="vil_troops">
        <thead>
        <tr>
            <?php if ($hasUnits): ?>
            <th><?= T("villageOverview", "Village"); ?></th>
            <?php endif; ?>
            <th colspan="11"><a
                        href="/build.php?gid=16&amp;newdid=<?= $vars['kid']; ?>"><?= $vars['name']; ?></a>
            </th>
        </tr>
        </thead>
        <tbody class="troops">
        <tr>
            <?php if ($hasUnits): ?>
            <td></td>
            <?php endif; ?>
            <?php
            for ($i = 1; $i <= 11; ++$i) {
                if ($i == 11) {
                    $unitId = 'hero';
                } else {
                    $unitId = nrToUnitId($i, $vars['race']);
                }
                $title = T("Troops", "$unitId.title");
                echo '<td><img class="unit u' . $unitId . '" src="img/spacer.gif" title="' . $title . '" alt="' . $title . '" /></td>';
            }
            ?>
        </tr>
        <tr>
            <?php if ($hasUnits): ?>
            <th><?= T("villageOverview", "Troops"); ?></th>
            <?php endif; ?>
            <?php
            for ($i = 1; $i <= 11; ++$i) {
                if ($vars['units'][$i]) {
                    echo '<td>' . number_format_x($vars['units'][$i]) . '</td>';
                } else {
                    echo '<td class="none">0</td>';
                }
            }
            ?>
        </tr>
        <tr>
            <td colspan="<?= $hasUnits ? 12 : 11; ?>" class="empty"></td>
        </tr>
        </tbody>
        <tbody class="upkeep">
        <tr>
            <?php if ($hasUnits): ?>
            <th><?= T("villageOverview", "Upkeep"); ?></th>
            <?php endif; ?>
            <td colspan="11">
                <?= $vars['upkeep']; ?>&nbsp;<i class="r4"></i>&nbsp;<?= T("villageOverview", "per hour"); ?>
            </td>
        </tr>
        </tbody>
    </table>
</div>
