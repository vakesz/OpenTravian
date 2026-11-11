<?php

declare(strict_types=1);

namespace Game\Hero;

use Model\AuctionModel;

/** Inventory state returned by the hero inventory endpoints. */
final class HeroInventoryPayload
{
    /**
     * @return array{items: array<mixed>, inventorySize: int, hero: array{headUrl: string, bodyUrl: string}}
     */
    public static function build(int $uid): array
    {
        $hero = new SessionHero();

        return [
            'items' => array_merge($hero->getHeroItems(), $hero->getHeroEquipments()),
            'inventorySize' => (new AuctionModel())->getVisibleInventorySize($uid),
            'hero' => ['headUrl' => HeroImage::headUrl($uid), 'bodyUrl' => HeroImage::bodyUrl($uid)],
        ];
    }

    public static function positiveInteger(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = (string)$value;
        if ($value === '' || strlen($value) > 10 || !ctype_digit($value)) {
            return null;
        }
        $integer = (int)$value;

        return $integer > 0 ? $integer : null;
    }
}
