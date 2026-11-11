<?php

declare(strict_types=1);

// [color=] must never let a player inject CSS or markup: only the sixteen CSS colour names, as the
// reference accepts, are styled; everything else (hex values included) stays as typed.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "BBCode colour runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Helper\BBCode;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$render = static fn (string $bbcode): string => BBCode::translateMessagesBBCode($bbcode);

foreach (['[color=red]x[/color]' => 'color:red;', '[color=white]x[/color]' => 'color:white;', '[color=aqua]x[/color]' => 'color:aqua;'] as $input => $expected) {
    if (!str_contains($render($input), '<span style="' . $expected . '">x</span>')) {
        $fail("{$input} must render a coloured span.");
    }
}
foreach ([
    '[color=red;background:url(//evil.example/x)]x[/color]',
    '[color=red" onmouseover="alert(1)]x[/color]',
    "[color=red' onmouseover='alert(1)]x[/color]",
    '[color=expression(alert(1))]x[/color]',
    '[color=url(javascript:alert(1))]x[/color]',
    '[color=#12]x[/color]',
    '[color=#f00]x[/color]',
    '[color=RED]x[/color]',
    '[color=notacolor]x[/color]',
] as $input) {
    $output = $render($input);
    if (str_contains($output, '<') || str_contains($output, '"') || str_contains($output, "'")) {
        $fail("{$input} must stay inert text without markup or live quotes; got: {$output}");
    }
}
echo "BBCode colour runtime passed.\n";
