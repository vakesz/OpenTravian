<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/game/deployment/include/env.php';
foreach (['WorldSpeedProfile', 'FinishConstruction', 'Tribes', 'WorldOverrides', 'WorldSchemaVersion', 'SupportContact', 'SmtpConfig', 'Installer/InstallInput', 'Installer/WorldInstaller'] as $class) {
    require_once dirname(__DIR__, 2) . '/game/include/Game/' . $class . '.php';
}

use Game\Installer\InstallInput;

function expectOptionalSmtp(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$defaults = InstallInput::defaults();
$base = [
    'setup_mode' => 'simple',
    'database_password' => 'db-secret',
    'multihunter_password' => 'twelve-chars-ok',
    'create_starter_player' => '0',
];

// No SMTP host: valid install, no SMTP config, no support recipient required.
[$values, $errors] = InstallInput::fromSubmission($base, $defaults);
expectOptionalSmtp($errors === [] && $values['smtp'] === null, 'An install without SMTP must validate and carry no SMTP config.');

// A recipient on its own is still validated.
[, $errors] = InstallInput::fromSubmission($base + ['support_email' => 'not-an-email'], $defaults);
expectOptionalSmtp(count($errors) === 1, 'A malformed support recipient must be rejected even without SMTP.');

// An SMTP host makes the rest of the email settings required.
$smtp = $base + ['smtp_host' => 'smtp.example.test', 'smtp_from' => 'noreply@example.test', 'smtp_auth' => '0'];
[, $errors] = InstallInput::fromSubmission($smtp, $defaults);
expectOptionalSmtp($errors !== [], 'SMTP without a support recipient must be rejected.');
[$values, $errors] = InstallInput::fromSubmission($smtp + ['support_email' => 'support@example.test'], $defaults);
expectOptionalSmtp($errors === [] && is_array($values['smtp']) && $values['smtp']['host'] === 'smtp.example.test', 'A complete SMTP block must validate.');

// The wizard and the CLI must go through the shared installer.
$root = dirname(__DIR__, 2);
$wizard = (string)file_get_contents($root . '/game/deployment/public/setup.php');
$cli = (string)file_get_contents($root . '/tools/install.php');
foreach ([$wizard, $cli] as $entry) {
    expectOptionalSmtp(str_contains($entry, 'InstallInput::fromSubmission(') && str_contains($entry, 'WorldInstaller::install('), 'Wizard and CLI must both use the shared installer.');
}
expectOptionalSmtp(!str_contains($wizard, 'function setup_import') && !str_contains($cli, 'CREATE DATABASE'), 'Install logic must live only in Game\\Installer.');

echo "Installer optional-SMTP contract passed.\n";
