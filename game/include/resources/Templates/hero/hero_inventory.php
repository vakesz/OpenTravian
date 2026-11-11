<?php

?>
<?php if (($vars['heroView'] ?? 'inventory') === 'attributes'): ?>
<?php
    $heroProductionType = max(0, min(4, (int)$vars['hero']['productionType']));
    $heroProductionIcons = [0 => 'resources_small', 1 => 'lumber_small', 2 => 'clay_small', 3 => 'iron_small', 4 => 'crop_small'];
    $heroProductionAmount = $heroProductionType === 0
        ? (float)$vars['hero']['allResourcesProduct']
        : (float)$vars['hero']['eachResourceProduct'];
    // The flat +6 crop is part of every hero production; a crop focus adds its own share on top.
    $heroCropProduction = 6 + ($heroProductionType === 4 ? $heroProductionAmount : 0);
    $heroHealth = (bool)($vars['hero']['regenerating'] ?? false)
        ? (float)($vars['hero']['regeneratedHealth'] ?? 0)
        : (float)($vars['hero']['health'] ?? 0);
    $heroHealth = max(0, min(100, $heroHealth));
    $heroHealthPercent = (int)floor($heroHealth);
    $heroExperience = max(0, (int)($vars['hero']['exp'] ?? 0));
    $heroExperiencePercent = max(0, min(100, (float)($vars['hero']['expPercent'] ?? 0)));
    $heroPoints = max(0, (int)($vars['hero']['points'] ?? 0));
    $heroAttributeDefinitions = [
        [
            'requestKey' => 'power',
            'label' => T('HeroInventory', 'fightingStrength'),
            'icon' => 'attributeStrength_medium',
            'value' => max(0, min(100, (int)$vars['hero']['power'])),
            'displayValue' => number_format((int)$vars['hero']['totalPower']),
            'displayBase' => 100 + (int)$vars['hero']['itemfs'],
            'displayMultiplier' => (int)$vars['hero']['fsperpoint'],
            'displaySuffix' => '',
            'displayDecimals' => 0,
        ],
        [
            'requestKey' => 'offBonus',
            'label' => T('HeroInventory', 'offBonus'),
            'icon' => 'attributeOffBonus_medium',
            'value' => max(0, min(100, (int)$vars['hero']['offBonus'])),
            'displayValue' => "\u{202D}\u{202D}" . number_format(round((float)$vars['hero']['offBonus'] * 0.2, 1), 1) . "\u{202C}%\u{202C}",
            'displayBase' => 0,
            'displayMultiplier' => 0.2,
            'displaySuffix' => '%',
            'displayDecimals' => 1,
        ],
        [
            'requestKey' => 'defBonus',
            'label' => T('HeroInventory', 'defBonus'),
            'icon' => 'attributeDefBonus_medium',
            'value' => max(0, min(100, (int)$vars['hero']['defBonus'])),
            'displayValue' => "\u{202D}\u{202D}" . number_format(round((float)$vars['hero']['defBonus'] * 0.2, 1), 1) . "\u{202C}%\u{202C}",
            'displayBase' => 0,
            'displayMultiplier' => 0.2,
            'displaySuffix' => '%',
            'displayDecimals' => 1,
        ],
        [
            'requestKey' => 'productionPoints',
            'label' => T('HeroInventory', 'productBonus'),
            'icon' => 'attributeRessourceBonus_medium',
            'value' => max(0, min(100, (int)$vars['hero']['production'])),
            'displayValue' => number_format((int)$vars['hero']['production']),
            'displayBase' => 0,
            'displayMultiplier' => 1,
            'displaySuffix' => '',
            'displayDecimals' => 0,
        ],
    ];
    $heroEquipment = json_decode((string)($vars['HeroEquipments'] ?? '[]'), true);
    if (!is_array($heroEquipment)) {
        $heroEquipment = [];
    }
    $heroEquipmentBenefits = [];
    foreach ($heroEquipment as $equippedItem) {
        if (!is_array($equippedItem) || !is_array($equippedItem['benefits'] ?? null)) {
            continue;
        }
        foreach ($equippedItem['benefits'] as $benefit) {
            $heroEquipmentBenefits[] = (string)$benefit;
        }
    }
    ?>
<?php require_once dirname(__DIR__) . '/dailyQuest/icons.php'; ?>
<?php
    $heroDead = (string)$vars['hero']['status'] === '101';
    $heroReviving = !empty($vars['hero']['regenerating']);
    $reviveEndTime = max(0, (int)($vars['hero']['reviveEndTime'] ?? 0));
    $reviveSeconds = max(0, $reviveEndTime - time());
    $reviveTimer = sprintf(
        '%02d:%02d:%02d',
        (int)floor($reviveSeconds / 3600),
        (int)floor(($reviveSeconds % 3600) / 60),
        $reviveSeconds % 60
    );
    $reviveDetails = sprintf(
        T('HeroGlobal', 'inventoryStatus.reviving'),
        (int)$vars['hero']['kid'],
        htmlspecialchars((string)$vars['hero']['vname'], ENT_QUOTES, 'UTF-8')
    );
?>
<?php if ($heroReviving): ?>
<div class="attributeBox heroRevive" data-revive-end-time="<?=$reviveEndTime;?>">
    <div class="status">
        <i class="statusRevive_medium"></i>
        <div class="title"><?=htmlspecialchars(T('HeroGlobal', 'shortStatus.reviving'), ENT_QUOTES, 'UTF-8');?></div>
        <div class="details"><?=$reviveDetails;?> <span class="timerReact"><?=$reviveTimer;?></span>.</div>
    </div>
    <div class="stats">
        <div class="name"><i class="attributeHealth_medium"></i><span><?=T('HeroGlobal', 'health');?></span></div>
        <div class="progressBar preventMobileSwipeNavigation "><div class="bar"><?=daily_quest_svg('decoration_start');?><div class="filling primary orange" style="width: <?=$heroHealthPercent;?>%;"></div><?=daily_quest_svg('decoration_end');?></div></div>
        <div class="value">&#x202d;&#x202d;<?=$heroHealthPercent;?>&#x202c;%&#x202c;</div>
        <div class="name"><i class="attributeExperience_medium"></i><span><?=T('HeroGlobal', 'Experience');?></span></div>
        <div class="progressBar preventMobileSwipeNavigation "><div class="bar"><?=daily_quest_svg('decoration_start');?><div class="filling primary blue" style="width: <?=floor($heroExperiencePercent);?>%;"></div><?=daily_quest_svg('decoration_end');?></div></div>
        <div class="value"><?=number_format_x($heroExperience);?></div>
        <div class="name"><i class="attributeSpeed_medium"></i><span><?=T('HeroInventory', 'speed');?></span></div>
        <div class="value speedValue"><svg viewBox="0 0 10 7.75" class="arrowDouble"><path d="M0 0h3l3 3.88-3 3.87H0l3-3.87"></path><path d="M4 0h3l3 3.88-3 3.87H4l3-3.87"></path></svg><span><strong><?=number_format($vars['hero']['speed']);?></strong> <?=T('HeroInventory', 'fieldPerHour');?></span></div>
    </div>
</div>
<?php elseif ($heroDead): ?>
<div class="attributeBox heroDead">
    <div class="status">
        <i class="statusDead_medium"></i>
        <div class="title"><?=htmlspecialchars(T('HeroGlobal', 'shortStatus.dead'), ENT_QUOTES, 'UTF-8');?></div>
    </div>
    <div class="reviveWrapper">
        <div class="description"><?=$vars['heroRevive']['description'];?></div>
        <div class="reviveWithResources charges">
            <div class="resourceWrapper">
                <div class="inlineIcon resource"><i class="r1Big"></i><span class="value"><?=number_format_x($vars['heroRevive']['costs'][0]);?></span></div>
                <div class="inlineIcon resource"><i class="r2Big"></i><span class="value"><?=number_format_x($vars['heroRevive']['costs'][1]);?></span></div>
                <div class="inlineIcon resource"><i class="r3Big"></i><span class="value"><?=number_format_x($vars['heroRevive']['costs'][2]);?></span></div>
                <div class="inlineIcon resource"><i class="r4Big"></i><span class="value"><?=number_format_x($vars['heroRevive']['costs'][3]);?></span></div>
                <div class="inlineIcon resource"><i class="cropConsumptionBig"></i><span class="value">6</span></div>
            </div>
            <div><div class="inlineIcon duration"><i class="clock_medium"></i><span class="value"><?=secondsToString($vars['heroRevive']['neededTime'], true);?></span></div></div>
            <form method="post" action="/hero/inventory">
                <?= \Core\Session::getCheckerInput(); ?>
                <input type="hidden" name="a" value="1">
                <?=$vars['heroRevive']['npc'];?>
                <?php if ($vars['heroRevive']['showButton']): ?>
                <button type="submit" value="ok" name="heroRegeneration" id="heroRegeneration" class="textButtonV2 buttonFramed rectangle withText green"><div><?=htmlspecialchars(T('HeroInventory', 'Regenerate'), ENT_QUOTES, 'UTF-8');?></div></button>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
<?php else: ?>
<div class="attributeBox">
    <div class="stats">
        <div class="name" title="<?=htmlspecialchars((string)($vars['heroTips']['health'] ?? ''), ENT_QUOTES, 'UTF-8');?>"><i class="attributeHealth_medium"></i><span><?=T('HeroGlobal', 'health');?></span></div>
        <div class="progressBar preventMobileSwipeNavigation "><div class="bar" title="<?=htmlspecialchars((string)($vars['heroTips']['health'] ?? ''), ENT_QUOTES, 'UTF-8');?>"><?=daily_quest_svg('decoration_start');?><div class="filling primary green" style="width: <?=$heroHealthPercent;?>%;"></div><?=daily_quest_svg('decoration_end');?></div></div>
        <div class="value" title="<?=htmlspecialchars((string)($vars['heroTips']['health'] ?? ''), ENT_QUOTES, 'UTF-8');?>">&#x202d;&#x202d;<?=$heroHealthPercent;?>&#x202c;%&#x202c;</div>
        <div class="name" title="<?=htmlspecialchars((string)($vars['heroTips']['experience'] ?? ''), ENT_QUOTES, 'UTF-8');?>"><i class="attributeExperience_medium"></i><span><?=T('HeroGlobal', 'Experience');?></span></div>
        <div class="progressBar preventMobileSwipeNavigation "><div class="bar" title="<?=htmlspecialchars((string)($vars['heroTips']['experience'] ?? ''), ENT_QUOTES, 'UTF-8');?>"><?=daily_quest_svg('decoration_start');?><div class="filling primary blue" style="width: <?=floor($heroExperiencePercent);?>%;"></div><?=daily_quest_svg('decoration_end');?></div></div>
        <div class="value" title="<?=htmlspecialchars((string)($vars['heroTips']['experience'] ?? ''), ENT_QUOTES, 'UTF-8');?>"><?=number_format_x($heroExperience);?></div>
        <div class="name" title="<?=htmlspecialchars((string)($vars['heroTips']['speed'] ?? ''), ENT_QUOTES, 'UTF-8');?>"><i class="attributeSpeed_medium"></i><span><?=T('HeroInventory', 'speed');?></span></div>
        <div class="value speedValue" title="<?=htmlspecialchars((string)($vars['heroTips']['speed'] ?? ''), ENT_QUOTES, 'UTF-8');?>"><svg viewBox="0 0 10 7.75" class="arrowDouble"><path d="M0 0h3l3 3.88-3 3.87H0l3-3.87"></path><path d="M4 0h3l3 3.88-3 3.87H4l3-3.87"></path></svg><span><strong><?=number_format($vars['hero']['speed']);?></strong> <?=T('HeroInventory', 'fieldPerHour');?></span></div>
    </div>
</div>
<?php endif; ?>
<div class="attributeBox">
    <div class="heroProduction">
        <div class="productionTotal" title="<?=htmlspecialchars((string)($vars['heroTips']['production'] ?? ''), ENT_QUOTES, 'UTF-8');?>">
            <strong><?=htmlspecialchars(T('HeroGlobal', 'HeroProduction'), ENT_QUOTES, 'UTF-8');?></strong>
            <div class="productionItem"><span class="value">+ <?=number_format_x($heroCropProduction);?></span><i class="crop_tiny"></i></div>
            <?php if ($heroProductionType !== 4): ?>
            <div class="productionItem"><span class="value">+ <?=number_format_x($heroProductionAmount);?></span><i class="<?=$heroProductionIcons[$heroProductionType];?>"></i></div>
            <?php endif; ?>
        </div>
        <div class="changeProduction">
            <?php foreach ($heroProductionIcons as $resource => $icon): ?>
            <?php $resourceIcon = $resource === 0 ? 'resources_medium' : $icon; ?>
            <div class="resource <?=$resourceIcon;?>">
                <button class="textButtonV2 buttonFramed pickProductionType rectangle withIcon toggle activeNotClickable <?=$heroProductionType === $resource ? 'active orange' : 'grey';?>" type="button" data-resource="<?=$resource;?>"><i class="<?=$resourceIcon;?>"></i></button>
                <span class="value"><?=number_format_x($resource === 0 ? (float)$vars['hero']['allResourcesProduct'] : (float)$vars['hero']['eachResourceProduct']);?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="attributeBox">
    <div class="heroAttributes">
        <div class="attributes formV2" data-available-points="<?=$heroPoints;?>" data-resource="<?=$heroProductionType;?>" data-attack-behaviour="<?=$vars['hero']['hide'] ? 'hide' : 'fight';?>">
            <div class="title"><?=T('Hero', 'Attributes');?></div>
            <div class="points_descr"><?=T('HeroInventory', 'availablePoints');?></div>
            <div class="pointsAvailable"><?=$heroPoints;?></div>
            <?php foreach ($heroAttributeDefinitions as $attribute): ?>
            <div class="name" title="<?=htmlspecialchars((string)($vars['heroTips'][$attribute['requestKey']] ?? ''), ENT_QUOTES, 'UTF-8');?>"><i class="<?=$attribute['icon'];?>"></i><span><?=htmlspecialchars((string)$attribute['label'], ENT_QUOTES, 'UTF-8');?></span></div>
            <div class="progressBar preventMobileSwipeNavigation " data-initial="<?=$attribute['value'];?>" data-base="<?=$attribute['displayBase'];?>" data-multiplier="<?=$attribute['displayMultiplier'];?>" data-suffix="<?=htmlspecialchars($attribute['displaySuffix'], ENT_QUOTES, 'UTF-8');?>" data-decimals="<?=$attribute['displayDecimals'];?>">
                <div class="bar" title="<?=htmlspecialchars((string)($vars['heroTips'][$attribute['requestKey']] ?? ''), ENT_QUOTES, 'UTF-8');?>"><?=daily_quest_svg('decoration_start');?><div class="filling primary blue" style="width: <?=$attribute['value'];?>%;"></div><div class="filling secondary blue" style="width: 0%; transition-duration: 250ms;"></div><?=daily_quest_svg('decoration_end');?></div>
                <?php if ($heroPoints > 0 && $attribute['value'] < 100): ?>
                <button class="textButtonV2 buttonFramed plus rectangle withIcon green" type="button"><svg><svg viewBox="0 0 18 18" class="outline"><path d="M16 10h-6v6H8v-6H2V8h6V2h2v6h6z"></path></svg><svg viewBox="0 0 18 18" class="icon"><path d="M16 10h-6v6H8v-6H2V8h6V2h2v6h6z"></path></svg></svg></button>
                <button class="textButtonV2 buttonFramed minus rectangle withIcon green" type="button" disabled=""><svg><svg viewBox="0 0 18 6" class="outline"><path d="M16 4H2V2h14z"></path></svg><svg viewBox="0 0 18 6" class="icon"><path d="M16 4H2V2h14z"></path></svg></svg></button>
                <div class="slider"><div class="handle blue" style="margin-inline-start: <?=$attribute['value'];?>%; transition-duration: 250ms;"></div></div>
                <?php endif; ?>
            </div>
            <div class="inputRatio pointsRatio isLtr">
                <span class="nominator"><label class="points"><input inputmode="numeric"<?=$heroPoints > 0 && $attribute['value'] < 100 ? '' : ' disabled=""';?> autocomplete="off" type="text" value="<?=$attribute['value'];?>" name="<?=$attribute['requestKey'];?>"></label></span>
                <span class="separator">/</span>
                <span class="denominator"><div class="value" title="<?=htmlspecialchars((string)($vars['heroTips'][$attribute['requestKey']] ?? ''), ENT_QUOTES, 'UTF-8');?>"><?=$attribute['displayValue'];?></div></span>
            </div>
            <?php endforeach; ?>
            <button class="textButtonV2 buttonFramed rectangle withText green" type="button" disabled="" id="savePoints"><div><?=htmlspecialchars(T('HeroInventory', 'save'), ENT_QUOTES, 'UTF-8');?></div></button>
        </div>
    </div>
</div>
<?php if ($heroEquipmentBenefits): ?>
<div class="attributeBox">
    <div class="heroEquipmentBenefits">
        <strong><?=htmlspecialchars(T('HeroGlobal', 'equipmentBenefits'), ENT_QUOTES, 'UTF-8');?></strong>
        <ul>
            <?php foreach ($heroEquipmentBenefits as $benefit): ?>
            <li><span class="listIcon"></span><span class="benefit"><?=htmlspecialchars($benefit, ENT_QUOTES, 'UTF-8');?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
<div class="attributeBox">
    <div class="heroState">
        <i class="<?=htmlspecialchars((string)$vars['hero']['heroStatusIcon'], ENT_QUOTES, 'UTF-8');?>"></i>
        <div><?= $vars['hero']['heroStatusMessage'] !== ''
            ? $vars['hero']['heroStatusMessage']
            : sprintf(T("HeroGlobal", "inventoryStatus.Home"), (int)$vars['hero']['kid'], htmlspecialchars((string)$vars['hero']['vname'], ENT_QUOTES, 'UTF-8')); ?></div>
    </div>
    <div class="formV2 heroHideSwitch">
        <label class="switch">
            <input type="checkbox" value="hide" name="attackBehaviour"<?= $vars['hero']['hide'] ? ' checked=""' : ''; ?>>
            <div class="switch"></div>
            <div class="label"><?=htmlspecialchars(T("HeroInventory", "HeroHideDesc"), ENT_QUOTES, 'UTF-8');?></div>
        </label>
    </div>
</div>
<script type="text/javascript" src="/hero-attributes.js?v=<?=filemtime(PUBLIC_INTERNAL_PATH . 'hero-attributes.js');?>"></script>
<?php else: ?>
<div class="inventoryPageWrapper">
    <div class="currentHeroEquipment <?= $vars['hero']['health'] == 0 ? 'isDead' : ''; ?>">
        <div class="equipmentSlots">
            <?php foreach ([
                'helmet' => ['FilterHelmets', 1, -1],
                'body' => ['FilterBodyArmor', 2, -4],
                'shoes' => ['FilterShoes', 3, -5],
                'leftHand' => ['FilterLeftHandItems', 4, -2],
                'rightHand' => ['FilterRightHandItems', 5, -3],
                'horse' => ['FilterHorses', 6, -7],
                'bag' => ['FilterConsumables', 7, -6],
            ] as $slot => [$labelKey, $slotIndex, $placeId]): ?>
                <?php
                $emptySlotTitles = [
                    'helmet' => 'Empty head gear slot', 'body' => 'Empty armor slot', 'shoes' => 'Empty boots slot',
                    'leftHand' => 'Empty utility slot', 'rightHand' => 'Empty weapon slot', 'bag' => 'Empty consumable slot',
                ];
                ?>
                <div class="heroItem empty neutral <?=$slot; ?> heroItemV1"
                     data-placeid="<?=$placeId; ?>"
                     data-slot="<?=$slot; ?>"
                     data-refkey="equipped_<?=$slotIndex; ?>"<?= isset($emptySlotTitles[$slot]) ? ' data-empty-title="' . htmlspecialchars((string)T('HeroInventory', $emptySlotTitles[$slot]), ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 62 62" class="arrows"><path class="top" d="M31 4l2-4h-4l2 4z"></path><path class="bottom" d="M29 62h4l-2-4-2 4z"></path><path class="from" d="M0 33l4-2-4-2v4z"></path><path class="to" d="M58 31l4 2v-4l-4 2z"></path></svg>
                    <div class="item"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        $horseType = (int)($vars['hero']['horseType'] ?? 0);
        $horseClasses = in_array($horseType, [103, 104, 105], true)
            ? ' horse horse' . $horseType
            : '';
        ?>
        <div class="hero <?=htmlspecialchars((string)$vars['hero']['gender'], ENT_QUOTES, 'UTF-8'); ?><?=$horseClasses; ?>">
            <img src="<?= \Game\Hero\HeroImage::bodyUrl((int)$vars['hero']['uid']); ?>"
                 class="heroBodyImage heroBodyImage-<?= getDirection(); ?>"
                 alt="<?= T("HeroInventory", "Body"); ?>"/>
        </div>
        <button class="textButtonV2 buttonFramed download rectangle withIcon green" type="button" title="<?=htmlspecialchars((string)T('HeroInventory', 'Download hero image'), ENT_QUOTES, 'UTF-8'); ?>">
            <svg>
                <svg viewBox="0 0 16 16" class="outline"><path d="M14 11v3H2v-3H0v3c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2v-3h-2zm-1-4-1.4-1.4L9 8.2V0H7v8.2L4.4 5.6 3 7l5 5 5-5z"></path></svg>
                <svg viewBox="0 0 16 16" class="icon"><path d="M14 11v3H2v-3H0v3c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2v-3h-2zm-1-4-1.4-1.4L9 8.2V0H7v8.2L4.4 5.6 3 7l5 5 5-5z"></path></svg>
            </svg>
        </button>
    </div>
    <div class="inventoryWrapper">
    <?php
    $resourceClaimMessages = [
        'collected' => (string)T('HeroInventory', 'carriedResourcesCollected', 'The resources were added to this village.'),
        'empty' => (string)T('HeroInventory', 'carriedResourcesEmpty', 'There are no hero resources to collect.'),
        'unavailable' => (string)T('HeroInventory', 'carriedResourcesUnavailable', 'The hero resources could not be collected.'),
    ];
    $resourceClaimStatus = (string)($vars['resourceClaimStatus'] ?? '');
    ?>
        <div class="inventoryFilters">
            <div class="heroItemSlotFilter filterSlotsRow">
                        <?php
                        $filterTitles = [
                            'all' => 'Show everything', 'leftHand' => 'Show utilities', 'rightHand' => 'Show weapons',
                            'helmet' => 'Show head gear', 'body' => 'Show armor', 'shoes' => 'Show boots',
                            'bag' => 'Show consumables', 'horse' => 'Show horses',
                        ];
                        ?>
                        <?php foreach ([
                            'all' => 'FilterAllItems',
                            'leftHand' => 'FilterLeftHandItems',
                            'rightHand' => 'FilterRightHandItems',
                            'helmet' => 'FilterHelmets',
                            'body' => 'FilterBodyArmor',
                            'shoes' => 'FilterShoes',
                            'bag' => 'FilterConsumables',
                            'horse' => 'FilterHorses',
                        ] as $filter => $labelKey): ?>
                            <div class="filterSlot <?=$filter; ?><?=$filter === 'all' ? ' active' : ' '; ?>" data-key="<?=$filter; ?>" title="<?=htmlspecialchars((string)T('Auction', $filterTitles[$filter]), ENT_QUOTES, 'UTF-8'); ?>"><i></i></div>
                        <?php endforeach; ?>
            </div>
        </div>
        <a class="tradeItems lightColor" href="/auctions">
            <svg viewBox="0 0 20 20">
                <defs>
                    <linearGradient id="arrowColored_goldGradient" x1="26.81" y1="42.42" x2="26.81" y2="41.31" gradientTransform="matrix(0 16.23 13.53 0 -556.56 -425.35)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#cba467"></stop><stop offset="0.05" stop-color="#cba467"></stop><stop offset="0.13" stop-color="#f3e2ae"></stop><stop offset="0.32" stop-color="#efbf7b"></stop><stop offset="0.48" stop-color="#aa8050"></stop><stop offset="0.72" stop-color="#835e35"></stop><stop offset="0.93" stop-color="#ad8a54"></stop><stop offset="1" stop-color="#d7b672"></stop></linearGradient>
                    <linearGradient id="arrowColored_greenGradient" x1="50.52" y1="58.77" x2="51.23" y2="59.18" gradientTransform="matrix(0 8.3 7.47 0 -433.41 -413.45)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#bdff83"></stop><stop offset="0.53" stop-color="#60a124"></stop><stop offset="0.84" stop-color="#5c9722"></stop><stop offset="1" stop-color="#496818"></stop></linearGradient>
                </defs>
                <path class="goldBorder" d="M3 18V1.78L16.57 9.9Z" fill="url(#arrowColored_goldGradient)" stroke="#000000"></path>
                <path class="greenInner" d="M5 14.05v-8.3l7.51 4.15Z" fill="url(#arrowColored_greenGradient)" stroke="#000000"></path>
            </svg>
            <?=htmlspecialchars((string)T('HeroInventory', 'TradeItems'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php if (isset($resourceClaimMessages[$resourceClaimStatus])): ?>
                    <p class="heroResourceClaimMessage" role="status"><?=htmlspecialchars($resourceClaimMessages[$resourceClaimStatus], ENT_QUOTES, 'UTF-8');?></p>
        <?php endif; ?>
        <div class="heroItems filter_all">
        <?php for ($i = 1; $i <= $vars['inventorySize']; ++$i): ?>
            <div class="heroItem empty neutral heroItemV1"
                 data-placeid="<?= $i; ?>"
                 data-refkey="inventory_<?= $i; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 62 62" class="arrows"><path class="top" d="M31 4l2-4h-4l2 4z"></path><path class="bottom" d="M29 62h4l-2-4-2 4z"></path><path class="from" d="M0 33l4-2-4-2v4z"></path><path class="to" d="M58 31l4 2v-4l-4 2z"></path></svg>
                <div class="item"></div>
            </div>
        <?php endfor; ?>
        </div>
    </div>
</div>
<script type="text/javascript">
    jQuery(function () {
        Travian.Game.Hero.Inventory = new Travian.Game.Hero.Inventory(
            {
                villageId: <?=(int)\Core\Session::getInstance()->getKid();?>,
                isInVillage: <?=$vars['hero']['status'] == 100 ? 'true' : 'false';?>,
                isDead: <?=$vars['hero']['health'] == 0 ? 'true' : 'false';?>,
                data: <?=$vars['HeroItems'];?>,
                inventorySize: <?=(int)$vars['inventorySize'];?>,
                text: {
                    culturePointsGainFromItems: <?=json_encode(T("HeroInventory", "culturePointsGainFromItems"), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);?>,
                    buttonCancel: <?=json_encode(T("HeroInventory", "buttonCancel"), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);?>
                }
            });

        var inventoryItems = document.querySelector('.inventoryWrapper .heroItems');
        if (!inventoryItems) {
            return;
        }

        var downloadPortrait = document.querySelector('.currentHeroEquipment button.download');
        if (downloadPortrait) {
            downloadPortrait.addEventListener('click', function () {
                var heroBodyImage = document.querySelector('.currentHeroEquipment .heroBodyImage');
                if (!heroBodyImage || !heroBodyImage.currentSrc) {
                    return;
                }
                var download = document.createElement('a');
                download.href = heroBodyImage.currentSrc;
                download.download = 'hero-portrait.png';
                download.click();
            });
        }

        var filterButtons = document.querySelectorAll('.inventoryWrapper .heroItemSlotFilter .filterSlot');
        filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var filter = button.dataset.key;
                if (!/^(all|bag|body|helmet|horse|leftHand|rightHand|shoes)$/.test(filter)) {
                    return;
                }
                inventoryItems.classList.remove('filter_bag', 'filter_body', 'filter_helmet', 'filter_horse', 'filter_leftHand', 'filter_rightHand', 'filter_shoes');
                if (filter !== 'all') {
                    inventoryItems.classList.add('filter_' + filter);
                }
                filterButtons.forEach(function (filterButton) {
                    var active = filterButton === button;
                    filterButton.classList.toggle('active', active);
                });
            });
        });

        /*
         * The old inventory widget only completed an item move after a
         * double-click. The V2 hero screen treats a normal activation as the
         * intended action: an equipped item returns to the first free slot,
         * and an inventory item equips itself in its matching slot. Intercept
         * the click in capture phase so the legacy double-click timer never
         * gets involved; dragging remains owned by the widget below.
         */
        var inventoryPage = document.querySelector('.inventoryPageWrapper');
        if (inventoryPage) {
            inventoryPage.addEventListener('click', function (event) {
                if (event.button !== 0) {
                    return;
                }

                var itemElement = event.target.closest('.item');
                var heroInventory = Travian.Game.Hero.Inventory;
                if (!itemElement || !inventoryPage.contains(itemElement)
                    || !itemElement.item || !itemElement.item.isUseable
                    || heroInventory.dragStatus || heroInventory.startDrop) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                heroInventory.moveToMatchingPlace(itemElement.item);
            }, true);
        }
    });
</script>
<?php endif; ?>
