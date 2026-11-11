<?php

declare(strict_types=1);

namespace Core;

use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\Notification;

class Voucher
{
    /**
     * Create a voucher; returns its id, or 0 when it could not be stored.
     */
    public static function addVoucher(string $email, int $goldNum, string $reason = 'gift', string $player = ''): int
    {
        if ($email === '') {
            return 0;
        }
        $voucherCode = generate_guid();
        GlobalDB::getInstance()->run(
            'INSERT INTO paymentVoucher (gold, email, worldId, reason, player, voucherCode, time) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$goldNum, $email, self::worldKey(), $reason, $player, $voucherCode, time()]
        );
        $id = (int) GlobalDB::getInstance()->lastInsertId();
        if ($id === 0) {
            Notification::notify('Voucher add failed', "Failed to add voucher. Email: $email, Gold: $goldNum, reason: $reason.");
        }

        return $id;
    }

    public static function isValidCode(string $code): bool
    {
        // codes are produced by generate_guid(): a UUID
        return preg_match('/^[0-9a-fA-F]{8}(-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}$/', $code) === 1;
    }

    /**
     * The matching unused voucher rows (an empty result for a malformed code).
     */
    public static function getVoucherWithCode(string $code, string $email, bool $used = false): \mysqli_result
    {
        $db = GlobalDB::getInstance();
        $empty = static fn (): \mysqli_result => $db->query('SELECT * FROM paymentVoucher WHERE 1=0');
        if (!self::isValidCode($code)) {
            return $empty();
        }
        $usedFlag = $used ? 1 : 0;
        $statement = getCustom('allowVouchersOnlyOnSameEmail')
            ? $db->run("SELECT * FROM paymentVoucher WHERE voucherCode=? AND email=? AND used=$usedFlag", [$code, $email])
            : $db->run("SELECT * FROM paymentVoucher WHERE voucherCode=? AND used=$usedFlag", [$code]);
        $result = $statement ? $statement->get_result() : false;

        return $result instanceof \mysqli_result ? $result : $empty();
    }

    /**
     * Redeem a voucher for a player; returns the gold it was worth, or false.
     */
    public static function useVoucher(int $id, string $voucherCode, string $email, int $uid, string $username): int|false
    {
        if (!self::isValidCode($voucherCode)) {
            return false;
        }
        $globalDB = GlobalDB::getInstance();
        $escapedEmail = $globalDB->real_escape_string($email);
        $result = $globalDB->query("SELECT * FROM paymentVoucher WHERE id=$id AND used=0 AND voucherCode='$voucherCode'");
        if (!$result || !$result->num_rows) {
            return false;
        }
        $voucher = $result->fetch_assoc();
        if (getCustom('allowVouchersOnlyOnSameEmail') && strcasecmp($email, (string) $voucher['email']) !== 0) {
            return false;
        }
        $wid = self::worldKey();
        $player = $globalDB->real_escape_string(sprintf('[%s] %s', $uid, $username));
        $globalDB->query('UPDATE paymentVoucher SET used=1, usedTime=' . time() . " ,usedWorldId='$wid', usedEmail='$escapedEmail', usedPlayer='$player' WHERE id=$id");
        if (!$globalDB->affectedRows()) {
            return false;
        }
        $gold = (int) $voucher['gold'];
        $db = DB::getInstance();
        $db->query("UPDATE users SET bought_gold=bought_gold+$gold WHERE id=$uid");
        $secureId = sprintf('[%s]-%s', $id, $voucherCode);
        $db->query('INSERT INTO buyGoldMessages (uid, gold, type, trackingCode) VALUES (' . (int) Session::getInstance()->getPlayerId() . ", $gold, 2, '$secureId')");

        return $gold;
    }

    private static function worldKey(): string
    {
        return sprintf('%s-%s', Config::getProperty('settings', 'worldUniqueId'), Config::getProperty('settings', 'worldId'));
    }
}
