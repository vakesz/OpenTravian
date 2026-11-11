<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static function (string $path): string {
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}.");
    }
    return $contents;
};
$languages = $read($root . '/game/include/Game/ProfileLanguages.php');
$picker = $read($root . '/game/include/Controller/Ajax/profileLanguages.php');
$profile = $read($root . '/game/include/Controller/Ajax/profile.php');
$routes = $read($root . '/game/deployment/include/api_legacy_routes.php');
$ajax = $read($root . '/game/include/Controller/AjaxCtrl.php');
$schema = $read($root . '/database/world.sql');
$update = $read($root . '/game/include/update.php');
$schemaVersion = $read($root . '/game/include/Game/WorldSchemaVersion.php');
$template = $read($root . '/game/include/resources/Templates/profile/EditProfile.php');

foreach ([
    "'de-DE' => ['flag' => 'de', 'language' => 'Deutsch', 'country' => 'Deutschland']",
    "'en-GB' => ['flag' => 'uk', 'language' => 'English', 'country' => 'United Kingdom']",
    "'en-US' => ['flag' => 'us', 'language' => 'English', 'country' => 'United States']",
    "'vi-VN' => ['flag' => 'vn', 'language' => 'Tiếng Việt', 'country' => 'Việt Nam']",
    'return array_slice($normalized, 0, 4);',
    'id="dialogLanguagesList"',
    'class="languagesGroup allLanguages"',
] as $fragment) {
    if (!str_contains($languages, $fragment)) {
        throw new RuntimeException("Profile-language reference data is missing {$fragment}.");
    }
}

if (substr_count($languages, "'flag' =>") !== 51) {
    throw new RuntimeException('The profile-language picker must retain all 51 locale choices.');
}
if (!str_contains($routes, "'profile' => [null, 'profile']")
    || !str_contains($routes, "'profile-languages' => [null, 'profile-languages']")
    || !str_contains($ajax, "Content-Type: text/html; charset=UTF-8")
    || !str_contains($picker, 'ProfileLanguageCatalog::pickerHtml')) {
    throw new RuntimeException('The V2 profile-language endpoint must be routed and return HTML.');
}
if (!str_contains($profile, 'WebService::isPut()')
    || !str_contains($profile, "'additionalLanguages'")
    || !str_contains($profile, "'villageNames'")) {
    throw new RuntimeException('The V2 profile save must accept the PUT payload.');
}
$villageValidation = strpos($profile, '$villageNames = $_POST[\'villageNames\'] ?? [];');
$profileUpdate = strpos($profile, '$model->updateProfileV2(');
if ($villageValidation === false || $profileUpdate === false || $villageValidation > $profileUpdate) {
    throw new RuntimeException('The V2 profile save must validate village input before changing profile data.');
}
if (!str_contains($schema, '`additionalLanguages` VARCHAR(512) NOT NULL DEFAULT \'[]\'')
    || preg_match('/public const CURRENT = (\d+);/', $schemaVersion, $patchVersion) !== 1
    || (int)$patchVersion[1] < 105
    || !str_contains($update, 'ADD COLUMN IF NOT EXISTS `additionalLanguages`')) {
    throw new RuntimeException('Profile-language persistence needs the fresh-world schema and the patch-history step.');
}
if (!str_contains($template, 'class="delete"')
    || !str_contains($template, "Travian.api('profile-languages?'")
    || !str_contains($template, "Travian.api('profile',")) {
    throw new RuntimeException('The profile editor must render/remove languages and persist through the V2 API.');
}

echo "Profile language contract passed.\n";
