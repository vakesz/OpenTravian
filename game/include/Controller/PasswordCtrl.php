<?php

declare(strict_types=1);

namespace Controller;

use Core\Database\DB;
use Core\Security\Password;
use Core\Session;
use resources\View\OutOfGameView;

class PasswordCtrl extends OutOfGameCtrl
{
    private const TOKEN_LIFETIME = 3600;

    public function __construct()
    {
        $this->view = new OutOfGameView();
        $this->view->vars['titleInHeader'] = T("Login", "Login");
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings login passwordReset';
        $this->view->vars['contentCssClass'] = 'login';
        $request = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $npw = isset($request['npw']) && is_string($request['npw']) && ctype_digit($request['npw']) ? (int)$request['npw'] : null;
        $cpw = isset($request['cpw']) && is_string($request['cpw']) && preg_match('/^[a-f0-9]{64}$/D', $request['cpw']) === 1
            ? $request['cpw']
            : null;
        $content = '';
        if ($npw === null || $cpw === null || !$this->isTokenValid($npw, $cpw)) {
            $content = $this->renderMessage(T("Login", "PasswordFail"), 'error');
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $this->resetPassword($npw, $cpw);
        } else {
            $content = $this->renderForm($npw, $cpw, '');
        }
        $this->view->vars['content'] .= '<div id="passwordForgotten" class="passwordResetCard">'
            . '<h1 class="passwordResetHeading">' . T("Login", "PasswordForgotten?") . '</h1>'
            . $content
            . '</div>';
    }

    private function isTokenValid(int $uid, string $token): bool
    {
        $statement = DB::getInstance()->run(
            "SELECT cpw FROM newproc WHERE uid=? AND time>=? LIMIT 1",
            [$uid, time() - self::TOKEN_LIFETIME]
        );
        $find = $statement ? $statement->get_result() : false;
        if (!$find || !$find->num_rows) {
            return false;
        }
        $row = $find->fetch_assoc();

        return hash_equals((string)$row['cpw'], hash('sha256', $token));
    }

    private function resetPassword(int $uid, string $token): string
    {
        if (!Session::validateChecker()) {
            return $this->renderForm($uid, $token, T("Login", "PasswordFail"));
        }
        $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $confirm = isset($_POST['password2']) && is_string($_POST['password2']) ? $_POST['password2'] : '';
        $db = DB::getInstance();
        $user = $db->run("SELECT name FROM users WHERE id=? LIMIT 1", [$uid]);
        $userRow = $user ? $user->get_result()->fetch_assoc() : null;
        if (!$userRow) {
            return $this->renderMessage(T("Login", "PasswordFail"), 'error');
        }
        $error = Password::validate($password, (string)$userRow['name']);
        if ($error === 'passwordInsecure') {
            return $this->renderForm($uid, $token, sprintf(
                T("Login", "PasswordTooShort", "The password must be at least %d characters long."),
                Password::MIN_LENGTH
            ));
        }
        if ($error !== null) {
            return $this->renderForm($uid, $token, T("Login", "PasswordLikeName", "The password must not be the same as the player name."));
        }
        if (!hash_equals($password, $confirm)) {
            return $this->renderForm($uid, $token, T("Login", "PasswordMismatch", "The passwords do not match."));
        }
        // single use: only the request that really deletes the row may set the password
        $db->run("DELETE FROM newproc WHERE uid=? AND cpw=?", [$uid, hash('sha256', $token)]);
        if (!$db->affectedRows()) {
            return $this->renderMessage(T("Login", "PasswordFail"), 'error');
        }
        // users.password is compared with the session copy on every request, so this also ends existing sessions
        $db->run("UPDATE users SET password=? WHERE id=?", [Password::hash($password), $uid]);

        return $this->renderMessage(T("Login", "PasswordChangedSuccessfully"), 'success');
    }

    private function renderForm(int $uid, string $token, string $error): string
    {
        $html = $error === '' ? '' : $this->renderMessage($error, 'error');
        $html .= '<form class="passwordResetForm" method="post" action="/password.php">'
            . Session::getCheckerInput()
            . '<input type="hidden" name="npw" value="' . (int)$uid . '"/>'
            . '<input type="hidden" name="cpw" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '"/>'
            . '<div class="passwordResetField"><label for="passwordNew">' . T("Login", "PasswordNew", "New password") . '</label>'
            . '<input id="passwordNew" class="text" type="password" name="password" minlength="' . Password::MIN_LENGTH . '" required autocomplete="new-password"/></div>'
            . '<div class="passwordResetField"><label for="passwordConfirm">' . T("Login", "PasswordConfirm", "Repeat new password") . '</label>'
            . '<input id="passwordConfirm" class="text" type="password" name="password2" minlength="' . Password::MIN_LENGTH . '" required autocomplete="new-password"/></div>'
            . '<div class="passwordResetSubmit"><button type="submit" class="textButtonV1 green " version="textButtonV1">' . T("Login", "PasswordSet", "Set password") . '</button></div>'
            . '</form>';

        return $html;
    }

    private function renderMessage(string $message, string $type): string
    {
        return '<p class="passwordResetMessage ' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</p>';
    }
}
