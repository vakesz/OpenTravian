<?php

// Lists the playable tribes first in id order (Romans, Teutons, Gauls,
// Egyptians, Huns, Spartans), then Nature and Natars. The playable-tribe world
// setting decides which of the selectable tribes appear.
$troopTribes = array_merge(
    array_values(array_filter([1, 2, 3, 6, 7, 8, 9], static fn (int $tribe): bool => \Game\Tribes::isPlayable($tribe))),
    [4, 5]
);
$buildings = [
    'Infrastructure' => [10, 11, 15, 17, 18, 23, 24, 25, 26, 27, 28, 34, 35, 38, 39, 40, 41, 44, 45],
    'Military' => array_merge(
        [13, 14, 16, 19, 20, 21, 22, 29, 30, 31, 32, 33, 36, 37, 42, 43, 46, 47, 48],
        \Game\Tribes::isPlayable(9) ? [50] : []
    ),
    'resources' => [1, 2, 3, 4, 5, 6, 7, 8, 9],
];
?>
<div id="troops" class="instantTabs">
    <h3><img src="/img/spacer.gif" class="unit uunits"> <?= T('RallyPoint', 'troops'); ?></h3>
    <div class="contentNavi tabNavi ">
        <?php foreach ($troopTribes as $index => $tribe): ?>
            <div title="" class="container<?= $index === 0 ? ' active' : ' normal'; ?>">
                <div class="background-start">&nbsp;</div>
                <div class="background-end">&nbsp;</div>
                <div class="content">
                    <a class="tabItem" id="manualTroopTab<?= $tribe; ?>">
                        <?= T('Global', 'races.' . $tribe); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="clear"></div>
    </div>
    <?php foreach ($troopTribes as $index => $tribe): ?>
        <div class="tabContainer<?= $index === 0 ? '' : ' hide'; ?>">
            <ul class="troops<?= $tribe; ?>">
                <?php $firstUnit = ($tribe - 1) * 10 + 1; ?>
                <?php for ($unitId = $firstUnit; $unitId < $firstUnit + 10; ++$unitId): ?>
                    <li>
                        <img src="/img/spacer.gif" class="unit u<?= $unitId; ?>">
                        <a onclick="Travian.Game.Manual.open('troop', <?= $unitId; ?>);">
                            <?= T('Troops', $unitId . '.title'); ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>

<div id="buildings" class="instantTabs">
    <h3><i class="building_small"></i> <?= T('inGame', 'Navigation.Buildings'); ?></h3>
    <div class="contentNavi tabNavi ">
        <?php foreach ($buildings as $index => $buildingIds): ?>
            <?php $categoryIndex = array_search($index, array_keys($buildings), true) + 1; ?>
            <div title="" class="container<?= $categoryIndex === 1 ? ' active' : ' normal'; ?>">
                <div class="background-start">&nbsp;</div>
                <div class="background-end">&nbsp;</div>
                <div class="content">
                    <a class="tabItem" id="manualBuildingTab<?= $categoryIndex; ?>">
                        <?= T('Buildings', $index); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="clear"></div>
    </div>
    <?php foreach ($buildings as $index => $buildingIds): ?>
        <?php $categoryIndex = array_search($index, array_keys($buildings), true) + 1; ?>
        <div class="tabContainer<?= $categoryIndex === 1 ? '' : ' hide'; ?>">
            <ul class="buildings<?= $categoryIndex; ?>">
                <?php foreach ($buildingIds as $buildingId): ?>
                    <li>
                        <i class="building_small tribe3 type<?= $buildingId; ?>"></i>
                        <a onclick="Travian.Game.Manual.open('building', <?= $buildingId; ?>)">
                            <?= T('Buildings', $buildingId . '.title'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</div>
