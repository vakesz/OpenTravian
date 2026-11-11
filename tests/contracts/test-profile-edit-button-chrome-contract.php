<?php

declare(strict_types=1);

/**
 * Contract: Profile overview Edit Profile button uses textButtonV2 class order.
 */

function failProfileEditButtonChromeContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$profile = file_get_contents($root . '/game/include/resources/Templates/profile/Profile.php');
if ($profile === false) {
    failProfileEditButtonChromeContract('Unable to read Profile.php');
}
if (!str_contains($profile, 'textButtonV2 buttonFramed editProfile rectangle withText green')) {
    failProfileEditButtonChromeContract('Profile Edit Profile button must use textButtonV2 buttonFramed editProfile rectangle withText green.');
}
if (str_contains($profile, 'buttonFramed editProfile green rectangle textButtonV2')) {
    failProfileEditButtonChromeContract('Profile still uses older Edit Profile class order.');
}

$languages = file_get_contents($root . '/game/include/Game/ProfileLanguages.php');
if ($languages === false) {
    failProfileEditButtonChromeContract('Unable to read ProfileLanguages.php');
}
if (!str_contains($languages, 'textButtonV2 buttonFramed rectangle withText green')) {
    failProfileEditButtonChromeContract('Language dialog Add button must use textButtonV2 buttonFramed rectangle withText green.');
}
if (str_contains($languages, 'buttonFramed green withText rectangle')
    || str_contains($languages, '<div>Add<div></div></div>')) {
    failProfileEditButtonChromeContract('Language dialog still uses broken older Add button markup.');
}

echo "Profile edit button chrome contract passed.\n";
