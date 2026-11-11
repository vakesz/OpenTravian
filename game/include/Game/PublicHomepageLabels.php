<?php

declare(strict_types=1);

namespace Game;

/**
 * Looks up one label of the public homepage catalogue for a public language
 * option. Presentation only: it never reads or writes the game/session locale.
 */
final class PublicHomepageLabels
{
    private const FALLBACK_LANGUAGE = 'en-US';

    /** @var array<string, mixed>|null */
    private static ?array $catalogue = null;

    private function __construct()
    {
    }

    /**
     * The label for a catalogue key. $optionId is a public option id such as `de-DE`;
     * without one, $gameLanguage (a game locale code) picks the catalogue when it has
     * one, otherwise English.
     */
    public static function get(string $key, ?string $optionId = null, ?string $gameLanguage = null): string
    {
        self::$catalogue ??= require dirname(__DIR__) . '/resources/Translation/HomepagePublicCatalog.php';
        $language = self::FALLBACK_LANGUAGE;
        if ($optionId === null && isset(self::$catalogue['catalogues'][$gameLanguage])) {
            $language = (string) $gameLanguage;
        }
        foreach (self::$catalogue['options'] as $option) {
            if ($optionId !== null && $option['id'] === $optionId) {
                $language = (string) $option['language'];
                break;
            }
        }

        return (string) (self::$catalogue['catalogues'][$language][$key]
            ?? self::$catalogue['catalogues'][self::FALLBACK_LANGUAGE][$key]
            ?? '');
    }
}
