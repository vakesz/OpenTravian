<?php
declare(strict_types=1);

/**
 * Contract: the Rally Point simulator uses the maintained combat SVGs and the
 * action/tab artwork rather than placeholder line icons.
 */

$root = dirname(__DIR__, 2);
$templatePath = $root . '/game/include/resources/Templates/combat/main.php';
$template = file_get_contents($templatePath);
if (!is_string($template)) {
    fwrite(STDERR, "Cannot read combat simulator template.\n");
    exit(1);
}

$attack = file_get_contents($root . '/game/deployment/public/img/svg/combat/svg-attack.svg');
$defend = file_get_contents($root . '/game/deployment/public/img/svg/combat/svg-defend.svg');
if (!is_string($attack) || !is_string($defend)) {
    fwrite(STDERR, "Maintained combat role SVGs must exist.\n");
    exit(1);
}

foreach ([
    "'svg-attack.svg'",
    "'svg-defend.svg'",
    "PUBLIC_INTERNAL_PATH . 'img/svg/combat/'",
    "'svgAttack roleIcon'",
    "'svgDefend roleIcon'",
    'viewBox="0 0 100 81.41"',
    'viewBox="0 0 14 14"',
    '<svg viewBox="0 0 48 48" class="star">',
    '<a class="favor"><svg viewBox="0 0 48 48">',
] as $needle) {
    if (!str_contains($template, $needle)) {
        fwrite(STDERR, "Combat simulator must preserve SVG detail: {$needle}\n");
        exit(1);
    }
}

foreach ([
    'viewBox="0 0 45 51"',
    'viewBox="0 0 43 48"',
    'viewBox="0 0 16 16"',
    'viewBox="0 0 10 10"',
    'viewBox="0 0 22 22"',
] as $retiredPlaceholder) {
    if (str_contains($template, $retiredPlaceholder)) {
        fwrite(STDERR, "Retired simulator placeholder SVG remains: {$retiredPlaceholder}\n");
        exit(1);
    }
}

echo "Combat simulator icon contract passed.\n";
