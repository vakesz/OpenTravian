<?php

declare(strict_types=1);

function failVideoRemovalContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

// No ad provider exists locally: the video runtime is gone, not disabled.
foreach (['game/include/Game/VideoAd', 'game/include/Controller/Ajax/videofeature', 'game/include/Controller/Ajax/videoCallback.php',
    'game/include/admin/include/Controllers/VideoAdsCtrl.php', 'game/include/admin/include/templates/videoAds.php'] as $path) {
    if (file_exists($root . '/' . $path)) {
        failVideoRemovalContract("{$path} must stay deleted.");
    }
}

$sources = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game/include', FilesystemIterator::SKIP_DOTS));
foreach ($sources as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), ['php'], true) || str_contains($file->getPathname(), '/Translation/')) {
        continue;
    }
    $code = (string)file_get_contents($file->getPathname());
    // The season pass keeps its reference "videofeatureSkip" shard; nothing else may mention the video feature.
    foreach (['VideoAd', 'VideoUi', 'VideoBonus', 'videoFeature', "'vft'", 'watchVideo'] as $needle) {
        if (str_contains($code, $needle)) {
            failVideoRemovalContract(str_replace($root . '/', '', $file->getPathname()) . " must not mention {$needle}.");
        }
    }
}

foreach (['game/deployment/public/game-client.js'] as $bundle) {
    $js = (string)file_get_contents($root . '/' . $bundle);
    if (stripos($js, 'videoFeature') !== false || stripos($js, 'videofeature/') !== false) {
        failVideoRemovalContract("{$bundle} must not carry the video feature client.");
    }
}

$schema = (string)file_get_contents($root . '/database/world.sql');
foreach (['video_ad_settings', 'video_ad_sessions', 'video_bonus', 'videoBonus', 'vq1', 'vq2', '`vb1`'] as $needle) {
    if (str_contains($schema, $needle)) {
        failVideoRemovalContract("world.sql must not define {$needle}.");
    }
}

$routes = (string)file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
if (str_contains($routes, 'videofeature')) {
    failVideoRemovalContract('The legacy route table must not map videofeature routes.');
}

// Without the video rows the daily quests still add up to the last reward (100 points).
$quests = new \Model\DailyQuestModel();
$total = 0;
foreach (range(1, 11) as $id) {
    $total += $quests->getTotalQuestReward($id);
}
if ($total !== 100) {
    failVideoRemovalContract("The daily quests must add up to 100 points, got {$total}.");
}

$agents = (string)file_get_contents($root . '/docs/development.md');
if (!str_contains($agents, 'Video rewards are intentionally absent')) {
    failVideoRemovalContract('docs/development.md must document the missing video rewards.');
}

echo "Video removal contract passed.\n";
