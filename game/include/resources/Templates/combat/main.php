<?php
use Controller\RallyPoint\Combat;
use Game\Formulas;

$attackerTribes = Combat::attackerTribes();
$defenderTribes = Combat::defenderTribes();
$reinforcementTribes = Combat::reinforcementTribes();
$mainDefender = (int)$vars['defender']['main'];
$playsDefender = $vars['role'] === 'defender';
$missingAttackerData = T('combat', 'needAttackerData', 'Please provide more data for the attacker first.');
$esc = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

$roleIcon = static function (string $kind): string {
    $file = $kind === 'attack' ? 'svg-attack.svg' : 'svg-defend.svg';
    $class = $kind === 'attack' ? 'svgAttack roleIcon' : 'svgDefend roleIcon';
    $source = file_get_contents(PUBLIC_INTERNAL_PATH . 'img/svg/combat/' . $file);

    return is_string($source)
        ? (preg_replace('/class="[^"]+"/', 'class="' . $class . '"', $source, 1) ?? '')
        : '';
};
$clearButton = '<a class="iconButtonV2 clear"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 81.41"><g fill="#fff"><path d="M42.77 81.28h54.85v-5.21H55.35L100 31.41 68.64.05 61.22 0l31.41 31.41-49.86 49.87z"/><path d="M5.89 54.15l25.79 27.26h8.55l49.95-49.9L59.36.69zm5.47.39l29.86-29.85 24.55 24.56L35.92 79.1zM0 81.28h17.37l-5.12-5.21H0"/></g></svg></a>';
$clearButton = str_replace('<a class="iconButtonV2 clear">', '<a class="iconButtonV2 clear" title="' . $esc(T('combat', 'clear')) . '">', $clearButton);
$tooltip = static fn (string $key, string $fallback): string => ' title="' . $esc(T('combat', $key, $fallback)) . '"';
$villageSelection = static function () use ($vars, $esc): string {
    $html = '<div class="villageSelection"><select name="village"><option>' . T('combat', 'noVillageSelected') . '</option>';
    foreach ($vars['villages'] as $village) {
        $html .= '<option value="' . $village['kid'] . '"' . ((int)$vars['village'] === $village['kid'] ? ' selected="selected"' : '') . '>' . $esc($village['name']) . '</option>';
    }

    return $html . '</select><a class="iconButtonV2 reload" title="' . $esc(T('combat', 'reload')) . '"><svg viewBox="0 0 14 14"><path d="M7 14c-1.954 0-3.61-.678-4.966-2.034C.678 10.609 0 8.954 0 7c0-1.954.678-3.61 2.034-4.966C3.391.678 5.046 0 7 0c1.006 0 1.969.208 2.887.623a6.67 6.67 0 0 1 2.363 1.783V0H14v6.125H7.875v-1.75h3.675A5.107 5.107 0 0 0 9.636 2.45 5.187 5.187 0 0 0 7 1.75c-1.458 0-2.698.51-3.719 1.531C2.261 4.302 1.75 5.541 1.75 7c0 1.458.51 2.698 1.531 3.719C4.302 11.739 5.541 12.25 7 12.25a5.13 5.13 0 0 0 3.04-.963 5.082 5.082 0 0 0 1.904-2.537h1.837a6.909 6.909 0 0 1-2.494 3.784C10.034 13.511 8.604 14 7 14Z"/></svg></a></div>';
};
$selection = static function (array $tribes, int $activeTribe, string $kind) use ($esc): string {
    $html = '<div class="tribeSelection ' . $kind . '"><div class="tribeWrapper">';
    foreach ($tribes as $tribe) {
        $active = $tribe === $activeTribe;
        $state = $active ? 'active' : 'inactive';
        $size = $kind === 'reinforcement' ? 'smallTribe' : 'bigTribe';
        $html .= '<div class="' . $state . '"><i class="tribeIcon ' . $size . $tribe . ' ' . $state . '" title="' . $esc(T('Global', 'races.' . $tribe)) . '"></i>';
        if ($kind === 'reinforcement') {
            $html .= '<svg viewBox="0 0 8 8" aria-hidden="true"><circle cx="4" cy="4" r="4"></circle><line x1="2" y1="4" x2="6" y2="4"></line><line x1="4" y1="2" x2="4" y2="6"></line></svg>';
        }
        $html .= '</div>';
    }

    return $html . '</div></div>';
};
$selectOptions = static function (array $options, float $selected, bool $percent = false) use ($esc): string {
    $html = '';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . $esc($value) . '"' . (abs((float)$value - $selected) < 0.00001 ? ' selected="selected"' : '') . '>' . $esc("\u{202D}\u{202D}" . $label . "\u{202C}" . ($percent ? '%' : '×') . "\u{202C}") . '</option>';
    }

    return $html;
};
$troopTable = static function (array $units, int $tribe, bool $heroChecked = false, bool $celebrationChecked = false) use ($esc): string {
    $icons = '<tr class="troopIcons"><th></th>';
    $numbers = '<tr class="troopUnits"><th><i class="troopCount_small" title="' . $esc(T('combat', 'troopUnits')) . '"></i></th>';
    $levels = '<tr class="troopLevels"><th><i class="troopLevel_small" title="' . $esc(T('combat', 'troopLevels')) . '"></i></th>';
    for ($slot = 1; $slot <= 10; ++$slot) {
        $unitId = nrToUnitId($slot, $tribe);
        $number = max(0, (int)($units[$slot - 1]['number'] ?? 0));
        $level = max(0, (int)($units[$slot - 1]['level'] ?? 0));
        $title = $esc(T('Troops', $unitId . '.title'));
        $icons .= '<td><i class="unit u' . $unitId . '" title="' . $title . '"></i></td>';
        $numbers .= '<td><input inputmode="numeric" data-t="t' . $slot . '" class="unit' . $slot . '" maxlength="11" type="text" value="' . $number . '" name="unit' . $slot . '"></td>';
        if ($slot <= 8) {
            $levels .= $tribe !== 4
                ? '<td class="t' . $slot . '"><input inputmode="numeric" class="unit' . $slot . '" type="text" value="' . ($level ?: '') . '" name="unit' . $slot . '"></td>'
                : '<td class="t' . $slot . '"></td>';
        }
    }
    $icons .= '<td><i class="unit uhero" title="' . $esc(T('Troops', '98.title')) . '"></i></td></tr>';
    // Hero controls are serialized explicitly by the simulator adapter.  Leaving
    // duplicate "hero" field names on both sides made a defender checkbox
    // silently enable an attacking hero in PHP.
    $numbers .= '<td><input class="hero shown" type="checkbox" data-hero="1"' . ($heroChecked ? ' checked="checked"' : '') . '></td></tr>';
    $levels .= '<td class="celebration" colspan="3"><div><i class="celebration_small" title="' . $esc(T('combat', 'celebration')) . '"></i><input class="celebration_small" type="checkbox" name="celebration"' . ($celebrationChecked ? ' checked="checked"' : '') . '></div></td></tr>';

    return '<table class="transparent"><tbody>' . $icons . $numbers . $levels . '</tbody></table>';
};
$attackerBonusOptions = [1 => '0', '1.02' => '2', '1.04' => '4', '1.06' => '6', '1.08' => '8', '1.1' => '10'];
$spyOptions = [0 => '0', 3 => '3', 5 => '5', 10 => '10', '0.33' => '0.33', '0.2' => '0.20', '0.1' => '0.10'];
$buildingMasterOptions = [0 => '0', 3 => '3', 4 => '4', 5 => '5', '0.33' => '0.33', '0.25' => '0.25', '0.2' => '0.20'];
$emptyUnits = array_fill(0, 10, ['number' => 0, 'level' => 0]);
$defenderUnitIds = [];
$defenderWallIds = [];
foreach ($defenderTribes as $tribe) {
    $defenderUnitIds[(int)$tribe] = array_map(static fn(int $slot): int => nrToUnitId($slot, (int)$tribe), range(1, 10));
    if ((int)$tribe !== 4) {
        $defenderWallIds[(int)$tribe] = Formulas::getWallID((int)$tribe);
    }
}
?>
<div id="rallyPointSimulators" class="contentV2">
    <div class="subTabs"><div class="subTabs_tabs">
        <a class="active favourite" title="<?=$esc(T('combat', 'combatSimulatorTab')); ?>"><?=T('combat', 'combatSimulatorTab'); ?><svg viewBox="0 0 48 48" class="star"><path d="m11.7 44 4.6-15.2L4 20h15.2L24 4l4.8 16H44l-12.3 8.8L36.4 44 24 34.6Z"/></svg></a>
        <a title="<?=$esc(T('combat', 'travelSimulatorTab')); ?>"><?=T('combat', 'travelSimulatorTab'); ?></a>
        <a class="favor"><svg viewBox="0 0 48 48"><path d="m11.7 44 4.6-15.2L4 20h15.2L24 4l4.8 16H44l-12.3 8.8L36.4 44 24 34.6Z"/></svg></a>
    </div></div>
    <div id="combatSimulator">
        <p class="description"><?=T('combat', 'simulatorDescription'); ?></p>
        <form id="combatSimulatorForm" autocomplete="off">
            <div id="switchRoleWrapper" class="switchRoleWrapper"><button class="switch textButtonV1 <?=$playsDefender ? 'attacker' : 'defender'; ?>"><?=T('combat', $playsDefender ? 'playAsAttacker' : 'playAsDefender'); ?></button></div>
            <div class="role attacker">
                <div class="header<?=$playsDefender ? '' : ' you'; ?>"><?=$roleIcon('attack'); ?><h2><?=T('combat', $playsDefender ? 'attacker' : 'you'); ?></h2><?php if (!$playsDefender) { echo $villageSelection(); } ?><?=$clearButton; ?></div>
                <?=$selection($attackerTribes, (int)$vars['attacker']['race'], 'attacker'); ?>
                <div class="content">
                    <div class="movement"><label><input type="radio" value="attack" name="movement"<?= $vars['attack_type'] === 2 ? ' checked="checked"' : ''; ?>><?=T('combat', 'attackMovement'); ?></label><label><input type="radio" value="raid" name="movement"<?= $vars['attack_type'] === 3 ? ' checked="checked"' : ''; ?>><?=T('combat', 'raid'); ?></label></div>
                    <div class="bonus shown"><div class="artefacts"><label><i class="allyBonus" title="<?=$esc(sprintf(T('combat', 'allianceBonus'), T('AllianceBonus', 'Metallurgy'))); ?>"></i><select name="allyBonus"><?=$selectOptions($attackerBonusOptions, (float)$vars['attacker']['allianceBonus'], true); ?></select></label><label><i class="artefact_spy" title="<?=$esc(T('combat', 'artefactSpy')); ?>"></i><select name="artefact_spy"><?=$selectOptions($spyOptions, (float)$vars['attacker']['artefactSpy']); ?></select></label></div><div class="buildings shown"><label><i class="building_small type35" title="<?=$esc(T('Buildings', '35.title')); ?>"></i><input inputmode="numeric" type="text" value="<?=$vars['attacker']['brewery']; ?>" name="brewery"></label></div></div>
                    <div class="troops attack"><?=$troopTable($vars['attacker']['units'], (int)$vars['attacker']['race'], (bool)$vars['attacker']['hero'], (bool)$vars['attacker']['celebration']); ?></div>
                    <div class="heroAttributes"></div>
                </div>
            </div>
            <div class="role defender">
                <div class="header<?=$playsDefender ? ' you' : ''; ?>"><?=$roleIcon('defend'); ?><h2><?=T('combat', $playsDefender ? 'you' : 'defender'); ?></h2><?php if ($playsDefender) { echo $villageSelection(); } ?><?=$clearButton; ?></div>
                <?=$selection($defenderTribes, $mainDefender, 'defender'); ?>
                <div class="content">
                    <div class="bonus shown"><div class="artefacts"><label><i class="allyBonus" title="<?=$esc(sprintf(T('combat', 'allianceBonus'), T('AllianceBonus', 'Metallurgy'))); ?>"></i><select name="allyBonus"><?=$selectOptions($attackerBonusOptions, (float)$vars['defender']['allianceBonus'], true); ?></select></label><label><i class="artefact_buildingMaster" title="<?=$esc(T('combat', 'artefactBuildingMaster')); ?>"></i><select name="buildingMasterBonus"><?=$selectOptions($buildingMasterOptions, (float)$vars['defender']['buildingMasterBonus']); ?></select></label><label><i class="artefact_spy" title="<?=$esc(T('combat', 'artefactSpy')); ?>"></i><select name="artefact_spy"><?=$selectOptions($spyOptions, (float)$vars['defender']['artefactSpy']); ?></select></label></div><div class="buildings shown"><span><?php if ($mainDefender !== 4): $wallId = Formulas::getWallID($mainDefender); ?><label><i class="building_small tribe<?=$mainDefender; ?> type<?=$wallId; ?>" title="<?=$esc(T('Buildings', $wallId . '.title')); ?>"></i><input inputmode="numeric" type="text" value="<?=$vars['defender']['wallLevel']; ?>" name="wall"></label><?php endif; ?><label><i class="building_small tribe<?=$mainDefender; ?> type26" title="<?=$esc(T('combat', 'palaceResidenceCommandCenter')); ?>"></i><input inputmode="numeric" type="text" value="<?=$vars['defender']['palast']; ?>" name="palace"></label><label><i class="building_small tribe<?=$mainDefender; ?> type34" title="<?=$esc(T('Buildings', '34.title')); ?>"></i><input inputmode="numeric" type="text" value="<?=$vars['defender']['steinmetz']; ?>" name="stonemason"></label></span></div></div>
                    <?php foreach ($vars['defender']['races'] as $tribe => $defenderData): ?><div class="troops defence" data-simulator-tribe="<?= (int)$tribe; ?>"><?=$troopTable($defenderData['units'], (int)$tribe, (int)$tribe === $mainDefender && (bool)$vars['defender']['hero']); ?></div><?php endforeach; ?>
                    <div class="heroAttributes"></div>
                </div>
            </div>
            <?php $activeReinforcement = 0; foreach ($reinforcementTribes as $tribe) { if ($tribe !== $mainDefender && isset($vars['defender']['races'][$tribe])) { $activeReinforcement = $tribe; break; } } ?>
            <?=$selection($reinforcementTribes, $activeReinforcement, 'reinforcement'); ?>
        </form>
        <div id="combatSimulatorTemplates" hidden><?php foreach ($attackerTribes as $tribe): ?><template data-simulator-role="attacker" data-simulator-tribe="<?= (int)$tribe; ?>"><?=$troopTable($emptyUnits, (int)$tribe); ?></template><?php endforeach; ?><?php foreach ($defenderTribes as $tribe): ?><template data-simulator-role="defender" data-simulator-tribe="<?= (int)$tribe; ?>"><?=$troopTable($emptyUnits, (int)$tribe); ?></template><?php endforeach; ?></div>
        <div id="stickyPin"><div id="stickyWrapper" class="simulateWrapper floating"><button class="textButtonV2 buttonFramed simulate rectangle withText green" type="button" id="simulate" disabled="disabled" title="<?=$esc($missingAttackerData); ?>"><div><?=T('combat', 'simulate'); ?></div></button></div></div>
        <script type="text/javascript">
            (function () {
                var form = document.getElementById('combatSimulatorForm'); if (!form) { return; }
                var simulate = document.getElementById('simulate'), missingAttackerData = <?= json_encode($missingAttackerData, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>;
                var attackerTribes = <?= json_encode(array_values($attackerTribes)); ?>, defenderTribes = <?= json_encode(array_values($defenderTribes)); ?>, reinforcementTribes = <?= json_encode(array_values($reinforcementTribes)); ?>, defenderUnitIds = <?= json_encode($defenderUnitIds); ?>, defenderWallIds = <?= json_encode($defenderWallIds); ?>, currentRole = <?= json_encode($vars['role']); ?>, villageSnapshots = <?= json_encode($vars['villageSnapshots'], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>, heroEquipment = <?= json_encode($vars['heroEquipment'], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>, initialHeroData = <?= json_encode(['attacker' => $vars['attacker']['heroData'], 'defender' => $vars['defender']['heroData']], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>;
                var labels = <?= json_encode([
                    'you' => T('combat', 'you'),
                    'attacker' => T('combat', 'attacker'),
                    'defender' => T('combat', 'defender'),
                    'playAsAttacker' => T('combat', 'playAsAttacker'),
                    'playAsDefender' => T('combat', 'playAsDefender'),
                ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>;
                var activeTribe = function (selector, tribes) { var tiles = form.querySelectorAll(selector + ' .tribeWrapper > div'); for (var i = 0; i < tiles.length; i++) { if (tiles[i].classList.contains('active')) { return tribes[i]; } } return tribes[0]; };
                var setActiveTribe = function (selector, tribe, tribes) { form.querySelectorAll(selector + ' .tribeWrapper > div').forEach(function (tile, index) { var active = tribes[index] === tribe; tile.classList.toggle('active', active); tile.classList.toggle('inactive', !active); var icon = tile.querySelector('.tribeIcon'); icon.classList.toggle('active', active); icon.classList.toggle('inactive', !active); }); };
                var tableTemplate = function (role, tribe) { return document.querySelector('#combatSimulatorTemplates template[data-simulator-role="' + role + '"][data-simulator-tribe="' + tribe + '"]'); };
                var clonedTable = function (role, tribe) { var template = tableTemplate(role, tribe); return template ? template.content.firstElementChild.cloneNode(true) : null; };
                var boundedHeroValue = function (value, fallback, maximum) { var numeric = Number(value); return Number.isFinite(numeric) ? Math.max(0, Math.min(maximum, numeric)) : fallback; };
                var heroStateFor = function (data, isAttacker) { var selected = data && data.selectedItems ? data.selectedItems : {}; return {health: boundedHeroValue(data && data.health, 100, 100), strength: boundedHeroValue(data && data.strength, 0, 100), points: boundedHeroValue(data && data.points, 0, 100), horse: isAttacker ? data && data.horse !== false : false, selectedItems: {leftHand: selected.leftHand || null, body: selected.body || null, bag: selected.bag || null}}; };
                var heroState = {
                    attacker: heroStateFor(initialHeroData.attacker, true),
                    defender: heroStateFor(initialHeroData.defender, false)
                };
                var roleSide = function (role) { return role.classList.contains('attacker') ? 'attacker' : 'defender'; };
                var heroCheckbox = function (role) { if (role.classList.contains('attacker')) { return role.querySelector('.troops.attack .troopUnits input.hero'); } var main = activeTribe('.tribeSelection.defender', defenderTribes); return role.querySelector('.troops.defence[data-simulator-tribe="' + main + '"] .troopUnits input.hero'); };
                var heroTribe = function (side) { return side === 'attacker' ? activeTribe('.tribeSelection.attacker', attackerTribes) : activeTribe('.tribeSelection.defender', defenderTribes); };
                var appendHeroStat = function (stats, iconClass, name, value, maxLength, calculated) { var label = document.createElement('label'), icon = document.createElement('i'), input = document.createElement('input'); icon.className = iconClass; input.inputMode = 'numeric'; input.maxLength = maxLength; input.type = 'text'; input.name = name; input.value = value; label.append(icon, input); if (calculated) { var total = document.createElement('span'); total.className = 'calculated'; label.append(total); } stats.appendChild(label); return input; };
                var heroEquipmentItem = function (slot, type) { var groups = heroEquipment[slot] || []; for (var groupIndex = 0; groupIndex < groups.length; groupIndex++) { for (var itemIndex = 0; itemIndex < groups[groupIndex].items.length; itemIndex++) { var item = groups[groupIndex].items[itemIndex]; if (item.type === Number(type)) { return {group: groups[groupIndex], item: item}; } } } return null; };
                var heroEquipmentCheckmark = function () { var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg'), path = document.createElementNS('http://www.w3.org/2000/svg', 'path'); svg.setAttribute('viewBox', '-1 -1 20 20'); svg.setAttribute('class', 'checkmark'); path.setAttribute('d', 'M.3 8.5s5 4.4 6.3 8.1C8.5 7.8 14.3.3 14.3.3'); svg.appendChild(path); return svg; };
                var closeHeroEquipment = function (container) { var dropdown = container.querySelector('.dropdown'); if (dropdown) { dropdown.classList.remove('opened', 'dropUp'); dropdown.classList.add('closed'); } var overlay = container.querySelector('.overlay'); if (overlay) { overlay.remove(); } };
                var closeAllHeroEquipment = function () { form.querySelectorAll('.heroAttributes .equipContainer').forEach(closeHeroEquipment); };
                var setHeroItem = function (state, slot, type, amount) { var current = state.selectedItems[slot]; if (slot !== 'bag' && current && current.itemTypeId === type) { state.selectedItems[slot] = null; return; } state.selectedItems[slot] = {itemTypeId: type, amount: amount}; };
                var renderHeroEquipment = function (role, state) {
                    var equipment = document.createElement('div'), caption = document.createElement('span'); equipment.className = 'equipment'; caption.className = 'title'; caption.textContent = <?= json_encode(T('HeroInventory', 'equipment', 'Equipment'), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>; equipment.appendChild(caption);
                    var makeSlot = function (slot, fallback, interactive) {
                        var container = document.createElement('div'), slotElement = document.createElement('div'), icon = document.createElement('i'), selected = state.selectedItems[slot], selectedItem = selected ? heroEquipmentItem(slot, selected.itemTypeId) : null;
                        container.className = 'equipContainer'; slotElement.className = 'slot'; icon.className = selectedItem ? selectedItem.group.icon : fallback; slotElement.appendChild(icon); container.appendChild(slotElement);
                        if (!interactive) { return container; }
                        var dropdown = document.createElement('div'); dropdown.className = 'dropdown ' + slot + ' closed';
                        (heroEquipment[slot] || []).forEach(function (group) {
                            var groupElement = document.createElement('div'); groupElement.className = 'group';
                            if (slot !== 'bag') { var groupTitle = document.createElement('div'), groupIcon = document.createElement('i'), groupText = document.createElement('span'); groupTitle.className = 'title'; groupIcon.className = group.icon; groupText.textContent = group.title; groupTitle.append(groupIcon, groupText); groupElement.appendChild(groupTitle); }
                            group.items.forEach(function (item) {
                                var entry = document.createElement('div'), active = state.selectedItems[slot], isSelected = active && active.itemTypeId === item.type, owned = Object.prototype.hasOwnProperty.call(heroEquipment.owned[slot] || {}, item.type);
                                entry.className = 'entry' + (owned ? ' owned' : '') + (isSelected ? ' selected' : ''); entry.setAttribute('data-item-type', String(item.type)); entry.appendChild(heroEquipmentCheckmark());
                                if (slot === 'bag') { var amount = document.createElement('input'); amount.inputMode = 'numeric'; amount.type = 'text'; amount.value = isSelected ? String(active.amount) : ''; entry.appendChild(amount); var bagIcon = document.createElement('i'); bagIcon.className = group.icon; entry.appendChild(bagIcon); }
                                entry.appendChild(document.createTextNode(item.name));
                                entry.addEventListener('click', function (event) { var input = entry.querySelector('input'); if (slot === 'bag' && event.target === input) { input.focus(); return; } setHeroItem(state, slot, item.type, slot === 'bag' ? Math.max(0, Number(input ? input.value : 0) || 0) : 1); renderHeroEditor(role); var updatedDropdown = role.querySelector('.dropdown.' + slot), updatedSlot = updatedDropdown ? updatedDropdown.parentElement.querySelector('.slot') : null; if (updatedSlot) { updatedSlot.click(); } if (slot === 'bag') { var selectedInput = role.querySelector('.dropdown.bag .entry.selected input'); if (selectedInput) { selectedInput.focus(); } } });
                                groupElement.appendChild(entry);
                            });
                            dropdown.appendChild(groupElement);
                        });
                        container.appendChild(dropdown);
                        slotElement.addEventListener('click', function () { var wasOpen = dropdown.classList.contains('opened'); closeAllHeroEquipment(); if (wasOpen) { return; } var overlay = document.createElement('div'); overlay.className = 'overlay'; overlay.addEventListener('click', function () { closeHeroEquipment(container); }); container.appendChild(overlay); dropdown.classList.remove('closed'); dropdown.classList.add('opened'); window.requestAnimationFrame(function () { dropdown.classList.toggle('dropUp', dropdown.getBoundingClientRect().bottom > window.innerHeight); }); });
                        return container;
                    };
                    equipment.append(makeSlot('rightHand', 'hero_handR', false), makeSlot('leftHand', 'hero_handL', true), makeSlot('body', 'hero_torso', true), makeSlot('bag', 'hero_bag', true));
                    return equipment;
                };
                var renderHeroEditor = function (role) {
                    var side = roleSide(role), target = role.querySelector('.heroAttributes'), hero = heroCheckbox(role), isYou = (side === 'attacker') === (currentRole === 'attacker');
                    target.replaceChildren();
                    if (!isYou || !hero || !hero.checked || [4, 5].indexOf(heroTribe(side)) !== -1) { return; }
                    var stats = document.createElement('div'), state = heroState[side], pointsName = side === 'attacker' ? 'offPoints' : 'defPoints';
                    stats.className = 'stats';
                    var title = document.createElement('span'); title.textContent = <?= json_encode(T('Troops', 'hero.title'), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>; stats.appendChild(title);
                    appendHeroStat(stats, 'hero_health', 'health', state.health, 3);
                    appendHeroStat(stats, 'hero_strength', 'strength', state.strength, 5, true);
                    appendHeroStat(stats, side === 'attacker' ? 'hero_offBonus' : 'hero_defBonus', pointsName, state.points, 3, true);
                    var horseLabel = document.createElement('label'), horseIcon = document.createElement('i'), horse = document.createElement('input'); horseIcon.className = 'hero_horse'; horse.type = 'checkbox'; horse.name = 'horse'; horse.checked = state.horse; horseLabel.append(horseIcon, horse); stats.appendChild(horseLabel);
                    target.append(stats, renderHeroEquipment(role, state));
                    updateHeroCalculations(role);
                };
                var updateHeroCalculations = function (role) {
                    var side = roleSide(role), target = role.querySelector('.heroAttributes'), state = heroState[side], tribe = heroTribe(side), fields = target.querySelectorAll('input');
                    fields.forEach(function (input) { if (input.name === 'health') { state.health = Math.max(0, Math.min(100, Number(input.value) || 0)); } if (input.name === 'strength') { state.strength = Math.max(0, Math.min(100, Number(input.value) || 0)); } if (input.name === 'offPoints' || input.name === 'defPoints') { state.points = Math.max(0, Math.min(100, Number(input.value) || 0)); } if (input.name === 'horse') { state.horse = input.checked; } });
                    var bonusPower = ['leftHand', 'body'].reduce(function (total, slot) { var selected = state.selectedItems[slot], selectedItem = selected ? heroEquipmentItem(slot, selected.itemTypeId) : null; return total + (selectedItem ? Number(selectedItem.item.power) || 0 : 0); }, 0), calculated = target.querySelectorAll('.calculated'); if (calculated[0]) { calculated[0].textContent = String(100 + state.strength * (tribe === 1 ? 100 : 80) + bonusPower); } if (calculated[1]) { calculated[1].textContent = (state.points * 0.2).toFixed(1) + '%'; }
                };
                var syncHeroEditors = function () { form.querySelectorAll('.role').forEach(renderHeroEditor); };
                var replaceAttackerTable = function (tribe) { var attacker = form.querySelector('.role.attacker'), current = attacker.querySelector('.troops.attack table'), replacement = clonedTable('attacker', tribe); if (current && replacement) { current.replaceWith(replacement); } syncHeroEditors(); };
                var renderDefenderBuildings = function (tribe) { var target = form.querySelector('.role.defender .buildings > span'), oldWall = form.querySelector('.role.defender [name="wall"]'), oldPalace = form.querySelector('.role.defender [name="palace"]'), oldStonemason = form.querySelector('.role.defender [name="stonemason"]'); if (!target) { return; } var make = function (iconClass, name, value) { var label = document.createElement('label'), icon = document.createElement('i'), input = document.createElement('input'); icon.className = iconClass; input.inputMode = 'numeric'; input.type = 'text'; input.name = name; input.value = value || 0; label.append(icon, input); return label; }; target.replaceChildren(); if (defenderWallIds[tribe]) { target.appendChild(make('building_small tribe' + tribe + ' type' + defenderWallIds[tribe], 'wall', oldWall ? oldWall.value : 0)); } target.appendChild(make('building_small tribe' + tribe + ' type26', 'palace', oldPalace ? oldPalace.value : 0)); target.appendChild(make('building_small tribe' + tribe + ' type34', 'stonemason', oldStonemason ? oldStonemason.value : 0)); };
                var activeReinforcements = function (main) { var active = []; form.querySelectorAll('.tribeSelection.reinforcement .tribeWrapper > div').forEach(function (tile, index) { if (reinforcementTribes[index] === main) { tile.classList.remove('active'); tile.classList.add('inactive'); var icon = tile.querySelector('.tribeIcon'); icon.classList.remove('active'); icon.classList.add('inactive'); return; } if (tile.classList.contains('active')) { active.push(reinforcementTribes[index]); } }); return active; };
                var renderDefenderTables = function () { var defender = form.querySelector('.role.defender'), main = activeTribe('.tribeSelection.defender', defenderTribes), tribes = [main].concat(activeReinforcements(main)).filter(function (tribe, index, values) { return values.indexOf(tribe) === index; }), anchor = defender.querySelector('.heroAttributes'); renderDefenderBuildings(main); defender.querySelectorAll('.troops.defence').forEach(function (node) { node.remove(); }); tribes.forEach(function (tribe) { var table = clonedTable('defender', tribe); if (!table) { return; } var wrapper = document.createElement('div'); wrapper.className = 'troops defence'; wrapper.setAttribute('data-simulator-tribe', tribe); wrapper.appendChild(table); anchor.before(wrapper); }); syncHeroEditors(); };
                var updateRole = function () { var attacker = form.querySelector('.role.attacker'), defender = form.querySelector('.role.defender'), attackerIsYou = currentRole === 'attacker', village = form.querySelector('.villageSelection'), button = form.querySelector('#switchRoleWrapper button'); attacker.classList.toggle('you', attackerIsYou); defender.classList.toggle('you', !attackerIsYou); attacker.querySelector('h2').textContent = attackerIsYou ? labels.you : labels.attacker; defender.querySelector('h2').textContent = attackerIsYou ? labels.defender : labels.you; button.className = 'switch textButtonV1 ' + (attackerIsYou ? 'defender' : 'attacker'); button.textContent = attackerIsYou ? labels.playAsDefender : labels.playAsAttacker; (attackerIsYou ? attacker : defender).querySelector('.clear').before(village); syncHeroEditors(); };
                var fillTroopTable = function (table, snapshot) { if (!table || !snapshot) { return; } table.querySelectorAll('.troopUnits input[data-t]').forEach(function (input, index) { input.value = snapshot.units[index + 1] || 0; }); table.querySelectorAll('.troopLevels input[class^="unit"]').forEach(function (input, index) { input.value = snapshot.smithy[index + 1] || ''; }); var hero = table.querySelector('.troopUnits input.hero'); if (hero) { hero.checked = Number(snapshot.units[11] || 0) > 0; } };
                var selectedSnapshot = function () { var village = form.querySelector('.villageSelection select'); return village ? villageSnapshots[String(village.value)] || null : null; };
                var applyVillage = function () { var snapshot = selectedSnapshot(); if (!snapshot) { return; } if (currentRole === 'attacker') { if (attackerTribes.indexOf(Number(snapshot.race)) === -1) { return; } setActiveTribe('.tribeSelection.attacker', Number(snapshot.race), attackerTribes); replaceAttackerTable(Number(snapshot.race)); fillTroopTable(form.querySelector('.role.attacker .troops.attack table'), snapshot); } else { if (defenderTribes.indexOf(Number(snapshot.race)) === -1) { return; } setActiveTribe('.tribeSelection.defender', Number(snapshot.race), defenderTribes); renderDefenderTables(); var wrapper = form.querySelector('.role.defender .troops.defence[data-simulator-tribe="' + snapshot.race + '"]'); fillTroopTable(wrapper ? wrapper.querySelector('table') : null, snapshot); var defender = form.querySelector('.role.defender'), wall = defender.querySelector('[name="wall"]'), palace = defender.querySelector('[name="palace"]'), stonemason = defender.querySelector('[name="stonemason"]'); if (wall) { wall.value = snapshot.wall || 0; } palace.value = snapshot.palace || 0; stonemason.value = snapshot.stonemason || 0; } syncHeroEditors(); updateSimulate(); };
                var addSimulationInput = function (name, value) { var input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; input.setAttribute('data-simulator-payload', '1'); form.appendChild(input); };
                var selectedValue = function (role, name) { var input = role.querySelector('[name="' + name + '"]:checked') || role.querySelector('[name="' + name + '"]'); return input ? input.value : ''; };
                var mapTroopTable = function (table, prefix, unitIds) { table.querySelectorAll('.troopUnits input[data-t]').forEach(function (input, index) { addSimulationInput(prefix + unitIds[index], input.value || 0); }); table.querySelectorAll('.troopLevels input.unit1, .troopLevels input.unit2, .troopLevels input.unit3, .troopLevels input.unit4, .troopLevels input.unit5, .troopLevels input.unit6, .troopLevels input.unit7, .troopLevels input.unit8').forEach(function (input, index) { addSimulationInput(prefix.replace('a', 'f') + unitIds[index], input.value || 0); }); };
                var mapHero = function (role, side) { var hero = heroCheckbox(role); if (!hero || !hero.checked) { return; } var state = heroState[side], prefix = side === 'attacker' ? 'a1' : 'a2'; addSimulationInput(prefix + '_hero', 1); addSimulationInput(prefix + '_hero_health', state.health); addSimulationInput(prefix + '_hero_strength', state.strength); addSimulationInput(prefix + (side === 'attacker' ? '_hero_off_points' : '_hero_def_points'), state.points); if (state.selectedItems.leftHand) { addSimulationInput(prefix + '_hero_left_hand', state.selectedItems.leftHand.itemTypeId); } if (state.selectedItems.body) { addSimulationInput(prefix + '_hero_body', state.selectedItems.body.itemTypeId); } if (state.selectedItems.bag) { addSimulationInput(prefix + '_hero_bag', state.selectedItems.bag.itemTypeId); addSimulationInput(prefix + '_hero_bag_amount', state.selectedItems.bag.amount); } if (side === 'attacker' && state.horse) { addSimulationInput('a1_hero_horse', 1); } };
                var buildSimulationPayload = function () {
                    form.querySelectorAll('[data-simulator-payload]').forEach(function (input) { input.remove(); });
                    var attacker = form.querySelector('.role.attacker'), defender = form.querySelector('.role.defender'), attackerTribe = activeTribe('.tribeSelection.attacker', attackerTribes), defenderTribe = activeTribe('.tribeSelection.defender', defenderTribes);
                    addSimulationInput('role', currentRole); addSimulationInput('a1_v', attackerTribe); addSimulationInput('a2_main', defenderTribe); addSimulationInput('ktyp', selectedValue(attacker, 'movement') === 'attack' ? 1 : 2);
                    addSimulationInput('a1_allyBonus', selectedValue(attacker, 'allyBonus')); addSimulationInput('a1_artefactSpy', selectedValue(attacker, 'artefact_spy')); addSimulationInput('a1_brewery', selectedValue(attacker, 'brewery'));
                    var celebration = attacker.querySelector('.troopLevels input[name="celebration"]'); if (celebration && celebration.checked) { addSimulationInput('a1_celebration', 1); }
                    addSimulationInput('a2_allyBonus', selectedValue(defender, 'allyBonus')); addSimulationInput('a2_artefactSpy', selectedValue(defender, 'artefact_spy')); addSimulationInput('a2_buildingMasterBonus', selectedValue(defender, 'buildingMasterBonus'));
                    addSimulationInput('palast', selectedValue(defender, 'palace')); addSimulationInput('steinmetz', selectedValue(defender, 'stonemason')); addSimulationInput('wall' + defenderTribe, selectedValue(defender, 'wall'));
                    var village = form.querySelector('.villageSelection select'), snapshot = selectedSnapshot(); if (snapshot) { addSimulationInput('village', village.value); addSimulationInput(currentRole === 'attacker' ? 'ew1' : 'ew2', snapshot.population); }
                    mapTroopTable(attacker.querySelector('.troops.attack table'), 'a1_', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]); mapHero(attacker, 'attacker');
                    defender.querySelectorAll('.troops.defence table').forEach(function (table) { var wrapper = table.closest('[data-simulator-tribe]'), tribe = Number(wrapper ? wrapper.getAttribute('data-simulator-tribe') : 0); if (tribe && defenderUnitIds[tribe]) { mapTroopTable(table, 'a2_', defenderUnitIds[tribe]); } }); mapHero(defender, 'defender');
                    form.querySelectorAll('.tribeSelection.reinforcement .tribeWrapper > div').forEach(function (tile, index) { var tribe = reinforcementTribes[index]; if (tribe !== defenderTribe && tile.classList.contains('active')) { addSimulationInput('a2_v' + tribe, 1); } });
                };
                var submitSimulation = function () { buildSimulationPayload(); form.method = 'post'; form.action = '/build.php?id=39&tt=3'; form.submit(); };
                var simulateTip = <?= json_encode((string)T('combat', 'simulate'), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG); ?>, setSimulateTip = function (text) { if (simulate._tippy) { simulate._tippy.setContent('<div class="title"></div><div class="text">' + text.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</div>'); } else { simulate.setAttribute('title', text); } };
                var updateSimulate = function () { var hasAttackerTroops = Array.prototype.some.call(form.querySelectorAll('.role.attacker .troopUnits input[data-t]'), function (input) { return Number(input.value) > 0; }); simulate.disabled = !hasAttackerTroops; setSimulateTip(hasAttackerTroops ? simulateTip : missingAttackerData); };
                form.addEventListener('input', function (event) { if (event.target.matches('.role.attacker .troopUnits input[data-t]')) { updateSimulate(); } var role = event.target.closest('.role'); if (role && event.target.matches('.dropdown.bag .entry input')) { var bagEntry = event.target.closest('.entry'), side = roleSide(role), selectedBag = heroState[side].selectedItems.bag, itemType = Number(bagEntry ? bagEntry.getAttribute('data-item-type') : 0); if (selectedBag && selectedBag.itemTypeId === itemType) { selectedBag.amount = Math.max(0, Math.min(100000, Number(event.target.value) || 0)); } } if (role && event.target.closest('.heroAttributes')) { updateHeroCalculations(role); } });
                form.addEventListener('change', function (event) { if (event.target.matches('.troopUnits input.hero')) { syncHeroEditors(); } if (event.target.matches('.heroAttributes input[name="horse"]')) { var role = event.target.closest('.role'); if (role) { updateHeroCalculations(role); } } });
                form.querySelectorAll('.tribeSelection.attacker .tribeWrapper > div').forEach(function (tile, index) { tile.addEventListener('click', function () { var tribe = attackerTribes[index]; if (tribe === activeTribe('.tribeSelection.attacker', attackerTribes)) { return; } setActiveTribe('.tribeSelection.attacker', tribe, attackerTribes); replaceAttackerTable(tribe); updateSimulate(); }); });
                form.querySelectorAll('.tribeSelection.defender .tribeWrapper > div').forEach(function (tile, index) { tile.addEventListener('click', function () { var tribe = defenderTribes[index]; if (tribe === activeTribe('.tribeSelection.defender', defenderTribes)) { return; } setActiveTribe('.tribeSelection.defender', tribe, defenderTribes); renderDefenderTables(); }); });
                form.querySelectorAll('.tribeSelection.reinforcement .tribeWrapper > div').forEach(function (tile, index) { tile.addEventListener('click', function () { var tribe = reinforcementTribes[index], main = activeTribe('.tribeSelection.defender', defenderTribes); if (tribe === main) { return; } tile.classList.toggle('active'); tile.classList.toggle('inactive'); var icon = tile.querySelector('.tribeIcon'); icon.classList.toggle('active'); icon.classList.toggle('inactive'); renderDefenderTables(); }); });
                var village = form.querySelector('.villageSelection select'); if (village) { village.addEventListener('change', applyVillage); } var reload = form.querySelector('.villageSelection .reload'); if (reload) { reload.addEventListener('click', function (event) { event.preventDefault(); applyVillage(); }); }
                form.querySelector('#switchRoleWrapper button').addEventListener('click', function (event) { event.preventDefault(); currentRole = currentRole === 'defender' ? 'attacker' : 'defender'; updateRole(); replaceAttackerTable(activeTribe('.tribeSelection.attacker', attackerTribes)); renderDefenderTables(); updateSimulate(); });
                form.querySelectorAll('.role .clear').forEach(function (button) { button.addEventListener('click', function (event) { event.preventDefault(); var role = button.closest('.role'); role.querySelectorAll('.troopUnits input[data-t]').forEach(function (input) { input.value = '0'; }); role.querySelectorAll('.troopLevels input').forEach(function (input) { input.value = ''; }); var hero = heroCheckbox(role); if (hero) { hero.checked = false; } syncHeroEditors(); updateSimulate(); }); });
                simulate.addEventListener('click', function () { if (!simulate.disabled) { submitSimulation(); } }); updateRole(); updateSimulate();
            })();
        </script>
        <div class="results"><?= $vars['result'] ?? ''; ?></div>
    </div>
</div>
