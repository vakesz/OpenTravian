<?php

use Core\Config;
use Core\Database\GlobalDB;
use Core\Helper\Mailer;
use Core\Helper\WebService;
use Core\Session;

class PreRegistrationByEmailCtrl
{
    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        $params['error'] = null;
        $params['email'] = is_string($_REQUEST['email'] ?? null) ? filter_var($_REQUEST['email'], FILTER_SANITIZE_EMAIL) : null;
        if (WebService::isPost() && Session::validateChecker()) {
            if (is_string($params['email']) && filter_var($params['email'], FILTER_VALIDATE_EMAIL) !== FALSE) {
                $HTML = str_replace(['[EMAIL]', '[WORLD_ID]', '[GAME_WORLD_URL_REGISTRATION]', '[GAME_WORLD_URL]', '[PRE_REGISTRATION_CODE]'], [
                    $params['email'], Config::getProperty("settings", "worldId"), Config::getProperty("settings", "gameWorldUrl") . 'register', Config::getProperty("settings", "gameWorldUrl"), $this->insertPreregistrationCode()
                ], T("Global", "INVITATION_WITH_PRE_REGISTRATION_CODE_EMAIL"));
                Mailer::sendEmail($params['email'], T("Global", "INVITATION_WITH_PRE_REGISTRATION_CODE_EMAIL_SUBJECT"), $HTML);
                $params['error'] = 'Success...';
                AdminLog::getInstance()->addLog("Added a preRegistration code for email: {$params['email']}.");
            } else {
                $params['error'] = 'Invalid email!';
            }
        }
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/addPreRegistrationCodeByEmail.tpl')->getAsString());
    }

    /**
     * @return mixed
     */
    private function insertPreregistrationCode()
    {
        $db = GlobalDB::getInstance();
        $worldId = Config::getProperty("settings", "worldUniqueId");
        $pre_key = substr(sha1(time() . get_random_string(6)), 0, mt_rand(12, 16));
        $db->run('INSERT IGNORE INTO preregistration_keys (worldId, pre_key) VALUES (?, ?)', [$worldId, $pre_key]);
        if (!$db->affectedRows()) {
            return $this->insertPreregistrationCode();
        }
        return $pre_key;
    }
}
