<?php

declare(strict_types=1);

namespace Core\Helper;

use Core\Config;
use Core\Session;
use function date_default_timezone_get;
use function get_locale;
use function jalali_to_gregorian;

class TimezoneHelper
{
    private const DEFAULT_DATE_FORMAT = 'd.m.y';
    private const DEFAULT_TIME_FORMAT = 'H:i';

    public static function autoDateString(?int $time = null, bool $hasHour = false, bool $hasSecond = false, bool $relative = true): string
    {
        if ($relative) {
            $diffDays = self::getDifDays($time);
            $clock = static fn (): string => self::date(self::getTimeFormat($hasSecond), $time);
            switch (true) {
                case $diffDays === 1:
                    return T("Global", "tomorrow") . " " . $clock();
                case $diffDays === 0:
                    return T("Global", "today") . " " . $clock();
                case $diffDays === -1:
                    return T("Global", "yesterday") . " " . $clock();
                case $diffDays === -2:
                    return sprintf(T("Global", "x days past"), abs($diffDays)) . " " . $clock();
                case $diffDays > 1:
                    return sprintf(T("Global", "x days later"), $diffDays) . " " . $clock();
            }
        }
        return self::date(self::getDateFormat() . ($hasHour ? ' ' . self::getTimeFormat($hasSecond) : ''), $time);
    }

    /**
     * list/detail timestamp: `today, 20:48`, `Yesterday, 20:49`, otherwise `05.11.26, 20:03`
     * (date style from the player's timezone option); `$hasSecond` adds seconds to the time part.
     */
    public static function reportDateString(int $time, bool $relative = true, bool $hasSecond = false): string
    {
        $timeFormat = self::getTimeFormat($hasSecond);
        if ($relative) {
            $dayDifference = self::getDifDays($time);
            if ($dayDifference === 0) {
                return lcfirst(T('Global', 'today')) . ', ' . self::date($timeFormat, $time);
            }
            if ($dayDifference === -1) {
                return T('Global', 'yesterday') . ', ' . self::date($timeFormat, $time);
            }
        }

        return self::date(self::getDateFormat() . ', ' . $timeFormat, $time);
    }

    public static function getDifDays(?int $time): int
    {
        $today = new \DateTime(); // This object represents current date/time
        $today->setTime(0, 0, 0); // reset time part, to prevent partial comparison
        $match_date = new \DateTime(date('Y-m-d H:i', $time));
        $match_date->setTime(0, 0, 0); // reset time part, to prevent partial comparison
        $diff = $today->diff($match_date);
        return (int)$diff->format("%R%a");
    }

    public static function strtotime(string $format, string $dateFormat = 'Y-m-d H:i'): int
    {
        if (get_locale() == "fa-IR") {
            if ($dateFormat == 'Y-m-d H:i' || $dateFormat == 'Y-m-d H:i:s') {
                list($date, $time) = explode(" ", $format);
                $date = explode("-", $date);
                $time = explode(":", $time);
                // The Jalali calendar library is needed only by the Persian locale.
                require_once INCLUDE_PATH . 'jdf.php';
                $dateString = jalali_to_gregorian($date[0], $date[1], $date[2], '-');
                if ($dateFormat == 'Y-m-d H:i:s') {
                    return self::real_strtotime(sprintf("%s %s:%s:%s", $dateString, $time[0], $time[1], $time[2]));
                }
                return self::real_strtotime(sprintf("%s %s:%s", $dateString, $time[0], $time[1]));
            }
        }
        $date = new \DateTime($format, new \DateTimeZone(self::getTimezone()));
        return $date->getTimestamp();
    }

    public static function real_strtotime(string $format): int
    {
        $date = new \DateTime($format, new \DateTimeZone(self::getTimezone()));
        return $date->getTimestamp();
    }

    /**
     * The time-zone select's options, ordered by the UTC offset in force now (so a zone in
     * daylight saving time sits with the zones it shares an offset with today), the configured order within
     * an offset.
     *
     * @param array<string, string> $zones zone id => configured label
     * @return array<string, string> zone id => label with the current UTC prefix
     */
    public static function timezoneOptions(array $zones, ?int $timestamp = null): array
    {
        $instant = new \DateTimeImmutable('@' . ($timestamp ?? time()));
        $rows = [];
        foreach ($zones as $zoneId => $label) {
            $rows[] = [(string) $zoneId, self::timezoneOptionLabel((string) $zoneId, (string) $label, $timestamp), $instant->setTimezone(new \DateTimeZone((string) $zoneId))->getOffset()];
        }
        usort($rows, static fn (array $a, array $b): int => $a[2] <=> $b[2]);

        return array_column($rows, 1, 0);
    }

    /**
     * The time-zone select keeps its city groups stable while its UTC
     * prefix follows the IANA offset at the current instant (including DST).
     */
    public static function timezoneOptionLabel(string $timezone, string $label, ?int $timestamp = null): string
    {
        $instant = new \DateTimeImmutable('@' . ($timestamp ?? time()));
        $offset = $instant->setTimezone(new \DateTimeZone($timezone))->getOffset();
        if ($offset === 0) {
            $prefix = 'UTC';
        } else {
            $absoluteOffset = abs($offset);
            $prefix = sprintf(
                'UTC%s%02d:%02d',
                $offset < 0 ? '-' : '+',
                intdiv($absoluteOffset, 3600),
                intdiv($absoluteOffset % 3600, 60)
            );
        }

        $prefixEnd = strpos($label, ')');
        return $prefixEnd === false
            ? '(' . $prefix . ') ' . $label
            : '(' . $prefix . ')' . substr($label, $prefixEnd + 1);
    }

    /**
     * A time in another player's own time zone and date style, for mail written outside their session.
     *
     * @param string $setting the player's users.timezone value, "<zone>,<style>"
     */
    public static function formatFor(string $setting, int $timestamp): string
    {
        [$zone, $style] = array_pad(explode(',', $setting), 2, '0');
        if ($zone === 'default' || !array_key_exists($zone, Config::getInstance()->timezones)) {
            $zone = date_default_timezone_get();
        }
        $style = (int) $style;
        $date = match ($style) {
            0, 2 => 'd.m.y',
            1 => 'm.d.y',
            default => 'y.m.d',
        };
        $time = $style === 1 || $style === 2 ? 'h:i a' : 'H:i';

        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone($zone))->format($date . ' ' . $time);
    }

    public static function getTimezone(): string
    {
        $session = Session::getInstance();
        if (!$session->isValid()) {
            return date_default_timezone_get();
        }
        $timezone = (string)($session->getTimezone()[0] ?? 'default');
        if ($timezone === 'default' || !array_key_exists($timezone, Config::getInstance()->timezones)) {
            return date_default_timezone_get();
        }
        return $timezone;
    }

    public static function date(string $format, ?int $timestamp = null, bool $real = false): string
    {
        $date = new \DateTime('now', new \DateTimeZone(date_default_timezone_get()));
        if ($timestamp !== NULL) {
            $date->setTimestamp($timestamp);
        }
        $date->setTimezone(new \DateTimeZone(self::getTimezone()));
        if (!$real && get_locale() == "fa-IR") {
            $timestamp ??= time();
            if (str_contains($format, 'M')) {
                $format = str_replace('M', 'F', $format);
                $format = str_replace('.', ' ', $format);
            }
            require_once INCLUDE_PATH . 'jdf.php';
            return (string) jdate($format, $timestamp, null, self::getTimezone());
        }
        return $date->format($format);
    }

    public static function getDateFormat(): string
    {
        $session = Session::getInstance();
        if (!$session->isValid()) {
            return self::DEFAULT_DATE_FORMAT;
        }

        return match ((int) $session->getTimezone()[1]) {
            0, 2 => 'd.m.y',
            1 => 'm.d.y',
            default => 'y.m.d',
        };
    }

    public static function getTimeFormat(bool $hasSecond = true): string
    {
        $session = Session::getInstance();
        if (!$session->isValid()) {
            $format = self::DEFAULT_TIME_FORMAT;
        } else {
            $dateStyle = (int) $session->getTimezone()[1];
            $format = $dateStyle === 1 || $dateStyle === 2 ? 'h:i' : 'H:i';
        }
        return $format . ($hasSecond ? ':s' : '');
    }

    /**
     * @return array{unit: string, time: int|float}
     */
    public static function getIntervalUnit(int|float $seconds, bool $round = true): array
    {
        if ($seconds >= 86400) {
            $unit = T("inGame", "Days");
            $seconds /= 86400;
        } elseif ($seconds >= 3600) {
            $unit = T("inGame", "Hours");
            $seconds /= 3600;
        } elseif ($seconds >= 60) {
            $unit = T("inGame", "Minutes");
            $seconds /= 60;
        } else {
            $unit = T("inGame", "Seconds");
        }
        if ($round) {
            $seconds = round($seconds, 1);
        }
        return ['unit' => $unit, 'time' => $seconds];
    }

    public static function autoDate(?int $time = null, bool $hasHour = false, bool $hasSecond = false): string
    {
        return self::date(self::getDateFormat() . ($hasHour ? ', ' . self::getTimeFormat($hasSecond) : ''), $time);
    }
}
