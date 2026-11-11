<?php

namespace Game;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Helper\Mailer;
use Core\Helper\WebService;
use Core\Session;
use resources\View\PHPBatchView;

class EmailVerification
{
    /**
     * @return mixed
     */
    public static function isEmailVerified()
    {
        $session = Session::getInstance();
        return $session->isEmailVerified();
    }
    /**
     * @param mixed $uid
     */
    public static function resendVerificationEmail($uid): bool
    {
        $m = new \Model\EmailVerification();
        Session::getInstance()->setValidationStatus(FALSE);
        $row = $m->getActivationProgressByUid($uid);
        $cache = Caching::getInstance();
        $key = self::fixKey("tries" . $row['id']);
        if ($cache->exists($key) && $cache->get($key) >= 2) {
            Session::getInstance()->setValidationStatus(false);
            return false;
        }
        $cache->set($key, $cache->exists($key) ? ($cache->get($key) + 1) : 1, 3600);
        $params = [
            'playerName' => Session::getInstance()->getName(),
            'email' => $row['email'],
            'verification_url' => WebService::get_base_url() . 'verify-url.php?code=' . sprintf("%s-%s", $row['id'], $row['activationCode']),
            'verification_code' => sprintf("%s-%s", $row['id'], $row['activationCode']),
        ];
        Mailer::sendEmail($row['email'], T("EVerify", "Email verification"), PHPBatchView::render("verify/email", $params));
        return true;
    }

    /**
     * @param string $key
     */
    private static function fixKey($key): string
    {
        return Session::getInstance()->getPlayerId() . ":TEV:" . $key;
    }

    /**
     * @param int $uid
     * @param string $email
     */
    public static function sendVerificationEmail($uid, $email): void
    {
        $db = DB::getInstance();
        $code = get_random_string(mt_rand(18, 28));
        $db->run("INSERT INTO `activation_progress` (`uid`, `email`, `activationCode`, `time`) VALUES (?, ?, ?, ?)", [(int)$uid, (string)$email, $code, time()]);
        $id = $db->lastInsertId();
        $params = [
            'playerName' => Session::getInstance()->getName(),
            'email' => $email,
            'verification_url' => WebService::get_base_url() . 'verify-url.php?code=' . sprintf("%s-%s", $id, $code),
            'verification_code' => sprintf("%s-%s", $id, $code),
        ];
        Mailer::sendEmail($email, T("EVerify", "Email verification"), PHPBatchView::render("verify/email", $params));
    }
}
