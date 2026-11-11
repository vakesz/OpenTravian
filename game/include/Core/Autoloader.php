<?php

declare(strict_types=1);

namespace Core;

class Autoloader
{
    public static function register(bool $prepend = false): void
    {
        spl_autoload_register([self::class, 'autoload'], true, $prepend);
    }

    public static function autoload(string $className): void
    {
        if (class_exists($className)) {
            return;
        }
        $fullpath = self::getFullPath($className);
        if (is_file($fullpath)) {
            require $fullpath;
        }
    }

    public static function getFullPath(string $className): string
    {
        return ROOT_PATH . 'include' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    }
}
Autoloader::register();
ErrorHandler::getInstance();
