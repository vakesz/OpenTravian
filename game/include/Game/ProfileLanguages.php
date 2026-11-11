<?php

declare(strict_types=1);

namespace Game;

/**
 * The profile editor has its own locale list: these are languages a player
 * speaks, not the much smaller set of languages installed for the game UI.
 * The labels and flag aliases are the profile language list.
 */
final class ProfileLanguages
{
    /** @var array<string,array{flag:string,language:string,country:string}> */
    private const DEFINITIONS = [
        'ar-EG' => ['flag' => 'eg', 'language' => 'العربية', 'country' => 'مصر'],
        'ar-SY' => ['flag' => 'arabia', 'language' => 'العربية', 'country' => 'عالمي'],
        'ar-SA' => ['flag' => 'sa', 'language' => 'العربية', 'country' => 'السعودية'],
        'ar-AE' => ['flag' => 'ae', 'language' => 'العربية', 'country' => 'الإمارات العربية'],
        'bs-BA' => ['flag' => 'ba', 'language' => 'Bosanski', 'country' => 'Bosna i Hercegovina'],
        'bg-BG' => ['flag' => 'bg', 'language' => 'Български', 'country' => 'България'],
        'zh-CN' => ['flag' => 'china', 'language' => '中文', 'country' => '中国'],
        'zh-HK' => ['flag' => 'hk', 'language' => '中文', 'country' => '香港'],
        'zh-TW' => ['flag' => 'tw', 'language' => '中文', 'country' => '台灣'],
        'hr-HR' => ['flag' => 'hr', 'language' => 'Hrvatski', 'country' => 'Hrvatska'],
        'cs-CZ' => ['flag' => 'cz', 'language' => 'Čeština', 'country' => 'Česká republika'],
        'da-DK' => ['flag' => 'dk', 'language' => 'Dansk', 'country' => 'Danmark'],
        'nl-BE' => ['flag' => 'be', 'language' => 'Nederlands', 'country' => 'België'],
        'nl-NL' => ['flag' => 'nl', 'language' => 'Nederlands', 'country' => 'Nederland'],
        'en-AU' => ['flag' => 'au', 'language' => 'English', 'country' => 'Australia'],
        'international' => ['flag' => 'international', 'language' => 'English', 'country' => 'International'],
        'en-NZ' => ['flag' => 'nz', 'language' => 'English', 'country' => 'New Zealand'],
        'en-GB' => ['flag' => 'uk', 'language' => 'English', 'country' => 'United Kingdom'],
        'en-US' => ['flag' => 'us', 'language' => 'English', 'country' => 'United States'],
        'et-EE' => ['flag' => 'ee', 'language' => 'Eesti', 'country' => 'Eesti'],
        'fi-FI' => ['flag' => 'fi', 'language' => 'Suomi', 'country' => 'Suomi'],
        'fr-BE' => ['flag' => 'be', 'language' => 'Français', 'country' => 'la Belgique'],
        'fr-FR' => ['flag' => 'fr', 'language' => 'Français', 'country' => 'France'],
        'de-DE' => ['flag' => 'de', 'language' => 'Deutsch', 'country' => 'Deutschland'],
        'el-GR' => ['flag' => 'gr', 'language' => 'Ελληνικά', 'country' => 'Ελλάδα'],
        'he-IL' => ['flag' => 'il', 'language' => 'עברית', 'country' => 'ישראל'],
        'hu-HU' => ['flag' => 'hu', 'language' => 'Magyar', 'country' => 'Magyarország'],
        'id-ID' => ['flag' => 'id', 'language' => 'Bahasa Indonesia', 'country' => 'Indonesia'],
        'it-IT' => ['flag' => 'it', 'language' => 'Italiano', 'country' => 'Italia'],
        'ja-JP' => ['flag' => 'jp', 'language' => '日本語', 'country' => '日本'],
        'lv-LV' => ['flag' => 'lv', 'language' => 'Latviešu', 'country' => 'Latvija'],
        'lt-LT' => ['flag' => 'lt', 'language' => 'Lietuvių', 'country' => 'Lietuva'],
        'ms-MY' => ['flag' => 'my', 'language' => 'Bahasa Melayu', 'country' => 'Malaysia'],
        'no-NO' => ['flag' => 'no', 'language' => 'Norsk', 'country' => 'Norge'],
        'pl-PL' => ['flag' => 'pl', 'language' => 'Polski', 'country' => 'Polska'],
        'pt-BR' => ['flag' => 'br', 'language' => 'Português', 'country' => 'Brasil'],
        'pt-PT' => ['flag' => 'pt', 'language' => 'Português', 'country' => 'Portugal'],
        'ro-RO' => ['flag' => 'ro', 'language' => 'Română', 'country' => 'România'],
        'ru-RU' => ['flag' => 'ru', 'language' => 'Русский', 'country' => 'Россия'],
        'sr-RS' => ['flag' => 'rs', 'language' => 'Српски', 'country' => 'Србија'],
        'sk-SK' => ['flag' => 'sk', 'language' => 'Slovenčina', 'country' => 'Slovenská republika'],
        'sl-SI' => ['flag' => 'si', 'language' => 'Slovenščina', 'country' => 'Slovenija'],
        'es-AR' => ['flag' => 'ar', 'language' => 'Español', 'country' => 'Argentina'],
        'es-CL' => ['flag' => 'cl', 'language' => 'Español', 'country' => 'Chile'],
        'es-MX' => ['flag' => 'mx', 'language' => 'Español', 'country' => 'México'],
        'es-ES' => ['flag' => 'es', 'language' => 'Español', 'country' => 'España'],
        'sv-SE' => ['flag' => 'se', 'language' => 'Svenska', 'country' => 'Sverige'],
        'th-TH' => ['flag' => 'th', 'language' => 'ภาษาไทย', 'country' => 'ประเทศไทย'],
        'tr-TR' => ['flag' => 'tr', 'language' => 'Türkçe', 'country' => 'Türkiye'],
        'uk-UA' => ['flag' => 'ua', 'language' => 'Українська', 'country' => 'Україна'],
        'vi-VN' => ['flag' => 'vn', 'language' => 'Tiếng Việt', 'country' => 'Việt Nam'],
    ];

    /** @return array<string,array{flag:string,language:string,country:string}> */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @param mixed $languages
     * @return list<string>
     */
    public static function normalize($languages, string $fallback): array
    {
        $normalized = [];
        if (is_array($languages)) {
            foreach ($languages as $language) {
                if (is_string($language) && isset(self::DEFINITIONS[$language]) && !in_array($language, $normalized, true)) {
                    $normalized[] = $language;
                }
            }
        }

        if ($normalized === []) {
            $normalized[] = isset(self::DEFINITIONS[$fallback]) ? $fallback : 'en-US';
        }

        return array_slice($normalized, 0, 4);
    }

    /** @return list<string> */
    public static function fromStorage(string $stored, string $fallback): array
    {
        $decoded = json_decode($stored, true);
        return self::normalize($decoded, $fallback);
    }

    /** @param list<string> $selected */
    public static function pickerHtml(array $selected): string
    {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $labels = '';
        foreach (self::DEFINITIONS as $code => $definition) {
            if (in_array($code, $selected, true)) {
                continue;
            }
            $name = $definition['language'] . ' (' . $definition['country'] . ')';
            $labels .= '<label data-name="' . $escape($name) . '" data-language="' . $escape($code) . '">'
                . '<input type="radio" name="languages">'
                . '<i class="flag ' . $escape($definition['flag']) . '" title="' . $escape($name) . '"></i>'
                . '<span>' . $escape($definition['language']) . ' (<span>' . $escape($definition['country']) . '</span>)</span>'
                . '</label>';
        }

        return '<div id="dialogLanguagesList">'
            . '<h1>Select a language</h1>'
            . '<input type="text" autocomplete="off" placeholder="Search for your language or country">'
            . '<div class="languagesGroup"></div>'
            . '<div class="languagesGroup allLanguages">' . $labels . '</div>'
            . '<div class="buttonContainer"><button type="button" class="textButtonV2 buttonFramed rectangle withText green"><div>Add</div></button></div>'
            . '</div>';
    }
}
