<?php

declare(strict_types=1);

namespace Game\Hero;

use function T;

/**
 * What the use dialog of an inventory consumable shows, as the reference client builds it: the item's description,
 * an optional error, the amount field with its pinned label, the impact rows and the confirm button. The labels
 * and rows are the reference's own strings (assets/i18n/reference, `hero` section).
 *
 * An ointment dialog has two variants: while the hero is hurt, ointments are used (the amount that heals the hero)
 * and the rest of the amount is equipped; at full health the amount is equipped. Equipped ointments heal the hero
 * after a battle (`BagOintment`).
 */
final class ConsumableDialog
{
    private const OINTMENT = 106;
    private const SCROLL = 107;
    private const BUCKET = 108;
    private const TABLET_OF_LAW = 109;
    private const BOOK_OF_WISDOM = 110;
    private const ARTWORK = 111;
    private const BANDAGES = [112 => 'smallBandagesEquipped', 113 => 'bandagesEquipped', 114 => 'item114_impact'];
    private const AMOUNT_LABEL = [
        self::OINTMENT => 'item106_inputLabel_healPossible',
        self::SCROLL => 'item107_inputLabel',
        self::TABLET_OF_LAW => 'item109_inputLabel',
        112 => 'item112_inputLabel',
        113 => 'item113_inputLabel',
        114 => 'item114_inputLabel',
    ];

    private function __construct()
    {
    }

    /**
     * @param array{health: int, experience: int, perScroll: int, bonus: int, loyaltyFull: bool} $context
     * @return array{description: string, confirm: string, equip: bool, healthDelta: int, input: bool, label: string, impacts: list<array<string, mixed>>, error: string|null}
     */
    public static function describe(int $btype, int $type, bool $heroAlive, int $equipped, array $context, int $amount = 1): array
    {
        $stats = HeroItemTooltip::stats($btype, $type);
        $first = $stats[0] ?? ['text' => ''];
        $healthDelta = $type === self::OINTMENT ? max(0, 100 - $context['health']) : 0;
        $equip = isset(self::BANDAGES[$type]) || ($type === self::OINTMENT && $healthDelta === 0);
        $confirm = match (true) {
            $type === self::BUCKET => 'itemConsume',
            $equip => 'itemEquip',
            default => 'itemUse',
        };
        $impacts = [];
        if (isset(self::BANDAGES[$type])) {
            $impacts[] = ['kind' => 'count', 'label' => (string)T('HeroInventory', self::BANDAGES[$type]), 'from' => $equipped];
        } elseif ($type === self::OINTMENT) {
            if ($healthDelta > 0) {
                $impacts[] = ['kind' => 'health', 'label' => (string)T('HeroInventory', 'healthAfterUsage'), 'from' => $context['health']];
            }
            if ($healthDelta === 0 || $amount > $healthDelta) {
                $impacts[] = ['kind' => 'ointments', 'label' => (string)T('HeroInventory', 'ointmentsEquipped'), 'from' => $equipped, 'healthDelta' => $healthDelta];
            }
        } elseif ($type === self::SCROLL) {
            $perScroll = $context['perScroll'];
            $bonus = $context['bonus'];
            $impacts[] = ['kind' => 'value', 'label' => (string)T('HeroInventory', 'xpByScrolls'), 'value' => $perScroll];
            $impacts[] = [
                'kind' => 'value',
                'label' => str_replace('[PERCENT]', "\u{202D}\u{202D}" . $bonus . "\u{202C}%\u{202C}", (string)T('HeroInventory', 'xpByBonus')),
                'value' => (int)round($perScroll * $bonus / 100),
            ];
            $impacts[] = [
                'kind' => 'experience',
                'label' => (string)T('HeroInventory', 'experienceAfterUseOfItem'),
                'from' => $context['experience'],
                'perItem' => (int)round($perScroll * (1 + $bonus / 100)),
            ];
        }
        $error = null;
        if ($type === self::BUCKET && $heroAlive) {
            $error = (string)T('HeroItemTooltip', 'notUsableWhileAlive');
        } elseif ($type === self::TABLET_OF_LAW && $context['loyaltyFull']) {
            $error = (string)T('HeroInventory', 'loyaltyLimitReached');
        }

        return [
            // The Bucket shows its effect line, the others the detail line of their effect.
            'description' => $type === self::BUCKET ? (string)$first['text'] : (string)($first['details'] ?? $first['text']),
            'confirm' => (string)T('HeroInventory', $confirm),
            'equip' => $equip,
            'healthDelta' => $healthDelta,
            'input' => !in_array($type, [self::BUCKET, self::BOOK_OF_WISDOM, self::ARTWORK], true),
            'label' => $type === self::OINTMENT && $equip
                ? (string)T('HeroInventory', 'item106_inputLabel_fullHealth')
                : (isset(self::AMOUNT_LABEL[$type]) ? (string)T('HeroInventory', self::AMOUNT_LABEL[$type]) : ''),
            'impacts' => $impacts,
            'error' => $error,
        ];
    }
}
