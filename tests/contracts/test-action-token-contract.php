<?php

declare(strict_types=1);

// One stable session token replaces the rotating per-village checker; double submits are
// handled by Session::claimAction(), and no code may compare the token with ==.

function failActionToken(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

foreach (['game/include/Core/Village.php', 'database/world.sql', 'game/include/config.php'] as $file) {
    $source = $read($file);
    if (stripos($source, 'checker') !== false && $file !== 'game/include/config.php') {
        failActionToken("$file must not keep the per-village checker.");
    }
    if (str_contains($source, 'useSessionCheckerInsteadOfDB')) {
        failActionToken("$file must not keep the useSessionCheckerInsteadOfDB switch.");
    }
}

$session = $read('game/include/Core/Session.php');
foreach ([
    'private static function sessionToken(): string',
    'bin2hex(random_bytes(16))',
    'public static function claimAction(',
    'public static function claimRequest(): bool',
    'hash_equals(self::sessionToken(), trim($submitted))',
] as $needle) {
    if (!str_contains($session, $needle)) {
        failActionToken("Session must keep: $needle");
    }
}
if (str_contains($session, 'changeChecker') || str_contains($session, "'logout_token'")) {
    failActionToken('Session must not rotate the checker or keep a second logout token.');
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/game/include', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }
    $source = (string)file_get_contents($file->getPathname());
    $name = substr($file->getPathname(), strlen($root) + 1);
    if (str_contains($source, 'changeChecker')) {
        failActionToken("$name still calls changeChecker().");
    }
    if (preg_match('/(?:==|!=)=?\s*(?:\\\\?Core\\\\)?(?:Session::getInstance\(\)|\$(?:this->)?session)->getChecker\(\)|getChecker\(\)\s*(?:==|!=)/', $source)) {
        failActionToken("$name compares the token with ==; use Session::claimAction() or matchesChecker().");
    }
}

// Build links redirect after acting so a reload cannot repeat an upgrade.
foreach (['ResourceFieldsCtrl' => '/dorf1.php', 'VillageCenterCtrl' => '/dorf2.php'] as $controller => $page) {
    $source = $read("game/include/Controller/$controller.php");
    if (!str_contains($source, "WebService::redirect('$page')") || !str_contains($source, 'BuildRequest::handle(')) {
        failActionToken("$controller must claim the build link and redirect to $page afterwards.");
    }
}

echo "Action token contract passed.\n";
