<?php

declare(strict_types=1);

// The canonical implementation lives in the game; the API loads it through its
// autoloader so both hash and validate passwords identically.
$canonicalPassword = dirname(__DIR__, 5) . '/game/include/Core/Security/Password.php';
if (!is_file($canonicalPassword)) {
    throw new \RuntimeException('Missing ' . $canonicalPassword . '; services/api must run inside the repository.');
}
require_once $canonicalPassword;
