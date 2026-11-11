<?php

declare(strict_types=1);

namespace Core;

use Core\Database\GlobalDB;

use function generate_guid;

class PackageCode
{
    /**
     * @return list<string> the generated codes
     */
    public static function generateCode(int $packageId, int $count, bool $isGift = false): array
    {
        $codes = [];
        for ($i = 1; $i <= $count; ++$i) {
            $codes[] = self::insert($packageId, $isGift);
        }

        return $codes;
    }

    public static function getCodesByPage(int $packageId, int $page, int $pageSize, bool $isGift = false): \mysqli_result|bool
    {
        $gift = $isGift ? ' AND isGift=1' : '';
        $offset = ($page - 1) * $pageSize;

        return GlobalDB::getInstance()->query("SELECT * FROM package_codes WHERE used=0$gift AND package_id=$packageId ORDER BY id DESC LIMIT $offset, $pageSize");
    }

    /**
     * @return array<string, mixed>|false
     */
    public static function getProduct(int $id): array|false
    {
        $stmt = GlobalDB::getInstance()->query('SELECT * FROM `goldProducts` WHERE goldProductId=' . $id);

        return $stmt && $stmt->num_rows ? $stmt->fetch_assoc() : false;
    }

    private static function insert(int $packageId, bool $isGift = false): string
    {
        $db = GlobalDB::getInstance();
        $gift = $isGift ? 1 : 0;
        $code = generate_guid();
        $db->query("INSERT INTO `package_codes`(`package_id`, `code`, `isGift`) VALUES ('$packageId', '$code', '$gift')");

        return $code;
    }
}
