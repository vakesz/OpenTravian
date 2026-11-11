<?php

declare(strict_types=1);

function failLegacyUiInteractionContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$building = file_get_contents($root . '/game/include/resources/Templates/build/buildingReadyWrapper.php');
$manualContent = file_get_contents($root . '/game/include/Game/Manual/ManualContent.php');
$manualEndpoint = file_get_contents($root . '/game/include/Controller/Ajax/manual/building.php');
$reportLayout = file_get_contents($root . '/game/include/resources/Templates/reports/report_layout.php');
$reportController = file_get_contents($root . '/game/include/Controller/ReportsCtrl.php');
$profile = file_get_contents($root . '/game/include/resources/Templates/profile/Profile.php');
$login = file_get_contents($root . '/game/include/resources/Templates/system/login.php');
$forumEdit = file_get_contents($root . '/game/include/resources/Templates/alliance/forum_edit.php');
$newForum = file_get_contents($root . '/game/include/resources/Templates/alliance/newForum.php');
$vacation = file_get_contents($root . '/game/include/resources/Templates/options/Vacation.php');
$translations = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$sitters = file_get_contents($root . '/game/include/resources/Templates/options/Sitters.php');

if (in_array(false, [$layout, $building, $manualContent, $manualEndpoint, $reportLayout, $reportController, $profile, $login, $forumEdit, $newForum, $vacation, $translations, $sitters], true)) {
    failLegacyUiInteractionContract('Legacy UI interaction source files must be readable.');
}

if (str_contains($layout, "Travian.api('logout')") || !str_contains($layout, 'class="logout" href="<?= htmlspecialchars(\\Core\\Session::logoutUrl()')) {
    failLegacyUiInteractionContract('Logout must use its working navigation endpoint.');
}

if (!str_contains($building, "Travian.Game.Manual.open('building', <?= (int)\$vars['itemId']; ?>)")
    || !str_contains($building, 'href="#"')
    || !str_contains($manualEndpoint, '(new ManualContent())->renderBuilding($buildingId)')
    || !str_contains($manualEndpoint, "filter_var(\$_GET['id'] ?? null")
    || str_contains($building, 'manual.php')
    || str_contains($manualContent, 'manual.php')) {
    failLegacyUiInteractionContract('Building manuals must use the maintained dialog renderer without a legacy page fallback.');
}

if (!str_contains($reportLayout, 'Travian.Game.Reports.editRights(this, <?= (int)$vars[\'reportId\']; ?>)')
    || !str_contains($reportLayout, 'reportButton access')
    || !str_contains($reportController, 'showPermissionsButton')) {
    failLegacyUiInteractionContract('Reports must render the maintained permission control for owned combat reports.');
}

if (str_contains($profile, "Travian.api('reportPlayer")) {
    failLegacyUiInteractionContract('Profiles must not retain an unreachable player-report action.');
}

if (str_contains($login, "showOrHide('#arrow'); return false;")
    || !str_contains($login, 'href="<?= $escape($publicRecoveryUrl); ?>"')
    || !str_contains($login, 'action="<?= $escape($publicRecoveryUrl); ?>"')
    || !str_contains($login, ": '/?forgotPassword=true';")
    || str_contains($login, 'href="?forgotPassword=true"')) {
    failLegacyUiInteractionContract('Forgot-password navigation must use the maintained login route, not the retired disclosure widget.');
}

if (str_contains($forumEdit, '.addEvent(')
    || str_contains($newForum, '.addEvent(')
    || substr_count($forumEdit, "jQuery(newInputElement).on('keyup'") !== 2
    || substr_count($newForum, "jQuery(newInputElement).on('keyup'") !== 2) {
    failLegacyUiInteractionContract('Dynamically added alliance-forum rows must bind key handling through jQuery.');
}

if (!str_contains($vacation, 'e.key === "Enter"')
    || !str_contains($vacation, 'inputmode="numeric"')
    || !str_contains($translations, "'While in vacation mode the following actions will be deactivated'] = 'While in vacation mode, the following actions will be deactivated'")) {
    failLegacyUiInteractionContract('Vacation must retain the reference copy and numeric day-input semantics.');
}

if (!str_contains($sitters, 'jQuery(id).text(')
    || str_contains($sitters, '.innerHTML = obj.value.stripTags()')) {
    failLegacyUiInteractionContract('Sitter name mirroring must use the available, safe jQuery text API.');
}

echo "Legacy UI interaction contract passed.\n";
