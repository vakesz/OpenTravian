<?php

declare(strict_types=1);

function failLinkListEditorContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/LinkListCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/linklist/main.php');
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$router = file_get_contents($root . '/game/include/mainInclude.php');

if ($controller === false || $template === false || $translation === false || $router === false) {
    failLinkListEditorContract('Link-list editor sources must be readable.');
}

if (!str_contains($controller, "\$this->view->vars['bodyCssClass'] = 'linklist';")) {
    failLinkListEditorContract('The link-list editor body class must be linklist.');
}

if (str_contains($template, 'boxes boxesColor gray recommendedLinks')) {
    failLinkListEditorContract('Recommended links must use the V2 shell without the legacy boxes chrome.');
}

if (!str_contains($template, '<div class="recommendedLinks">')) {
    failLinkListEditorContract('Recommended links must render the V2 recommendedLinks container.');
}

if (!str_contains($template, 'action="/linklist.php"')) {
    failLinkListEditorContract('The editor form must post to the clean /linklist.php path.');
}

foreach (
    [
        "'url' => '/village/statistics'",
        "'url' => '/alliance/reports'",
        "'url' => '/report/surrounding'",
        "'url' => '/options'",
    ] as $needle
) {
    if (!str_contains($translation, $needle)) {
        failLinkListEditorContract('Recommended links must use the reference labels and clean targets.');
    }
}

if (str_contains($translation, "'name' => 'Knowledge Base'")) {
    failLinkListEditorContract('Recommended links must not offer the retired Knowledge Base.');
}

if (str_contains($translation, "'url' => 'reports.php?t=5'")) {
    failLinkListEditorContract('Surrounding Reports must not point at the Archive tab.');
}

foreach (
    [
        "'/village/statistics'",
        "'/alliance/reports'",
        "'/report/surrounding'",
    ] as $route
) {
    if (!str_contains($router, $route)) {
        failLinkListEditorContract("Clean link-list target {$route} must dispatch in mainInclude.");
    }
}

if (!str_contains($router, "'/report/surrounding' => 6")) {
    failLinkListEditorContract('/report/surrounding must dispatch to reports tab 6.');
}

echo "Link-list editor contract passed.\n";
