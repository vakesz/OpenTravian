<?php

declare(strict_types=1);

namespace Game\Hero;

/**
 * Hero item categories: the items.btype column and the first segment of the
 * T('HeroItems', '{btype}.{type}.name') keys. Source: HeroItems::getAvailableItemTypes() and
 * getHeroItemProperties() (slot per btype); names from the en-US HeroItems translations.
 * btype 1-6 are equipment slots, 7 and up go in the bag, 12 and 13 are materials.
 */
final class ItemBtype
{
    public const HELMET = 1;
    public const BODY = 2;
    public const LEFT_HAND = 3;
    /** Weapons. */
    public const RIGHT_HAND = 4;
    public const SHOES = 5;
    public const HORSE = 6;
    public const SMALL_BANDAGE = 7;
    public const BANDAGE = 8;
    public const CAGE = 9;
    public const SCROLL = 10;
    public const OINTMENT = 11;
    public const BUCKET = 12;
    public const BOOK_OF_WISDOM = 13;
    public const TABLET_OF_LAW = 14;
    public const ARTWORK = 15;
    /** Carried resources (HeroResourceInventory). */
    public const RESOURCES = 16;

    /** Last equipment btype: `$btype <= ItemBtype::LAST_EQUIPMENT`. */
    public const LAST_EQUIPMENT = self::HORSE;
    /** First bag btype: `$btype >= ItemBtype::FIRST_CONSUMABLE`. */
    public const FIRST_CONSUMABLE = self::SMALL_BANDAGE;
    /** Materials: kept in the inventory, not consumed from the bag. */
    public const MATERIALS = [self::BUCKET, self::BOOK_OF_WISDOM];
}
