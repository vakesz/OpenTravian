<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\WebService;
use Core\Session;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;
use Securimage;

use const INCLUDE_PATH;

require_once INCLUDE_PATH . 'Plugins/securimage/securimage.php';

class RecaptchaCtrl extends GameCtrl
{
    private const CAPTCHA_NAMESPACE = 'recaptcha_gate';

    public function __construct()
    {
        parent::__construct();
        // This runs after GameCtrl has established an authenticated session.
        // The image's session-stored code is therefore bound to the player who
        // must later solve it.
        if (isset($_GET['loadCaptcha'])) {
            $_GET['namespace'] = self::CAPTCHA_NAMESPACE;
            require_once INCLUDE_PATH . 'Plugins/securimage/securimage_show.php';
            exit();
        }
        $this->view = new OutOfGameView();
        $this->view->renderLoginBox = false;
        $this->view->vars['headerBar'] = true;
        $this->view->vars['showTime'] = true;
        $this->view->vars['titleInHeader'] = T("reCaptcha", "title");
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings recaptcha';
        $this->view->vars['contentCssClass'] = 'error_site recaptcha';
        $this->view->vars['containerCssClass'] = 'contentPage';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $error = '';
        if (WebService::isPost()) {
            $code = isset($_POST['captcha_code']) && is_string($_POST['captcha_code']) ? trim($_POST['captcha_code']) : '';
            $secureImage = new Securimage(['namespace' => self::CAPTCHA_NAMESPACE]);
            if (!Session::validateChecker()) {
                $error = T('Login', 'tooManyRequests');
            } elseif (!throttle_public_form('clickunblock', 5, 3600)) {
                $error = T('Login', 'tooManyRequests');
            } elseif ($code === '' || !$secureImage->check($code)) {
                $error = T('reCaptcha', 'Sorry you submitted wrong answer');
            } else {
                Session::getInstance()->setValidationStatus(true);
                WebService::redirect("/dorf1.php");
            }
        }
        $requestPath = $_SERVER['REQUEST_URI'] ?? '/dorf1.php';
        $requestPath = is_string($requestPath) ? strtok($requestPath, '?') : '/dorf1.php';
        if ($requestPath === false || $requestPath === '') {
            $requestPath = '/dorf1.php';
        }
        $this->view->vars['content'] .= PHPBatchView::render('system/recaptcha', [
            'captchaUrl' => $requestPath . '?verify&loadCaptcha&v=',
            'formAction' => $requestPath . '?verify',
            'checker' => Session::getCheckerInput(),
            'error' => $error,
        ]);
    }
}
