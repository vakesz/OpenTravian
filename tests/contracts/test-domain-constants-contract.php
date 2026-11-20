#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: the named domain-id constants (Game\Gid, Game\Tribes, Game\Unit,
 * Game\OasisType, Game\Hero\ItemBtype, Game\Hero\ItemType and the movement attack
 * types) carry the values the game data defines. The Formulas building table, the
 * oasis effect table, the tribe slugs, nrToUnitId() and the hero item catalogue
 * stay the source of truth; the constants only name them.
 */

use Core\Time;
use Game\AccessLevel;
use Game\Admin\AdminAccounts;
use Game\CatapultTargets;
use Game\Conquest\CaptureResult;
use Game\Conquest\OasisCaptureResult;
use Game\Formulas;
use Game\Gid;
use Game\Hero\HeroItems;
use Game\Hero\ItemBtype;
use Game\Hero\ItemType;
use Game\OasisType;
use Game\ResearchMode;
use Game\Ships;
use Game\Slot;
use Game\Tribes;
use Game\Unit;
use Game\UserId;
use Game\Wounded;
use Model\DailyQuestModel;
use Model\HeroResourceInventory;
use Model\MovementsModel;
use Model\OasesModel;
use Model\Quest;

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

function assertDomainConstants(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

/** @return array<string, int> the class's int constants */
function intConstants(string $class): array
{
    return array_filter((new ReflectionClass($class))->getConstants(), 'is_int');
}

// Buildings: one constant per Formulas building, value = gid, name = normalised English name.
$buildings = Formulas::$data['buildings'];
$gids = intConstants(Gid::class);
unset($gids['LAST_RESOURCE_FIELD']);
assertDomainConstants(count($gids) === count($buildings), 'Gid must name every building in Formulas::$data[\'buildings\'].');
foreach ($gids as $name => $gid) {
    $building = $buildings[$gid - 1] ?? null;
    assertDomainConstants(is_array($building) && (int)$building['gid'] === $gid, "Gid::$name ($gid) must match the building at key " . ($gid - 1) . '.');
    $expected = strtoupper((string)preg_replace('/[^A-Za-z]+/', '_', str_replace("'", '', $building['name'])));
    assertDomainConstants($expected === $name, "Gid::$name must be named after '{$building['name']}' ($expected).");
}
assertDomainConstants(Gid::LAST_RESOURCE_FIELD === 4 && $buildings[Gid::LAST_RESOURCE_FIELD - 1]['name'] === 'Cropland', 'The last resource field is the cropland, gid 4.');
assertDomainConstants(Gid::WALLS === Formulas::WALL_BUILDINGS, 'Formulas::WALL_BUILDINGS is an alias of Gid::WALLS.');
assertDomainConstants(Gid::WALLS === [31, 32, 33, 42, 43, 47, 50], 'The wall gids are unchanged.');
foreach (Gid::WALLS as $wall) {
    assertDomainConstants(str_contains(strtolower($buildings[$wall - 1]['name']), 'wall') || in_array($wall, [Gid::PALISADE, Gid::BARRICADE], true), "Gid $wall in Gid::WALLS must be a wall.");
}
assertDomainConstants(Gid::EXPANSION === [25, 26, 44], 'The expansion buildings are Residence, Palace and Command Center.');
assertDomainConstants(Wounded::HOSPITAL === Gid::HOSPITAL && Wounded::ASCLEPEION === Gid::ASCLEPEION, 'Wounded building ids alias Gid.');
assertDomainConstants(Ships::HARBOR_GID === Gid::HARBOR, 'Ships::HARBOR_GID aliases Gid::HARBOR.');

// Tribes: each named id maps to its slug, and the existing tables keep their values.
$slugs = ['ROMANS' => 'roman', 'TEUTONS' => 'teuton', 'GAULS' => 'gaul', 'NATURE' => 'nature', 'NATARS' => 'natar', 'EGYPTIANS' => 'egyptian', 'HUNS' => 'hun', 'SPARTANS' => 'spartan', 'VIKINGS' => 'viking'];
$tribeIds = intConstants(Tribes::class);
assertDomainConstants(array_keys($tribeIds) === array_keys($slugs) && array_values($tribeIds) === range(1, 9), 'Tribes names ids 1-9 in order.');
foreach ($slugs as $name => $slug) {
    assertDomainConstants(Tribes::slug(constant(Tribes::class . '::' . $name)) === $slug, "Tribes::$name must be the $slug tribe.");
}
assertDomainConstants(Tribes::SELECTABLE === [1, 2, 3, 6, 7, 8, 9] && Tribes::DEFAULT_PLAYABLE === [1, 2, 3, 6, 7, 8, 9], 'Selectable and default tribes are unchanged.');
assertDomainConstants(Tribes::PICKER_ORDER === [3, 1, 2, 6, 7, 8, 9], 'The picker order is unchanged.');
assertDomainConstants(Tribes::NAMES === [1 => 'Romans', 2 => 'Teutons', 3 => 'Gauls', 6 => 'Egyptians', 7 => 'Huns', 8 => 'Spartans', 9 => 'Vikings'], 'Tribe display names are unchanged.');
assertDomainConstants(Tribes::CAVALRY_START === [1 => 3, 2 => 4, 3 => 2, 4 => -1, 5 => 4, 6 => 3, 7 => 2, 8 => 4, 9 => 4], 'Cavalry start positions are unchanged.');
assertDomainConstants(Tribes::trainingUnitIds(Tribes::GAULS, Gid::RESIDENCE) === [29, 30] && Tribes::trainingUnitIds(Tribes::ROMANS, Gid::WORKSHOP) === [7, 8], 'Expansion buildings train chiefs and settlers, the workshop rams and catapults.');

// Units: the special numbers agree with the unit id helpers.
assertDomainConstants(nrToUnitId(Unit::HERO, Tribes::GAULS) === Unit::HERO_ID && unitIdToNr(Unit::HERO_ID) === Unit::HERO, 'The hero is number 11, global id 98.');
assertDomainConstants(nrToUnitId(Unit::TRAP, Tribes::GAULS) === Unit::TRAP && unitIdToNr(Unit::TRAP) === Unit::TRAP, 'Traps are number and id 99.');
assertDomainConstants(nrToUnitId(Unit::SETTLER, Tribes::TEUTONS) === 20 && nrToUnitId(Unit::CHIEF, Tribes::TEUTONS) === 19, 'Settler is number 10 and chief number 9.');
assertDomainConstants(Unit::RAM === 7 && Unit::CATAPULT === 8, 'Rams and catapults are numbers 7 and 8.');

// Oases: the named types are exactly the Formulas effect table and the animal table keys.
$oasisTypes = array_values(intConstants(OasisType::class));
sort($oasisTypes);
$effectTypes = array_keys(Formulas::$data['oases']);
sort($effectTypes);
$animalTypes = array_keys(OasesModel::ANIMALS_BY_TYPE);
sort($animalTypes);
assertDomainConstants($oasisTypes === $effectTypes && $oasisTypes === $animalTypes, 'OasisType names every oasis type in Formulas and OasesModel.');
assertDomainConstants(Formulas::getOasisEffect(OasisType::WOOD_50) === [1 => 2] && Formulas::getOasisEffect(OasisType::CROP_25) === [4 => 1], 'Oasis type names match their effects.');
assertDomainConstants(Formulas::getOasisEffect(OasisType::IRON_25_CROP_25) === [3 => 1, 4 => 1], 'Mixed oasis types add 25% crop.');
foreach (OasisType::WOOD as $type) {
    assertDomainConstants(isset(Formulas::getOasisEffect($type)[1]), "Oasis type $type in OasisType::WOOD must produce wood.");
}

// Hero items: single-item bag categories map to their item type, and the boundaries hold.
$single = [
    ItemBtype::SMALL_BANDAGE => ItemType::SMALL_BANDAGE,
    ItemBtype::BANDAGE => ItemType::BANDAGE,
    ItemBtype::CAGE => ItemType::CAGE,
    ItemBtype::SCROLL => ItemType::SCROLL,
    ItemBtype::OINTMENT => ItemType::OINTMENT,
    ItemBtype::BUCKET => ItemType::BUCKET,
    ItemBtype::BOOK_OF_WISDOM => ItemType::BOOK_OF_WISDOM,
    ItemBtype::TABLET_OF_LAW => ItemType::TABLET_OF_LAW,
    ItemBtype::ARTWORK => ItemType::ARTWORK,
];
$catalogue = HeroItems::getAvailableItemTypes();
foreach ($single as $btype => $type) {
    assertDomainConstants(($catalogue[$btype] ?? null) === [$type], "Hero item btype $btype must hold only item type $type.");
}
$slots = [ItemBtype::HELMET => 'helmet', ItemBtype::BODY => 'body', ItemBtype::LEFT_HAND => 'leftHand', ItemBtype::RIGHT_HAND => 'rightHand', ItemBtype::SHOES => 'shoes', ItemBtype::HORSE => 'horse'];
foreach ($slots as $btype => $slot) {
    assertDomainConstants((new HeroItems())->getHeroItemProperties($btype, $catalogue[$btype][0])['slot'] === $slot, "Hero item btype $btype must equip the $slot slot.");
}
assertDomainConstants(ItemBtype::LAST_EQUIPMENT + 1 === ItemBtype::FIRST_CONSUMABLE && ItemBtype::FIRST_CONSUMABLE === 7, 'Bag items start at btype 7.');
assertDomainConstants(ItemBtype::MATERIALS === [12, 13], 'Bucket and book of wisdom are the materials.');
assertDomainConstants(HeroResourceInventory::ITEM_BTYPE === ItemBtype::RESOURCES, 'HeroResourceInventory::ITEM_BTYPE aliases ItemBtype::RESOURCES.');
assertDomainConstants(HeroResourceInventory::ITEM_TYPES === ['wood' => ItemType::RESOURCE_WOOD, 'clay' => ItemType::RESOURCE_CLAY, 'iron' => ItemType::RESOURCE_IRON, 'crop' => ItemType::RESOURCE_CROP], 'Carried resource item types are 145-148.');

// Movement attack types stay where they are, with these values.
assertDomainConstants(
    [MovementsModel::ATTACKTYPE_SPY, MovementsModel::ATTACKTYPE_REINFORCEMENT, MovementsModel::ATTACKTYPE_NORMAL, MovementsModel::ATTACKTYPE_RAID, MovementsModel::ATTACKTYPE_SETTLERS, MovementsModel::ATTACKTYPE_EVASION, MovementsModel::ATTACKTYPE_ADVENTURE] === range(1, 7),
    'Movement attack types are 1 spy to 7 adventure.'
);

// Round 2. Time units.
assertDomainConstants(Time::MINUTE === 60 && Time::HOUR === 60 * Time::MINUTE && Time::DAY === 24 * Time::HOUR && Time::WEEK === 7 * Time::DAY, 'Time units are 60 s, 1 h, 1 day and 1 week.');
assertDomainConstants(Time::SECOND_MS === 1000 && Time::HOUR_MS === Time::HOUR * Time::SECOND_MS, 'Millisecond factors are 1000 per second and 3600000 per hour.');

// System accounts (InstallerModel::finalize renumbers Support, Natars and Multihunter to 0, 1, 2).
assertDomainConstants(UserId::SUPPORT === 0 && UserId::NATURE === 0 && UserId::NATARS === 1 && UserId::MULTIHUNTER === 2, 'Support and Nature are 0, Natars 1, Multihunter 2.');
assertDomainConstants(UserId::LAST_SYSTEM === UserId::MULTIHUNTER && UserId::FIRST_PLAYER === UserId::LAST_SYSTEM + 1, 'Players start at id 3.');
assertDomainConstants(!defined(UserId::class . '::LEGACY_HIDDEN_MAX'), 'No legacy hidden-account id range: only the system accounts refuse reinforcements while hidden.');
assertDomainConstants(AdminAccounts::BUILT_IN === [UserId::SUPPORT, UserId::MULTIHUNTER], 'The built-in administrators are Support and Multihunter.');
$installer = (string)file_get_contents($root . '/game/include/Model/InstallerModel.php');
assertDomainConstants(str_contains($installer, 'UPDATE users SET id=1 WHERE id=2') && str_contains($installer, 'UPDATE users SET id=2 WHERE id=3'), 'The installer renumbers Natars to 1 and Multihunter to 2.');

// users.access, movement/send mode, research mode.
assertDomainConstants([AccessLevel::BANNED, AccessLevel::PLAYER, AccessLevel::ADMIN, AccessLevel::FAKE_USER] === [0, 1, 2, 3], 'Access levels are 0 banned, 1 player, 2 admin, 3 fake user.');
assertDomainConstants(MovementsModel::MODE_GOING === 0 && MovementsModel::MODE_RETURN === 1, 'Movement mode is 0 going, 1 returning.');
assertDomainConstants(MovementsModel::SORTTYPE_GOING === MovementsModel::MODE_GOING && MovementsModel::SORTTYPE_RETURN === MovementsModel::MODE_RETURN, 'The SORTTYPE names alias the movement modes.');
assertDomainConstants(ResearchMode::SMITHY === 0 && ResearchMode::ACADEMY === 1, 'Research mode is 0 smithy, 1 academy.');

// Building slots.
assertDomainConstants([Slot::FIRST_FIELD, Slot::LAST_FIELD, Slot::FIRST_BUILDING, Slot::LAST_BUILDING, Slot::RALLY_POINT, Slot::WALL, Slot::WONDER] === [1, 18, 19, 38, 39, 40, 99], 'Slots: fields 1-18, buildings 19-38, rally point 39, wall 40, wonder 99.');
assertDomainConstants(Slot::LAST === Slot::WALL && Slot::LAST_BUILDING + 1 === Slot::RALLY_POINT, 'The last regular slot is the wall.');

// Unit bounds.
assertDomainConstants(Unit::FIRST === 1 && Unit::LAST === Unit::HERO && Unit::LAST_TROOP === Unit::SETTLER, 'Unit columns run 1..10 for troops and 1..11 with the hero.');

// Capture results and the random catapult target.
$capture = array_values(intConstants(CaptureResult::class));
assertDomainConstants($capture === [0, -1, -2, -3, -4, -5, -6], 'Village capture results are 0 and -1..-6.');
assertDomainConstants(array_values(intConstants(OasisCaptureResult::class)) === [0, -1], 'Oasis capture results are 0 and -1.');
assertDomainConstants(CatapultTargets::RANDOM === 99 && CatapultTargets::allowed(20, false)[0] === CatapultTargets::RANDOM, 'The random catapult target is 99 and always allowed.');

// Daily quests: one constant per quest_data entry.
$questData = (new ReflectionProperty(DailyQuestModel::class, 'quest_data'))->getValue(new DailyQuestModel());
$dailyIds = array_filter(intConstants(DailyQuestModel::class), static fn (string $name): bool => str_starts_with($name, 'QUEST_') && $name !== 'QUEST_COUNT', ARRAY_FILTER_USE_KEY);
assertDomainConstants(array_values($dailyIds) === array_keys($questData) && DailyQuestModel::QUEST_COUNT === count($questData), 'DailyQuestModel::QUEST_* names every daily quest, 1..QUEST_COUNT.');

// Battle/economy/world quest bits: each id has an en-US quest name.
$Definition = [];
require $root . '/game/include/resources/Translation/en-US.php';
$groups = ['BATTLE' => 15, 'ECONOMY' => 12, 'WORLD' => 16];
foreach ($groups as $group => $count) {
    $ids = array_filter(intConstants(Quest::class), static fn (string $name): bool => str_starts_with($name, $group . '_'), ARRAY_FILTER_USE_KEY);
    assertDomainConstants(array_values($ids) === range(1, $count), "Quest::{$group}_* must be 1..$count.");
    foreach ($ids as $name => $id) {
        $key = sprintf('%s_%02d_name', strtolower($group), $id);
        assertDomainConstants(isset($Definition['Quest'][$key]), "Quest::$name has no en-US text $key.");
    }
}

echo "Domain constants contract passed.\n";
