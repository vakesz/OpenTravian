<?php

declare(strict_types=1);

function failRetiredDebugEndpointsContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$mainInclude = file_get_contents(dirname(__DIR__, 2) . '/game/include/mainInclude.php');
if ($mainInclude === false) {
    failRetiredDebugEndpointsContract('The front controller must be readable.');
}

foreach ([
    "\$_GET['showTime']",
    "\$page == 'myInfo'",
    'ResourcesHelper::updateVillageUpkeep(-1',
] as $retiredContract) {
    if (str_contains($mainInclude, $retiredContract)) {
        failRetiredDebugEndpointsContract('Retired debug endpoint remains reachable: ' . $retiredContract);
    }
}

if (!str_contains($mainInclude, "isset(\$_GET['summary'])")
    || !str_contains($mainInclude, "isset(\$_GET['errors']) && Session::getInstance()->isAdmin()")) {
    failRetiredDebugEndpointsContract('The separately retained operations endpoints must not be removed incidentally.');
}

echo "Retired debug endpoints contract passed.\n";
