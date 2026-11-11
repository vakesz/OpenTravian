<?php

declare(strict_types=1);

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\EmailService;
use Core\LoginOperator;
use Core\LoginThrottle;
use Core\Security\Password;
use Core\Server;
use Core\WebService;
use Database\DB;
use Database\ServerDB;
use PDO;

class AuthCtrl extends ApiAbstractCtrl
{
    public function updatePassword(): void
    {
        $recoveryCode = trim($this->requireString('recoveryCode'));
        $worldId = $this->requireString('worldId');
        $uid = $this->requireInt('uid');
        $this->requireString('password');
        $server = Server::getServerByWId($worldId);
        if ($server === false) {
            $this->response['fields']['password'] = 'unknownGameWorld';
            return;
        }
        $this->response['success'] = false;
        if (preg_match('/^[a-f0-9]{64}$/D', $recoveryCode) !== 1) {
            $this->response['fields']['password'] = 'passwordWasNotUpdated';
            return;
        }
        $recovery = $this->findRecoveryByRecoveryCode($uid, (int) $server['id'], $recoveryCode);
        if ($recovery === false) {
            $this->response['fields']['password'] = 'passwordWasNotUpdated';
            return;
        }
        $password = $this->optionalString('password') ?? '';
        $serverDB = ServerDB::getInstance((string) $server['configFileLocation']);
        $name = $serverDB->prepare('SELECT name FROM users WHERE id=:id');
        $name->bindValue('id', (int) $recovery['uid'], PDO::PARAM_INT);
        $name->execute();
        $playerName = $name->fetchColumn();
        if ($playerName === false) {
            $this->response['fields']['password'] = 'passwordWasNotUpdated';
            return;
        }
        $passwordError = Password::validate($password, (string) $playerName);
        if ($passwordError !== null) {
            $this->response['fields']['password'] = $passwordError;
            return;
        }
        $db = DB::getInstance();
        $delete = $db->prepare('DELETE FROM passwordRecovery WHERE id=:id');
        $delete->bindValue('id', (int) $recovery['id'], PDO::PARAM_INT);
        $delete->execute();
        if (!$delete->rowCount()) {
            // another request used the code first
            $this->response['fields']['password'] = 'passwordWasNotUpdated';
            return;
        }
        $stmt = $serverDB->prepare('UPDATE users SET password=:password WHERE id=:id');
        $stmt->bindValue('password', Password::hash($password), PDO::PARAM_STR);
        $stmt->bindValue('id', (int) $recovery['uid'], PDO::PARAM_INT);
        $stmt->execute();
        $this->response['success'] = true;
    }

    /**
     * @return array<string, mixed>|false
     */
    private function findRecoveryByRecoveryCode(int $uid, int $wid, string $recoveryCode): array|false
    {
        $db = DB::getInstance();
        $stmt = $db->prepare('SELECT * FROM passwordRecovery WHERE uid=:uid AND wid=:wid AND recoveryCode=:recoveryCode AND created>=:minCreated');
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue('wid', $wid, PDO::PARAM_INT);
        // only the hash of the code is stored
        $stmt->bindValue('recoveryCode', hash('sha256', $recoveryCode), PDO::PARAM_STR);
        $stmt->bindValue('minCreated', time() - 3600, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function forgotGameWorld(): void
    {
        $email = $this->sanitizedEmail();
        $gameWorlds = [];
        $this->response['success'] = false;
        $db = DB::getInstance();
        $serverFindStatement = $db->select('SELECT worldId, gameWorldUrl, configFileLocation FROM gameServers WHERE finished=0 ORDER BY startTime DESC');
        while ($server = $serverFindStatement->fetch(PDO::FETCH_ASSOC)) {
            $serverDB = ServerDB::getInstance((string) $server['configFileLocation']);
            $stmt = $serverDB->prepare('SELECT name FROM users WHERE email=:email');
            $stmt->bindValue('email', $email, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->rowCount()) {
                $gameWorlds[] = [
                    'worldId' => $server['worldId'],
                    'username' => $stmt->fetchColumn(),
                    'gameWorldUrl' => $server['gameWorldUrl'],
                ];
            }
        }
        // identical answer whether or not accounts exist
        $this->response['success'] = true;
        if ($gameWorlds !== [] && LoginThrottle::allowMail('worlds', $email)) {
            EmailService::sendForgottenAccounts($email, $gameWorlds);
        }
    }

    public function forgotPassword(): void
    {
        $email = $this->sanitizedEmail();
        $gameWorldId = $this->requireInt('gameWorldId');
        $this->response['success'] = false;
        $server = Server::getServerById($gameWorldId);
        if ($server === false) {
            $this->response['fields']['email'] = 'unknownGameWorld';
            return;
        }
        $serverDB = ServerDB::getInstance((string) $server['configFileLocation']);
        $loginHelper = new LoginOperator($serverDB);
        $find = $loginHelper->findLogin((int) $server['id'], $email);
        // identical answer whether or not the account exists
        $this->response['success'] = true;
        if ($find['type'] === 1 && LoginThrottle::allowMail('recovery', $email)) {
            $uid = (int) $find['row']['id'];
            $recoveryCode = $this->addNewPassword($uid, (int) $server['id']);
            EmailService::sendPasswordForgotten($email, (int) $server['id'], (string) $server['worldId'], $uid, $recoveryCode);
        }
    }

    /** The e-mail payload member with characters that cannot occur in an address removed. */
    private function sanitizedEmail(): string
    {
        return (string) filter_var($this->requireString('email'), FILTER_SANITIZE_EMAIL);
    }

    private function addNewPassword(int $uid, int $wid): string
    {
        $db = DB::getInstance();
        $recoveryCode = bin2hex(random_bytes(32));

        $stmt = $db->prepare('DELETE FROM passwordRecovery WHERE uid=:uid AND wid=:wid');
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue('wid', $wid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $db->prepare('INSERT INTO passwordRecovery (uid, wid, recoveryCode, created) VALUES (:uid, :wid, :recoveryCode, :created)');
        $stmt->bindValue('uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue('wid', $wid, PDO::PARAM_INT);
        $stmt->bindValue('recoveryCode', hash('sha256', $recoveryCode), PDO::PARAM_STR);
        $stmt->bindValue('created', time(), PDO::PARAM_INT);
        $stmt->execute();

        return $recoveryCode;
    }

    public function login(): void
    {
        $gameWorldId = $this->requireInt('gameWorldId');
        $this->requireString('password');
        $this->requireString('usernameOrEmail');
        $usernameOrEmail = trim($this->requireString('usernameOrEmail'));
        $password = $this->optionalString('password') ?? '';
        if ($password === '') {
            $this->response['fields']['password'] = 'passwordWrong';
            return;
        }
        $server = Server::getServerById($gameWorldId);
        if ($server === false) {
            $this->response['fields']['usernameOrEmail'] = 'unknownGameWorld';
            return;
        }
        if ((int) $server['startTime'] > time()) {
            $this->response['fields']['usernameOrEmail'] = 'gameWorldNotStartedYet';
            return;
        }
        $this->response = [];
        $ip = WebService::ipAddress();
        $serverDB = ServerDB::getInstance((string) $server['configFileLocation']);
        $loginHelper = new LoginOperator($serverDB);
        $find = $loginHelper->findLogin((int) $server['id'], $usernameOrEmail);
        $accountId = isset($find['row']['id']) ? (int) $find['row']['id'] : null;
        $reservation = LoginThrottle::reserveLogin($gameWorldId, $accountId, $ip, $usernameOrEmail);
        if ($reservation === false) {
            // Same code as a wrong password, so the UI needs no new message.
            $this->response['fields']['password'] = 'passwordWrong';
            return;
        }
        if ($find['type'] === 0 || !isset($find['row']['id'])) {
            // Keep the unknown-identity path on the same adaptive-hash class.
            Password::verifyDummy($password);
            LoginThrottle::completeLogin($reservation, true);
            $this->response['fields']['password'] = 'passwordWrong';
            return;
        }
        $result = $loginHelper->checkLogin($password, $find);
        if (!LoginThrottle::completeLogin($reservation, $result === 3)) {
            $this->response['fields']['password'] = 'passwordWrong';
            return;
        }
        if ($result === 3) {
            $this->response['fields']['password'] = 'passwordWrong';
            return;
        }
        switch ($find['type']) {
            case 1:
                if ($result !== 0 && (int) $find['row']['last_owner_login_time'] <= time() - 21 * 86400) {
                    $this->response['fields']['password'] = 'accountIsInactive';
                    return;
                }
                $handshake = $loginHelper->insertHandshake((int) $find['row']['id'], $result !== 0);
                $this->response['redirect'] = $server['gameWorldUrl'] . 'login.php?detectLang&handshake=' . $handshake;
                return;
            case 2:
                $this->response['redirect'] = $server['gameWorldUrl'] . 'activate.php?detectLang&token=' . $find['row']['token'];
                return;
        }
    }
}
