#!/usr/bin/env php
<?php

declare(strict_types=1);

function failPlayerLabelLocalization(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/** @return mixed */
function playerLabelTranslationValue(array $catalog, string $section, string $key)
{
    $value = $catalog[$section] ?? null;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
}

$root = dirname(__DIR__, 2);

// These are exact player-facing replacements: the canonical en-US result must
// continue to be byte-for-byte equal to the label that was previously inline.
$sourceCalls = [
    'game/include/Controller/Ajax/favouriteTab.php' => [
        "T('map', 'please_resend_all_data')",
    ],
    'game/include/Controller/Ajax/village/changeNames.php' => [
        "T('map', 'please_resend_all_data')",
    ],
    'game/include/Controller/Ajax/village/updateSortIndex.php' => [
        "T('map', 'please_resend_all_data')",
    ],
    'game/include/Controller/ReportsCtrl.php' => [
        "T('Reports', 'Village')",
    ],
    'game/include/Controller/Build/MarketPlaceCtrl.php' => [
        "T('MarketPlace', 'Delete')",
    ],
    'game/include/Game/ButtonPagination.php' => [
        "T('Tasks', 'previous')",
        "T('Tasks', 'next')",
        "sprintf(T('Tasks', 'page'), \$number)",
    ],
    'game/include/resources/Templates/build/building/TroopBuildingTrainingWrapper.php' => [
        "T('Buildings', 'level')",
    ],
    'game/include/resources/Templates/hero/AuctionFilter.php' => [
        "T('inGame', 'goldShort')",
        "T('inGame', 'silverShort')",
    ],
    'game/include/resources/Templates/payment/paymentWizardLayout.php' => [
        "T('PaymentWizard', 'Vouchers')",
        "T('PaymentWizard', 'Advantages')",
    ],
    'game/include/resources/Templates/payment/pros.php' => [
        "T('PaymentWizard', 'goldClub')",
        "T('PaymentWizard', 'Bonus duration')",
        "T('PaymentWizard', 'ToTheEndOfTheGame')",
        "T('PaymentWizard', 'Extend automatically')",
    ],
    'game/include/resources/Templates/payment/specialOffers.php' => [
        "T('PaymentWizard', 'Buy now')",
        "T('Logout', 'back_to_the_game')",
    ],
    'game/include/resources/Templates/system/login.php' => [
        "return (string)T('Login', \$referenceKey);",
        "return (string)T('Login', \$legacyKey);",
        "return (string)T('Login', \$sourceKey);",
        "\$loginCopy('input_emailAccount_error_empty', 'userErrors.empty')",
        "\$loginCopy('input_password_placeholder', 'pass')",
        "\$loginCopy('input_password_error_empty', 'pwErrors.empty')",
        "\$loginCopy('login', 'Login')",
        "\$recoveryCopy('Password Recovery')",
        "\$recoveryCopy('Back to Login')",
        "\$recoveryCopy('Recover password')",
        "\$recoveryCopy('Please enter your email address')",
        "\$recoveryCopy('Please enter a valid email address. E.g. mymail@provider.com')",
    ],
    'game/include/resources/Templates/system/registrationDialog.php' => [
        "\$copy['Email Address']",
        "T('Login', 'pass')",
    ],
];

foreach ($sourceCalls as $path => $calls) {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failPlayerLabelLocalization("Unable to read {$path}.");
    }

    foreach ($calls as $call) {
        if (!str_contains($source, $call)) {
            failPlayerLabelLocalization("{$path} must retain canonical player label {$call}.");
        }
    }
}

$removedFallbacks = [
    'game/include/Controller/Ajax/favouriteTab.php' => [
        'Not all of the necessary data has been submitted. Please resend this request with all the necessary data.',
    ],
    'game/include/Controller/Ajax/village/changeNames.php' => [
        'Not all of the necessary data has been submitted. Please resend this request with all the necessary data.',
    ],
    'game/include/Controller/Ajax/village/updateSortIndex.php' => [
        'Not all of the necessary data has been submitted. Please resend this request with all the necessary data.',
    ],
    'game/include/Controller/HeroAuctionCtrl.php' => [
        'aria-label="Previous page"',
        "'Page ' . \$number",
        'aria-label="Next page"',
    ],
    'game/include/resources/Templates/build/building/TroopBuildingTrainingWrapper.php' => [
        'Level <?= $smithyLevel; ?>',
    ],
    'game/include/resources/Templates/hero/AuctionFilter.php' => [
        '<label>Gold <input',
        '<span class="resultValue">100</span> silver',
        '<label>Silver <input',
        '<span class="resultValue">1</span> gold',
    ],
    'game/include/resources/Templates/payment/paymentWizardLayout.php' => [
        "'vouchers' => 'Vouchers'",
        "'advantages' => 'Advantages'",
    ],
    'game/include/resources/Templates/payment/pros.php' => [
        '<h3>Gold club</h3>',
        '>whole game round<',
        '>Extend automatically<',
    ],
    'game/include/resources/Templates/payment/specialOffers.php' => [
        '<div>Buy now</div>',
        '<div>Back to the game</div>',
    ],
    'game/include/resources/Templates/system/login.php' => [
        '<div class="label">Email address:</div>',
        'placeholder="Password"',
        ": 'Please enter your email address or account name'",
        ": 'Please enter your password'",
        '<div>Login</div>',
        '<h1>Password recovery</h1>',
        '<span>Back to login</span>',
        'placeholder="Enter your email address"',
        '<div>Recover password</div>',
    ],
    'game/include/resources/Templates/system/registrationDialog.php' => [
        'aria-label="Close"',
        '<div class="label">Email address:</div>',
        "\$field('password', 'Password'",
    ],
];

foreach ($removedFallbacks as $path => $fallbacks) {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failPlayerLabelLocalization("Unable to read {$path}.");
    }

    foreach ($fallbacks as $fallback) {
        if (str_contains($source, $fallback)) {
            failPlayerLabelLocalization("Inline player label survived in {$path}: {$fallback}");
        }
    }
}

require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$keys = [
    ['map', 'please_resend_all_data', 'Not all of the necessary data has been submitted. Please resend this request with all the necessary data.'],
    ['Reports', 'Village', 'Village'],
    ['MarketPlace', 'Delete', 'Delete'],
    ['Tasks', 'previous', 'Previous page'],
    ['Tasks', 'next', 'Next page'],
    ['Tasks', 'page', 'Page %s'],
    ['Buildings', 'level', 'Level'],
    ['inGame', 'goldShort', 'Gold'],
    ['inGame', 'silverShort', 'Silver'],
    ['PaymentWizard', 'Vouchers', 'Vouchers'],
    ['PaymentWizard', 'Advantages', 'Advantages'],
    ['PaymentWizard', 'goldClub', 'Gold club'],
    ['PaymentWizard', 'Bonus duration', 'Bonus duration'],
    ['PaymentWizard', 'ToTheEndOfTheGame', 'whole game round'],
    ['PaymentWizard', 'Extend automatically', 'Extend automatically'],
    ['PaymentWizard', 'Buy now', 'Buy now'],
    ['Logout', 'back_to_the_game', 'Back to the game'],
    ['Messages', 'closeButtonText', 'Close'],
    ['EVerify', 'email.EMAIL_ADDRESS', 'Email address'],
    ['Login', 'pass', 'Password'],
    ['Login', 'userErrors.empty', 'Please enter your email address or account name'],
    ['Login', 'pwErrors.empty', 'Please enter your password'],
    ['Global', 'Login', 'Login'],
];

foreach (['en-US', 'ar-AE', 'el-GR', 'fa-IR'] as $locale) {
    $GLOBALS['Definition'] = [];
    $catalog = require $root . '/game/include/resources/Translation/' . $locale . '.php';
    if (!is_array($catalog)) {
        failPlayerLabelLocalization("{$locale} catalog did not return an array.");
    }

    foreach ($keys as [$section, $key, $english]) {
        $value = playerLabelTranslationValue($catalog, $section, $key);
        if (!is_string($value)) {
            failPlayerLabelLocalization("{$locale} is missing string translation {$section}.{$key}.");
        }
        if ($locale === 'en-US' && $value !== $english) {
            failPlayerLabelLocalization("en-US {$section}.{$key} must retain exact player text {$english}.");
        }
    }
}

echo "Player label localization contract passed.\n";
