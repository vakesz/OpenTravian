<?php

declare(strict_types=1);

use Core\Dev\RequestTiming;

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Core/Dev/RequestTiming.php';

function expectRequestTiming(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

putenv('TRAVIAN_DEV_TOOLS');
expectRequestTiming(!RequestTiming::available(), 'Developer tooling is off without TRAVIAN_DEV_TOOLS.');
putenv('TRAVIAN_DEV_TOOLS=0');
expectRequestTiming(!RequestTiming::available(), 'Only the value 1 switches it on.');
putenv('TRAVIAN_DEV_TOOLS=1');
expectRequestTiming(RequestTiming::available(), 'TRAVIAN_DEV_TOOLS=1 makes the tooling available.');
putenv('TRAVIAN_DEV_TOOLS');

$stats = ['ms' => 123.4, 'queries' => 14, 'queryMs' => 5.6, 'memory' => 6 * 1048576];
$badge = RequestTiming::badge($stats);
expectRequestTiming(str_contains($badge, 'dev 123 ms · 14 queries 5 ms · 6.0 MB'), 'The badge shows time, queries and memory: ' . $badge);
foreach (['position:fixed', 'pointer-events:none', 'z-index:2147483647'] as $needle) {
    expectRequestTiming(str_contains($badge, $needle), 'The badge must be out of flow and inert: missing ' . $needle);
}
expectRequestTiming(!str_contains($badge, '<script') && substr_count($badge, '<div') === 1, 'The badge is one element with inline style only.');

$page = '<html><body><div id="x">game</div></body></html>';
$injected = RequestTiming::inject($page, $stats);
expectRequestTiming(str_ends_with($injected, $badge . '</body></html>') && str_starts_with($injected, '<html><body><div id="x">game</div>'), 'The badge goes just before the closing body tag and nothing else changes.');
expectRequestTiming(RequestTiming::inject($injected, $stats) === $injected, 'Injecting twice must not add a second badge.');
expectRequestTiming(RequestTiming::inject('{"json":true}', $stats) === '{"json":true}' && RequestTiming::inject('<p>fragment</p>', $stats) === '<p>fragment</p>', 'Anything without a closing body tag is left alone.');

// Only the dev layer enables it, and only the bootstrap starts it, once, outside every template.
$base = (string) file_get_contents($root . '/compose.yaml');
$dev = (string) file_get_contents($root . '/compose.dev.yaml');
expectRequestTiming(!str_contains($base, 'TRAVIAN_DEV_TOOLS') && !str_contains((string) file_get_contents($root . '/docker/php/Dockerfile'), 'TRAVIAN_DEV_TOOLS') && !str_contains((string) file_get_contents($root . '/compose.tls.yaml'), 'TRAVIAN_DEV_TOOLS'), 'No production file may set TRAVIAN_DEV_TOOLS.');
expectRequestTiming(preg_match('/php:\s.*?environment:\s*\n(?:\s*#[^\n]*\n)*\s*TRAVIAN_DEV_TOOLS: "1"/s', $dev) === 1, 'compose.dev.yaml must set TRAVIAN_DEV_TOOLS on the php service.');
expectRequestTiming(substr_count((string) file_get_contents($root . '/game/include/bootstrap.php'), 'RequestTiming::start()') === 1, 'The bootstrap starts the timing once.');
$offenders = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game/include/resources', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && str_contains((string) file_get_contents($file->getPathname()), 'travianDevFooter')) {
        $offenders[] = $file->getFilename();
    }
}
expectRequestTiming($offenders === [], 'No game template may mention the dev footer: ' . implode(', ', $offenders));
$database = (string) file_get_contents($root . '/game/include/Core/Database/DB.php');
expectRequestTiming(substr_count($database, 'self::$profile') >= 3 && str_contains($database, 'public static bool $profile = false;'), 'The query counters only run while profiling is switched on.');

echo "Dev request timing contract passed.\n";
