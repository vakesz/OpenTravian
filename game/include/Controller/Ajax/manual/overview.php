<?php

declare(strict_types=1);

namespace Controller\Ajax\manual;

use Controller\Ajax\AjaxBase;
use Core\Helper\WebService;
use Game\Manual\ManualContent;

final class overview extends AjaxBase
{
    public function dispatch(): void
    {
        if (!WebService::isGet()) {
            $this->error('The manual can only be opened with a GET request.');
            return;
        }

        $this->response['html'] = (new ManualContent())->renderOverview();
    }
}
