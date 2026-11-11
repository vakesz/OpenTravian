<?php

namespace Controller\Ajax\infobox;

use Controller\Ajax\AjaxBase;
use Model\InfoBoxModel;
use resources\View\PHPBatchView;

final class read extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to read infobox messages.', 403);
            return;
        }

        $infoIds = $_POST['infoIds'] ?? [];
        if (!is_array($infoIds) || count($infoIds) > 50) {
            $this->error('Invalid infobox message list.');
            return;
        }

        $ids = [];
        foreach ($infoIds as $infoId) {
            if (!is_scalar($infoId) || !preg_match('/^[1-9][0-9]{0,9}$/', (string)$infoId)) {
                $this->error('Invalid infobox message list.');
                return;
            }
            $ids[(int)$infoId] = true;
        }

        $uid = (int)$this->session->getPlayerId();
        $model = new InfoBoxModel();
        if ($ids !== []) {
            $model->setInfoboxItemsRead($uid, array_keys($ids));
        }

        $view = new PHPBatchView('ajax/infoboxCounter');
        $view->vars['unreadCount'] = $model->getUnreadInfoBoxCount($uid);
        $view->vars['total'] = count($model->getMyInfoBox($uid)) + count($model->getPublicInfoBox());
        $view->vars['title'] = T('inGame', 'total_messages') . ': ' . $view->vars['total'];
        if ($view->vars['unreadCount']) {
            $view->vars['title'] .= '<br />' . T('inGame', 'unReadMessages') . ': ' . $view->vars['unreadCount'];
        }
        $this->response['messageCounterContent'] = $view->output();
    }
}
