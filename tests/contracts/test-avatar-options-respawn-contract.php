<?php

declare(strict_types=1);

/**
 * Contract: Avatar options tab matches shell (rename → colorblind →
 * respawn → delete), no legacy password/email/gpack/newsletter, and
 * beginner-protection respawn routes to /respawn.
 */

function failAvatarOptionsRespawnContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$account = file_get_contents($root . '/game/include/resources/Templates/options/Account.php');
$optionCtrl = file_get_contents($root . '/game/include/Controller/OptionCtrl.php');
$respawnCtrl = file_get_contents($root . '/game/include/Controller/RespawnCtrl.php');
$respawnModel = file_get_contents($root . '/game/include/Model/AvatarRespawnModel.php');
$mainInclude = file_get_contents($root . '/game/include/mainInclude.php');
$wizard = file_get_contents($root . '/game/include/resources/Templates/activation/wizard.php');
$en = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

foreach ([$account, $optionCtrl, $respawnCtrl, $respawnModel, $mainInclude, $wizard, $en] as $source) {
    if ($source === false) {
        failAvatarOptionsRespawnContract('Unable to read avatar/respawn sources.');
    }
}

foreach ([
    'id="change_account_name"',
    'id="colorblind"',
    'id="respawnAvatar"',
    'id="del_acc"',
    'function avatarDelete()',
    'function avatarRespawn()',
    "Travian.redirectTo('/respawn')",
    'placeholder="respawn"',
    'placeholder="delete"',
    'AvatarNameChange(getAccountRenameValues())',
    'name="a"',
] as $needle) {
    if (!str_contains($account, $needle)) {
        failAvatarOptionsRespawnContract('Account template missing avatar shell piece: ' . $needle);
    }
}

foreach (['name="pw1"', 'name="email_alt"', 'name="gpackNew"', 'newsletter_posted', 'del_pw', 'change_pass'] as $legacy) {
    if (str_contains($account, $legacy)) {
        failAvatarOptionsRespawnContract('Account template must not keep legacy Avatar section: ' . $legacy);
    }
}

if (!str_contains($optionCtrl, "\$view->vars['canRespawn']")) {
    failAvatarOptionsRespawnContract('OptionCtrl must pass canRespawn into the Avatar template.');
}

if (!str_contains($optionCtrl, "isset(\$_POST['free_rename'])")) {
    failAvatarOptionsRespawnContract('Free rename must only run when the free_rename submit is posted.');
}

$avatarChecker = strpos($optionCtrl, "Session::matchesChecker(\$_POST['a'] ?? null)");
$avatarMutation = strpos($optionCtrl, "\$_POST['colorblind']");
if ($avatarChecker === false || $avatarMutation === false || $avatarChecker > $avatarMutation) {
    failAvatarOptionsRespawnContract('Avatar changes must validate the native a checker before mutating preferences.');
}

foreach (['gpackNew', 'autoReload', 'email_alt', 'code_email_alt', 'newsletter_posted', "\$_POST['pw1']"] as $legacy) {
    if (str_contains($optionCtrl, $legacy)) {
        failAvatarOptionsRespawnContract('OptionCtrl must not retain the retired Avatar write path: ' . $legacy);
    }
}

if (!str_contains($mainInclude, "'respawn' => 'RespawnCtrl'")) {
    failAvatarOptionsRespawnContract('mainInclude must dispatch /respawn to RespawnCtrl.');
}

if (!str_contains($mainInclude, "'avatar' => '2'")
    || !str_contains($mainInclude, 'account|avatar|sitter|vacation')) {
    failAvatarOptionsRespawnContract('mainInclude must alias /options/avatar onto the Avatar preferences tab.');
}

foreach ([
    'class RespawnCtrl extends GameCtrl',
    "bodyCssClass'] = 'activate'",
    "'action' => '/respawn'",
    'AvatarRespawnModel',
] as $needle) {
    if (!str_contains($respawnCtrl, $needle)) {
        failAvatarOptionsRespawnContract('RespawnCtrl missing: ' . $needle);
    }
}

foreach ([
    'function canRespawn',
    'function respawn',
    'deleteVillage((int) $row[\'kid\'], false)',
    'createBaseVillage',
    'gift_gold',
    'bought_gold',
] as $needle) {
    if (!str_contains($respawnModel, $needle)) {
        failAvatarOptionsRespawnContract('AvatarRespawnModel missing: ' . $needle);
    }
}

if (!str_contains($wizard, "\$action = (string) \$vars['action'];")
    || !str_contains($respawnCtrl, "PHPBatchView::render('activation/wizard'")
    || !str_contains($respawnCtrl, "'action' => '/respawn'")) {
    failAvatarOptionsRespawnContract('/respawn must reuse the activation wizard with its own form action.');
}

foreach ([
    "Change account name'] = 'Change avatar name'",
    "Delete account'] = 'Delete avatar'",
    "['confirmation']['typeExactText'] = 'Please type the exact text displayed above to confirm.'",
] as $needle) {
    if (!str_contains($en, $needle)) {
        failAvatarOptionsRespawnContract('en-US missing avatar copy: ' . $needle);
    }
}

fwrite(STDOUT, "Avatar options/respawn contract passed.\n");
