<?php

declare(strict_types=1);

/**
 * Contract: buttonClicked payloads must echo the rendered button class string.
 */

function failButtonClickedClassContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$cases = [
    'game/include/resources/Templates/messages/note.php' => '"class":"textButtonV1 green "',
    'game/include/resources/Templates/statistics/table.php' => '"class": "textButtonV1 green"',
];

foreach ($cases as $relative => $needle) {
    $contents = file_get_contents($root . '/' . $relative);
    if ($contents === false) {
        failButtonClickedClassContract("Unable to read {$relative}");
    }
    if (!str_contains($contents, $needle)) {
        failButtonClickedClassContract("{$relative} buttonClicked payload missing {$needle}");
    }
    if (preg_match('/"class":\s*"green(\s|")/', $contents)) {
        failButtonClickedClassContract("{$relative} still emits bare green in buttonClicked payload.");
    }
}

echo "buttonClicked class contract passed.\n";
