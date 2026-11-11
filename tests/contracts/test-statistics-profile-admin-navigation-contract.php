<?php

declare(strict_types=1);

function failStatisticsProfileAdminNavigationContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$statistics = file_get_contents($root . '/game/include/Controller/StatisticsCtrl.php');
$profile = file_get_contents($root . '/game/include/Controller/ProfileCtrl.php');
$profileTemplate = file_get_contents($root . '/game/include/resources/Templates/profile/Profile.php');

if ($statistics === false || $profile === false || $profileTemplate === false) {
    failStatisticsProfileAdminNavigationContract('Unable to read Statistics and Profile navigation sources.');
}

foreach ([
    'href="/admin.php?action=editPlayer&uid=',
    "window.location.href=\\'/admin.php?action=editAlliance&aid=",
] as $required) {
    if (!str_contains($statistics, $required)) {
        failStatisticsProfileAdminNavigationContract(
            "Statistics administrator navigation must be root-absolute: missing {$required}."
        );
    }
}

if (!str_contains($profileTemplate, 'href="/admin.php?action=editPlayer&uid=')) {
    failStatisticsProfileAdminNavigationContract(
        'Profile administrator player navigation must be root-absolute.'
    );
}

foreach ([$statistics, $profile, $profileTemplate] as $source) {
    if (preg_match('/(?:href|window\\.location\\.href)\\s*=\\s*["\\\']admin\\.php\\?/', $source) === 1) {
        failStatisticsProfileAdminNavigationContract(
            'Statistics and Profile must not emit a relative administrator destination under <base href="/">.'
        );
    }
}

echo "Statistics and Profile administrator navigation contract passed.\n";
