<?php

declare(strict_types=1);

use Core\Helper\WebService;
use Core\Session;

/**
 * Legacy detector URL. The page now lives in the control panel shell.
 */
if (!Session::getInstance()->isAdmin()) {
    WebService::redirect('/admin.php');
}

WebService::redirect('/admin.php?action=detector');
