<?php

declare(strict_types=1);

namespace Core;

use Core\Security\Password;
use Database\DB;
use PDO;

/**
 * @phpstan-type LoginRow array<string, mixed>
 * @phpstan-type LoginResult array{type: int, row: array<string, mixed>}
 */
class LoginOperator
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return LoginResult type: 0 unknown, 1 user, 2 pending activation of this world, 3 activation of the account database
     */
    public function findLogin(int $worldUniqueId, string $name): array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, sit1Uid, sit2Uid, password, last_owner_login_time FROM users WHERE (name=:name OR email=:email) LIMIT 1');
        $stmt->bindValue('name', $name, PDO::PARAM_STR);
        $stmt->bindValue('email', $name, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return ['type' => 1, 'row' => $row];
        }

        $stmt = $this->db->prepare('SELECT id, token, password FROM activation WHERE (name=:name OR email=:email) LIMIT 1');
        $stmt->bindValue('name', $name, PDO::PARAM_STR);
        $stmt->bindValue('email', $name, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return ['type' => 2, 'row' => $row];
        }

        $activation = $this->getActivation($worldUniqueId, $name);
        if ($activation !== false) {
            return ['type' => 3, 'row' => $activation];
        }

        return ['type' => 0, 'row' => []];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getActivation(int $worldId, string $name): array|false
    {
        $stmt = DB::getInstance()->prepare('SELECT id, name, password FROM activation WHERE worldId=:worldId AND used=0 AND (name=:name OR email=:email)');
        $stmt->bindValue('worldId', $worldId, PDO::PARAM_STR);
        $stmt->bindValue('name', $name, PDO::PARAM_STR);
        $stmt->bindValue('email', $name, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insertHandshake(int $uid, bool $isSitter = false): string
    {
        $handshake = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare('INSERT INTO login_handshake (uid, token, time, isSitter) VALUES (:uid, :token, :time, :isSitter)');
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue('token', hash('sha256', $handshake), PDO::PARAM_STR);
        $stmt->bindValue('time', time(), PDO::PARAM_INT);
        $stmt->bindValue('isSitter', $isSitter, PDO::PARAM_BOOL);
        $stmt->execute();

        return $handshake;
    }

    /**
     * @param LoginResult $result
     * @return int 0: success login, 1 and 2: success through sitter 1 or 2, 3: password wrong
     */
    public function checkLogin(string $password, array $result): int
    {
        $storedHash = (string) $result['row']['password'];
        if (Password::verify($password, $storedHash)) {
            $table = match ($result['type']) {
                1 => 'users',
                2 => 'activation',
                default => null,
            };
            $this->upgradeLegacyHash($table, (int) $result['row']['id'], $password, $storedHash);

            return 0;
        }
        if ($result['type'] === 1) {
            foreach ([1 => 'sit1Uid', 2 => 'sit2Uid'] as $code => $col) {
                if (!$result['row'][$col]) {
                    continue;
                }
                $sitterHash = $this->getSitterPassword((int) $result['row'][$col]);
                if (Password::verify($password, $sitterHash)) {
                    $this->upgradeLegacyHash('users', (int) $result['row'][$col], $password, $sitterHash);

                    return $code;
                }
            }
        }

        return 3;
    }

    /**
     * Rehash a successfully verified password stored as legacy unsalted SHA-1 (or an outdated algorithm).
     */
    private function upgradeLegacyHash(?string $table, int $id, string $password, string $storedHash): void
    {
        if ($table === null || !Password::needsRehash($storedHash)) {
            return;
        }
        $table = $table === 'activation' ? 'activation' : 'users';
        $stmt = $this->db->prepare("UPDATE $table SET password=:password WHERE id=:id AND password=:old");
        $stmt->bindValue('password', Password::hash($password), PDO::PARAM_STR);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->bindValue('old', $storedHash, PDO::PARAM_STR);
        $stmt->execute();
    }

    /** The stored hash of a sitter account, or '' when there is none. */
    private function getSitterPassword(int $uid): string
    {
        if ($uid === 0) {
            return '';
        }
        $stmt = $this->db->prepare('SELECT password FROM users WHERE id=:uid LIMIT 1');
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $password = $stmt->fetchColumn();

        return $password === false ? '' : (string) $password;
    }
}
