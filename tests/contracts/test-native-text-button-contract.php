<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$buttonHelper = file_get_contents($root . '/game/include/functions.general.php');

if ($buttonHelper === false) {
    fwrite(STDERR, "Cannot read the shared button helper.\n");
    exit(1);
}

$layoutBranch = "if (str_contains(\$buttonClass, 'layoutButton'))";
$legacyChrome = '<div class="button-container addHoverClick">';
$directContent = '$output .= $content;';

if (!str_contains($buttonHelper, $layoutBranch)) {
    fwrite(STDERR, "Shared buttons must keep layoutButton chrome gated separately from text buttons.\n");
    exit(1);
}

$layoutOffset = strpos($buttonHelper, $layoutBranch);
$legacyOffset = strpos($buttonHelper, $legacyChrome, $layoutOffset);
$directContentOffset = strpos($buttonHelper, $directContent, $layoutOffset);

if ($legacyOffset === false || $directContentOffset === false || $legacyOffset > $directContentOffset) {
    fwrite(STDERR, "layoutButton must emit icon chrome before the flat text-button content path.\n");
    exit(1);
}

if (str_contains($buttonHelper, 'button-background') || str_contains($buttonHelper, 'button-content')) {
    fwrite(STDERR, "Shared buttons must not emit nested Button V1 background/content chrome.\n");
    exit(1);
}

foreach ([
    "if (\$k === 'value')",
    "value=\"' . htmlspecialchars((string) \$v, ENT_QUOTES, 'UTF-8') . '\"'",
] as $fragment) {
    if (!str_contains($buttonHelper, $fragment)) {
        fwrite(STDERR, "Shared buttons must preserve their escaped HTML value attribute.\n");
        exit(1);
    }
}

echo "Native text button contract passed.\n";
