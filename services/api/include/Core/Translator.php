<?php

declare(strict_types=1);

namespace Core;

use Exceptions\ErrorException;

class Translator
{
    public static string $locale = 'en-US';
    /** @var array<string, array{hrefLang: string, direction: string}> */
    private static array $locales = [
        'en-US' => [
            'hrefLang' => 'international',
            'direction' => 'ltr',
        ],
        'fa-IR' => [
            'hrefLang' => 'ir',
            'direction' => 'rtl',
        ],
        'ar-AE' => [
            'hrefLang' => 'ae',
            'direction' => 'rtl',
        ],
        'el-GR' => [
            'hrefLang' => 'gr',
            'direction' => 'ltr',
        ],
    ];
    /** @var array<string, string> */
    private static array $translation = [];

    public static function translate(string $name): string
    {
        return self::$translation[$name] ?? $name;
    }

    public static function getDirection(): string
    {
        return self::$locales[self::$locale]['direction'];
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function getHrefLang(): string
    {
        return self::$locales[self::$locale]['hrefLang'];
    }

    public static function setLanguage(string $locale, bool $restoreDefault = false): void
    {
        $parts = explode('-', $locale);
        if (count($parts) !== 2) {
            throw new ErrorException("Invalid locale \"$locale\"");
        }
        [$language, $country] = $parts;
        $locale = sprintf('%s-%s', strtolower($language), strtoupper($country));
        $loc = dirname(__DIR__) . '/locale/' . $locale . '.php';
        if (!is_file($loc)) {
            if (!$restoreDefault) {
                throw new ErrorException("Locale $locale not found.");
            }
            $locale = self::$locale;
            $loc = dirname(__DIR__) . '/locale/' . $locale . '.php';
        }
        self::$locale = $locale;
        self::$translation = require $loc;
    }
}
