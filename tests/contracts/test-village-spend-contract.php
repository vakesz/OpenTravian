<?php

declare(strict_types=1);

// Resource spending is a guarded relative UPDATE inside one village-locked transaction;
// the absolute-write Village::modifyResources() path no longer exists.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

$village = (string)file_get_contents($root . '/game/include/Core/Village.php');
foreach ([
    'public function spend(array $costs): bool',
    'public function refund(array $amounts): bool',
    'public function transaction(callable $callback): bool',
    'UPDATE vdata SET wood=wood-?, clay=clay-?, iron=iron-?, crop=crop-? WHERE kid=? AND ',
    'LEAST(maxstore, wood+?)',
    'LEAST(maxcrop, crop+?)',
    "GET_LOCK('\$lock', 5)",
    'RELEASE_LOCK',
] as $needle) {
    if (!str_contains($village, $needle)) {
        $fail("Village.php must contain: {$needle}");
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    if (str_contains((string)file_get_contents($file->getPathname()), 'modifyResources(')) {
        $fail('modifyResources() must stay deleted: ' . $file->getPathname());
    }
}

// Every paid action must spend through Village::spend() inside Village::transaction().
foreach ([
    'Controller/HeroInventoryCtrl.php',
    'Controller/Build/AcademyCtrl.php',
    'Controller/Build/SmithyCtrl.php',
    'Controller/Build/TroopBuilding.php',
    'Controller/Build/HarborCtrl.php',
    'Controller/Build/HospitalCtrl.php',
    'Controller/Build/MarketPlaceCtrl.php',
    'Controller/Build/TownhallCtrl.php',
    'Controller/Build/BreweryCtrl.php',
    'Controller/RallyPoint/RallyPoint.php',
] as $relative) {
    $source = (string)file_get_contents($root . '/game/include/' . $relative);
    if (!str_contains($source, '->spend(') && !str_contains($source, '->refund(')) {
        $fail("{$relative} must use Village::spend()/refund().");
    }
    if (!str_contains($source, '->transaction(')) {
        $fail("{$relative} must run the payment inside Village::transaction().");
    }
}

// Concurrency: two connections race the guarded statement on a scratch table.
$stateDir = getenv('TRAVIAN_STATE_DIR') ?: '';
$connectionFile = $stateDir . '/connection.local.php';
if ($stateDir === '' || !is_file($connectionFile) || !class_exists('mysqli')) {
    echo "Village spend contract passed (static shape; database check skipped).\n";
    exit(0);
}
$cfg = (static function (string $file): ?array {
    include $file;

    return $connection['database'] ?? null;
})($connectionFile);
if (!is_array($cfg)) {
    echo "Village spend contract passed (static shape; database check skipped).\n";
    exit(0);
}
mysqli_report(MYSQLI_REPORT_OFF);
$open = static fn () => @new mysqli($cfg['hostname'], $cfg['username'], $cfg['password'], $cfg['database']);
$a = $open();
$b = $open();
if ($a->connect_errno || $b->connect_errno) {
    echo "Village spend contract passed (static shape; database unreachable, check skipped).\n";
    exit(0);
}
$table = 'zz_spend_contract_' . getmypid();
$a->query("CREATE TABLE `$table` (kid INT PRIMARY KEY, wood DOUBLE NOT NULL, clay DOUBLE NOT NULL, iron DOUBLE NOT NULL, crop DOUBLE NOT NULL, maxstore INT NOT NULL, maxcrop INT NOT NULL) ENGINE=InnoDB");
try {
    $a->query("INSERT INTO `$table` VALUES (1, 100, 100, 100, 100, 800, 800)");
    $spend = static function (mysqli $c, array $cost) use ($table): int {
        $stmt = $c->prepare("UPDATE `$table` SET wood=wood-?, clay=clay-?, iron=iron-?, crop=crop-? WHERE kid=? AND wood>=? AND clay>=? AND iron>=? AND crop>=?");
        $kid = 1;
        $stmt->bind_param('ddddidddd', $cost[0], $cost[1], $cost[2], $cost[3], $kid, $cost[0], $cost[1], $cost[2], $cost[3]);
        $stmt->execute();

        return $stmt->affected_rows;
    };
    // Both sessions saw 100 wood; both try to pay 60 inside open transactions.
    $a->begin_transaction();
    $b->begin_transaction();
    $first = $spend($a, [60.0, 0.0, 0.0, 0.0]);
    $a->commit();
    $second = $spend($b, [60.0, 0.0, 0.0, 0.0]);
    $b->commit();
    if ($first !== 1 || $second !== 0) {
        $fail("Exactly one of two racing payments may succeed (got {$first}/{$second}).");
    }
    $wood = (float)$a->query("SELECT wood FROM `$table` WHERE kid=1")->fetch_row()[0];
    if ($wood !== 40.0) {
        $fail("Wood must be 40 after one payment, got {$wood}.");
    }
    // A zero-cost component never blocks and stock never goes negative.
    if ($spend($a, [40.0, 100.0, 100.0, 100.0]) !== 1) {
        $fail('An exact-funds payment must succeed.');
    }
    if ($spend($a, [1.0, 0.0, 0.0, 0.0]) !== 0) {
        $fail('An empty store must reject any payment.');
    }
    // Refund clamps to the warehouse.
    $a->query("UPDATE `$table` SET wood=LEAST(maxstore, wood+5000) WHERE kid=1");
    $wood = (float)$a->query("SELECT wood FROM `$table` WHERE kid=1")->fetch_row()[0];
    if ($wood !== 800.0) {
        $fail("A refund must clamp to maxstore, got {$wood}.");
    }
} finally {
    $a->query("DROP TABLE IF EXISTS `$table`");
}

echo "Village spend contract passed.\n";
