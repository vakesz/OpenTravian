<?php

declare(strict_types=1);

/**
 * Contract: the game guest shell emits mobileOptimized. Logged-in desktop
 * pages remain `village1 ltr` without it; there the class gates
 * Travian.Game.Layout.MobileOptimizations → mobileForced under narrow portrait.
 * The standalone public Homepage must opt out of that game-only viewport path.
 */

function failMobileOptimizedBodyContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
if ($layout === false) {
    failMobileOptimizedBodyContract('Unable to read Layout.php');
}

if (preg_match('/\$bodyClasses\s*=\s*implode\([\s\S]*?"mobileOptimized"\s*,/', $layout)
    && !str_contains($layout, '$emitMobileOptimized')) {
    failMobileOptimizedBodyContract('Layout must not hardcode mobileOptimized into every body class.');
}

foreach (['$useGameMobileOptimizations', '$emitMobileOptimized', 'HTTP_USER_AGENT', 'Android|webOS|iPhone', '!$layoutSession->isValid()'] as $needle) {
    if (!str_contains($layout, $needle)) {
        failMobileOptimizedBodyContract('Layout missing mobile UA gate: ' . $needle);
    }
}

if (!str_contains($layout, '$emitMobileOptimized = $useGameMobileOptimizations')) {
    failMobileOptimizedBodyContract('Only views using the game mobile runtime may emit mobileOptimized.');
}

if (!str_contains($layout, '$emitMobileOptimized ? "mobileOptimized" : ""')) {
    failMobileOptimizedBodyContract('Layout must conditionally append mobileOptimized.');
}
if (!str_contains($layout, '<?php if ($useGameMobileOptimizations): ?>')
    || !str_contains($layout, 'Travian.Game.Layout.MobileOptimizations.init();')) {
    failMobileOptimizedBodyContract('The game mobile initializer must be conditionally rendered with its body class.');
}

fwrite(STDOUT, "Mobile-optimized body contract passed.\n");
