<?php

declare(strict_types=1);

use Core\WebService;
define("TEMPLATES_PATH", __DIR__ . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR);
define("FILTERING_PATH", dirname(__DIR__, 3) . '/config/filters/');
$stateDir = getenv('TRAVIAN_STATE_DIR');
$templateCachePath = $stateDir !== false && $stateDir !== ''
    ? rtrim($stateDir, '/') . '/api-template-cache'
    : TEMPLATES_PATH . 'Cache';
require __DIR__ . "/vendor/autoload.php";
spl_autoload_register(function (string $name): void {
    $location = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';
    if (is_file($location)) {
        require($location);
    } else {
        throw new Exception("Couldn't load $name.");
    }
});
require __DIR__ . "/config.php";
require __DIR__ . "/functions.php";
if (!is_dir($templateCachePath) && !mkdir($templateCachePath, 0770, true) && !is_dir($templateCachePath)) {
    throw new RuntimeException("Unable to create the API template cache directory.");
}
if (!is_writable($templateCachePath)) {
    throw new RuntimeException("The API template cache directory is not writable.");
}
$loader = new Twig\Loader\FilesystemLoader(TEMPLATES_PATH);
$twig = new Twig\Environment($loader, [
    'cache' => $templateCachePath,
    'auto_reload' => true,
]);
$function = new Twig\TwigFunction('T', function (string $t): string {
    return T($t);
});
$twig->addFunction($function);
$twig->addGlobal('WEBSITE_INDEX_URL', WebService::getIndexUrl());
$twig->addGlobal('GPACK_URL', rtrim(WebService::getIndexUrl(), '/') . '/gpack/');
