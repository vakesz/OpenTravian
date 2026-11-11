<?php

declare(strict_types=1);

function failAutoReloadDefaultContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$schema = file_get_contents(dirname(__DIR__, 2) . '/database/world.sql');
$session = file_get_contents(dirname(__DIR__, 2) . '/game/include/Core/Session.php');
$view = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/View/GameView.php');
$head = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/layout/head.php');

if ($schema === false || $session === false || $view === false || $head === false) {
    failAutoReloadDefaultContract('Unable to read the auto-reload sources.');
}

if (!str_contains($schema, "`display` VARCHAR(32) NOT NULL DEFAULT '0,10,10,0,1,0,1'")) {
    failAutoReloadDefaultContract('Fresh players must enable construction-completion reload by default.');
}

if (!str_contains($session, "\$data['display'][4] = isset(\$data['display'][4]) ? (int)\$data['display'][4] : 1;")) {
    failAutoReloadDefaultContract('Incomplete legacy display vectors must inherit the completion-reload default.');
}

if (
    !str_contains(
        $view,
        "\$this->vars['autoReload'] = !getDisplay('allowAutoReloadSettingChange') || \$this->session->getDisplay()[4];"
    )
    || !str_contains($head, 'window.reload_enabled = false;')
) {
    failAutoReloadDefaultContract('The player setting must still control client timer reload behaviour.');
}

echo "Auto-reload default contract passed.\n";
