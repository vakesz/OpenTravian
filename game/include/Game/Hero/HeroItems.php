<?php

namespace Game\Hero;

use Core\Config;
use Core\Time;
use Game\Formulas;
use Game\WorldSpeedProfile;
use Game\Tribes;

class HeroItems
{
    /**
     * Item types that can be obtained from adventures or listed at auction.
     *
     * @return array<int, array<int, int>>
     */
    public static function getAvailableItemTypes(): array
    {
        $btypes = [
            ItemBtype::HELMET => range(1, 15),
            ItemBtype::BODY => range(82, 93),
            ItemBtype::LEFT_HAND => array_merge(range(61, 69), range(73, 81)),
            ItemBtype::RIGHT_HAND => array_merge(range(16, 60), range(164, 178)),
            ItemBtype::SHOES => range(94, 102),
            ItemBtype::HORSE => range(103, 105),
            ItemBtype::SMALL_BANDAGE => [ItemType::SMALL_BANDAGE],
            ItemBtype::BANDAGE => [ItemType::BANDAGE],
            ItemBtype::CAGE => [ItemType::CAGE],
            ItemBtype::SCROLL => [ItemType::SCROLL],
            ItemBtype::OINTMENT => [ItemType::OINTMENT],
            ItemBtype::BUCKET => [ItemType::BUCKET],
            ItemBtype::BOOK_OF_WISDOM => [ItemType::BOOK_OF_WISDOM],
            ItemBtype::TABLET_OF_LAW => [ItemType::TABLET_OF_LAW],
            ItemBtype::ARTWORK => [ItemType::ARTWORK],
        ];

        if (array_intersect([Tribes::EGYPTIANS, Tribes::HUNS, Tribes::SPARTANS, Tribes::VIKINGS], \Game\Tribes::playable())) {
            $btypes[4] = array_merge($btypes[4], range(115, 144), range(149, 163));
        }

        return $btypes;
    }

    /**
     * Every right-hand weapon type: the original tribes, the newer tribes and the Spartan sets.
     *
     * @return list<int>
     */
    public static function weaponTypes(): array
    {
        return array_merge(range(16, 60), range(115, 144), range(149, 163), range(164, 178));
    }

    /**
     * The slot class an item tile carries while it sits in the inventory: gear and bag consumables show
     * their own slot, while materials (bucket, book of wisdom) and the artwork show none.
     */
    public static function tileSlot(int $btype, string $slot): string
    {
        return in_array($btype, [ItemBtype::BUCKET, ItemBtype::BOOK_OF_WISDOM, ItemBtype::ARTWORK], true) ? 'inventory' : $slot;
    }

    /**
     * @return array<string, bool|int>
     */
    public function getRandomItem()
    {
        mt_srand(make_seed());
        $btypes = self::getAvailableItemTypes();
        $btype = mt_rand(1, 15);
        $type = $btypes[$btype][mt_rand(0, sizeof($btypes[$btype]) - 1)];
        $consume = FALSE;
        if ($btype >= ItemBtype::FIRST_CONSUMABLE && $btype != ItemBtype::BUCKET && $btype != ItemBtype::BOOK_OF_WISDOM) {
            $consume = TRUE;
        }
        return ["btype" => $btype, "type" => $type, "consume" => $consume];
    }

    /**
     *
     * @param mixed $btype
     * @param mixed $type
     * @return array<string, bool|list<string>|string>|array<string, mixed>
     */
    public function getHeroItemProperties($btype, $type)
    {
        $result = ["name" => "", "title" => "",];
        $heroItemsSettings = (array)Config::getProperty("heroConfig", "heroItemsSettings");
        $btype = (int)$btype;
        $type = (int)$type;

        // Carried adventure/quest resources — inventory tiles item145–148.
        // attributes must be a string[] — Inventory JS calls attributes.join().
        if ($btype === ItemBtype::RESOURCES && in_array($type, [ItemType::RESOURCE_WOOD, ItemType::RESOURCE_CLAY, ItemType::RESOURCE_IRON, ItemType::RESOURCE_CROP], true)) {
            $names = [
                ItemType::RESOURCE_WOOD => (string)T('inGame', 'resources.r1'),
                ItemType::RESOURCE_CLAY => (string)T('inGame', 'resources.r2'),
                ItemType::RESOURCE_IRON => (string)T('inGame', 'resources.r3'),
                ItemType::RESOURCE_CROP => (string)T('inGame', 'resources.r4'),
            ];
            // hero.json calls this out as a transfer action, not a
            // production bonus. Persian has no catalog, so do
            // not substitute invented text there.
            $title = in_array(get_locale(), ['en-US', 'ar-AE', 'el-GR'], true)
                ? (string)T('HeroInventory', 'resourceItemInInventory')
                : '';
            $result['name'] = $names[$type];
            $result['title'] = $title;
            $result['attributes'] = $title === '' ? [] : [$title];
            $result['slot'] = 'bag';
            $result['instant'] = true;
            $result['isUsableIfDead'] = true;

            return $result;
        }

        // The Spartan weapon sets (149-163) carry the same effect as the other tribes' weapons: an attack and
        // defence bonus per unit plus fighting strength. The catalogue publishes no legacy attribute text for them, so
        // the name is shown only where the catalogue has one and the effect comes from the tooltip builder.
        if ($btype === ItemBtype::RIGHT_HAND && $type >= 149 && $type <= 163) {
            // Hoplite, Shieldsman, Twinsteel Therion, Elpida Rider and Corinthian Crusher, three tiers each.
            $weapon = [[71, 4, 2], [73, 4, 2], [74, 4, 2], [75, 8, 4], [76, 12, 6]][intdiv($type - 149, 3)];
            $tier = ($type - 149) % 3;
            $result['name'] = in_array(get_locale(), ['en-US', 'ar-AE', 'el-GR'], true)
                ? T("HeroItems", "{$btype}.{$type}.name")
                : '';
            $result['title'] = '';
            $result['attributes'] = [];
            $result['slot'] = 'rightHand';
            $result['instant'] = FALSE;
            $result['isUsableIfDead'] = FALSE;
            $result['unitId'] = $weapon[0];
            $result['hero_power'] = [375, 1500, 6000][$tier] * $heroItemsSettings['heroIncreaseHeroPower'];
            $result['unit_power'] = ($weapon[1] + $tier * $weapon[2]) * $heroItemsSettings['heroIncreaseUnitPower'];

            return $result;
        }

        // Viking weapons (164-178). Heimdall's Eye has none. Thrall and Shield
        // Maiden are +3/+4/+5; Berserker, Huskarl Rider and Valkyrie's Blessing
        // are +6/+8/+10, each with fighting strength 250/1000/4000.
        if ($btype === ItemBtype::RIGHT_HAND && $type >= 164 && $type <= 178) {
            $weapon = [[81, 3, 1], [82, 3, 1], [83, 6, 2], [85, 6, 2], [86, 6, 2]][intdiv($type - 164, 3)];
            $tier = ($type - 164) % 3;
            $result['name'] = in_array(get_locale(), ['en-US', 'ar-AE', 'el-GR'], true)
                ? T("HeroItems", "{$btype}.{$type}.name")
                : '';
            $result['title'] = '';
            $result['attributes'] = [];
            $result['slot'] = 'rightHand';
            $result['instant'] = FALSE;
            $result['isUsableIfDead'] = FALSE;
            $result['unitId'] = $weapon[0];
            $result['hero_power'] = [250, 1000, 4000][$tier] * $heroItemsSettings['heroIncreaseHeroPower'];
            $result['unit_power'] = ($weapon[1] + $tier * $weapon[2]) * $heroItemsSettings['heroIncreaseUnitPower'];

            return $result;
        }

        $result['name'] = T("HeroItems", "{$btype}.{$type}.name");
        if ($btype == ItemBtype::HELMET) {
            $result['slot'] = "helmet";
        } elseif ($btype == ItemBtype::BODY) {
            $result['slot'] = "body";
        } elseif ($btype == ItemBtype::LEFT_HAND) {
            $result['slot'] = "leftHand";
        } elseif ($btype == ItemBtype::RIGHT_HAND) {
            $result['slot'] = "rightHand";
        } elseif ($btype == ItemBtype::SHOES) {
            $result['slot'] = "shoes";
        } elseif ($btype == ItemBtype::HORSE) {
            $result['slot'] = "horse";
        } elseif ($btype >= ItemBtype::FIRST_CONSUMABLE) {
            $result['slot'] = "bag";
        }
        $result['instant'] = !($btype <= 9 || $btype == 10);
        if ($btype < ItemBtype::FIRST_CONSUMABLE || $btype == ItemBtype::SCROLL || $btype == ItemBtype::OINTMENT) {
            $result['isUsableIfDead'] = FALSE;
        } else {
            $result['isUsableIfDead'] = TRUE;
        }
        $result['attributes'] = T("HeroItems", "{$btype}.{$type}.title");
        switch ($btype) {
            case ItemBtype::HELMET:
                if ($type <= 3) {
                    $result['exp'] = (10 + ($type * 5)) * $heroItemsSettings['heroExtraExperienceGainRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['exp']);
                } else if ($type <= 6) {
                    $result['reg'] = (5 + (($type - 3) * 5)) * $heroItemsSettings['heroRegenerationRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['reg']);
                } else if ($type <= 9) {
                    $result['cp'] = ($type == 7 ? 100 : ($type == 8 ? 400 : 1600)) * $heroItemsSettings['heroExtraCulturePointsRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['cp']);
                } else if ($type <= 12) {
                    $result['cav'] = (10 + (($type - 10) * 5)) * $heroItemsSettings['heroTrainingSpeedRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['cav']);
                } else if ($type <= 15) {
                    $result['inf'] = (10 + (($type - 13) * 5)) * $heroItemsSettings['heroTrainingSpeedRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['inf']);
                }
                break;
            case ItemBtype::BODY:
                if ($type <= 84) {
                    $result['reg'] = (20 + (($type - 82) * 10)) * $heroItemsSettings['heroRegenerationRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['reg']);
                } else if ($type <= 87) {
                    $result['reg'] = (10 + (($type - 85) * 5)) * $heroItemsSettings['heroRegenerationRate'];
                    $result['resist'] = (4 + (($type - 85) * 2)) * $heroItemsSettings['heroResistantRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['reg'], $result['resist']);
                } else if ($type <= 90) {
                    $result['hero_power'] = [250, 1000, 4000][$type - 88] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['attributes'] = sprintf($result['attributes'], $result['hero_power']);
                } else if ($type <= 93) {
                    $result['hero_power'] = [125, 500, 2000][$type - 91] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['resist'] = (2 + (($type - 90))) * $heroItemsSettings['heroResistantRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['hero_power'], $result['resist']);
                }
                break;
            case ItemBtype::LEFT_HAND:
                if ($type <= 63) {
                    $result['return_speed'] = (30 + (($type - 61) * 10)) * $heroItemsSettings['heroIncreaseSpeedRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['return_speed']);
                } else if ($type <= 66) {
                    $result['speed_own'] = (30 + (($type - 64) * 10)) * $heroItemsSettings['heroIncreaseSpeedOwnRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['speed_own']);
                } else if ($type <= 69) {
                    $result['speed_alliance'] = (10 + (($type - 66) * 5)) * $heroItemsSettings['heroIncreaseSpeedAllianceRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['speed_alliance']);
                } else if ($type <= 75) {
                    $result['raid'] = (10 + (($type - 73) * 5)) * $heroItemsSettings['heroRaidBagRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['raid']);
                } else if ($type <= 78) {
                    $result['hero_power'] = [250, 1000, 4000][$type - 76] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['attributes'] = sprintf($result['attributes'], $result['hero_power']);
                } else if ($type <= 81) {
                    $result['powerAgainstNatars'] = (15 + (($type - 78) * 5)) * $heroItemsSettings['natarsIncreasePowerAgainstPercent'];
                    $result['attributes'] = sprintf($result['attributes'], $result['powerAgainstNatars']);
                }
                break;
            case ItemBtype::RIGHT_HAND:
                //roman tribe
                if ($type <= 18) {
                    $result['unitId'] = 1;
                    $result['hero_power'] = [250, 1000, 4000][$type - 16] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 16)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 21) {
                    $result['unitId'] = 2;
                    $result['hero_power'] = [250, 1000, 4000][$type - 19] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 19)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 24) {
                    $result['unitId'] = 3;
                    $result['hero_power'] = [250, 1000, 4000][$type - 22] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 22)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 27) {
                    $result['unitId'] = 5;
                    $result['hero_power'] = [250, 1000, 4000][$type - 25] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (9 + ($type - 25) * 3) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 30) {
                    $result['unitId'] = 6;
                    $result['hero_power'] = [250, 1000, 4000][$type - 28] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (12 + ($type - 28) * 4) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                    //tribe 2
                } else if ($type <= 33) {
                    $result['unitId'] = 21;
                    $result['hero_power'] = [250, 1000, 4000][$type - 31] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 31)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 36) {
                    $result['unitId'] = 22;
                    $result['hero_power'] = [250, 1000, 4000][$type - 34] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 34)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 39) {
                    $result['unitId'] = 24;
                    $result['hero_power'] = [250, 1000, 4000][$type - 37] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (6 + ($type - 37) * 2) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 42) {
                    $result['unitId'] = 25;
                    $result['hero_power'] = [250, 1000, 4000][$type - 40] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (6 + ($type - 40) * 2) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 45) {
                    $result['unitId'] = 26;
                    $result['hero_power'] = [250, 1000, 4000][$type - 43] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (9 + ($type - 43) * 3) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                    //tribe 3
                } else if ($type <= 48) {//
                    $result['unitId'] = 11;
                    $result['hero_power'] = [250, 1000, 4000][$type - 46] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 46)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 51) {
                    $result['unitId'] = 12;
                    $result['hero_power'] = [250, 1000, 4000][$type - 49] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 49)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 54) {
                    $result['unitId'] = 13;
                    $result['hero_power'] = [250, 1000, 4000][$type - 52] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 52)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 57) {
                    $result['unitId'] = 15;
                    $result['hero_power'] = [250, 1000, 4000][$type - 55] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (6 + ($type - 55) * 2) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 60) {
                    $result['unitId'] = 16;
                    $result['hero_power'] = [250, 1000, 4000][$type - 58] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (9 + ($type - 58) * 3) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 117) {
                    $result['unitId'] = 51;
                    $result['hero_power'] = [250, 1000, 4000][$type - 115] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 115)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 120) {
                    $result['unitId'] = 52;
                    $result['hero_power'] = [250, 1000, 4000][$type - 118] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 118)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 123) {
                    $result['unitId'] = 53;
                    $result['hero_power'] = [250, 1000, 4000][$type - 121] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 121)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 126) {
                    $result['unitId'] = 55;
                    $result['hero_power'] = [250, 1000, 4000][$type - 124] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (6 + ($type - 124) * 2) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 129) {
                    $result['unitId'] = 56;
                    $result['hero_power'] = [250, 1000, 4000][$type - 127] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (9 + ($type - 127) * 3) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 132) {
                    $result['unitId'] = 61;
                    $result['hero_power'] = [250, 1000, 4000][$type - 130] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 130)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 135) {
                    $result['unitId'] = 62;
                    $result['hero_power'] = [250, 1000, 4000][$type - 133] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (3 + ($type - 133)) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 138) {
                    $result['unitId'] = 64;
                    $result['hero_power'] = [250, 1000, 4000][$type - 136] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (6 + ($type - 136) * 2) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 141) {
                    $result['unitId'] = 65;
                    $result['hero_power'] = [250, 1000, 4000][$type - 139] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (6 + ($type - 139) * 2) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                } else if ($type <= 144) {
                    $result['unitId'] = 66;
                    $result['hero_power'] = [250, 1000, 4000][$type - 142] * $heroItemsSettings['heroIncreaseHeroPower'];
                    $result['unit_power'] = (9 + ($type - 142) * 3) * $heroItemsSettings['heroIncreaseUnitPower'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['hero_power'],
                        $result['unit_power'],
                        $result['unit_power']);
                }
                break;
            case ItemBtype::SHOES:
                if ($type <= 96) {
                    $result['reg'] = (10 + (($type - 94) * 5)) * $heroItemsSettings['heroRegenerationRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['reg']);
                } else if ($type <= 99) {
                    $result['min_distance'] = 20 * $heroItemsSettings['heroMinDistanceRate'];
                    $result['speed_distance'] = (25 + (($type - 97) * 25)) * $heroItemsSettings['heroDistanceSpeedRate'];
                    $result['attributes'] = sprintf($result['attributes'],
                        $result['speed_distance'],
                        $result['min_distance']);
                } else if ($type <= 102) {
                    $result['hero_cav_speed'] = (3 + (($type - 100))) * $heroItemsSettings['heroIncreaseSpeedRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['hero_cav_speed']);
                }
                break;
            case ItemBtype::HORSE:
                if ($type <= 105) {
                    // Legends article 89: the mount itself is 14 / 17 / 20 fields per
                    // hour at ×1. Movement scaling is applied when the hero travels.
                    $result['speed_horse'] = (14 + (($type - 103) * 3)) * $heroItemsSettings['heroIncreaseSpeedRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['speed_horse']);
                }
                break;
            case ItemBtype::SMALL_BANDAGE:
                $result['revive'] = 25 * $heroItemsSettings['heroReviveRate'];
                $result['arrival_time'] = self::bandageArrivalHours($heroItemsSettings);
                $result['attributes'] = sprintf($result['attributes'], $result['revive'], $result['arrival_time']);
                break;
            case ItemBtype::BANDAGE:
                $result['revive'] = 33 * $heroItemsSettings['heroReviveRate'];
                $result['arrival_time'] = self::bandageArrivalHours($heroItemsSettings);
                $result['attributes'] = sprintf($result['attributes'], $result['revive'], $result['arrival_time']);
                break;
            case ItemBtype::CAGE:
                break;
            case ItemBtype::SCROLL:
                $result['exp'] = 10 * $heroItemsSettings['heroExtraExperienceGainRate'] * Formulas::getHeroExpLevelMultiplier();
                break;
            case ItemBtype::OINTMENT:
                $result['reg'] = 1;
                $result['attributes'] = sprintf($result['attributes'], 100);
                break;
            case ItemBtype::BUCKET:
                break;
            case ItemBtype::BOOK_OF_WISDOM:
                break;
            case ItemBtype::TABLET_OF_LAW:
                $result['loyalty'] = 1;
                $result['attributes'] = sprintf($result['attributes'], $result['loyalty'], 125);
                break;
            case ItemBtype::ARTWORK:
                $artwork = Artwork::rules((int)getGameSpeed());
                $result['cp'] = $artwork['limit'];
                $result['attributes'] = sprintf(
                    $result['attributes'],
                    rtrim(rtrim(number_format(100 * $artwork['numerator'] / $artwork['denominator'], 2, '.', ''), '0'), '.'),
                    $artwork['limit'],
                    intdiv($artwork['cooldown'], Time::HOUR)
                );
                break;
        }
        $result['attributes'] = str_replace("&#37;", "%", $result['attributes']);
        $result['title'] = $result['attributes'];
        $result['attributes'] = explode("<br />", $result['attributes']);

        return $result;
    }

    /**
     * Hours shown on a bandage: the shortest trip home for revived troops on
     * this world, so the tooltip matches what BattleModel schedules.
     *
     * @param array<string, mixed> $heroItemsSettings
     */
    private static function bandageArrivalHours(array $heroItemsSettings): float
    {
        $rate = max(1.0, (float)($heroItemsSettings['heroReviveArrivalTimeRate'] ?? 1));
        $seconds = WorldSpeedProfile::bandageReturnFloor((int)getGameSpeed()) / $rate;

        return round($seconds / Time::HOUR, 2);
    }
}
