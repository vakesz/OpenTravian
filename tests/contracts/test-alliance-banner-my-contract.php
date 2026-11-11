<?php

declare(strict_types=1);

/**
 * Contract: Alliance banner "my" shell matches 
 * (.bannerPresentation + .bannerCreatorLink + action button ids).
 */

function failAllianceBannerMyContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$banner = file_get_contents($root . '/game/include/resources/Templates/alliance/banner.php');
$en = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$ornament = file_get_contents($root . '/game/include/resources/Templates/alliance/banner/alliance-banner-ornament.svg');
if ($banner === false || $en === false || $ornament === false) {
    failAllianceBannerMyContract('Unable to read banner sources.');
}

foreach ([
    'class="bannerPresentation',
    'class="bannerCreatorLink"',
    'class="bannerStateDescription"',
    'id="submitToGallery"',
    'id="setAllianceBanner"',
    "T('Alliance', \$playerBanner === null ? 'Create banner' : 'Edit banner')",
    "T('Alliance', 'My Banner')",
    "buttonFramed<?= \$submitDisabled ? ' disabled' : '' ?>",
    "buttonFramed<?= \$setDisabled ? ' disabled' : '' ?>",
    'alliance-banner-ornament.svg',
] as $needle) {
    if (!str_contains($banner, $needle)) {
        failAllianceBannerMyContract('banner.php missing my-banner piece: ' . $needle);
    }
}

if (!str_contains($ornament, 'id="allianceBannerOrnamentClipPath"')) {
    failAllianceBannerMyContract('ornament SVG missing allianceBannerOrnamentClipPath.');
}

foreach ([
    "Create banner'] = 'Create banner'",
    "Edit banner'] = 'Edit banner'",
    "My Banner'] = 'My Banner'",
] as $needle) {
    if (!str_contains($en, $needle)) {
        failAllianceBannerMyContract('en-US missing banner copy: ' . $needle);
    }
}

fwrite(STDOUT, "Alliance banner my-contract passed.\n");
