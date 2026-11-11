<?php

declare(strict_types=1);

namespace Model;

use Core\Caching\Caching;
use Core\Database\DB;

/**
 * Local alliance-banner storage (player draft, gallery proposals, alliance set).
 * Uses the shared cache so banners survive across requests without a schema migration.
 */
final class AllianceBannerModel
{
    private const TTL = 365 * 86400;

    /** @var list<int> */
    public const BASE_FORMS = [4, 6, 13, 15, 1, 7, 5, 11, 8, 10, 2, 12, 14, 3, 9];

    /** @var list<int> */
    public const PATTERNS = [2, 3, 6, 1, 13, 4, 12, 8, 9, 10, 5, 7, 11, 14];

    /** @var list<int> */
    public const BACK_SYMBOLS = [3, 12, 13, 14, 1, 6, 9, 7, 8, 2, 10, 4, 11, 5];

    /** @var list<int> */
    public const FRONT_SYMBOLS = [6, 8, 9, 10, 7, 11, 12, 13, 14, 1, 2, 3, 4, 5];

    /** @var list<int> */
    public const PATTERNS_WITH_SECONDARY = [6, 1, 13, 12, 8, 9, 10, 7, 11, 14];

    /** @var list<string> */
    public const COLOR_PALETTE = [
        '#E2786D', '#E6A314', '#F6F46E', '#A7BE6C', '#91EAE7', '#85A0E9', '#C88CDB', '#EBEBEB',
        '#A22B1E', '#BD7A0E', '#E2CE5C', '#4E9C29', '#299C8B', '#29489C', '#57299C', '#808080',
        '#82173B', '#76400D', '#928224', '#1F6A34', '#0F4655', '#131257', '#322377', '#000000',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultBanner(): array
    {
        return [
            'baseForm' => 4,
            'pattern' => 0,
            'backSymbol' => 0,
            'frontSymbol' => 0,
            'baseColor' => '#808080',
            'borderColor' => '#322377',
            'patternPrimaryColor' => '#808080',
            'patternSecondaryColor' => '#808080',
            'backSymbolColor' => '#000000',
            'frontSymbolColor' => '#000000',
        ];
    }

    /**
     * @param array<string, mixed> $banner
     * @return array<string, mixed>
     */
    public static function normalizeBanner(array $banner): array
    {
        $defaults = self::defaultBanner();
        $out = $defaults;
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $banner)) {
                continue;
            }
            $value = $banner[$key];
            if (is_int($default)) {
                $out[$key] = (int) $value;
            } elseif (is_string($default) && is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
                $out[$key] = '#' . strtoupper(substr($value, 1));
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPlayerBanner(int $uid): ?array
    {
        $raw = Caching::getInstance()->get($this->playerKey($uid));

        return is_array($raw) ? self::normalizeBanner($raw) : null;
    }

    /**
     * @param array<string, mixed> $banner
     */
    public function setPlayerBanner(int $uid, array $banner): void
    {
        Caching::getInstance()->set($this->playerKey($uid), self::normalizeBanner($banner), self::TTL);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAllianceBanner(int $aid): ?array
    {
        $raw = Caching::getInstance()->get($this->allianceKey($aid));

        return is_array($raw) ? self::normalizeBanner($raw) : null;
    }

    /**
     * @param array<string, mixed> $banner
     */
    public function setAllianceBanner(int $aid, array $banner): void
    {
        Caching::getInstance()->set($this->allianceKey($aid), self::normalizeBanner($banner), self::TTL);
    }

    /**
     * @return list<array{uid:int,playerName:string,banner:array<string,mixed>}>
     */
    public function getGallery(int $aid): array
    {
        $raw = Caching::getInstance()->get($this->galleryKey($aid));
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        $uids = [];
        foreach ($raw as $row) {
            if (!is_array($row) || !isset($row['uid'], $row['banner']) || !is_array($row['banner'])) {
                continue;
            }
            $uid = (int) $row['uid'];
            $uids[] = $uid;
            $out[] = [
                'uid' => $uid,
                'playerName' => '',
                'banner' => self::normalizeBanner($row['banner']),
            ];
        }
        if ($out === []) {
            return [];
        }
        $names = [];
        $uidList = array_values(array_unique($uids));
        if ($uidList !== []) {
            $db = DB::getInstance();
            $idCsv = implode(',', array_map('intval', $uidList));
            $result = $db->query("SELECT id, name FROM users WHERE id IN ($idCsv)");
            while ($result && ($user = $result->fetch_assoc())) {
                $names[(int) $user['id']] = (string) $user['name'];
            }
        }
        foreach ($out as $i => $row) {
            $out[$i]['playerName'] = $names[$row['uid']] ?? ('#' . $row['uid']);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $banner
     */
    public function submitToGallery(int $aid, int $uid, array $banner): void
    {
        $gallery = $this->getGallery($aid);
        $banner = self::normalizeBanner($banner);
        $replaced = false;
        foreach ($gallery as $i => $row) {
            if ($row['uid'] === $uid) {
                $gallery[$i]['banner'] = $banner;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $gallery[] = ['uid' => $uid, 'banner' => $banner];
        }
        Caching::getInstance()->set($this->galleryKey($aid), $gallery, self::TTL);
    }

    /**
     * Build a display SVG matching the reference composition.
     *
     * @param array<string, mixed> $banner
     */
    public static function renderSvg(array $banner, string $svgId = 'allianceBanner_display'): string
    {
        $banner = self::normalizeBanner($banner);
        $base = (int) $banner['baseForm'];
        $pattern = (int) $banner['pattern'];
        $back = (int) $banner['backSymbol'];
        $front = (int) $banner['frontSymbol'];
        $baseColor = htmlspecialchars((string) $banner['baseColor'], ENT_QUOTES, 'UTF-8');
        $borderColor = htmlspecialchars((string) $banner['borderColor'], ENT_QUOTES, 'UTF-8');
        $patternPrimary = htmlspecialchars((string) $banner['patternPrimaryColor'], ENT_QUOTES, 'UTF-8');
        $patternSecondary = htmlspecialchars((string) $banner['patternSecondaryColor'], ENT_QUOTES, 'UTF-8');
        $backColor = htmlspecialchars((string) $banner['backSymbolColor'], ENT_QUOTES, 'UTF-8');
        $frontColor = htmlspecialchars((string) $banner['frontSymbolColor'], ENT_QUOTES, 'UTF-8');
        $clipId = htmlspecialchars($svgId . '_clipPath', ENT_QUOTES, 'UTF-8');
        $svgIdEsc = htmlspecialchars($svgId, ENT_QUOTES, 'UTF-8');

        $parts = [];
        $parts[] = '<svg class="allianceBanner" width="300" height="300" viewBox="0 0 300 300" id="' . $svgIdEsc . '">';
        $parts[] = '<clipPath id="' . $clipId . '"><use href="#allianceBanner_baseForm' . $base . 'Path"></use></clipPath>';
        if ($back > 0) {
            $parts[] = '<svg x="40" y="40" width="220" height="220" viewBox="0 0 300 300" overflow="visible">';
            $parts[] = '<use href="#allianceBanner_backSymbol' . $back . 'Path" fill="' . $backColor . '"></use>';
            $parts[] = '</svg>';
        }
        $parts[] = '<use href="#allianceBanner_baseForm' . $base . 'Path" fill="' . $baseColor . '"></use>';
        if ($pattern > 0) {
            $parts[] = '<use clip-path="url(#' . $clipId . ')" href="#allianceBanner_pattern' . $pattern . 'Primary" fill="' . $patternPrimary . '"></use>';
            if (in_array($pattern, self::PATTERNS_WITH_SECONDARY, true)) {
                $parts[] = '<use clip-path="url(#' . $clipId . ')" href="#allianceBanner_pattern' . $pattern . 'Secondary" fill="' . $patternSecondary . '"></use>';
            }
        }
        $parts[] = '<use href="#allianceBanner_baseForm' . $base . 'Path" stroke-width="5" stroke="' . $borderColor . '" fill="transparent"></use>';
        if ($front > 0) {
            $parts[] = '<svg x="40" y="40" width="220" height="220" viewBox="0 0 300 300" overflow="visible">';
            $parts[] = '<use href="#allianceBanner_frontSymbol' . $front . 'Path" fill="' . $frontColor . '"></use>';
            $parts[] = '</svg>';
        }
        $parts[] = '</svg>';

        return implode('', $parts);
    }

    private function playerKey(int $uid): string
    {
        return 'allianceBanner:player:' . $uid;
    }

    private function allianceKey(int $aid): string
    {
        return 'allianceBanner:aid:' . $aid;
    }

    private function galleryKey(int $aid): string
    {
        return 'allianceBanner:gallery:' . $aid;
    }
}
