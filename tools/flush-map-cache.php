#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Flushes the map image caches once: every cached map block and mark image, the stored third-zoom block rows of the
 * former 120x120 layout, and a version bump of the remaining blocks so browsers revalidate them. Run it after the map
 * tile size or art changes (the zoom-3 move to 100x100 blocks at 6 pixels) or after changing the map data by hand.
 *
 * Usage: php tools/flush-map-cache.php
 */

use Core\Caching\Caching;
use Core\Database\DB;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool may only run from the command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/game/deployment/include/env.php';
require_once dirname(__DIR__) . '/game/include/bootstrap.php';

$db = DB::getInstance();
$db->query('DELETE b FROM blocks b JOIN map_block m ON m.id=b.map_id WHERE m.zoomLevel=3');
$db->query('DELETE FROM map_block WHERE zoomLevel=3');
$db->query('UPDATE map_block SET version=version+1');
$db->query('UPDATE map_mark SET version=version+1');
$cache = Caching::getInstance();
foreach (['map_block_*', 'map_mark_*', 'minimap*'] as $pattern) {
    $cache->deleteByPattern($pattern);
}
echo "Map caches flushed.\n";
