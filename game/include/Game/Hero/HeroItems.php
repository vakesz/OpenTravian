<?php

namespace Game\Hero;

use Core\Config;
use Game\Formulas;

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
            1 => range(1, 15),
            2 => range(82, 93),
            3 => array_merge(range(61, 69), range(73, 81)),
            4 => range(16, 60),
            5 => range(94, 102),
            6 => range(103, 105),
            7 => [112],
            8 => [113],
            9 => [114],
            10 => [107],
            11 => [106],
            12 => [108],
            13 => [110],
            14 => [109],
            15 => [111],
        ];

        if (array_intersect([6, 7, 8, 9], \Game\Tribes::playable())) {
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
        return array_merge(range(16, 60), range(115, 144), range(149, 163));
    }

    /**
     * The slot class an item tile carries while it sits in the inventory: gear and bag consumables show
     * their own slot, while materials (bucket, book of wisdom) and the artwork show none.
     */
    public static function tileSlot(int $btype, string $slot): string
    {
        return in_array($btype, [12, 13, 15], true) ? 'inventory' : $slot;
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
        if ($btype >= 7 && $btype != 12 && $btype != 13) {
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
        if ($btype === 16 && in_array($type, [145, 146, 147, 148], true)) {
            $names = [
                145 => (string)T('inGame', 'resources.r1'),
                146 => (string)T('inGame', 'resources.r2'),
                147 => (string)T('inGame', 'resources.r3'),
                148 => (string)T('inGame', 'resources.r4'),
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
        if ($btype === 4 && $type >= 149 && $type <= 163) {
            // Hoplite, Shieldsman, Twinsteel Therion, Elpida Rider and Corinthian Crusher, three tiers each.
            $weapon = [[71, 3, 1], [73, 3, 1], [74, 3, 1], [75, 6, 2], [76, 9, 3]][intdiv($type - 149, 3)];
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
            $result['hero_power'] = [250, 1000, 4000][$tier] * $heroItemsSettings['heroIncreaseHeroPower'];
            $result['unit_power'] = ($weapon[1] + $tier * $weapon[2]) * $heroItemsSettings['heroIncreaseUnitPower'];

            return $result;
        }

        $result['name'] = T("HeroItems", "{$btype}.{$type}.name");
        if ($btype == 1) {
            $result['slot'] = "helmet";
        } elseif ($btype == 2) {
            $result['slot'] = "body";
        } elseif ($btype == 3) {
            $result['slot'] = "leftHand";
        } elseif ($btype == 4) {
            $result['slot'] = "rightHand";
        } elseif ($btype == 5) {
            $result['slot'] = "shoes";
        } elseif ($btype == 6) {
            $result['slot'] = "horse";
        } elseif ($btype >= 7) {
            $result['slot'] = "bag";
        }
        $result['instant'] = !($btype <= 9 || $btype == 10);
        if ($btype < 7 || $btype == 10 || $btype == 11) {
            $result['isUsableIfDead'] = FALSE;
        } else {
            $result['isUsableIfDead'] = TRUE;
        }
        $result['attributes'] = T("HeroItems", "{$btype}.{$type}.title");
        switch ($btype) {
            case 1:
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
            case 2:
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
            case 3:
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
            case 4:
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
            case 5:
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
            case 6:
                if ($type <= 105) {
                    $result['speed_horse'] = (14 + (($type - 103) * 6)) * $heroItemsSettings['heroIncreaseSpeedRate'];
                    $result['attributes'] = sprintf($result['attributes'], $result['speed_horse']);
                }
                break;
            case 7:
                $result['revive'] = 25 * $heroItemsSettings['heroReviveRate'];
                $result['arrival_time'] = 24 / $heroItemsSettings['heroReviveArrivalTimeRate'];
                $result['attributes'] = sprintf($result['attributes'], $result['revive'], $result['arrival_time']);
                break;
            case 8:
                $result['revive'] = 33 * $heroItemsSettings['heroReviveRate'];
                $result['arrival_time'] = 24 / $heroItemsSettings['heroReviveArrivalTimeRate'];
                $result['attributes'] = sprintf($result['attributes'], $result['revive'], $result['arrival_time']);
                break;
            case 9:
                break;
            case 10:
                $result['exp'] = 10 * $heroItemsSettings['heroExtraExperienceGainRate'] * Formulas::getHeroExpLevelMultiplier();
                break;
            case 11:
                $result['reg'] = 1;
                $result['attributes'] = sprintf($result['attributes'], 100);
                break;
            case 12:
                break;
            case 13:
                break;
            case 14:
                $result['loyalty'] = 1;
                $result['attributes'] = sprintf($result['attributes'], $result['loyalty'], 125);
                break;
            case 15:
                $artwork = Artwork::rules((int)getGameSpeed());
                $result['cp'] = $artwork['limit'];
                $result['attributes'] = sprintf(
                    $result['attributes'],
                    rtrim(rtrim(number_format(100 * $artwork['numerator'] / $artwork['denominator'], 2, '.', ''), '0'), '.'),
                    $artwork['limit'],
                    intdiv($artwork['cooldown'], 3600)
                );
                break;
        }
        $result['attributes'] = str_replace("&#37;", "%", $result['attributes']);
        $result['title'] = $result['attributes'];
        $result['attributes'] = explode("<br />", $result['attributes']);

        return $result;
    }
}
