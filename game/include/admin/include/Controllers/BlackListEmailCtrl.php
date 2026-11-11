<?php

use Core\Helper\WebService;
use Core\Session;

class BlackListEmailCtrl
{
    public function __construct()
    {
        $params = [
            'emails' => is_string($_POST['emails'] ?? null) ? trim($_POST['emails']) : null
        ];
        $globalDB = \Core\Database\GlobalDB::getInstance();
        if(!isServerFinished() && WebService::isPost() && Session::validateChecker()){
            if(!empty($params['emails'])){
                foreach (preg_split('/\R/', $params['emails']) ?: [] as $email) {
                    $email = trim(strtolower($email));
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $globalDB->run('INSERT IGNORE INTO `email_blacklist` (`email`, `time`) VALUES (?, ?)', [$email, time()]);
                    }
                }
                $params['error'] = 'Emails were blacklisted.';
            } else {
                $params['error'] = 'no emails to blacklist!';
            }
        } else {
            if (!isServerFinished() && is_string($_GET['del'] ?? null) && ctype_digit($_GET['del']) && Session::validateChecker()) {
                $globalDB->run('DELETE FROM `email_blacklist` WHERE id=?', [(int)$_GET['del']]);
            }
        }
        $params['content'] = $globalDB->query("SELECT * FROM email_blacklist");
        $params['total'] = $params['content']->num_rows;
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load($params, 'tpl/blacklistedEmails.tpl')->getAsString());
    }
}