<?php

declare(strict_types=1);

namespace Api\Ctrl;

use Api\ApiAbstractCtrl;
use Core\WebService;

class ConfigCtrl extends ApiAbstractCtrl
{
    public function loadConfig(): void
    {
        global $globalConfig;
        $lang = (string) $globalConfig['staticParameters']['default_language'];
        if ($lang === 'en') {
            $lang = 'international';
        }
        $keyValue = [
            'ir' => 'ir',
            'us' => 'international',
            'uk' => 'international',
        ];
        $ip = WebService::ipAddress();
        $countryFlag = $ip !== false && function_exists('geoip_country_code_by_name')
            ? strtolower((string) geoip_country_code_by_name($ip))
            : '';
        if (isset($keyValue[$countryFlag])) {
            $lang = $keyValue[$countryFlag];
        }
        $this->response = [
            'defaultLang' => $lang,
            'globalCssClass' => $globalConfig['staticParameters']['global_css_class'],
            'autoCheckTermsAndConditions' => true,
            'registrationRecommendedMinSecondsPast' => -3600,
            'showLoginAfterServerFinished' => true,
        ];
    }
}
