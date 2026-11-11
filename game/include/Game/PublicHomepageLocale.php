<?php

declare(strict_types=1);

namespace Game;

/**
 * Keeps the public start-page locale in the URL while a guest moves through
 * local public routes. It deliberately never writes the game/session locale.
 */
final class PublicHomepageLocale
{
    private const QUERY_KEY = 'homepageLocale';

    /** @var array<string, true>|null */
    private static ?array $optionIds = null;

    private function __construct()
    {
    }

    public static function fromRequest(): ?string
    {
        $candidate = $_GET[self::QUERY_KEY] ?? null;

        return is_string($candidate) ? self::normalize($candidate) : null;
    }

    public static function normalize(string $candidate): ?string
    {
        $optionIds = self::optionIds();

        return isset($optionIds[$candidate]) ? $candidate : null;
    }

    /**
     * Append a verified transient public locale to a root-absolute local URL.
     *
     * @param ?string $locale Supply a previously resolved option when a
     *                        controller has already selected its default.
     */
    public static function append(string $path, ?string $locale = null): string
    {
        $locale ??= self::fromRequest();
        if ($locale === null) {
            return $path;
        }

        $fragment = '';
        $fragmentStart = strpos($path, '#');
        if ($fragmentStart !== false) {
            $fragment = substr($path, $fragmentStart);
            $path = substr($path, 0, $fragmentStart);
        }

        return $path
            . (str_contains($path, '?') ? '&' : '?')
            . self::QUERY_KEY
            . '='
            . rawurlencode($locale)
            . $fragment;
    }

    /**
     * Root-absolute URL that opens the public landing page's registration
     * overlay. The invite code stays in the query string; only the overlay
     * marker lives in the hash.
     */
    public static function registrationUrl(?string $locale = null, string $inviteCode = ''): string
    {
        $path = '/homepage';
        if ($inviteCode !== '') {
            $path .= '?uc=' . rawurlencode($inviteCode);
        }

        return self::append($path . '#registration', $locale);
    }

    /** @return array<string, true> */
    private static function optionIds(): array
    {
        if (self::$optionIds !== null) {
            return self::$optionIds;
        }

        $catalogue = require dirname(__DIR__) . '/resources/Translation/HomepagePublicCatalog.php';
        $options = is_array($catalogue) ? ($catalogue['options'] ?? null) : null;
        if (!is_array($options)) {
            throw new \RuntimeException('Homepage public locale snapshot is invalid.');
        }

        $optionIds = [];
        foreach ($options as $option) {
            $id = is_array($option) ? ($option['id'] ?? null) : null;
            if (!is_string($id) || $id === '') {
                throw new \RuntimeException('Homepage public locale snapshot is invalid.');
            }
            $optionIds[$id] = true;
        }

        return self::$optionIds = $optionIds;
    }
}
