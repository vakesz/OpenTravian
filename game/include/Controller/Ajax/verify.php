<?php

namespace Controller\Ajax;

use Game\EmailVerification;
use Game\SmtpConfig;
use Model\EmailVerification as EmailVerificationModel;

/**
 * cmd=verify, action=newProgress: start an email verification for the logged in player.
 * Business failures answer HTTP 200 with verifyResult=false and verifyError=<EVerify.errors key>.
 */
class verify extends AjaxBase
{
    public function dispatch(): void
    {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action !== 'newProgress' && $action !== 'verify') {
            $this->error('Invalid action.');
            return;
        }
        $uid = (int)$this->session->getPlayerId();
        if ($uid <= 2) {
            $this->error('Invalid session.', 403);
            return;
        }
        if (EmailVerification::isEmailVerified()) {
            $this->fail('alreadyVerified');
            return;
        }
        if ($action === 'verify') {
            $this->verifyCode($uid);
            return;
        }
        if (!SmtpConfig::isConfigured()) {
            $this->fail('deliveryNotConfigured');
            return;
        }
        $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
        if ($email === '') {
            $this->fail('emptyEmail');
            return;
        }
        if (strlen($email) > 99 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('invalidEmail');
            return;
        }
        $model = new EmailVerificationModel();
        if ($model->isVerificationInProgress($uid)) {
            $this->fail('alreadyInProgress');
            return;
        }
        if (!throttle_public_form('verify', 5, 3600) || !throttle_public_form('verify-uid-' . $uid, 3, 3600)) {
            $this->fail('tooManyResends');
            return;
        }
        if ($model->emailExists($email)) {
            $this->fail('emailAlreadyExists');
            return;
        }
        if ($model->emailBlackListed($email)) {
            $this->fail('emailBlacklisted');
            return;
        }
        EmailVerification::sendVerificationEmail($uid, $email);
        $this->response['verifyResult'] = true;
    }

    /**
     * @param int $uid
     */
    private function verifyCode($uid): void
    {
        $code = isset($_POST['code']) && is_string($_POST['code']) ? trim($_POST['code']) : '';
        if ($code === '') {
            $this->fail('emptyCode');
            return;
        }
        if (!preg_match('/^[A-Za-z0-9-]{1,100}$/', $code)) {
            $this->fail('invalidCode');
            return;
        }
        $model = new EmailVerificationModel();
        $row = $model->getActivationProgressByUid($uid);
        if (!$row) {
            $this->fail('noVerificationInProgress');
            return;
        }
        if (!throttle_public_form('verifycode-uid-' . $uid, 5, 3600)) {
            $this->fail('tooManyResends');
            return;
        }
        // accepted forms: the bare code, or "<progressId>-<code>" as printed in the email
        $matches = hash_equals((string)$row['activationCode'], $code)
            || hash_equals(sprintf('%s-%s', $row['id'], $row['activationCode']), $code);
        if (!$matches) {
            $this->fail('invalidCode');
            return;
        }
        if ($model->emailExists($row['email'])) {
            $this->fail('emailAlreadyExists');
            return;
        }
        if ($model->emailBlackListed($row['email'])) {
            $this->fail('emailBlacklisted');
            return;
        }
        $model->removeVerificationById($row['id']);
        $model->setEmailAsVerified($uid, $row['email']);
        $this->response['verifyResult'] = true;
    }

    /**
     * @param string $key
     */
    private function fail($key): void
    {
        $this->response['verifyResult'] = false;
        $this->response['verifyError'] = $key;
    }
}
