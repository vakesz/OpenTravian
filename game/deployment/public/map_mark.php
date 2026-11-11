<?php

declare(strict_types=1);

$_SERVER['REQUEST_URI'] = '/map_mark.php?' . ($_SERVER['QUERY_STRING'] ?? '');

require __DIR__ . '/index.php';
