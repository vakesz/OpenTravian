<?php

declare(strict_types=1);

// The mail queue's "blocked" warning is logged once per state, not every few minutes.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Mail queue warning runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Mail\QueueWorker;

$log = tempnam(sys_get_temp_dir(), 'mailwarn');
ini_set('error_log', $log);
$worker = new QueueWorker();
$warn = new ReflectionMethod($worker, 'warnOnce');
foreach (['a', 'a', 'a', 'b', 'b', 'a'] as $message) {
    $warn->invoke($worker, $message);
}
$lines = array_map(static fn (string $line): string => substr($line, -1), array_filter(explode("\n", (string)file_get_contents($log))));
unlink($log);
if ($lines !== ['a', 'b', 'a']) {
    fwrite(STDERR, 'The warning must be logged once per state change, got ' . json_encode($lines) . "\n");
    exit(1);
}

echo "Mail queue warning runtime passed.\n";
