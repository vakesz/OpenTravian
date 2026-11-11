<?php

declare(strict_types=1);

namespace Controller;

use Core\Database\DBI;
use Core\Helper\WebService;
use Game\Admin\AuditLog;
use Game\Mail\EmailKind;
use Game\Mail\EmailPreferences;
use Game\Mail\UnsubscribeToken;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;

use function T;

/**
 * /unsubscribe/<token>: the link in every mail. The signed token is the credential, so there is no session or CSRF
 * token (mail clients post one-click unsubscribes without either). A GET only asks; a POST switches off the mail's
 * own kind ("Confirm"), every kind ("All email"), or, for a one-click POST, the token's own scope.
 */
final class UnsubscribeCtrl extends OutOfGameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $token = UnsubscribeToken::verify((string) ($_GET['unsubscribeToken'] ?? ''));
        if ($token === null) {
            $this->innerRedirect('PageNotFoundCtrl');
        }
        $done = false;
        if (WebService::isPost() && throttle_public_form('unsubscribe', 30, 600)) {
            $scope = isset($_POST['all']) ? UnsubscribeToken::ALL : $token['scope'];
            EmailPreferences::unsubscribe($token['uid'], $scope);
            $name = (string) DBI::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$token['uid']], '');
            AuditLog::record('mail.unsubscribe', 'uid=' . $token['uid'], $scope, $token['uid'], $name);
            $done = true;
        }
        $kind = EmailKind::tryFrom($token['scope']);
        $title = $kind !== null ? $kind->label() : (string) T('Mail', 'All email');

        $this->view = new OutOfGameView();
        $this->configurePublicHomepageLocale();
        $this->view->vars['titleInHeader'] = $title;
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings support';
        $this->view->vars['contentCssClass'] = 'support';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['content'] = PHPBatchView::render('system/unsubscribe', [
            'title' => $title,
            'done' => $done,
            'all' => $token['scope'] === UnsubscribeToken::ALL,
            'action' => '/unsubscribe/' . UnsubscribeToken::issue($token['uid'], $token['scope']),
        ]);
    }
}
