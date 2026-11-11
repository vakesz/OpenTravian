<?php

namespace Controller\Ajax\preferences;

use Controller\Ajax\AjaxBase;
use Core\Helper\PreferencesHelper;

class all extends AjaxBase
{
    public function dispatch(): void
    {
        $this->response = PreferencesHelper::getPreferences();
    }
}
