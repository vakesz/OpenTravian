<?php

declare(strict_types=1);

// BBCode must not use the deprecated FILTER_SANITIZE_STRING or "self::" callables, and the
// autocomplete endpoint must bind its search term (behaviour: tests/runtime).

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$bbcode = (string)file_get_contents($root . '/game/include/Core/Helper/BBCode.php');
if (str_contains($bbcode, 'FILTER_SANITIZE_STRING') || str_contains($bbcode, '"self::')) {
    $fail('BBCode must validate or escape explicitly, with no deprecated filter or "self::" callables.');
}
if (!str_contains($bbcode, 'COLOR_NAMES') || str_contains($bbcode, '[0-9a-f]{3}|[0-9a-f]{6}')) {
    $fail('[color=] must be limited to the sixteen CSS colour names.');
}
$autocomplete = (string)file_get_contents($root . '/game/include/Controller/Ajax/autocomplete/playername.php');
if (str_contains($autocomplete, 'real_escape_string') || str_contains($autocomplete, "LIKE '%")) {
    $fail('The autocomplete search term must be bound, not interpolated.');
}
foreach (["addcslashes(\$term, '%_", 'MIN_TERM_LENGTH', 'publicUserCondition', 'LIMIT $limit'] as $needle) {
    if (!str_contains($autocomplete, $needle)) {
        $fail("Autocomplete must contain {$needle}.");
    }
}

echo "Input hardening contract passed.\n";
