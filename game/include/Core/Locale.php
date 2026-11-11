<?php

namespace Core;

use Core\Caching\Caching;
use Core\Helper\Notification;

use function get_locale;
use function logError;

/**
 * Runtime translation catalogue.
 *
 * A locale is resolved as en-US, then the locale's hand-maintained catalogue
 * (when one exists), then the generated reference overlay
 * (Translation/generated/<locale>.php, maintained by hand).
 * Because en-US is always the base layer, a key a locale does not translate
 * renders its English text; only a key missing from en-US itself is an error.
 */
final class Locale
{
    public const DEFAULT_LOCALE = 'en-US';

    /**
     * Unit words that quest rewards need before the catalogue is available.
     * Locales without an entry use the English words; "hours" is taken from
     * the reference bundles by the generator (Translation/locales.php).
     * @var array<string, array<string, string>>
     */
    public static $translation_preloaded = [
        'fa-IR' => ['days' => 'روز', 'hours' => 'ساعت', 'minutes' => 'دقیقه'],
        'en-US' => ['days' => 'day(s)', 'hours' => 'hour(s)', 'minutes' => 'min(s)'],
        'ar-AE' => ['days' => 'الأيام', 'hours' => 'ساعات', 'minutes' => 'الدقائق'],
        'el-GR' => ['days' => 'ημέρα(ες)', 'hours' => 'ώρα(ες)', 'minutes' => 'λεπτό(α)'],
    ];
    /** @var \Core\Locale|null */
    private static $_self;
    /** @var mixed */
    public $Locale = [];

    public function __construct()
    {
        $this->populate();
    }

    /**
     * @return array{days: string, hours: string, minutes: string}
     */
    public static function units(string $locale): array
    {
        $units = self::$translation_preloaded[self::DEFAULT_LOCALE];
        if (isset(self::$translation_preloaded[$locale])) {
            return self::$translation_preloaded[$locale];
        }
        $manifest = self::manifest()[$locale]['units'] ?? [];

        return array_replace($units, is_array($manifest) ? $manifest : []);
    }

    /**
     * Installed locales: locale code => display data (Translation/locales.php).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function manifest(): array
    {
        static $manifest = null;
        if ($manifest === null) {
            $manifest = require LOCALE_PATH . 'locales.php';
        }

        return $manifest;
    }

    /**
     * The merged catalogue for one locale, without caching or session state.
     *
     * @return array<string, mixed>
     */
    public static function catalog(string $locale): array
    {
        $catalog = self::loadFile(LOCALE_PATH . self::DEFAULT_LOCALE . '.php');
        if ($locale === self::DEFAULT_LOCALE) {
            return $catalog;
        }
        foreach (self::overlayFiles($locale) as $file) {
            $catalog = self::merge($catalog, self::loadFile($file));
        }

        return $catalog;
    }

    private function populate(): void
    {
        $locale = get_locale();
        if (!is_file(LOCALE_PATH . self::DEFAULT_LOCALE . '.php')) {
            throw new \RuntimeException('The default translation catalogue is missing.');
        }
        if ($locale !== self::DEFAULT_LOCALE && !isset(self::manifest()[$locale])) {
            // A locale outside the manifest is never loaded; render English.
            Notification::notify('Locale not found!', $locale);
            $locale = self::DEFAULT_LOCALE;
        }
        $files = array_merge([LOCALE_PATH . self::DEFAULT_LOCALE . '.php'], self::overlayFiles($locale));
        $signature = implode(',', array_map(static fn(string $file): int => (int)filemtime($file), $files));
        $cache = Caching::getInstance();
        $cachedLocale = $cache->get("Language:$locale");
        if (!is_array($cachedLocale)
            || !isset($cachedLocale['signature'], $cachedLocale['catalog'])
            || $cachedLocale['signature'] !== $signature) {
            $this->Locale = self::catalog($locale);
            $cache->set("Language:$locale", ['signature' => $signature, 'catalog' => $this->Locale], 2 * 3600);
            return;
        }
        $this->Locale = $cachedLocale['catalog'];
    }

    /**
     * @return list<string>
     */
    private static function overlayFiles(string $locale): array
    {
        if ($locale === self::DEFAULT_LOCALE || preg_match('/^[a-z]{2}-[A-Z]{2}$/', $locale) !== 1) {
            return [];
        }
        $files = [];
        foreach ([LOCALE_PATH . $locale . '.php', LOCALE_PATH . 'generated/' . $locale . '.php'] as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Hand catalogues assign into the global $Definition and return it, so it
     * is reset before every file to keep layers independent.
     *
     * @return array<string, mixed>
     */
    private static function loadFile(string $file): array
    {
        global $Definition;
        $Definition = [];
        // The cache signature reads the file's current mtime, so the content must be
        // at least as new: opcache may otherwise serve bytecode up to revalidate_freq
        // seconds old and the stale catalogue would be cached under the new signature.
        // Without timestamp validation (production) files change only with the container,
        // so a forced recompile there would only cost time.
        if (function_exists('opcache_invalidate') && ini_get('opcache.validate_timestamps')) {
            opcache_invalidate($file, true);
        }
        $catalog = require $file;

        return is_array($catalog) ? $catalog : [];
    }

    /**
     * Overlay wins. Maps merge key by key; when both sides are lists (rows, option
     * lists) the overlay list replaces the English one as a whole.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])
                && !(array_is_list($value) && array_is_list($base[$key]))) {
                $base[$key] = self::merge($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param mixed $base
     * @param mixed $Code
     * @param bool $Default
     * @return mixed
     */
    public function Translate($base, $Code, $Default = false)
    {
        $self = self::getInstance();
        $usesImplicitDefault = $Default === false;
        if ($Default === false) {
            $Default = $Code;
        }
        $Code = trim($Code);
        // Shortcut, get the whole config
        if ($Code == '.') {
            return $self->Locale[$base];
        }
        $Value = $self->Locale[$base] ?? null;
        // Legacy catalogs contain player-visible literal sentence keys, some of
        // which include a full stop. Resolve those exact keys before treating a
        // dot as a nested catalog separator.
        if (is_array($Value) && array_key_exists($Code, $Value)) {
            return $Value[$Code];
        }
        $Keys = explode('.', $Code);
        $KeyCount = count($Keys);
        for ($i = 0; $i < $KeyCount; ++$i) {
            if (is_array($Value) && array_key_exists($Keys[$i], $Value)) {
                $Value = $Value[$Keys[$i]];
            } else {
                if ($usesImplicitDefault) {
                    // en-US is the base layer of every locale, so a miss here
                    // means the key is absent from the canonical catalogue.
                    logError("Translation not found: Language: " . get_locale() . " | Section: $base | Key: $Code");
                }
                return $Default;
            }
        }
        return $Value;
    }

    /**
     * @return \Core\Locale
     */
    public static function getInstance()
    {
        if (!(self::$_self instanceof self)) {
            self::$_self = new self();
        }
        return self::$_self;
    }

    public function Cleanup(): void
    {
        unset(self::getInstance()->Locale);
    }
}
