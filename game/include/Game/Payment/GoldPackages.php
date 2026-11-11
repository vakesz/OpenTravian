<?php

declare(strict_types=1);

namespace Game\Payment;

use Core\Database\DB;

/** The operator-edited gold packages players can buy (account database, one currency). */
final class GoldPackages
{
    /**
     * Active packages in display order. While an offer runs, packages flagged for it carry 20% more gold.
     *
     * @return list<array{id:int,name:string,gold:int,priceMinor:int,image:string,bestSeller:bool,bestValue:bool}>
     */
    public static function active(DB $db, int $now): array
    {
        $offer = (int) $db->fetchScalar('SELECT offer FROM paymentConfig LIMIT 1', [], 0) >= $now;
        $result = $db->query('SELECT * FROM goldProducts WHERE isActive=1 AND goldProductPrice>0 ORDER BY goldProductId ASC');
        $packages = [];
        while ($result && ($row = $result->fetch_assoc())) {
            $packages[] = self::fromRow($row, $offer);
        }

        return $packages;
    }

    /** @return array{id:int,name:string,gold:int,priceMinor:int,image:string,bestSeller:bool,bestValue:bool}|null */
    public static function findActive(DB $db, int $id, int $now): ?array
    {
        foreach (self::active($db, $now) as $package) {
            if ($package['id'] === $id) {
                return $package;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,name:string,gold:int,priceMinor:int,image:string,bestSeller:bool,bestValue:bool}
     */
    private static function fromRow(array $row, bool $offer): array
    {
        $gold = (int) $row['goldProductGold'];

        return [
            'id' => (int) $row['goldProductId'],
            'name' => (string) $row['goldProductName'],
            'gold' => $offer && (int) $row['goldProductHasOffer'] === 1 ? (int) ceil($gold * 1.2) : $gold,
            'priceMinor' => (int) round(((float) $row['goldProductPrice']) * 100),
            'image' => (string) $row['goldProductImageName'],
            'bestSeller' => (int) $row['isBestSeller'] === 1,
            'bestValue' => (int) $row['isBestValue'] === 1,
        ];
    }
}
