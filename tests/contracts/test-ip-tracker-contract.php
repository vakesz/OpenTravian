#!/usr/bin/env php
<?php

declare(strict_types=1);

// IP storage: IPv4 and IPv6 addresses are kept as canonical text, an IPv4-mapped IPv6 address is the IPv4 one,
// the per-player address log is unique per address, and a request inside the refresh window writes nothing.

use Core\Helper\IPTracker;

function expectIpTracker(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Core/Helper/IPTracker.php';

$cases = [
    ['1.2.3.4', '1.2.3.4'],
    ['::ffff:1.2.3.4', '1.2.3.4'],
    ['::FFFF:1.2.3.4', '1.2.3.4'],
    ['2001:DB8::1', '2001:db8::1'],
    ['2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1'],
    ['::1', '::1'],
    ['not an ip', null],
    ['1.2.3', null],
    ['', null],
    [false, null],
    [null, null],
];
foreach ($cases as [$input, $expected]) {
    expectIpTracker(IPTracker::normalize($input) === $expected, 'IPTracker::normalize(' . var_export($input, true) . ') must be ' . var_export($expected, true));
}

$world = (string) file_get_contents($root . '/database/world.sql');
$account = (string) file_get_contents($root . '/database/account.sql');
preg_match('/CREATE TABLE IF NOT EXISTS `log_ip` \((.*?)\) ENGINE/s', $world, $logIp);
expectIpTracker(isset($logIp[1]) && str_contains($logIp[1], '`ip` VARCHAR(45) NOT NULL'), 'log_ip.ip must be VARCHAR(45).');
expectIpTracker(str_contains($logIp[1], 'UNIQUE KEY `uid_ip` (`uid`, `ip`)'), 'log_ip must be unique per player and address.');
preg_match('/CREATE TABLE `banIP` \((.*?)\) ENGINE/s', $account, $banIp);
expectIpTracker(isset($banIp[1]) && str_contains($banIp[1], '`ip` varchar(45) NOT NULL'), 'banIP.ip must be varchar(45).');

$tracker = (string) file_get_contents($root . '/game/include/Core/Helper/IPTracker.php');
expectIpTracker(!str_contains($tracker, 'ip2long') && !str_contains($tracker, 'long2ip'), 'IPTracker must not convert addresses to numbers.');
foreach (['game/include/bootstrap.php', 'game/include/admin/include/Controllers/IPBanCtrl.php', 'game/include/admin/include/Controllers/EditPlayerCtrl.php', 'game/include/Core/Automation.php'] as $path) {
    $source = (string) file_get_contents($root . '/' . $path);
    expectIpTracker(!str_contains($source, 'ip2long') && !str_contains($source, 'long2ip'), $path . ' must not convert addresses to numbers.');
}

echo "IP tracker contract passed." . PHP_EOL;
