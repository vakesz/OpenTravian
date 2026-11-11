<?php

declare(strict_types=1);

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\ActivateHandler;
use Core\EmailService;
use Core\LoginThrottle;
use Core\Security\Password;
use Core\Server;
use Database\DB;
use Database\ServerDB;
use Exceptions\MissingParameterException;
use PDO;

class RegisterCtrl extends ApiAbstractCtrl
{
    public function resendActivationMail(): void
    {
        $gameWorld = $this->requireInt('gameWorld');
        $rawEmail = $this->requireString('email');
        $this->response['success'] = false;
        $server = Server::getServerById($gameWorld);
        if ($server === false) {
            $this->response['fields']['email'] = 'unknownGameWorld';
            return;
        }
        $email = (string) filter_var($rawEmail, FILTER_SANITIZE_EMAIL);
        $activation = $this->getActivationByEmail((int) $server['id'], $email);
        // identical answer whether or not a pending activation exists
        $this->response['success'] = true;
        if ($activation !== false && LoginThrottle::allowMail('activation', $email)) {
            EmailService::sendActivationMail($email, (int) $activation['worldId'], (string) $server['worldId'], (string) $activation['name'], (string) $activation['activationCode']);
        }
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getActivationByEmail(int $worldId, string $email): array|false
    {
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT * FROM activation WHERE worldId=:wid AND email=:email AND used=0");
        $stmt->bindValue('wid', $worldId, PDO::PARAM_INT);
        $stmt->bindValue('email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function activate(): void
    {
        $gameWorld = $this->requireInt('gameWorld');
        $activationCode = $this->requireString('activationCode');
        $this->requireString('password');
        $this->response['success'] = false;
        $activation = $this->getActivationByActivationCode($gameWorld, $activationCode);
        if ($activation !== false) {
            $password = $this->optionalString('password') ?? '';
            $passwordError = Password::validate($password, (string) $activation['name']);
            if ($passwordError !== null) {
                $this->response['fields']['password'] = $passwordError;
                return;
            }
            $db = DB::getInstance();
            $db->query('UPDATE activation SET used=1 WHERE id=' . (int) $activation['id']);
            $server = Server::getServerById((int) $activation['worldId']);
            if ($server === false) {
                $this->response['fields']['activationCode'] = 'activationNotFound';
                return;
            }
            $serverDB = ServerDB::getInstance((string) $server['configFileLocation']);
            $token = ActivateHandler::addActivation((string) $activation['name'], $password, (string) $activation['email'], (int) $activation['refUid'], $serverDB);
            $this->response['success'] = true;
            $this->response['redirect'] = $server['gameWorldUrl'] . 'activate.php?token=' . $token;
        } else {
            $this->response['fields']['activationCode'] = 'activationNotFound';
        }
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getActivationByActivationCode(int $worldId, string $activationCode): array|false
    {
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT * FROM activation WHERE worldId=:wid AND activationCode=:activationCode AND used=0");
        $stmt->bindValue('wid', $worldId, PDO::PARAM_INT);
        $stmt->bindValue('activationCode', $activationCode, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function register(): void
    {
        $gameWorld = $this->requireInt('gameWorld');
        $this->requireString('username');
        $this->requireString('email');
        if (!isset($this->payload['termsAndConditions'])) {
            throw new MissingParameterException('termsAndConditions');
        }
        $this->response['success'] = false;
        $server = Server::getServerById($gameWorld);
        if ($server === false) {
            $this->response['fields']['username'] = 'unknownGameWorld';
            return;
        }
        if ($server['registerClosed'] == 1 || $server['finished'] == 1) {
            $this->response['fields']['username'] = 'registrationClosed';
            return;
        }
        $inviter = is_array($this->payload['inviter'] ?? null) ? $this->payload['inviter'] : [];
        $username = trim($this->optionalString('username') ?? '');
        $password = $this->optionalString('password');
        $email = trim($this->optionalString('email') ?? '');
        $registrationKey = $this->optionalString('registrationKey');
        $registrationKey = $registrationKey === null ? null : trim($registrationKey);
        $subscribeNewsletter = !empty($this->payload['subscribeNewsletter']);
        $termsAndConditions = !empty($this->payload['termsAndConditions']);
        $errors = 0;
        {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                ++$errors;
            }
            if ($email === '') {
                ++$errors;
            }
            if (strlen($email) < 5) { //5 letters.
                ++$errors;
            }
            if (strlen($email) > 90) { //5 letters.
                ++$errors;
            }
        }
        {
            if ($username !== '' && $this->isNameBlackListed($username)) {
                $this->response['fields']['username'] = 'usernameBlacklisted';
                ++$errors;
            }
        }
        {
            if ($username === '' || !preg_match('/^[\\p{L}\\p{N}][\\p{L}\\p{N} ._-]*$/u', $username)) {
                if (!isset($this->response['fields']['username'])) {
                    $this->response['fields']['username'] = 'invalidChars';
                }
                ++$errors;
            }
            if (strlen($username) < 3) {
                ++$errors;
            }
            if (strlen($username) > 15) {
                ++$errors;
            }
        }
        if ($server['activation'] == 0) {
            $passwordError = Password::validate((string) $password, $username);
            if ($passwordError !== null) {
                $this->response['fields']['password'] = $passwordError;
                ++$errors;
            }
        }
        if (!$termsAndConditions) {
            ++$errors;
        }
        $preRegister = FALSE;
        if ($server['preregistration_key_only']) {
            if ($registrationKey === null || $registrationKey === '') {
                ++$errors;
            } elseif (!$this->checkPreRegistrationKey((int) $server['id'], $registrationKey)) {
                $this->response['fields']['registrationKey'] = 'registrationCodeInvalid';
                ++$errors;
            } else {
                $preRegister = TRUE;
            }
        }
        $serverDB = ServerDB::getInstance((string) $server['configFileLocation']);
        if (!isset($this->response['fields']['username'])) {
            if ($this->doesNameExists((int) $server['id'], $serverDB, $username)) {
                $this->response['fields']['username'] = 'nameAlreadyExists';
                $errors++;
            }
        }
        if (!isset($this->response['fields']['email'])) {
            if ($this->doesEmailExists((int) $server['id'], $serverDB, $email)) {
                $this->response['fields']['email'] = 'emailAlreadyRegistered';
                $errors++;
            } else if ($this->isEmailBlackListed($email)) {
                $this->response['fields']['email'] = 'emailInvalid';
                $errors++;
            }
        }
        if ($errors) {
            return;
        }
        if ($preRegister) {
            $this->useRegistrationKey((int) $server['id'], (string) $registrationKey);
        }
        $activationCode = substr(bin2hex(random_bytes(8)), 0, 13);
        $refUid = 0;
        if (isset($inviter['gameWorldName']) && isset($inviter['uid']) && is_scalar($inviter['uid'])) {
            $refUid = (int) $inviter['uid'];
        }
        $this->response['success'] = true;
        if ($server['activation'] == 0) {
            $token = ActivateHandler::addActivation($username, (string) $password, $email, $refUid, $serverDB);
            EmailService::sendYouRegisteredOn($email, (string) $server['worldId'], $username, (string) $server['gameWorldUrl']);
            $this->response['redirect'] = $server['gameWorldUrl'] . 'activate.php?token=' . $token;
        } else {
            $db = DB::getInstance();
            $stmt = $db->prepare("INSERT INTO activation (`worldId`, `name`, `password`, `email`, `activationCode`, `newsletter`, `refUid`, `time`) VALUES (:wid, :username, :password, :email, :activationCode, :newsletter, :refUid, :time)");
            $stmt->bindValue('wid', $server['id'], PDO::PARAM_INT);
            $stmt->bindValue('username', $username, PDO::PARAM_STR);
            $stmt->bindValue('password', bin2hex(random_bytes(32)), PDO::PARAM_STR);
            $stmt->bindValue('email', $email, PDO::PARAM_STR);
            $stmt->bindValue('activationCode', $activationCode, PDO::PARAM_STR);
            $stmt->bindValue('newsletter', $subscribeNewsletter ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindValue('refUid', $refUid, PDO::PARAM_INT);
            $stmt->bindValue('time', time(), PDO::PARAM_INT);
            $stmt->execute();
            if (LoginThrottle::allowMail('activation', $email)) {
                EmailService::sendActivationMail($email, (int) $server['id'], (string) $server['worldId'], $username, $activationCode);
            }
        }
    }

    private function isNameBlackListed(string $name): bool
    {
        $invalidNames = explode(',', (string) file_get_contents(FILTERING_PATH . 'blackListedNames.txt'));
        // Reserve system-account names such as Natars, Multihunter, and Support.
        foreach ($invalidNames as $blackListed) {
            $percent = 0;
            similar_text($name, $blackListed, $percent);
            if ($percent > 78.5) {
                return true; //:|
            }
        }
        return false;
    }

    private function checkPreRegistrationKey(int $worldId, string $key): bool
    {
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT COUNT(id) FROM preregistration_keys WHERE worldId=:worldId AND pre_key=:preRegKey AND used=0");
        $stmt->bindValue('worldId', $worldId, PDO::PARAM_STR);
        $stmt->bindValue('preRegKey', $key, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    private function doesNameExists(int $activeServerId, PDO $serverDB, string $playerName): bool
    {
        {
            $stmt = DB::getInstance()->prepare("SELECT COUNT(id) FROM activation WHERE name=:name AND worldId=:worldId AND used=0");
            $stmt->bindValue("name", $playerName, PDO::PARAM_STR);
            $stmt->bindValue("worldId", $activeServerId, PDO::PARAM_INT);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() > 0) return true;
        }
        {
            $stmt = $serverDB->prepare("SELECT COUNT(id) FROM users WHERE name=:name");
            $stmt->bindValue("name", $playerName);
            $stmt->execute();
            if ((int) $stmt->fetchColumn() > 0) return true;
        }
        {
            $stmt = $serverDB->prepare("SELECT COUNT(id) FROM activation WHERE name=:name");
            $stmt->bindValue("name", $playerName);
            $stmt->execute();
            if ((int) $stmt->fetchColumn() > 0) return true;
        }
        return false;
    }

    private function doesEmailExists(int $activeServerId, PDO $serverDB, string $email): bool
    {
        $stmt = DB::getInstance()->prepare("SELECT COUNT(id) FROM activation WHERE email=:email AND worldId=:worldId AND used=0");
        $stmt->bindValue("email", $email, PDO::PARAM_STR);
        $stmt->bindValue("worldId", $activeServerId, PDO::PARAM_INT);
        $stmt->execute();
        if ((int)$stmt->fetchColumn() > 0) return true;
        {
            $stmt = $serverDB->prepare("SELECT COUNT(id) FROM activation WHERE email=:email");
            $stmt->bindValue("email", $email, PDO::PARAM_STR);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() > 0) return true;
        }
        $stmt = $serverDB->prepare("SELECT COUNT(uid) FROM changeEmail WHERE email=:email");
        $stmt->bindValue("email", $email, PDO::PARAM_STR);
        $stmt->execute();
        if ((int)$stmt->fetchColumn() > 0) return true;

        $stmt = $serverDB->prepare("SELECT COUNT(id) FROM users WHERE email=:email AND email_verified=1");
        $stmt->bindValue("email", $email, PDO::PARAM_STR);
        $stmt->execute();
        if ((int)$stmt->fetchColumn() > 0) return true;

        return false;
    }

    private function isEmailBlackListed(string $email): bool
    {
        $email = strtolower($email);
        $db = DB::getInstance();
        $stmt = $db->prepare("SELECT COUNT(id) FROM email_blacklist WHERE email=:email");
        $stmt->bindValue('email', $email, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    private function useRegistrationKey(int $worldId, string $key): void
    {
        $db = DB::getInstance();
        $stmt = $db->prepare("UPDATE preregistration_keys SET used=1 WHERE worldId=:worldId AND pre_key=:preRegKey");
        $stmt->bindValue('worldId', $worldId, PDO::PARAM_STR);
        $stmt->bindValue('preRegKey', $key, PDO::PARAM_STR);
        $stmt->execute();
    }
}
