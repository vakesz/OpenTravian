<?php

declare(strict_types=1);

namespace Core;
class Config
{
    private static ?object $_self = null;

    public static function &getInstance(): object
    {
        if (!is_object(self::$_self)) {
            self::$_self = include INCLUDE_PATH . 'config.php';
        }
        return self::$_self;
    }
    public static function getAdvancedProperty(string $property): mixed
    {
        return self::getProperty("settings", "advanced", $property);
    }
    /**
     * Walk the configuration object along the given property names.
     */
    public static function getProperty(string ...$path): mixed
    {
        $config = self::getInstance();
        foreach ($path as $property) {
            $config = $config->{$property};
        }

        return $config;
    }
}