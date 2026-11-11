#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Contract: the Discord link is an operator-configured https URL
 * (config.discordUrl). While it is unset no Discord link renders (footer,
 * mobile menu, lobby menu, help dialog); once set each link opens in a new tab
 * with rel="noopener". The setting is chosen at install and editable in the
 * admin panel behind the session checker.
 */

use Core\Config;
use Game\DiscordLink;
use Game\WorldOverrides;
use resources\View\PHPBatchView;

function failDiscordContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertDiscord(bool $condition, string $message): void
{
    if (!$condition) {
        failDiscordContract($message);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

// ---- validation: blank or https only ----------------------------------------------
foreach (['', 'https://discord.gg/abc123', 'https://discord.com/invite/x-y_z?event=1'] as $ok) {
    assertDiscord(DiscordLink::isValid($ok), "Expected a valid Discord URL: {$ok}");
    assertDiscord(WorldOverrides::normalize(['discordUrl' => $ok]) === ['discordUrl' => $ok], "The installer override must accept: {$ok}");
}
foreach (['http://discord.gg/abc', 'discord.gg/abc', 'javascript:alert(1)', '//discord.gg/abc', 'https://', 'https://discord.gg/a b', 'https://discord.gg/"x', "https://discord.gg/a\n", '/alliance/forum', 'https://' . str_repeat('a', 260) . '.com'] as $bad) {
    assertDiscord(!DiscordLink::isValid($bad), "Expected an invalid Discord URL: {$bad}");
    assertDiscord(WorldOverrides::normalize(['discordUrl' => $bad]) === [], "The installer override must drop: {$bad}");
}

// ---- the setting is install-time only, never re-applied over the admin value --------
$probe = (object)['dynamic' => (object)['discordUrl' => 'https://discord.gg/admin']];
WorldOverrides::apply($probe, ['discordUrl' => 'https://discord.gg/installer']);
assertDiscord($probe->dynamic->discordUrl === 'https://discord.gg/admin', 'WorldOverrides::apply must not overwrite the admin-edited Discord URL.');

// ---- rendering: hidden when unset, rendered when set ---------------------------------
$config = Config::getInstance();
$original = $config->dynamic->discordUrl ?? null;
$render = static fn (): array => [
    'menu' => PHPBatchView::render('layout/sidebarBoxMenu'),
];
try {
    foreach ([null, '', 'http://discord.gg/insecure'] as $unset) {
        $config->dynamic->discordUrl = $unset;
        assertDiscord(getDiscordUrl() === '', 'An unset or non-https value must yield no Discord URL.');
        foreach ($render() as $name => $html) {
            assertDiscord(stripos($html, 'discord') === false, "The {$name} template must not mention Discord while the URL is unset.");
        }
    }

    $url = 'https://discord.gg/test&x=1';
    $config->dynamic->discordUrl = 'https://discord.gg/test';
    assertDiscord(getDiscordUrl() === 'https://discord.gg/test', 'A configured https URL must be returned.');
    $out = $render();
    foreach ($out as $name => $html) {
        assertDiscord(preg_match('{<a [^>]*href="https://discord\.gg/test"[^>]*>}', $html, $m) === 1, "The {$name} template must link to the configured URL.");
        assertDiscord(str_contains($m[0], 'target="_blank"') && str_contains($m[0], 'rel="noopener'), "The {$name} Discord link must open in a new tab with rel=noopener.");
    }
} finally {
    $config->dynamic->discordUrl = $original;
}

// ---- layout (header menu + footer) and wiring are gated in source ---------------------
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);
$layout = $read('game/include/resources/Templates/layout/Layout.php');
assertDiscord(substr_count($layout, "<?php if (getDiscordUrl() !== '') { ?>") === 1, 'Layout.php must gate the mobile-menu Discord link.');
assertDiscord(str_contains($read('game/include/Game/FooterLinks.php'), "getDiscordUrl() !== '' ? getDiscordUrl() : null"), 'FooterLinks must gate the footer Discord link.');
assertDiscord(preg_match('{<a class="discord" target="_blank" rel="noopener" href=}', $layout) === 1, 'The mobile menu Discord link needs rel=noopener.');
assertDiscord(!str_contains($read('game/include/resources/Templates/layout/outOfGame.php'), 'class="discord"'), 'The out-of-game header carries no Discord tile.');
assertDiscord(!str_contains($read('game/include/functions.general.php'), "return getForumUrl();\n}\n\nfunction getNewsUrl"), 'getDiscordUrl() must not fall back to the forum.');

$admin = $read('game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php');
foreach (['saveDiscordUrl', 'DiscordLink::isValid', 'Session::getCheckerInput', 'Session::validateChecker'] as $needle) {
    assertDiscord(str_contains($admin, $needle), "The admin settings page must handle the Discord URL ({$needle}).");
}
assertDiscord(str_contains($read('database/world.sql'), '`discordUrl` VARCHAR(255)'), 'world.sql must define config.discordUrl.');
assertDiscord(str_contains($read('game/include/Game/Installer/InstallInput.php') . $read('game/include/Game/Installer/WorldInstaller.php'), 'discordUrl'), 'The installer must persist the Discord URL.');

echo "Discord link contract passed.\n";
