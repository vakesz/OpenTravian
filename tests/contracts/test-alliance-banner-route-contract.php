<?php

declare(strict_types=1);

/**
 * Contract: bare /alliance/banner maps to alliance overview (shell),
 * and options banner gallery empty-state uses the translated sprintf copy.
 */

function failAllianceBannerRouteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$main = file_get_contents($root . '/game/include/mainInclude.php');
$banner = file_get_contents($root . '/game/include/resources/Templates/alliance/banner.php');

if ($main === false || $banner === false) {
    failAllianceBannerRouteContract('Unable to read alliance banner route sources.');
}

if (!str_contains($main, "preg_match('#^/alliance/banner/?$#'")) {
    failAllianceBannerRouteContract('mainInclude must soft-map /alliance/banner to the alliance overview shell.');
}

if (str_contains($banner, 'No banners have been submitted yet. Go to <a href="/alliance/options/banner/create">')) {
    failAllianceBannerRouteContract('Gallery empty state must not hardcode English around the Create link.');
}

if (!str_contains($banner, "T('Alliance', 'No banners have been submitted yet. Go to %s to submit yours.')")) {
    failAllianceBannerRouteContract('Gallery empty state must use the translated sprintf copy.');
}

foreach ([
    'class="allianceBannerGalleryOverview"',
    'class="galleryBannerDetailView"',
    'class="bannerBackground"',
    'class="playerLink"',
] as $needle) {
    if (!str_contains($banner, $needle)) {
        failAllianceBannerRouteContract('Gallery markup missing piece: ' . $needle);
    }
}

fwrite(STDOUT, "Alliance banner route contract passed.\n");
