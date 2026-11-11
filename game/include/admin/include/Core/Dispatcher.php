<?php

use Core\Config;
use Core\Database\GlobalDB;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\Admin\AdminAccounts;
use Game\Admin\AdminMenu;
use Game\Admin\AdminRoles;
use Game\Admin\AuditLog;
use Game\AdminUi;
use Game\AdminView;

require("Template.php");
require("RequestGuard.php");

class Dispatcher
{
    private static $_self;
    public         $data = ['menuGroups' => [], 'content' => null, 'handler' => 'main'];

    public static function getInstance()
    {
        if (!(self::$_self instanceof self)) {
            self::$_self = new self();
            self::$_self->init();
        }
        return self::$_self;
    }

    public function init()
    {
        $this->data['loadStartTime'] = $GLOBALS['start_time'];
        $this->data['currentTime'] = TimezoneHelper::date("H:i:s");
        $this->data['infoSide'] = null;
        $this->data['gameWorldUrl'] = Config::getProperty("settings", "gameWorldUrl");
        $session = Session::getInstance();
        $handler = is_string($_REQUEST['action'] ?? null) ? $_REQUEST['action'] : 'main';
        if ($handler == 'logout' && $session->isValid() && $session->isAdmin()) {
            if (!Session::matchesLogoutToken($_GET['c'] ?? null)) {
                WebService::redirect('/admin.php');
            }
            AdminLog::getInstance()->addLog('Signed out.', 'sign-out');
            $session->logout();
            $handler = 'adminLogin';
        }
        if (!$session->isValid() || !$session->isAdmin()) {
            // An authenticated player can arrive through a stale admin link.
            // Keep that session in-game instead of making the public lobby look
            // like a logout; guests still land on the normal login route.
            WebService::redirect($session->isValid() ? '/dorf1.php' : '/');
        }
        if ($handler == 'adminLogin') {
            WebService::redirect('/');
        }
        // Simple or Advanced: a remembered display choice (?view=), never an access rule.
        $view = AdminView::resolve($_COOKIE, $_GET);
        if ($view['remember'] !== null) {
            AdminView::remember($view['remember']);
            WebService::redirect(AdminView::withoutParameter((string) ($_SERVER['REQUEST_URI'] ?? '/admin.php')));
        }
        $this->data['view'] = $view['mode'];
        if (!$this->handlerExists($handler)) {
            $handler = 'notFound';
        }
        $config = Config::getInstance();

        if (WebService::isPost() && isset($_POST['toggleInterruption']) && Session::matchesChecker($_POST[Session::getCheckerName()] ?? null)) {
            if (isset($_SESSION[WebService::fixSessionPrefix('allow_interruption')]))
                unset($_SESSION[WebService::fixSessionPrefix('allow_interruption')]);
            else {
                $_SESSION[WebService::fixSessionPrefix('allow_interruption')] = 1;
            }
            WebService::redirect('admin.php');
        }
        $config->custom->allowInterruptionInGame = isset($_SESSION[WebService::fixSessionPrefix('allow_interruption')]);
        $handlerName = $handler;
        $adminUid = (int) $session->getAdminUid();
        $role = AdminAccounts::role($adminUid);
        $this->data['role'] = $role;
        $blocked = null;
        if (AdminAccounts::mustChangePassword($adminUid) && !in_array(strtolower($handlerName), ['changepassword', 'logout'], true)) {
            // An account with a temporary password sets its own password before anything else.
            WebService::redirect('/admin.php?action=changePassword');
        }
        if (!AdminRoles::allows($role, $handlerName)) {
            AuditLog::record('access-denied', 'action=' . substr(preg_replace('/[^A-Za-z0-9]/', '', $handlerName) ?? '', 0, 40), 'Role ' . (string) $role);
            $blocked = AdminUi::page('Not allowed', '', AdminUi::notice('Your role (' . AdminRoles::label((string) $role) . ') cannot open this page. Ask an owner.', 'error'));
        } else {
            $blocked = AdminRequestGuard::intercept($handlerName);
        }
        if ($blocked !== null) {
            $this->appendContent($blocked);
        } else {
            // Every state-changing request (a POST that passed the CSRF guard, including a confirmed
            // state-changing GET) is recorded before the controller runs, so an action that
            // redirects or exits is still on the trail. Only field names and identifying
            // parameters are stored, never values.
            AdminLog::setAction($handlerName);
            if (WebService::isPost()) {
                AuditLog::record($handlerName, AuditLog::targetFromRequest($_GET, $_POST), AuditLog::fieldNames($_POST, Session::getCheckerName()));
            }
            require($this->getHandleFileLocation($handler));
            $handler = $this->fixHandlerName($handler);
            new $handler();
        }
        // Every page opens with a title, whether or not its controller prints one.
        $title = AdminMenu::titleFor((string) $handlerName);
        if ($title !== null && stripos((string) $this->data['content'], '<h1') === false) {
            $this->data['content'] = AdminUi::page($title, AdminMenu::leadFor((string) $handlerName), '') . $this->data['content'];
        }
        $this->data['handler'] = $handlerName;
        $this->buildMenu($session);
    }

    /**
     * Admin navigation, grouped for the framed sidebar (Game\Admin\AdminMenu): the layout
     * renders each group with the Gpack contentNavi pattern and marks the row for the current action.
     */
    private function buildMenu(Session $session): void
    {
        $handler = (string) ($this->data['handler'] ?? 'main');
        $this->data['menuGroups'] = AdminMenu::groups([
            'staff' => (int) $session->getAdminUid() === 2,
            'role' => $this->data['role'] ?? null,
            'gameWorldUrl' => (string) $this->data['gameWorldUrl'],
            'preregistration' => (bool) GlobalDB::getInstance()->fetchScalar(
                "SELECT preregistration_key_only FROM gameServers WHERE id=" . getWorldUniqueId()
            ),
            'logoutToken' => Session::getLogoutToken(),
        ], ($this->data['view'] ?? 'simple') === 'advanced', $handler === 'main' ? 'admin.php' : 'admin.php?action=' . $handler);
    }

    private function handlerExists($handler)
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9]*$/', (string)$handler) === 1 && is_file($this->getHandleFileLocation($handler));
    }

    private function getHandleFileLocation($handler)
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "Controllers" . DIRECTORY_SEPARATOR . $this->fixHandlerName($handler) . ".php";
    }

    private function fixHandlerName($handler)
    {
        return ucfirst($handler) . 'Ctrl';
    }

    public function appendContent($content)
    {
        $this->data['content'] .= $content;
    }

    public function appendInfoSide($content)
    {
        $this->data['infoSide'] .= $content;
    }
}
