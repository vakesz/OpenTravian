<?php

use Core\Config;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Game\TruceDay;

class TruceCtrl
{
    public function __construct()
    {
        $config = Config::getInstance();
        $params = [];
        if (!isServerFinished() && WebService::isPost()) {
            $reasonId = is_string($_POST['truceReasonId'] ?? null) ? $_POST['truceReasonId'] : '';
            $from = is_string($_POST['truceFrom'] ?? null) ? TimezoneHelper::strtotime($_POST['truceFrom']) : false;
            $to = is_string($_POST['truceTo'] ?? null) ? TimezoneHelper::strtotime($_POST['truceTo']) : false;
            if (in_array($reasonId, array_map('strval', array_keys(T("Truce", "reasons"))), true) && $from !== false && $to !== false && $from <= $to) {
                $config->dynamic->truceFrom = $from;
                $config->dynamic->truceTo = $to;
                $config->dynamic->truceReasonId = (int)$reasonId;
                TruceDay::saveTruce($config->dynamic->truceFrom, $config->dynamic->truceTo, $config->dynamic->truceReasonId);
                $params['result'] = 'Truce settings saved.';
            } else {
                $params['result'] = 'Invalid truce settings.';
            }
        }
        $params = array_merge($params, [
            'now' => TimezoneHelper::date("Y-m-d H:i", time()),
            'truceFrom' => TimezoneHelper::date("Y-m-d H:i", $config->dynamic->truceFrom),
            'truceTo' => TimezoneHelper::date("Y-m-d H:i", $config->dynamic->truceTo),
            'truceReasonId' => $config->dynamic->truceReasonId,
        ]);
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load($params, 'tpl/truceDay.tpl')->getAsString());
    }
}