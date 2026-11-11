<?php

declare(strict_types=1);

function failHomepageMobileLayoutContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$stylesPath = $root . '/assets/gpack/travian/mainPage/lang/overrides.css';
$styles = file_get_contents($stylesPath);
if ($styles === false) {
    failHomepageMobileLayoutContract('The public Homepage stylesheet must be readable.');
}

if (preg_match('/\.homepageHeader\s*\{[^}]*\bmin-width\s*:\s*360px\s*;/s', $styles) !== 1) {
    failHomepageMobileLayoutContract('The source-sized Homepage header floor is missing.');
}

if (preg_match(
    '/@media\s+screen\s+and\s+\(max-width:\s*359px\)\s*\{\s*'
    . '\.publicHomepage\s+\.homepageHeader\s*\{[^}]*\bmin-width\s*:\s*0\s*;/s',
    $styles
) !== 1) {
    failHomepageMobileLayoutContract(
        'A sub-360px Homepage viewport must override the desktop header floor instead of overflowing horizontally.'
    );
}

echo "Homepage mobile layout contract passed.\n";
