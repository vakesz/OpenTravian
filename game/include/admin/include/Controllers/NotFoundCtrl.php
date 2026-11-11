<?php

use Game\AdminUi;

class NotFoundCtrl
{
    public function __construct()
    {
        Dispatcher::getInstance()->appendContent(
            '<h1>Page not found</h1>'
            . AdminUi::notice('This control panel page does not exist.', 'warning')
            . '<div class="adminActions">' . AdminUi::button('Back to the control panel', 'green', ['href' => 'admin.php']) . '</div>'
        );
    }
}
