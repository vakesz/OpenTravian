<?php

declare(strict_types=1);

function assertInstallerAccountFlow(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$setup = file_get_contents($root . '/game/deployment/public/setup.php')
    . file_get_contents($root . '/game/deployment/include/setup-view.php')
    . file_get_contents($root . '/game/include/Game/Installer/InstallInput.php')
    . file_get_contents($root . '/game/include/Game/Installer/WorldInstaller.php');
$installer = file_get_contents($root . '/game/include/Model/InstallerModel.php');

assertInstallerAccountFlow(
    $setup !== false && $installer !== false,
    'Could not read the installer account-flow sources.'
);

assertInstallerAccountFlow(
    str_contains($setup, "\$_SESSION['travian_setup_clear']")
        && str_contains($setup, "\$_POST['setup_action'] === 'confirm_clear'")
        && str_contains($setup, '$clearConfirmed = true')
        && str_contains($setup, 'if ($clearExisting) {')
        && str_contains($setup, 'self::clearDatabases(')
        && str_contains($setup, 'No, keep existing data')
        && str_contains($setup, 'Yes, clear and install'),
    'The installer must clear non-empty databases only after an explicit Yes/No confirmation.'
);

assertInstallerAccountFlow(
    str_contains($setup, "\$values['create_starter_player'] = isset(\$submitted['create_starter_player'])")
        && str_contains($setup, 'Create a starter player with a first village')
        && str_contains($setup, "if (\$values['create_starter_player'] === '1')")
        && str_contains($setup, "if (!empty(\$values['create_starter_player']))"),
    'Starter-player creation must remain an explicit installer choice.'
);

foreach ([
    "in_array((int)\$values['player_race'], \$values['playable_tribes'], true)",
    "'Egyptians', 7 => 'Huns', 8 => 'Spartans', 9 => 'Vikings'",
    'foreach ($setupTribeNames as $tribeId => $tribeName)',
    'name="playable_tribes[]"',
    "\$register->addUser(",
    "\$register->createBaseVillage(\$playerId, \$values['player_name'], \$race, \$playerKid)",
] as $requiredStarterBehavior) {
    assertInstallerAccountFlow(
        str_contains($setup, $requiredStarterBehavior),
        sprintf('The starter-player installer flow must include %s.', $requiredStarterBehavior)
    );
}

$finalizePosition = strpos($setup, "self::seedMap(\$values['multihunter_password']);");
$starterPosition = strpos($setup, "if (!empty(\$values['create_starter_player']))");
assertInstallerAccountFlow(
    $finalizePosition !== false && $starterPosition !== false && $starterPosition > $finalizePosition,
    'The playable starter account must be created after the Multihunter account.'
);
assertInstallerAccountFlow(
    str_contains($installer, 'Password::hash($multihunterPassword)')
        && str_contains($installer, '$register->createBaseVillage(2, "Multihunter", 1, Formulas::xy2kid(1, 0));'),
    'The installer must retain its dedicated Multihunter account identity.'
);

echo "Installer account-flow contract passed.\n";
