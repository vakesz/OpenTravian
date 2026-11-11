<?php
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div id="villagesNearby">
    <table>
        <thead>
        <tr>
            <th><i class="village_medium" title="<?= $escape(T('Treasury', 'Village')); ?>"></i></th>
            <th><i class="player_medium" title="<?= $escape(T('Treasury', 'Owner')); ?>"></i></th>
            <th><i class="alliance_medium" title="<?= $escape(T('Embassy', 'Alliance')); ?>"></i></th>
            <th><i class="population_medium" title="<?= $escape(T('Embassy', 'Population')); ?>"></i></th>
            <th><i class="distance_medium" title="<?= $escape(T('Treasury', 'Distance')); ?>"></i></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($vars['rows'])): ?>
            <tr><td class="empty" colspan="5"><p><?= T('Embassy', 'There are no villages nearby', 'There are no villages nearby.'); ?></p></td></tr>
        <?php else: ?>
            <?php foreach ($vars['rows'] as $row): ?>
                <tr>
                    <td class="village"><a href="/karte.php?d=<?= (int)$row['kid']; ?>"><?= $escape($row['village']); ?></a></td>
                    <td class="player"><a href="<?= profileUrl((int) (int)$row['uid']); ?>"><?= $escape($row['owner']); ?></a></td>
                    <td class="alliance"><?php if ((int)$row['aid'] > 0): ?><a href="<?= allianceUrl((int) (int)$row["aid"]); ?>"><?= $escape($row['tag']); ?></a><?php endif; ?></td>
                    <td class="population"><?= (int)$row['pop']; ?></td>
                    <td class="distance"><?= $escape(round((float)$row['distance'], 1)); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
