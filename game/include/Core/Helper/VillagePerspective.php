<?php

declare(strict_types=1);

namespace Core\Helper;

/**
 * Remembers the village view from which an overlay page should close.
 *
 * Keeps this as session state: Reports, Map, Messages and building
 * pages return to the last visited resource or buildings village, regardless
 * of the overlay's own visual perspective.
 */
final class VillagePerspective
{
    private const SESSION_KEY = 'lastVillagePerspective';
    private const RESOURCE_VIEW = 'dorf1';
    private const BUILDINGS_VIEW = 'dorf2';

    private function __construct()
    {
    }

    public static function rememberResourceView(): void
    {
        SessionVar::setVariable(self::SESSION_KEY, self::RESOURCE_VIEW);
    }

    public static function rememberBuildingsView(): void
    {
        SessionVar::setVariable(self::SESSION_KEY, self::BUILDINGS_VIEW);
    }

    public static function closeContentHref(): string
    {
        return SessionVar::getVariable(self::SESSION_KEY, self::RESOURCE_VIEW) === self::BUILDINGS_VIEW
            ? '/dorf2.php'
            : '/dorf1.php';
    }
}
