<?php

use Core\Helper\WebService;
use Model\RegisterModel;

class FakeUserCtrl
{
    public function __construct()
    {
        $result = false;
        if (!isServerFinished() && WebService::isPost()) {
            set_time_limit(0);
            ignore_user_abort(true);
            $m = new RegisterModel();
            $names = $_POST['names'] ?? null;
            $result = $m->addFakeUser(is_string($names) ? strip_tags($names) : '');
        }
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load(['result' => $result], 'tpl/fakeUser.tpl')->getAsString());
    }
}
