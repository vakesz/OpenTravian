<?php

declare(strict_types=1);

namespace Game;

use Core\Database\DB;

/**
 * The operator's footer pages: Game rules, Terms, Imprint and Privacy policy,
 * plus the optional source-repository link. Each page is Markdown written in
 * the admin panel and served at its own local route; an https URL overrides
 * the local page, and a page with neither stays out of every footer.
 */
final class LegalPages
{
    public const RULES = 'rules';
    public const TERMS = 'terms';
    public const IMPRINT = 'imprint';
    public const PRIVACY = 'privacy';
    /** The source-repository link has no page body, only a URL. */
    public const SOURCE = 'source';

    public const PAGES = [self::RULES, self::TERMS, self::IMPRINT, self::PRIVACY];
    public const MAX_BODY_LENGTH = 100000;

    /** @var array<string, array{url: string, hasBody: bool}>|null */
    private static ?array $summary = null;

    private function __construct()
    {
    }

    public static function isPage(string $key): bool
    {
        return in_array($key, self::PAGES, true);
    }

    /** True when $url is blank or an acceptable https URL. */
    public static function isValidUrl(string $url): bool
    {
        return DiscordLink::isValid($url);
    }

    /** The footer destination: the https override, the local route, or null when hidden. */
    public static function href(string $key): ?string
    {
        $entry = self::summary()[$key] ?? null;
        if ($entry === null) {
            return null;
        }
        if ($entry['url'] !== '') {
            return $entry['url'];
        }

        return $entry['hasBody'] && self::isPage($key) ? '/' . $key : null;
    }

    /** True when the local page at /{key} has content. */
    public static function hasBody(string $key): bool
    {
        return (self::summary()[$key]['hasBody'] ?? false) && self::isPage($key);
    }

    /** True when the footer link leaves the local world. */
    public static function isExternal(string $key): bool
    {
        return (self::summary()[$key]['url'] ?? '') !== '';
    }

    /** The stored Markdown of a page, '' when unset. */
    public static function body(string $key): string
    {
        if (!self::isPage($key)) {
            return '';
        }
        $result = DB::getInstance()->run('SELECT body FROM legal_pages WHERE page_key = ?', [$key]);
        $row = $result ? $result->get_result()->fetch_row() : null;

        return is_array($row) ? (string) $row[0] : '';
    }

    /** The stored https override of a page or the source link, '' when unset. */
    public static function url(string $key): string
    {
        return self::summary()[$key]['url'] ?? '';
    }

    /** The release label such as v1.2.3, or null when no version is known. */
    public static function versionLabel(): ?string
    {
        $candidates = [getenv('TRAVIAN_VERSION') ?: '', self::versionFile()];
        foreach ($candidates as $candidate) {
            if (preg_match('/^v?(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)$/', trim($candidate), $match) === 1) {
                return 'v' . $match[1];
            }
        }

        return null;
    }

    /** Admin save; the caller validates with isPage()/isValidUrl() and the length limit first. */
    public static function save(string $key, string $body, string $url): void
    {
        DB::getInstance()->run(
            'INSERT INTO legal_pages (page_key, body, url) VALUES (?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE body = VALUES(body), url = VALUES(url)',
            [$key, $body, $url],
        );
        self::$summary = null;
    }

    private static function versionFile(): string
    {
        $path = dirname(__DIR__, 3) . '/VERSION';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /** @return array<string, array{url: string, hasBody: bool}> */
    private static function summary(): array
    {
        if (self::$summary !== null) {
            return self::$summary;
        }
        self::$summary = [];
        // A world installed before this table existed simply shows no legal links.
        try {
            $result = DB::getInstance()->query("SELECT page_key, url, body <> '' AS has_body FROM legal_pages");
            while ($result && ($row = $result->fetch_assoc())) {
                $url = trim((string) $row['url']);
                self::$summary[(string) $row['page_key']] = [
                    'url' => self::isValidUrl($url) ? $url : '',
                    'hasBody' => (bool) $row['has_body'],
                ];
            }
        } catch (\Throwable) {
            self::$summary = [];
        }

        return self::$summary;
    }
}
