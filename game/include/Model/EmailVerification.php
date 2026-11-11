<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Core\Database\GlobalDB;

class EmailVerification
{
    public function isVerificationInProgress(int $uid): bool
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM activation_progress WHERE uid=?', [$uid]) > 0;
    }

    public function getActivationProgressEmail(int $uid): string|false
    {
        $email = DB::getInstance()->fetchScalar('SELECT email FROM activation_progress WHERE uid=?', [$uid]);

        return $email === false ? false : (string) $email;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActivationProgressByUid(int $uid): ?array
    {
        $statement = DB::getInstance()->run('SELECT * FROM activation_progress WHERE uid=?', [$uid]);

        return $statement === false ? null : $statement->get_result()->fetch_assoc();
    }

    public function emailExists(string $email): bool
    {
        return (int) DB::getInstance()->fetchScalar('SELECT COUNT(id) FROM users WHERE email=? AND email_verified=1', [$email]) > 0;
    }

    public function emailBlackListed(string $email): bool
    {
        return (int) GlobalDB::getInstance()->fetchScalar('SELECT COUNT(id) FROM email_blacklist WHERE email=?', [$email]) > 0;
    }

    public function getVerificationById(int $id): \mysqli_result|false
    {
        $statement = DB::getInstance()->run('SELECT * FROM activation_progress WHERE id=?', [$id]);

        return $statement === false ? false : $statement->get_result();
    }

    public function removeVerificationById(int $id): bool
    {
        return DB::getInstance()->run('DELETE FROM activation_progress WHERE id=?', [$id]) !== false;
    }

    public function setEmailAsVerified(int $uid, string $email): void
    {
        DB::getInstance()->run('UPDATE users SET email=?, email_verified=1 WHERE id=?', [$email, $uid]);
        (new InfoBoxModel())->deleteInfoByType($uid, 16);
        InfoBoxModel::invalidateUserInfoBoxCache($uid);
    }
}
