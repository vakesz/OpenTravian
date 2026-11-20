<?php

declare(strict_types=1);

namespace Game\Hero;

/**
 * items.type ids of the single-type consumables and carried resources (Gpack `.item.item{type}`).
 * Equipment tiers (1-105, 115-178) are catalogue ranges and have no constants.
 * Source: HeroItems::getAvailableItemTypes(), HeroResourceInventory::ITEM_TYPES, en-US HeroItems.
 */
final class ItemType
{
    public const OINTMENT = 106;
    public const SCROLL = 107;
    public const BUCKET = 108;
    public const TABLET_OF_LAW = 109;
    public const BOOK_OF_WISDOM = 110;
    public const ARTWORK = 111;
    public const SMALL_BANDAGE = 112;
    public const BANDAGE = 113;
    public const CAGE = 114;
    public const RESOURCE_WOOD = 145;
    public const RESOURCE_CLAY = 146;
    public const RESOURCE_IRON = 147;
    public const RESOURCE_CROP = 148;
}
