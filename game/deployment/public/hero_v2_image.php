<?php

declare(strict_types=1);

// No session: hero pictures are public, and an image must not wait for the
// player's session lock while another of their requests holds it.
define('TRAVIAN_WITHOUT_SESSION', true);

$_SERVER['REQUEST_URI'] = '/hero_v2_image.php?' . ($_SERVER['QUERY_STRING'] ?? '');

require __DIR__ . '/index.php';
