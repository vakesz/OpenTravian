<?php

declare(strict_types=1);

function failReferAFriendContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$router = file_get_contents($root . '/game/include/mainInclude.php');
$controller = file_get_contents($root . '/game/include/Controller/ReferAFriendCtrl.php');
$template = file_get_contents($root . '/game/include/resources/Templates/referAFriend/main.php');
$layout = file_get_contents($root . '/game/include/resources/Templates/layout/Layout.php');
$en = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$config = file_get_contents($root . '/game/include/config.php');

$translationFiles = ['ar-AE.php', 'el-GR.php', 'en-US.php', 'fa-IR.php'];

if ($router === false || $controller === false || $template === false || $layout === false || $en === false || $config === false) {
    failReferAFriendContract('Refer-a-friend sources must be readable.');
}

if (!str_contains($router, "'referAFriend' => 'ReferAFriendCtrl'")) {
    failReferAFriendContract('mainInclude must dispatch /referAFriend to ReferAFriendCtrl.');
}

if (!str_contains($layout, 'href="/referAFriend"') || !str_contains($layout, 'class="referAFriend"')) {
    failReferAFriendContract('The shell menu must expose Refer a friend.');
}

foreach ([
    'contentCssClass',
    'inviteLink',
    'inviteGold',
    'villageReward',
    'tenthVillageReward',
    'maxReward',
    'player_references',
    'inviteEmail',
    'Mailer::sendEmail',
    'playerEmail',
    'defaultMessage',
] as $fragment) {
    if (!str_contains($controller, $fragment)) {
        failReferAFriendContract("ReferAFriendCtrl is missing {$fragment}.");
    }
}

foreach ([
    'id="referAFriend"',
    'class="referAFriendInfo"',
    'class="referralOverview"',
    'id="referralLinkInput"',
    'class="infoGraphic"',
    'class="badge badgeBig friendJoins"',
    'class="badge badgeSmall secondVillage"',
    'class="badge badgeSmallDecoration tenthVillage"',
    'class="shareButtonsWrapper"',
    'class="textButtonV2 buttonFramed openEmailForm rectangle withText green"',
    'class="fb-share-button"',
    "T('ReferAFriend', 'shareOnFacebook'",
    'id="inviteViaEmailDialog"',
    "cssClass: 'basic referAFriendInvite'",
    'navigator.share',
    '/referAFriend?t=overview',
] as $fragment) {
    if (!str_contains($template, $fragment)) {
        failReferAFriendContract("Refer-a-friend template is missing {$fragment}.");
    }
}

if (str_contains($template, 'shareLink')
    || str_contains($template, 'referAFriendDialog/sendInvites')
    || str_contains($template, 'paymentWizard')) {
    failReferAFriendContract('Template must use the reference div.fb-share-button control (no Share Link button) and no payment glue.');
}

$requiredKeys = [
    'inviteViaEmail',
    'shareOnFacebook',
    'friendJoins',
    'ordinal2nd',
    'ordinal10th',
    'defaultInviteMessage',
    'descriptionHeader',
];
foreach ($translationFiles as $translationFile) {
    $translation = file_get_contents($root . '/game/include/resources/Translation/' . $translationFile);
    if ($translation === false || !str_contains($translation, "\$Definition['ReferAFriend']")) {
        failReferAFriendContract("Missing ReferAFriend translation section in {$translationFile}.");
    }
    foreach ($requiredKeys as $key) {
        if (!str_contains($translation, "'{$key}' =>")) {
            failReferAFriendContract("Missing ReferAFriend key {$key} in {$translationFile}.");
        }
    }
}

if (!str_contains($en, "'shareOnFacebook' => 'Share on Facebook'")
    || !str_contains($en, "'inviteViaEmail' => 'Invite via email'")
    || !str_contains($en, "'friendJoins' => 'Friend joins'")) {
    failReferAFriendContract('en-US must define ReferAFriend share and ladder strings.');
}

if (!preg_match("/'invitePlayerGold'\\s*=>\\s*20/", $config)) {
    failReferAFriendContract('Local invitePlayerGold default (20) must remain the intentional reward config.');
}

foreach ([
    'img_ltr/views/refer-a-friend/header-image.png',
    'img_ltr/views/refer-a-friend/badge-big.png',
    'img_ltr/views/refer-a-friend/badge-small.png',
    'img_ltr/views/refer-a-friend/badge-small-decoration.png',
    'img_ltr/views/refer-a-friend/background-pattern.jpg',
    'img_rtl/views/refer-a-friend/header-image.png',
] as $asset) {
    if (!is_file($root . '/assets/gpack/travian/mainPage/' . $asset)) {
        failReferAFriendContract("Missing ReferAFriend Gpack asset: {$asset}");
    }
}

echo "Refer a friend contract passed.\n";
