<?php

declare(strict_types=1);

/**
 * Contract: the nginx Content-Security-Policy allows 'unsafe-inline' but not 'unsafe-eval', so
 * first-party client scripts must never evaluate strings: no setTimeout/setInterval with a string
 * argument, no eval(), no new Function(). A blocked call fails silently in the browser, which once
 * froze every timer and resource counter after a single tick.
 */

$root = dirname(__DIR__, 2);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$template = (string) file_get_contents($root . '/docker/nginx/default.conf.template');
if (!preg_match('/Content-Security-Policy "([^"]+)"/', $template, $csp)) {
    $fail('nginx template no longer sets a Content-Security-Policy; revisit this contract.');
}
if (str_contains($csp[1], "'unsafe-eval'")) {
    $fail("The CSP must not allow 'unsafe-eval'; fix the client code instead.");
}

// First-party scripts only: vendored libraries under public/js/default feature-detect eval themselves.
$files = array_merge(
    glob($root . '/game/deployment/public/*.js') ?: [],
    glob($root . '/assets/gpack/travian/mainPage/js/*.js') ?: [],
);
if ($files === []) {
    $fail('No first-party client scripts found.');
}

$patterns = [
    'string timer' => '/\bset(?:Timeout|Interval)\(\s*["\'`]/',
    'eval()' => '/(?<![\w.$])eval\s*\(/',
    'new Function()' => '/\bnew\s+Function\s*\(/',
];
foreach ($files as $file) {
    $source = (string) file_get_contents($file);
    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) {
            $line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
            $fail(sprintf('%s:%d uses %s, which the CSP blocks.', substr($file, strlen($root) + 1), $line, $label));
        }
    }
}

fwrite(STDOUT, 'CSP no-string-eval contract passed (' . count($files) . " scripts).\n");
