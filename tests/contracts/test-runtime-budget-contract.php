<?php

declare(strict_types=1);

// The container budget: game workers stay inside the PHP memory cap, admin and
// setup get one long-lived worker, Redis does not snapshot, and InnoDB is set
// for SSD and virtual disks.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

$ini = $read('docker/php/php.ini');
foreach ([
    'memory_limit = 256M' => 'a 256M game-worker memory cap',
    'max_execution_time = 60' => 'a 60s game-request cap',
    'opcache.validate_timestamps = 0' => 'OPcache timestamp checks off in the image',
    'session.save_handler = redis' => 'Redis sessions',
    'redis.session.locking_enabled = 1' => 'session locking',
] as $needle => $what) {
    if (!str_contains($ini, $needle)) {
        $fail("php.ini must set {$what}.");
    }
}

$pool = $read('docker/php/www.conf');
foreach ([
    'request_terminate_timeout = 60' => 'the game pool must die with the nginx timeout',
    'php_admin_value[memory_limit] = 256M' => 'game workers must not raise memory_limit',
    "listen = 9001" => 'a long pool for admin and setup',
    'pm = ondemand' => 'the long pool must not stay resident',
    'pm.max_children = 1' => 'one long worker',
    'request_terminate_timeout = 3600' => 'the long pool keeps the hour-long admin and setup limit',
    'php_admin_value[memory_limit] = 1536M' => 'a finite cap for map seeding and backups',
] as $needle => $what) {
    if (!str_contains($pool, $needle)) {
        $fail("FPM pool: {$what}.");
    }
}

$nginx = $read('docker/nginx/default.conf.template');
if (!str_contains($nginx, 'set $php_long php:9001;') || substr_count($nginx, 'fastcgi_pass $php_long;') !== 2) {
    $fail('Only admin.php and setup.php may use the long PHP pool.');
}

$entry = $read('docker/php/entrypoint.sh');
if (!str_contains($entry, 'TRAVIAN_DEV_TOOLS') || !str_contains($entry, 'opcache.validate_timestamps = 1')) {
    $fail('The dev layer must turn OPcache timestamp checks back on.');
}

$compose = $read('compose.yaml');
foreach ([
    '--innodb-flush-neighbors=${TRAVIAN_MARIADB_FLUSH_NEIGHBORS:-0}' => 'InnoDB neighbor flushing off',
    '--innodb-io-capacity=${TRAVIAN_MARIADB_IO_CAPACITY:-1000}' => 'InnoDB io capacity 1000',
    'PHP_INI_SCAN_DIR: ":/run/travian"' => 'the entrypoint ini directory',
] as $needle => $what) {
    if (!str_contains($compose, $needle)) {
        $fail("compose.yaml must set {$what}.");
    }
}

$redis = $read('docker/redis/redis.conf');
foreach (['save ""', 'appendonly no', 'maxmemory 200mb', 'maxmemory-policy volatile-lru'] as $needle) {
    if (!str_contains($redis, $needle)) {
        $fail("redis.conf must contain {$needle}.");
    }
}

$game = $read('game/include/update.php') . $read('game/deployment/include/install.php');
if (str_contains($game, 'memory_limit", -1') || str_contains($game, "memory_limit', -1") || str_contains($game, "'-1'")) {
    $fail('Install and update must cap memory instead of removing the limit.');
}
if (!str_contains($read('game/deployment/include/install.php'), "ini_set('memory_limit', '1536M')")
    || !str_contains($read('game/include/update.php'), "ini_set('memory_limit', '1536M')")) {
    $fail('Install and update keep a 1536M cap, matching the long FPM pool.');
}

echo "Runtime budget contract passed.\n";
