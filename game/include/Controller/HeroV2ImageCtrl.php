<?php

declare(strict_types=1);

namespace Controller;

use Game\Hero\HeroImage;

/**
 * Serves /heroV2/{head,body}/<hash>.<uid>.1.png (see HeroImage). nginx maps
 * the pretty URL onto this controller as kind/hash/uid query parameters.
 */
class HeroV2ImageCtrl extends AnyCtrl
{
    public function __construct()
    {
        $kind = (string)($_GET['kind'] ?? '');
        $hash = (string)($_GET['hash'] ?? '');
        $uid = filter_var($_GET['uid'] ?? null, FILTER_VALIDATE_INT);
        $png = $uid !== false && $uid !== null && $uid > 0 ? HeroImage::png($uid, $kind) : null;
        if ($png === null) {
            http_response_code(404);
            exit;
        }

        // The hash only versions the URL. A stale one still gets the current
        // picture, but must not be cached as if it were permanent.
        $current = hash_equals(HeroImage::hash($uid), $hash);
        header('Content-Type: image/png');
        header('Cache-Control: ' . ($current ? 'public, max-age=31536000, immutable' : 'no-cache'));
        header('ETag: "' . sha1($png) . '"');
        echo $png;
        exit;
    }
}
