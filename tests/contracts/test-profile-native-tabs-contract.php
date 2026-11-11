<?php

declare(strict_types=1);

$template = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/profile/menu.php');
$controller = file_get_contents(__DIR__ . '/../../game/include/Controller/ProfileCtrl.php');
$editProfile = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/profile/EditProfile.php');
$model = file_get_contents(__DIR__ . '/../../game/include/Model/ProfileModel.php');
$layout = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/layout/Layout.php');
$tabStrip = file_get_contents(__DIR__ . '/../../game/include/resources/Templates/layout/tabStrip.php');
$router = file_get_contents(__DIR__ . '/../../game/include/mainInclude.php');

if ($template === false || $controller === false || $editProfile === false || $model === false || $layout === false || $tabStrip === false || $router === false) {
    fwrite(STDERR, "Unable to read the profile navigation sources.\n");
    exit(1);
}

foreach (
    [
        'layout/tabStrip',
        '"wrapperClass" => "tabFavorWrapper"',
        'T("Profile", "Overview")',
        'T("Profile", "Edit Profile")',
        '"href" => "/profile"',
        '"href" => "/profile/edit"',
        '"reactState" => true',
        '"bareAnchors" => true',
        "window.location.href = '/profile/overview'",
    ]
    as $fragment
) {
    if (!str_contains($template, $fragment)) {
        fwrite(STDERR, "Profile navigation is missing native-tab fragment: {$fragment}\n");
        exit(1);
    }
}

if (str_contains($template, '<nav') || str_contains($template, "trigger('tabClicked'")) {
    fwrite(STDERR, "Profile navigation must not keep legacy tab click proxies.\n");
    exit(1);
}

if (str_contains($template, 'spieler.php') || str_contains($template, '?s=')) {
    fwrite(STDERR, "Profile tabs must use clean /profile and /profile/edit routes.\n");
    exit(1);
}

if (
    !str_contains($router, "'profile' => 'ProfileCtrl'") ||
    !str_contains($router, "'/profile/edit'") ||
    !str_contains($router, "'/profile/overview'") ||
    !str_contains($router, "\$page = 'profile'")
) {
    fwrite(STDERR, "The /profile and /profile/edit clean aliases must dispatch to ProfileCtrl.\n");
    exit(1);
}

if (!str_contains($tabStrip, '$bareAnchors = !empty($vars[\'bareAnchors\']);')
    || !str_contains($tabStrip, '$bareAnchors ? \'\' :')) {
    fwrite(STDERR, "The shared tab strip must support the bare React anchor shell.\n");
    exit(1);
}
if (str_contains($tabStrip, 'aria-current="page"')) {
    fwrite(STDERR, "The shared tab strip must not add aria-current.\n");
    exit(1);
}

if (
    !str_contains($controller, "vars['titleInHeader'] = \$this->selectedPlayerData['name']") ||
    str_contains($controller, 'T("Profile", "Player Profile")')
) {
    fwrite(STDERR, "Profile h1 must be the player name, not the Player Profile label.\n");
    exit(1);
}

if (
    !str_contains($controller, "bodyCssClass'] = 'playerProfile'") ||
    !str_contains($controller, 'playerProfileOverview') ||
    !str_contains($controller, 'playerProfileEdit')
) {
    fwrite(STDERR, "Profile must use reference body/content tokens playerProfile + Overview|Edit.\n");
    exit(1);
}

if (
    !str_contains($controller, "(int) \$_REQUEST['s']") ||
    !str_contains($controller, "\$selectedTab === 2 ? 'playerProfileEdit'")
) {
    fwrite(
        STDERR,
        "Profile Edit content class must cast request tab to int (s=2 string must not stick on Overview).\n"
    );
    exit(1);
}

if (str_contains($controller, 'WebService::isPost()')
    || str_contains($controller, "WebService::redirect('/profile/edit')")
    || str_contains($controller, "\$_POST['ort']")
    || str_contains($controller, "\$_POST['be1']")
    || str_contains($controller, "\$_POST['be2']")) {
    fwrite(STDERR, "Profile routes must not retain the obsolete direct POST save fallback.\n");
    exit(1);
}

if (str_contains($model, 'public function updateProfile(')) {
    fwrite(STDERR, "Profile model must not retain the obsolete direct-page update helper.\n");
    exit(1);
}

if (
    !str_contains($editProfile, 'class="formV2 "') ||
    !str_contains($editProfile, 'name="location"') ||
    !str_contains($editProfile, 'name="gender"') ||
    !str_contains($editProfile, 'name="description1"') ||
    !str_contains($editProfile, 'genderOptions') ||
    !str_contains($editProfile, 'editPlayerLanguages') ||
    !str_contains($editProfile, 'medals lifetime') ||
    !str_contains($editProfile, 'villages borderGap') ||
    str_contains($editProfile, 'id="editDetails"') ||
    str_contains($editProfile, 'name="ort"') ||
    str_contains($editProfile, 'name="mw"')
) {
    fwrite(STDERR, "Profile edit form must use the reference formV2 chrome, not the legacy details table.\n");
    exit(1);
}

if (str_contains($editProfile, 'action="/profile/edit"')
    || str_contains($editProfile, 'method="post"')
    || str_contains($editProfile, 'name="uid"')
    || str_contains($editProfile, 'name="did"')) {
    fwrite(STDERR, "Profile editor must retain the bare React form DOM.\n");
    exit(1);
}

if (str_contains($editProfile, 'profileDescription1')
    || str_contains($editProfile, 'profileDescription2')
    || str_contains($editProfile, 'addLanguageButton rectangle withIcon green" type="button" disabled')
    || !str_contains($editProfile, "Travian.api('profile',")) {
    fwrite(STDERR, "Profile editor must expose the enabled native language control and V2 save flow.\n");
    exit(1);
}

if (str_contains($editProfile, 'UnloadHelper.watchHtmlForm')) {
    fwrite(STDERR, "Profile edit must leave the reference Save button enabled before a change.\n");
    exit(1);
}

if (!str_contains($layout, 'href="/profile"') || preg_match('/href="spieler\.php"/', $layout) === 1) {
    fwrite(STDERR, "Shell profile links must use /profile, not spieler.php.\n");
    exit(1);
}

echo "Profile native tab contract passed.\n";
