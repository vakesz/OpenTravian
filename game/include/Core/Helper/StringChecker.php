<?php

declare(strict_types=1);

namespace Core\Helper;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Session;
use Model\InfoBoxModel;

class StringChecker
{
    private const MAX_TRIES = 6;
    private const MAX_TRIES_URL = 12;

    public static function isValidName(string $text): bool
    {
        $string = self::clearString($text);
        if ($string === '') {
            return true;
        }
        $badWords = (new BadWordsFilter())->containsBadWords($string, $text);
        $urls = self::checkUrls($string, $text);
        self::checkForBan($badWords, $urls);

        return $badWords && $urls;
    }

    private static function checkForBan(bool $badWords, bool $urls): void
    {
        if ($badWords && $urls) return;
        if (!Session::getInstance()->banned()) {
            $spam_count = (int) SessionVar::getVariable('spamCount', 0, 600);
            SessionVar::setVariable('spamCount', ++$spam_count);
            $maxTries = !$urls ? self::MAX_TRIES : self::MAX_TRIES_URL;
            if ($spam_count >= intdiv($maxTries, 2)) {
                Session::getInstance()->setValidationStatus(FALSE);
            }
            if ($spam_count >= $maxTries) {
                $uid = (int) Session::getInstance()->getPlayerId();
                $reason = !$urls ? 'Spam' : 'Bad name';
                $end = time() + 15 * 60;
                $db = DB::getInstance();
                $exists = $db->fetchScalar("SELECT COUNT(id) FROM banQueue WHERE uid=$uid");
                if (!$exists) {
                    $db->query("UPDATE users SET access=0 WHERE id=$uid");
                    if ($db->affectedRows()) {
                        $db->query("INSERT INTO `banQueue`(`uid`, `reason`, `time`, `end`) VALUES ($uid, '$reason', " . time() . ", $end)");
                        $db->query("INSERT INTO `banHistory`(`uid`, `reason`, `time`, `end`) VALUES ($uid, '$reason', " . time() . ", $end)");
                        $db->query("DELETE FROM multiaccount_users WHERE uid=$uid");
                        (new InfoBoxModel())->addInfo($uid, 0, 14, '', time(), time() + 365 * 86400);
                        Notification::notify("Spam blocked",
                            "A player was banned because he reached maximum spam limit.");
                    }
                }
            }
        }
    }

    public static function isValidMessage(string $text): bool
    {
        $string = self::clearString($text);
        if ($string === '') {
            return true;
        }
        $badWords = (new BadWordsFilter())->containsBadWords($string, $text);
        $urls = self::checkUrls($string, $text);
        self::checkForBan($badWords, $urls);
        return $badWords && $urls;
    }

    public static function clearString(string $string): string
    {
        $string = str_replace([
            PHP_EOL,
            '!',
            '@',
            '#',
            '$',
            '%',
            '^',
            '&',
            '*',
            '(',
            ')',
            '_',
            '+',
            '*',
            '-',
            '|',
            '[',
            ']',
            ':',
            ';',
            '<',
            '>',
            '\"',
            ',',
            ' ',
            '€',
            '\\',
            '/'
        ],
            '',
            $string);
        $string = str_replace(str_split('¹½⅓¼⅛1¹²⅔³⅜¾⁴⅝4532⅝56⅞789ⁿ∅0@#¢£¥€₱$‰%&_—–·±+{<[]}>†‡★*”“„«»’‘‚‹›:;¡!¿?…/._,~`|♣️♠️♪♥️♦️√•Ππ÷×§¶∆←↑↓→′^″°∞≠≈©®™℅›≥»>≤«‹,'),
            '',
            $string);
        $string = (string) clean_string_from_white($string);
        $string = (string) preg_replace('/\s+/', '', $string);
        $string = str_replace('..', '.', $string);
        $string = str_replace('..', '.', $string);
        $string = strtolower($string);
        return $string;
    }

    public static function checkUrls(string $string, string $mainString): bool
    {
        $cache = Caching::getInstance();
        if (!($invalidPages = $cache->get("filteredUrlsCache"))) {
            $raw = (string) file_get_contents(FILTERING_PATH . "filteredUrls.txt");
            // Empty filter files explode to ['']; strpos($name, '') is always a hit.
            $invalidPages = array_values(array_filter(
                array_map('trim', explode(',', $raw)),
                static fn (string $token): bool => $token !== ''
            ));
            $cache->set("filteredUrlsCache", $invalidPages, 2 * 86400);
        }
        if ($string === '') {
            return true;
        }
        foreach ($invalidPages as $link) {
            $link = (string) $link;
            if ($link === '') {
                continue;
            }
            $split = explode('.', $link);
            $host = $split[0];
            $tld = $split[1] ?? '';
            $haystack = $string;
            $hit = str_contains($haystack, $link)
                || (
                    $host !== ''
                    && str_contains($haystack, $host)
                    && $tld !== ''
                    && (str_contains($haystack, $tld) || str_contains($haystack, '.' . $tld))
                );
            if ($hit) {
                Notification::notify("Spam detected!",
                    "Player " . Session::getInstance()->getName() . " is trying to spam domain $link");
                return false;
            }
        }
        return true;
    }
}