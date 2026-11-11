<?php

namespace Controller\Ajax\addressbook;

use Controller\Ajax\addressbook;
use Core\Session;
use Core\Helper\WebService;
use Model\MessageModel;
use resources\View\PHPBatchView;

class add extends addressbook
{
    public function dispatch(): void
    {
        if (WebService::isPost()) {
            $m = new MessageModel();
            $total = 20 - $m->getTotalFriendListCount(Session::getInstance()->getPlayerId());
            for ($i = 0; $i <= 19; ++$i) {
                $friend = $_POST['friend'][$i] ?? null;
                if (is_string($friend) && $friend !== '' && $total) {
                    $name = strip_tags($friend);
                    $uid = $m->getPlayerIdByName($name);
                    if ($uid !== false && $uid != Session::getInstance()->getPlayerId() && !$m->isPlayerInFriendList($uid)) {
                        $m->addFriendList(Session::getInstance()->getPlayerId(), $uid);
                        $total--;
                    }
                }
            }

            $this->response = array();
            $view = new PHPBatchView("ajax/addressbook");
            $view->vars['addressBook'] = $this->renderAddressBook();
            $this->response['title'] = T("Messages", "Addressbook");
            $this->response['html'] = $view->output();
        } elseif (!WebService::isPost()) {
            $this->response['error'] = true;
        }
    }
}
