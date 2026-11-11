<?php

declare(strict_types=1);

function failEmailVerificationClientContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$templates = [
    $root . '/game/include/resources/Templates/verify/inProgress.php',
    $root . '/game/include/resources/Templates/verify/newProgress.php',
];

foreach ($templates as $templatePath) {
    $template = file_get_contents($templatePath);
    if ($template === false
        || !str_contains($template, 'dialog.setContent(a.message || \'Request failed.\');')
        || (
            !str_contains($template, 'switchSubmit(true);')
            && !str_contains($template, "removeClass(\"disabled\")")
        )) {
        failEmailVerificationClientContract('Email-verification failures must show the API message and re-enable the form.');
    }
}

echo "Email-verification client contract passed.\n";
