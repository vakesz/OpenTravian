<?php

declare(strict_types=1);

namespace Core;

use Core\Security\Password;
use PDO;

class ActivateHandler
{
    public static function addActivation(string $name, string $password, string $email, int $refUid, PDO $serverDB): string
    {
        $token = bin2hex(random_bytes(24));
        $stmt = $serverDB->prepare('INSERT INTO activation (name, password, email, token, refUid, time) VALUES (:name, :password, :email, :token, :refUid, :time)');
        $stmt->bindValue('name', $name, PDO::PARAM_STR);
        $stmt->bindValue('password', Password::hash($password), PDO::PARAM_STR);
        $stmt->bindValue('email', $email, PDO::PARAM_STR);
        $stmt->bindValue('token', $token, PDO::PARAM_STR);
        $stmt->bindValue('refUid', $refUid, PDO::PARAM_INT);
        $stmt->bindValue('time', time(), PDO::PARAM_INT);
        $stmt->execute();

        return $token;
    }
}
