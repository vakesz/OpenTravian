<?php

declare(strict_types=1);

namespace Controller\Ajax\manual;

use Controller\Ajax\AjaxBase;
use Core\Helper\WebService;
use Game\Manual\ManualContent;

final class troop extends AjaxBase
{
    public function dispatch(): void
    {
        if (!WebService::isGet()) {
            $this->error('The manual can only be opened with a GET request.');
            return;
        }

        $unitId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($unitId === false) {
            $this->error('The requested manual entry is not available.');
            return;
        }

        try {
            $this->response['html'] = (new ManualContent())->renderUnit($unitId);
        } catch (\DomainException $exception) {
            $this->error($exception->getMessage());
        }
    }
}
