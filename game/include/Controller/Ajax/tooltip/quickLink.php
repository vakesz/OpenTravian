<?php

namespace Controller\Ajax\tooltip;

use Controller\Ajax\AjaxBase;
use Core\Session;
use Game\QuickLink as QuickLinkTooltip;

class quickLink extends AjaxBase
{
    public function dispatch(): void
    {
        if (!Session::getInstance()->isValid()) {
            return;
        }
        $type = is_string($_POST['type'] ?? null) ? $_POST['type'] : '';
        if (!QuickLinkTooltip::isKnownType($type)) {
            $this->error('Unknown quick link.');
            return;
        }
        $tooltip = QuickLinkTooltip::tooltip($type);
        $this->response['title'] = $tooltip['title'];
        $this->response['text'] = $tooltip['text'];
        $this->response['success'] = true;
    }
}
