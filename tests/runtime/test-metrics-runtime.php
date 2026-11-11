<?php

declare(strict_types=1);

// Collects the real world's metrics and checks that the output is well-formed exposition text
// without anything player-specific in it.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Metrics runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
if (!is_file(SETUP_LOCK_FILE)) {
    fwrite(STDERR, "\n*** SKIPPED: metrics: no world is installed.\n\n");
    echo "Metrics runtime passed (SKIPPED).\n";
    exit(0);
}
require '/travian/game/include/bootstrap.php';

use Game\Metrics\Metrics;

$text = Metrics::render(Metrics::collect(time()));
foreach (explode("\n", rtrim($text)) as $line) {
    if (preg_match('/^(# (HELP|TYPE) [a-z_]+ .+|[a-z_]+(\{[a-z]+="[a-z0-9]+"\})? -?[0-9.]+)$/', $line) !== 1) {
        fwrite(STDERR, "Not exposition format: $line\n");
        exit(1);
    }
}
foreach (['travian_engine_up ', 'travian_queue_overdue{queue="construction"} ', 'travian_players_total ', 'travian_mail_queue{status="queued"} '] as $needle) {
    if (!str_contains($text, $needle)) {
        fwrite(STDERR, "The live metrics are missing $needle\n");
        exit(1);
    }
}
$leaks = preg_grep('/Multihunter|@|\d+\.\d+\.\d+\.\d+/', explode("\n", $text));
if ($leaks !== []) {
    fwrite(STDERR, "The metrics must carry no names, addresses or e-mails.\n");
    exit(1);
}

echo "Metrics runtime passed.\n";
