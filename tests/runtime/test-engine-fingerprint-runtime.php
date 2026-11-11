<?php

declare(strict_types=1);

// The engine restarts itself when the deployed code changes. Its fingerprint must follow file content, not
// mtimes: touching or rewriting identical text must not restart it, a real edit, a new file or a removed file must.

require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Jobs\EngineHeartbeat;

$root = sys_get_temp_dir() . '/engine-fingerprint-' . bin2hex(random_bytes(4));
mkdir($root . '/Core', 0777, true);
mkdir($root . '/resources', 0777, true);
register_shutdown_function(static function () use ($root): void {
    foreach (glob($root . '/{,*/}*', GLOB_BRACE) ?: [] as $path) {
        is_file($path) && unlink($path);
    }
    @rmdir($root . '/Core');
    @rmdir($root . '/resources');
    @rmdir($root);
});

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
};

file_put_contents($root . '/Core/A.php', "<?php\n// a\n");
file_put_contents($root . '/b.php', "<?php\n// b\n");
file_put_contents($root . '/resources/ignored.php', "<?php\n// templates are skipped\n");
$base = EngineHeartbeat::fingerprint($root);

touch($root . '/Core/A.php', time() + 3600);
$check(EngineHeartbeat::fingerprint($root) === $base, 'touching a file must not change the fingerprint');

file_put_contents($root . '/Core/A.php', "<?php\n// a\n");
$check(EngineHeartbeat::fingerprint($root) === $base, 'rewriting identical text must not change the fingerprint');

file_put_contents($root . '/resources/ignored.php', "<?php\n// changed\n");
$check(EngineHeartbeat::fingerprint($root) === $base, 'files under resources are not part of the fingerprint');

file_put_contents($root . '/Core/A.php', "<?php\n// a2\n");
$edited = EngineHeartbeat::fingerprint($root);
$check($edited !== $base, 'editing a file must change the fingerprint');

file_put_contents($root . '/c.php', "<?php\n");
$added = EngineHeartbeat::fingerprint($root);
$check($added !== $edited, 'adding a file must change the fingerprint');

unlink($root . '/c.php');
$check(EngineHeartbeat::fingerprint($root) === $edited, 'removing the added file must restore the fingerprint');

echo "Engine fingerprint runtime test passed.\n";
