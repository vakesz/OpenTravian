<?php

declare(strict_types=1);
global $indexConfig;
global $globalConfig;
$stateDir = getenv('TRAVIAN_STATE_DIR');
$path = getenv('TRAVIAN_GLOBAL_CONFIG')
    ?: (($stateDir !== false && $stateDir !== '')
        ? rtrim($stateDir, '/') . '/globalConfig.php'
        : dirname(__DIR__, 3) . "/globalConfig.php");
if (!is_file($path)) {
    die("Global config not found.");
}
require($path);
date_default_timezone_set($globalConfig['staticParameters']['default_timezone']);
