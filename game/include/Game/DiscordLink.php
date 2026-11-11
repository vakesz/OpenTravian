<?php

declare(strict_types=1);

namespace Game;

use Core\Config;

/**
 * The world's optional Discord invite link. The operator sets it at install
 * (setup wizard, advanced mode) or later in the admin panel ("Info and
 * settings"); it lives in config.discordUrl. While it is blank no Discord link
 * is rendered anywhere (footer, mobile menu, lobby menu, help dialog).
 */
final class DiscordLink
{
    public const MAX_LENGTH = 255;

    /** https only; blank means "not configured". Shared with the installer override. */
    public const PATTERN = WorldOverrides::DISCORD_URL_PATTERN;

    private function __construct()
    {
    }

    /** True when $url is blank or an acceptable https URL. */
    public static function isValid(string $url): bool
    {
        return strlen($url) <= self::MAX_LENGTH && preg_match(self::PATTERN, $url) === 1;
    }

    /** The configured URL, or '' when unset (or when a stored value is no longer valid). */
    public static function url(): string
    {
        $config = Config::getInstance();
        $url = trim((string)($config->dynamic->discordUrl ?? ''));
        return self::isValid($url) ? $url : '';
    }

    public static function isConfigured(): bool
    {
        return self::url() !== '';
    }
}
