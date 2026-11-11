<?php
require_once dirname(__DIR__) . "/include/env.php";

$requestPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
if ($requestPath === '/setup' || $requestPath === '/setup/') {
    require __DIR__ . '/setup.php';
    exit;
}

$connectionTemplate = is_file(CONNECTION_FILE) ? file_get_contents(CONNECTION_FILE) : false;
if ($connectionTemplate === false || strpos($connectionTemplate, '[DATABASE_HOST]') !== false) {
    header('Location: /setup/');
    exit;
}

require_once dirname(__DIR__, 2) . '/include/mainInclude.php';
