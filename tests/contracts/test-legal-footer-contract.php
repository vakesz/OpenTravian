<?php

declare(strict_types=1);

function failLegalFooter(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

require_once $root . '/game/include/Game/LegalMarkdown.php';

// The Markdown renderer escapes everything and only emits its own constructs.
$html = \Game\LegalMarkdown::render("# T\n\n<script>x</script> **b** *i* [ok](https://a.example/p?q=1&r=2) [loc](/rules) [bad](javascript:alert(1)) [proto](//evil.example)\n\n- a\n- b\n\n1. c");
foreach (['<h2>T</h2>', '<strong>b</strong>', '<em>i</em>', '&lt;script&gt;', '<a href="/rules">loc</a>', '<ul><li>a</li><li>b</li></ul>', '<ol><li>c</li></ol>', 'rel="noopener noreferrer"'] as $needle) {
    str_contains($html, $needle) || failLegalFooter("Legal Markdown output lacks {$needle}.");
}
foreach (['<script', 'href="javascript', 'href="//'] as $forbidden) {
    !str_contains($html, $forbidden) || failLegalFooter("Legal Markdown must not emit {$forbidden}.");
}

// Wiring: schema, routes, footer row and canonical wording.
str_contains($read('database/world.sql'), 'CREATE TABLE IF NOT EXISTS `legal_pages`') || failLegalFooter('world.sql must define legal_pages.');
$main = $read('game/include/mainInclude.php');
str_contains($main, '(rules|terms|imprint|privacy)') && str_contains($main, "'legal_page' => 'LegalPageCtrl'")
    || failLegalFooter('The legal routes must dispatch to LegalPageCtrl.');
$links = $read('game/include/Game/FooterLinks.php');
foreach (["'/homepage/news'", "'Privacy Policy'", 'LegalPages::href(', "'Footer.Game rules'", "'Footer.Terms'", "'Footer.Imprint'", "'Footer.News'"] as $needle) {
    str_contains($links, $needle) || failLegalFooter("FooterLinks must use {$needle}.");
}
$readableCatalogue = json_decode($read('game/include/resources/Translation/HomepagePublicCatalog.json'), true);
is_array($readableCatalogue) && !str_contains($read('game/include/resources/Translation/HomepagePublicCatalog.php'), 'base64')
    || failLegalFooter('The public homepage snapshot must be a readable JSON file.');
$layout = $read('game/include/resources/Templates/layout/Layout.php');
str_contains($layout, 'FooterLinks::build(') && str_contains($layout, 'FooterLinks::version()')
    || failLegalFooter('Layout must render the shared footer row and version label.');

// Reference wording: in-game keys exist in the catalogue, public keys exist in all 37 public catalogues.
$game = $read('game/include/resources/Translation/en-US.php');
foreach (['News', 'Game rules', 'Terms', 'Imprint'] as $key) {
    str_contains($game, "\$Definition['Global']['Footer']['{$key}']") || failLegalFooter("Footer.{$key} is missing from en-US.");
}
$catalogue = require $root . '/game/include/resources/Translation/HomepagePublicCatalog.php';
foreach ($catalogue['catalogues'] as $language => $values) {
    foreach (['Game Rules', 'Terms & Conditions', 'Imprint', 'Privacy Policy', 'News & Updates'] as $key) {
        (isset($values[$key]) && $values[$key] !== '') || failLegalFooter("{$language} lacks the public {$key} label.");
    }
}

// The version label accepts release versions only.
require_once $root . '/game/include/Game/WorldOverrides.php';
require_once $root . '/game/include/Game/DiscordLink.php';
require_once $root . '/game/include/Game/LegalPages.php';
putenv('TRAVIAN_VERSION=1.2.3');
\Game\LegalPages::versionLabel() === 'v1.2.3' || failLegalFooter('TRAVIAN_VERSION=1.2.3 must render v1.2.3.');
putenv('TRAVIAN_VERSION=v2.0.0-rc.1');
\Game\LegalPages::versionLabel() === 'v2.0.0-rc.1' || failLegalFooter('A pre-release version must keep its suffix.');
putenv('TRAVIAN_VERSION=latest');
\Game\LegalPages::versionLabel() === null || is_file($root . '/VERSION') || failLegalFooter('A non-release tag must not render a version label.');

echo "Legal footer contract passed.\n";
