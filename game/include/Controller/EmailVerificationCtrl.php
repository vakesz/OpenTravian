<?php

namespace Controller;

use Core\Database\DB;
use Core\Session;
use Game\EmailVerification;
use resources\View\GameView;
use resources\View\PHPBatchView;

class EmailVerificationCtrl extends GameCtrl
{
    /** @var mixed */
    private $db;

    public function __construct()
    {
        parent::__construct();
        if (EmailVerification::isEmailVerified()) {
            $this->redirect("/dorf1.php");
        }
        $this->db = DB::getInstance();
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = T("EVerify", "Email verification");
        $this->view->vars['bodyCssClass'] = 'perspectiveResources';
        $this->view->vars['contentCssClass'] = 'messages';
        if (isset($_GET['cancelProgress']) && Session::validateChecker()) {
            $this->db->query("DELETE FROM activation_progress WHERE uid={$this->session->getPlayerId()}");
            $this->redirect("/verify.php");
        }
        $view = new PHPBatchView("verify/segment");
        $checker = $_GET['c'] ?? null;
        $view->vars['email_resent'] = isset($_GET['emailResent']) && Session::matchesChecker($checker);
        $view->vars['tooManyResends'] = isset($_GET['tooManyResends']) && Session::matchesChecker($checker);
        $m = new \Model\EmailVerification();
        $view->vars['activationInProgress'] = $m->isVerificationInProgress($this->session->getPlayerId());
        if ($view->vars['activationInProgress']) {
            if (isset($_GET['resendEmail']) && \Game\SmtpConfig::isConfigured() && Session::validateChecker()) {
                if (EmailVerification::resendVerificationEmail($this->session->getPlayerId())) {
                    $this->redirect("/verify.php?emailResent=1&c=" . Session::getInstance()->getChecker());
                } else {
                    $this->redirect("/verify.php?tooManyResends=1&c=" . Session::getInstance()->getChecker());
                }
            }
            $view->vars['verify_email_address'] = $m->getActivationProgressEmail($this->session->getPlayerId());
        }
        $this->view->vars['content'] .= $view->output();
    }
}
