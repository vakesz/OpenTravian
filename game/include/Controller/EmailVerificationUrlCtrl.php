<?php

declare(strict_types=1);

namespace Controller;

use resources\View\OutOfGameView;
use resources\View\PHPBatchView;

class EmailVerificationUrlCtrl extends OutOfGameCtrl
{
    private \Model\EmailVerification $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new \Model\EmailVerification();
        $this->view = new OutOfGameView();
        $this->view->vars['titleInHeader'] = T("EVerify", "Email verification");
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings login emailVerification';
        $this->view->vars['contentCssClass'] = 'login';
        $view = new PHPBatchView("verify/verify-url");
        $code = $_GET['code'] ?? null;
        if (!is_string($code) || ($code = trim(strip_tags($code))) === '') {
            $view->vars['codeEmpty'] = true;
        } else {
            $codeParts = explode("-", $code);
            if (count($codeParts) !== 2 || $codeParts[0] === '' || $codeParts[1] === '') {
                $view->vars['invalidCode'] = true;
            } else {
                [$verifyId, $verifyCode] = $codeParts;
                $result = $this->model->getVerificationById((int)$verifyId);
                if ($result->num_rows) {
                    $row = $result->fetch_assoc();
                    if (!hash_equals((string)$row['activationCode'], $verifyCode)) {
                        $view->vars['invalidCode'] = true;
                    } else {
                        if ($this->model->emailExists((string)$row['email'])) {
                            $view->vars['emailExists'] = true;
                        } elseif ($this->model->emailBlackListed((string)$row['email'])) {
                            $view->vars['emailBlacklisted'] = true;
                        } else {
                            $this->model->removeVerificationById((int)$row['id']);
                            $this->model->setEmailAsVerified((int)$row['uid'], (string)$row['email']);
                            $view->vars['success'] = true;
                        }
                    }
                } else {
                    $view->vars['notFound'] = true;
                }
            }
        }
        $this->view->vars['content'] .= $view->output();
    }
}
