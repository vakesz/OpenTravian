<?php

declare(strict_types=1);

namespace Core\Helper;

use Core\Caching\Caching;
use Core\Session;

class BadWordsFilter
{
    /** @var list<string> */
    private array $badWords;

    public function __construct()
    {
        $cache = Caching::getInstance();
        $cached = $cache->get('badWordsFilterCache');
        $this->badWords = is_array($cached) && $cached !== [] ? array_values(array_map('strval', $cached)) : [];
        if ($this->badWords === []) {
            $raw = (string) file_get_contents(FILTERING_PATH . "badWordsFilter.txt");
            // Empty filter files explode to ['']; strpos($name, '') is always 0 and
            // falsely rejects every rename/message. Keep only non-empty tokens.
            $this->badWords = array_values(array_filter(
                array_map('trim', explode(',', $raw)),
                static fn (string $token): bool => $token !== ''
            ));
            $cache->set("badWordsFilterCache", $this->badWords, 2 * 86400);
        }
    }

    public function containsBadWords(string $string, string $mainString): bool
    {
        return $this->censorString($string, $mainString);
    }

    public function censorString(string $string, string $mainString): bool
    {
        if ($string === '') {
            return true;
        }
        foreach ($this->badWords as $link) {
            if ($link === '') {
                continue;
            }
            if (str_contains($string, $link)) {
                Notification::notify("bad words detected!",
                    "Player " . Session::getInstance()->getName() . " is trying to use bad words $link.");
                return false;
            }
        }
        return true;
    }
}