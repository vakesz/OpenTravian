<?php

declare(strict_types=1);

// The world stats page is a local-only public page reached by direct URL: routed like the other public
// landing pages, linked from no footer or menu, aggregate-only, cached, and with no external request.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};
$read = static function (string $path) use ($root, $fail): string {
    $source = file_get_contents($root . '/' . $path);

    return $source === false ? $fail("{$path} must be readable.") : $source;
};
$routes = $read('game/include/mainInclude.php');
$controller = $read('game/include/Controller/HomepageCtrl.php');
$template = $read('game/include/resources/Templates/system/homepage.php');
$snapshot = $read('game/include/Game/WorldStatsSnapshot.php');
$footer = $read('game/include/Game/FooterLinks.php');
$agents = $read('docs/development.md');

foreach (["'/homepage/world' => ['homepagePage' => 'world']", "'/homepage/world/' => ['homepagePage' => 'world']"] as $needle) {
    if (!str_contains($routes, $needle)) {
        $fail("The world stats route must be a finite public route: {$needle}");
    }
}
if (!str_contains($controller, "'tournament', 'world']") || !str_contains($controller, 'WorldStatsSnapshot::get()')) {
    $fail('The public controller must resolve the world page and read the cached snapshot.');
}
foreach ([$footer, $controller] as $source) {
    if (str_contains($source, '/homepage/world')) {
        $fail('The world stats page is reached by direct URL only: no footer or menu link.');
    }
}
if (!str_contains($snapshot, 'returnCacheWithCallBack(self::CACHE_KEY, self::TTL')) {
    $fail('The snapshot must be cached.');
}
if (substr_count($snapshot, 'publicUserCondition') < 1) {
    $fail('Every snapshot query must use the public player condition.');
}
$start = strpos($template, "elseif (\$page === 'world'");
$end = strpos($template, "elseif (\$page === 'calendar'");
$section = $start === false || $end === false ? '' : substr($template, $start, $end - $start);
if ($section === '') {
    $fail('The world stats section must precede the calendar section in the public template.');
}
if (preg_match('#https?://#i', $section) === 1) {
    $fail('The world stats section must make no external request or link.');
}
if (!str_contains($agents, '/homepage/world')) {
    $fail('docs/development.md must describe the world stats page as a local-only public page reached by direct URL.');
}

echo "World stats contract passed.\n";
