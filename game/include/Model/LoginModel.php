<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Core\Helper\Mailer;
use Core\Helper\WebService;
use Core\Security\Password;

class LoginModel
{
    public const RESET_EMAIL_DEFAULT = "Hello %s,<br>a password reset was requested for your account on game world %s.<br>Open this one-time link within one hour to choose a new password. If you did not request it, ignore this email and your password stays unchanged:<br><a href=\"%s\">%s</a>";

    private ?string $authenticatedPasswordHash = null;

    /**
     * Looks the login name up as a player (type 1) and then as a pending activation (type 2).
     * Row values stay strings, as the login flow has always seen them.
     *
     * @return array{type: int, row: array<string, string|null>}
     */
    public function findLogin(string $name): array
    {
        $name = htmlspecialchars($name, ENT_QUOTES);
        $row = $this->fetchRow(
            'SELECT id, name, email, sit1Uid, sit2Uid, password, last_owner_login_time FROM users WHERE (name=? OR email=?) LIMIT 1',
            [$name, $name]
        );
        if ($row !== null) {
            return ['type' => 1, 'row' => $row];
        }
        $row = $this->fetchRow('SELECT id, token, password FROM activation WHERE (name=? OR email=?) LIMIT 1', [$name, $name]);
        if ($row !== null) {
            return ['type' => 2, 'row' => $row];
        }

        return ['type' => 0, 'row' => []];
    }

    /**
     * @param array<string, mixed> $row users row with id, name and email
     */
    public function addNewPassword(array $row): void
    {
        $db = DB::getInstance();
        $db->run('DELETE FROM newproc WHERE uid=?', [(int) $row['id']]);
        // only the hash of the one-time token is stored; the token itself exists only in the email
        $token = bin2hex(random_bytes(32));
        $db->run(
            "INSERT INTO newproc (uid, cpw, npw, time) VALUES (?, ?, '', ?)",
            [(int) $row['id'], hash('sha256', $token), time()]
        );
        $link = WebService::get_base_url() . 'password.php?cpw=' . $token . '&npw=' . (int) $row['id'];
        $html = vsprintf(T('Login', 'pw_reset_link_email', self::RESET_EMAIL_DEFAULT), [
            $row['name'], Config::getInstance()->settings->worldId, $link, $link,
        ]);
        Mailer::sendEmail($row['email'], T('Login', 'PasswordForgotten?'), $html);
    }

    /**
     * Checks the stored session password hash of a player or one of their sitters.
     *
     * @return int 0: the player, 1: unknown player or wrong password, 2: first sitter, 3: second sitter
     */
    public function checkUserOrSitterLogin(int $uid, string $password): int
    {
        $row = $this->fetchRow('SELECT id, sit1Uid, sit2Uid, password FROM users WHERE id=? LIMIT 1', [$uid]);
        if ($row === null) {
            return 1;
        }
        if (hash_equals((string) $row['password'], $password)) {
            return 0;
        }
        if ((int) $row['sit1Uid'] && hash_equals((string) $this->getSitterPassword((int) $row['sit1Uid']), $password)) {
            return 2;
        }
        if ((int) $row['sit2Uid'] && hash_equals((string) $this->getSitterPassword((int) $row['sit2Uid']), $password)) {
            return 3;
        }

        return 1;
    }

    private function getSitterPassword(int $uid): string|false
    {
        if (!$uid) {
            return false;
        }
        $row = $this->fetchRow('SELECT password FROM users WHERE id=? LIMIT 1', [$uid]);

        return $row === null ? false : (string) $row['password'];
    }

    /**
     * @param array{type: int, row: array<string, mixed>} $result the findLogin() result
     *
     * @return int
     * 0: success login
     * 1: first sitter's password
     * 2: second sitter's password
     * 3: password wrong.
     */
    public function checkLogin(string $password, array $result): int
    {
        $row = $result['row'];
        if (Password::verify($password, $row['password'])) {
            $this->authenticatedPasswordHash = $row['password'];
            if (Password::needsRehash($row['password'])) {
                $table = $result['type'] === 1 ? 'users' : ($result['type'] === 2 ? 'activation' : null);
                if ($table !== null) {
                    $newHash = Password::hash($password);
                    DB::getInstance()->run("UPDATE $table SET password=? WHERE id=?", [$newHash, (int) $row['id']]);
                    $this->authenticatedPasswordHash = $newHash;
                }
            }

            return 0;
        }
        if ($result['type'] === 1) {
            foreach ([1 => 'sit1Uid', 2 => 'sit2Uid'] as $outcome => $column) {
                if (!$row[$column]) {
                    continue;
                }
                $sitterPassword = $this->getSitterPassword((int) $row[$column]);
                if ($sitterPassword && Password::verify($password, $sitterPassword)) {
                    $this->authenticatedPasswordHash = $this->upgradeSitterPassword((int) $row[$column], $password, $sitterPassword);

                    return $outcome;
                }
            }
        }

        return 3;
    }

    public function getAuthenticatedPasswordHash(): ?string
    {
        return $this->authenticatedPasswordHash;
    }

    private function upgradeSitterPassword(int $uid, string $password, string $storedHash): string
    {
        if (!Password::needsRehash($storedHash)) {
            return $storedHash;
        }

        $newHash = Password::hash($password);
        DB::getInstance()->run('UPDATE users SET password=? WHERE id=?', [$newHash, $uid]);

        return $newHash;
    }

    /**
     * First row of a prepared query with every value as a string (or null).
     *
     * @param list<int|string> $parameters
     * @return array<string, string|null>|null
     */
    private function fetchRow(string $query, array $parameters): ?array
    {
        $statement = DB::getInstance()->run($query, $parameters);
        $row = $statement === false ? null : $statement->get_result()->fetch_assoc();
        if (!is_array($row)) {
            return null;
        }

        return array_map(static fn (mixed $value): ?string => $value === null ? null : (string) $value, $row);
    }
}
