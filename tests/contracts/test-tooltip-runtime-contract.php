<?php

declare(strict_types=1);

function failTooltipRuntimeContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$head = file_get_contents($root . '/game/include/resources/Templates/layout/head.php');
$crypt = file_get_contents($root . '/game/deployment/public/game-client.js');
$popper = file_get_contents($root . '/game/deployment/public/js/default/popper.js');
$tippy = file_get_contents($root . '/game/deployment/public/js/default/tippy.js');

if (in_array(false, [$head, $crypt, $popper, $tippy], true)) {
    failTooltipRuntimeContract('Could not read the tooltip runtime sources.');
}

$popperTag = '<script type="text/javascript" src="/js/default/popper.js"></script>';
$tippyTag = '<script type="text/javascript" src="/js/default/tippy.js"></script>';
$cryptTag = '<script type="text/javascript" src="<?= get_crypt_js_link() ?>"></script>';

$popperOffset = strpos($head, $popperTag);
$tippyOffset = strpos($head, $tippyTag);
$cryptOffset = strpos($head, $cryptTag);

if ($popperOffset === false || $tippyOffset === false || $cryptOffset === false) {
    failTooltipRuntimeContract('The shared head must load Popper and Tippy before the crypt bundle.');
}

if (!($popperOffset < $tippyOffset && $tippyOffset < $cryptOffset)) {
    failTooltipRuntimeContract('Popper, Tippy, and crypt must execute in dependency order.');
}

if (!preg_match('/\.Popper\s*=/', $popper)) {
    failTooltipRuntimeContract('The Popper runtime must expose its browser global.');
}

if (!preg_match('/\.tippy\s*=/', $tippy)) {
    failTooltipRuntimeContract('The Tippy runtime must expose its browser global.');
}

foreach (['game-client.js' => $crypt] as $name => $bundle) {
    if (!str_contains($bundle, 'tippy.setDefaultProps')) {
        failTooltipRuntimeContract("{$name} must keep the shared tooltip runtime contract.");
    }
}

echo "Tooltip runtime contract passed.\n";
