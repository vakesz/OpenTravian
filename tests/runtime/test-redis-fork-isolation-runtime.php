<?php

declare(strict_types=1);

// The engine forks its workers after the parent has already used the cache.
// Each worker must open its own Redis connection: on a shared socket workers
// read each other's replies, so a GET returned another worker's 3-byte reply,
// unserialize() warned and every attack being processed failed.

if (!is_file('/travian/game/deployment/include/env.php') || !function_exists('pcntl_fork')) {
    echo "Redis fork isolation runtime skipped outside a container with pcntl.\n";
    exit(0);
}
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Caching\Caching;

$cache = Caching::getInstance();
$big = str_repeat('x', 200000);
$cache->set('forkIsolation:big', ['payload' => $big], 120);
$cache->set('forkIsolation:small', 'abc', 120);
if ($cache->get('forkIsolation:small') !== 'abc') {
    fwrite(STDERR, "Redis fork isolation runtime failed: parent could not read its own value.\n");
    exit(1);
}

$children = [];
for ($worker = 0; $worker < 6; ++$worker) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        fwrite(STDERR, "Redis fork isolation runtime failed: fork failed.\n");
        exit(1);
    }
    if ($pid === 0) {
        $bad = 0;
        for ($i = 0; $i < 150; ++$i) {
            try {
                if ($worker % 2) {
                    $bad += $cache->get('forkIsolation:small') === 'abc' ? 0 : 1;
                } else {
                    $value = $cache->get('forkIsolation:big');
                    $bad += is_array($value) && ($value['payload'] ?? '') === $big ? 0 : 1;
                }
            } catch (\Throwable) {
                ++$bad;
            }
        }
        exit($bad === 0 ? 0 : 1);
    }
    $children[] = $pid;
}
$failed = 0;
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
    $failed += (pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0) ? 0 : 1;
}
if ($cache->get('forkIsolation:small') !== 'abc') {
    ++$failed;
}
$cache->delete(['forkIsolation:big', 'forkIsolation:small']);
if ($failed > 0) {
    fwrite(STDERR, "Redis fork isolation runtime failed: $failed process(es) read another worker's reply.\n");
    exit(1);
}
echo "Redis fork isolation runtime passed.\n";
