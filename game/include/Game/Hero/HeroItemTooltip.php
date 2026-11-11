<?php

namespace Game\Hero;

use Core\Database\DB;
use Core\Session;

use function getGameSpeed;

/**
 * The reference's hero item tooltip: tier, name, slot label and one stat block per effect.
 *
 * Numbers come from HeroItems::getHeroItemProperties(), so they follow the local world's
 * item rates; the wording comes from the HeroItemTooltip translation group. The same markup
 * serves the inventory, the auction lists and the equipment benefits list.
 */
final class HeroItemTooltip
{
    private const SLOT_LABELS = [
        'helmet' => 'slotHelmet',
        'body' => 'slotBody',
        'leftHand' => 'slotLeftHand',
        'rightHand' => 'slotRightHand',
        'shoes' => 'slotShoes',
        'horse' => 'slotHorse',
    ];

    /** Lowest item type of each three-tier family; the tier is the offset within it, modulo 3. */
    private static function tier(int $btype, int $type): int
    {
        $base = match ($btype) {
            1 => 1,
            2 => 82,
            3 => 61,
            4 => $type <= 60 ? 16 : ($type <= 144 ? 115 : 149),
            5 => 94,
            6 => 103,
            default => 1,
        };

        return (($type - $base) % 3) + 1;
    }

    /** The reference's Left-to-right isolate: LRO sign LRO number PDF suffix PDF. */
    private static function number(float|int $value, string $sign = '', string $suffix = ''): string
    {
        return "\u{202D}" . $sign . "\u{202D}" . self::plain($value) . "\u{202C}" . $suffix . "\u{202C}";
    }

    private static function plain(float|int $value): string
    {
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }

    private static function t(string $key): string
    {
        return (string)T('HeroItemTooltip', $key);
    }

    /**
     * @param array<mixed>|list<string> $numbers
     * @param list<string> $detailNumbers
     * @return array<string, string>
     */
    private static function stat(string $key, array $numbers = [], ?string $detailsKey = null, array $detailNumbers = []): array
    {
        $stat = ['text' => sprintf(self::t($key), ...$numbers)];
        if ($detailsKey !== null) {
            $stat['details'] = sprintf(self::t($detailsKey), ...$detailNumbers);
        }

        return $stat;
    }

    /** @return list<array{text: string, details?: string}> the effect blocks of an item. */
    public static function stats(int $btype, int $type): array
    {
        $item = (new HeroItems())->getHeroItemProperties($btype, $type);
        $plus = static fn (float|int $value, string $suffix = ''): string => self::number($value, '+', $suffix);
        $stats = [];

        switch ($btype) {
            case 1:
                if ($type <= 3) {
                    $stats[] = self::stat('experience', [$plus($item['exp'], '%')]);
                } elseif ($type <= 6) {
                    $stats[] = self::stat('regeneration', [$plus($item['reg'])]);
                } elseif ($type <= 9) {
                    $stats[] = self::stat('culturePoints', [$plus($item['cp'])]);
                } elseif ($type <= 12) {
                    $stats[] = self::stat('trainingStable', [self::number($item['cav'], '', '%')]);
                } else {
                    $stats[] = self::stat('trainingBarracks', [self::number($item['inf'], '', '%')]);
                }
                break;
            case 2:
                if (isset($item['reg'])) {
                    $stats[] = self::stat('regeneration', [$plus($item['reg'])]);
                }
                if (isset($item['hero_power'])) {
                    $stats[] = self::stat('fightingStrength', [$plus($item['hero_power'])]);
                }
                if (isset($item['resist'])) {
                    $stats[] = self::stat('damageReduction', [$plus($item['resist'])]);
                }
                break;
            case 3:
                if (isset($item['return_speed'])) {
                    $stats[] = self::stat('returnSpeed', [$plus($item['return_speed'], '%')], 'returnSpeedDetails');
                } elseif (isset($item['speed_own'])) {
                    $stats[] = self::stat('speedOwnVillages', [$plus($item['speed_own'], '%')], 'speedOwnVillagesDetails');
                } elseif (isset($item['speed_alliance'])) {
                    $stats[] = self::stat('speedConfederacy', [$plus($item['speed_alliance'], '%')]);
                } elseif (isset($item['raid'])) {
                    $stats[] = self::stat('crannyReduction', [$plus($item['raid'], '%')]);
                } elseif (isset($item['hero_power'])) {
                    $stats[] = self::stat('fightingStrength', [$plus($item['hero_power'])]);
                } elseif (isset($item['powerAgainstNatars'])) {
                    $stats[] = self::stat('strengthAgainstNatars', [$plus($item['powerAgainstNatars'], '%')], 'strengthAgainstNatarsDetails');
                }
                break;
            case 4:
                if (isset($item['unitId'])) {
                    $stats[] = self::stat('weaponUnit', [$plus($item['unit_power']), (string)T('Troops', $item['unitId'] . '.title')]);
                    $stats[] = self::stat('fightingStrength', [$plus($item['hero_power'])]);
                }
                break;
            case 5:
                if (isset($item['reg'])) {
                    $stats[] = self::stat('regeneration', [$plus($item['reg'])]);
                } elseif (isset($item['speed_distance'])) {
                    $stats[] = self::stat('speedArmies', [$plus($item['speed_distance'], '%')], 'speedArmiesDetails', [self::plain($item['min_distance'])]);
                } elseif (isset($item['hero_cav_speed'])) {
                    $stats[] = self::stat('speedMounted', [$plus($item['hero_cav_speed'])]);
                }
                break;
            case 6:
                $stats[] = self::stat('speedMounted', [$plus($item['speed_horse'])]);
                break;
            case 7:
            case 8:
                $stats[] = self::stat('bandage', [self::number($item['revive'], '', '%')], 'bandageDetails');
                break;
            case 9:
                $stats[] = self::stat('cage', [], 'cageDetails');
                break;
            case 10:
                $stats[] = self::stat('scroll', [], 'scrollDetails', [self::plain($item['exp'])]);
                break;
            case 11:
                $stats[] = self::stat('ointment', [], 'ointmentDetails', [self::number($item['reg'], '', '%')]);
                break;
            case 12:
                $stats[] = self::stat('bucket', [], 'bucketDetails');
                break;
            case 13:
                $stats[] = self::stat('bookOfWisdom', [], 'bookOfWisdomDetails');
                break;
            case 14:
                $one = self::number($item['loyalty'], '', '%');
                $max = self::number(125, '', '%');
                $stats[] = self::stat('tabletOfLaw', [$one, $max], 'tabletOfLawDetails', [$one, $max]);
                break;
            case 15:
                // The reward depends on the viewing player's culture point production.
                $production = (int)DB::getInstance()->fetchScalar(
                    'SELECT COALESCE(SUM(cp), 0) FROM vdata WHERE owner=' . (int)Session::getInstance()->getPlayerId()
                );
                $stats[] = self::stat('artwork', [self::plain(Artwork::reward((int)getGameSpeed(), $production))], 'artworkDetails', [(string)intdiv(Artwork::rules((int)getGameSpeed())['cooldown'], 3600)]);
                break;
            case 16:
                if ($item['title'] !== '') {
                    $stats[] = ['text' => (string)$item['title']];
                }
                break;
        }

        if ($btype >= 7 && $btype <= 15) {
            $stats[] = self::stat('takesEffectWhenEquipped');
            $stats[] = self::stat('stackable');
            if ($btype <= 9) {
                $stats[] = self::stat('equipBeforeBattle');
            }
            if ($btype === 9) {
                $stats[] = self::stat('noFight');
            }
        }

        return $stats;
    }

    /**
     * The one-line effects the attributes page lists for equipped items.
     *
     * @return list<string>
     */
    public static function benefits(int $btype, int $type): array
    {
        $benefits = $btype === 6 ? [self::t('mountsHero')] : [];
        foreach (self::stats($btype, $type) as $stat) {
            $benefits[] = $stat['text'];
        }

        return $benefits;
    }

    /**
     * @param bool $heroAlive the bucket only revives, so it explains itself while the hero lives
     */
    public static function html(int $btype, int $type, bool $heroAlive = false): string
    {
        $item = (new HeroItems())->getHeroItemProperties($btype, $type);
        $e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $consumable = $btype >= 7 && $btype <= 15;
        $tier = $consumable ? 'consumable' : 'tier' . self::tier($btype, $type);
        $slotKey = self::SLOT_LABELS[$item['slot']] ?? null;
        $subTitle = $consumable
            ? self::t('consumable')
            : ($slotKey === null ? '' : sprintf(self::t('tier'), self::tier($btype, $type)) . ' ' . self::t($slotKey));

        $html = '<div class="heroV2"><div class="heroItemTooltip ' . $tier . ' heroItemTooltipV1">'
            . '<div class="title">' . $e((string)$item['name']) . '</div>'
            . '<div class="subTitle">' . $e($subTitle) . '</div>'
            . '<div class="separator"></div>';
        if ($btype === 12 && $heroAlive) {
            $html .= '<div class="notClickable">' . $e(self::t('notUsableWhileAlive')) . '</div>';
        }
        foreach (self::stats($btype, $type) as $stat) {
            $html .= '<div class="stat"><div>' . $e($stat['text']) . '</div>'
                . (isset($stat['details']) ? '<div class="details">' . $e($stat['details']) . '</div>' : '')
                . '</div>';
        }

        return $html . '</div></div>';
    }
}
