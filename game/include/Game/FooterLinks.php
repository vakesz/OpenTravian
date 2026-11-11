<?php

declare(strict_types=1);

namespace Game;

/**
 * The footer link row shared by the in-game shell and the public pages: Home,
 * Discord, News, Support, then the operator's Game rules, Terms, Imprint and
 * Privacy pages. Discord and the operator pages appear only when configured.
 */
final class FooterLinks
{
    /** Link keys in display order. */
    private const ORDER = ['home', 'discord', 'news', 'support', 'rules', 'terms', 'imprint', 'privacy'];

    /** In-game footer translation key of each link. */
    private const TRANSLATION_KEYS = [
        'home' => 'Footer.HomePage',
        'discord' => 'Footer.Discord',
        'news' => 'Footer.News',
        'support' => 'Footer.Support',
        'rules' => 'Footer.Game rules',
        'terms' => 'Footer.Terms',
        'imprint' => 'Footer.Imprint',
    ];

    private function __construct()
    {
    }

    /**
     * @param array<string, string> $labels Per-key label overrides (public pages use their catalogue wording).
     * @param array<string, string> $urls   Per-key root-absolute URL overrides (home and support keep the public locale).
     * @return list<array{label: string, href: string, external: bool}>
     */
    public static function build(array $labels, array $urls, ?string $publicLocale = null): array
    {
        $links = [];
        foreach (self::ORDER as $key) {
            [$href, $external] = self::destination($key, $urls, $publicLocale);
            $label = $labels[$key] ?? self::defaultLabel($key, $publicLocale);
            if ($href !== null && $label !== '') {
                $links[] = ['label' => $label, 'href' => $href, 'external' => $external];
            }
        }

        return $links;
    }

    /**
     * The release label and, when the operator set a source repository, its link.
     *
     * @return array{label: string, href: ?string}|null
     */
    public static function version(): ?array
    {
        $label = LegalPages::versionLabel();

        return $label === null ? null : [
            'label' => $label,
            'href' => LegalPages::url(LegalPages::SOURCE) ?: null,
        ];
    }

    /** In-game wording from the Footer keys; Privacy has none, so it reuses the public homepage label. */
    private static function defaultLabel(string $key, ?string $publicLocale): string
    {
        if (isset(self::TRANSLATION_KEYS[$key])) {
            return (string) T('Global', self::TRANSLATION_KEYS[$key]);
        }

        return PublicHomepageLabels::get('Privacy Policy', $publicLocale, (string) \Core\Session::getInstance()->getLanguage());
    }

    /**
     * @param array<string, string> $urls
     * @return array{0: ?string, 1: bool} The destination (null when hidden) and whether it leaves the world.
     */
    private static function destination(string $key, array $urls, ?string $publicLocale): array
    {
        switch ($key) {
            case 'home':
                return [self::local($urls['home'] ?? null) ?? getHomepageUrl(), false];
            case 'discord':
                return [getDiscordUrl() !== '' ? getDiscordUrl() : null, true];
            case 'news':
                return [PublicHomepageLocale::append('/homepage/news', $publicLocale), false];
            case 'support':
                return [self::local($urls['support'] ?? null) ?? getSupportUrl(), false];
        }

        $href = LegalPages::href($key);
        if ($href === null || LegalPages::isExternal($key)) {
            return [$href, $href !== null];
        }

        return [PublicHomepageLocale::append($href, $publicLocale), false];
    }

    private static function local(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : null;
    }
}
