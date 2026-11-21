<?php

declare(strict_types=1);

// The engine forks its workers. Each process must end up with its own MySQL
// session: with a persistent ("p:") link a worker's reconnect was handed the
// parent's pooled socket, and a worker exiting sent QUIT for the parent too
// ("MySQL server has gone away" on the supervisor's next heartbeat).

if (!is_file('/travian/game/deployment/include/env.php') || !function_exists('pcntl_fork')) {
    echo "DB fork isolation runtime skipped outside a container with pcntl.\n";
    exit(0);
}
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Database\DB;

$fail = static function (string $message): never {
    fwrite(STDERR, 'DB fork isolation runtime failed: ' . $message . "\n");
    exit(1);
};

$db = DB::getInstance();
$parentId = (int) $db->fetchScalar('SELECT CONNECTION_ID()');
$pipes = [];
$children = [];
for ($worker = 0; $worker < 4; ++$worker) {
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $db->disconnectForFork();
    $pid = pcntl_fork();
    if ($pid === -1) {
        $fail('fork failed');
    }
    if ($pid === 0) {
        fclose($pair[0]);
        $child = DB::getInstance()->forceNewDatabase();
        $first = (int) $child->fetchScalar('SELECT CONNECTION_ID()');
        // The reconnect-retry path must not be handed another process's link either.
        $child->forceNewDatabase();
        $second = (int) DB::getInstance()->fetchScalar('SELECT CONNECTION_ID()');
        fwrite($pair[1], $first . ',' . $second);
        exit(0);
    }
    fclose($pair[1]);
    $db->forceNewDatabase();
    $pipes[] = $pair[0];
    $children[] = $pid;
}
foreach ($children as $pid) {
    pcntl_waitpid($pid, $status);
}
$parentAfter = (int) DB::getInstance()->fetchScalar('SELECT CONNECTION_ID()');
if ($parentAfter <= 0) {
    $fail('the parent lost its session when the workers exited');
}
$seen = [$parentAfter => 'parent'];
foreach ($pipes as $index => $pipe) {
    foreach (explode(',', (string) stream_get_contents($pipe)) as $id) {
        $id = (int) $id;
        if ($id <= 0) {
            $fail("worker $index reported no session");
        }
        if (isset($seen[$id]) && $seen[$id] !== "worker $index") {
            $fail("worker $index shares session $id with {$seen[$id]}");
        }
        $seen[$id] = "worker $index";
    }
}
unset($parentId);
echo "DB fork isolation runtime passed.\n";
