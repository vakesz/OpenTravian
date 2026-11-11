<?php

declare(strict_types=1);

/**
 * Source contract: Village constructor accrues resources with miliseconds() and
 * persists wood/clay/iron/crop/lastmupdate so vdata does not drift from the HUD.
 */

function failVillageLoadAccrualPersistContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$villagePath = $root . '/game/include/Core/Village.php';
$source = file_get_contents($villagePath);

if ($source === false) {
    failVillageLoadAccrualPersistContract('Could not read Village.php.');
}

if (!preg_match(
    '/public function __construct\(\$session = null\)\s*\{(?P<body>.*?)\n    \}/s',
    $source,
    $constructorMatch
)) {
    failVillageLoadAccrualPersistContract('Village constructor could not be located.');
}

$constructorBody = $constructorMatch['body'];

foreach ([
    'miliseconds()',
    "\$this->village['wood']",
    "\$this->village['clay']",
    "\$this->village['iron']",
    "\$this->village['crop']",
    "\$this->village['lastmupdate']",
    'persistAccruedResources',
] as $token) {
    if (!str_contains($constructorBody, $token)) {
        failVillageLoadAccrualPersistContract(
            'Village constructor must accrue resources with miliseconds() and persist them via persistAccruedResources.'
        );
    }
}

if (!str_contains($constructorBody, '$elapsedTime > 0')
    || !str_contains($constructorBody, 'previousLastmupdate')) {
    failVillageLoadAccrualPersistContract(
        'Load-time accrual persistence must skip zero elapsed time and keep the prior lastmupdate for the write guard.'
    );
}

if (!preg_match(
    '/private function persistAccruedResources\(int \$previousLastmupdate\):\s*bool\s*\{(?P<body>.*?)\n    \}/s',
    $source,
    $persistMatch
)) {
    failVillageLoadAccrualPersistContract('persistAccruedResources(int): bool must exist on Village.');
}

$persistBody = $persistMatch['body'];

if (!str_contains($persistBody, 'UPDATE vdata SET wood=?, clay=?, iron=?, crop=?, lastmupdate=? WHERE kid=? AND lastmupdate=?')) {
    failVillageLoadAccrualPersistContract(
        'persistAccruedResources must write absolute accrued stores and guard with AND lastmupdate=? to avoid double accrual.'
    );
}

foreach ([
    "\$this->village['wood']",
    "\$this->village['clay']",
    "\$this->village['iron']",
    "\$this->village['crop']",
    "\$this->village['lastmupdate']",
    "\$this->village['kid']",
    '$previousLastmupdate',
] as $token) {
    if (!str_contains($persistBody, $token)) {
        failVillageLoadAccrualPersistContract(
            'persistAccruedResources must bind wood, clay, iron, crop, lastmupdate, kid, and the previous lastmupdate.'
        );
    }
}

if (str_contains($source, 'function modifyResources(')
    || !str_contains($source, 'public function spend(array $costs): bool')) {
    failVillageLoadAccrualPersistContract('Resource spending must be the guarded relative spend() path, not an absolute write.');
}

echo "Village load-accrual persist contract passed.\n";
