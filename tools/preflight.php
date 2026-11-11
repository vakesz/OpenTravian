#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Operator preflight: prints the checks the control panel's "Engine health"
 * page shows and exits 1 when one fails. Run it right after `docker compose up`:
 *   docker compose exec -u www-data php php tools/preflight.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/game/deployment/include/env.php';
require dirname(__DIR__) . '/game/include/Game/Admin/TwoFactorStore.php';
require dirname(__DIR__) . '/game/include/Game/Admin/Preflight.php';

// Before installation there is no world configuration to load.
if (is_file(SETUP_LOCK_FILE)) {
    require dirname(__DIR__) . '/game/include/bootstrap.php';
}

$results = Game\Admin\Preflight::evaluate(Game\Admin\Preflight::collect());
foreach ($results as $result) {
    fwrite(STDOUT, sprintf("[%s] %s\n", strtoupper($result['level']), $result['message']));
}
exit(Game\Admin\Preflight::failed($results) ? 1 : 0);
