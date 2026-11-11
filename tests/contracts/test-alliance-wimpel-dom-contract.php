<?php

declare(strict_types=1);

function failAllianceDomContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failAllianceDomContract("Cannot read $path.");
    }
    return $source;
};

$tpl = 'game/include/resources/Templates/alliance/';
$menu = $read($tpl . 'menu.php');
$sub = $read($tpl . 'overviewSubNavi.php');
$profile = $read($tpl . 'Profile.php');
$options = $read($tpl . 'options.php');
$bonusMain = $read($tpl . 'bonuses/main.php');
$bonusOverview = $read($tpl . 'bonuses/overview.php');
$bonusAjax = $read('game/include/Controller/Ajax/alliance/bonusOverview.php');
$ctrl = $read('game/include/Controller/AllianceCtrl.php');
$forum = $read('game/include/Controller/AllianceForum.php');
$lang = $read('game/include/resources/Translation/en-US.php');

foreach (['overview', 'profile', 'reports', 'bonuses', 'forum', 'options'] as $key) {
    if (!str_contains($menu, 'favorKey' . $key)) {
        failAllianceDomContract("Alliance tab strip must expose favorKey$key.");
    }
}
if (preg_match('/favorKey\d/', $menu) === 1) {
    failAllianceDomContract('Alliance tab strip must not use numeric favorKey classes.');
}
if (!str_contains($sub, 'id="nestedTabFavorButton"') || !str_contains($sub, 'nestedTabRowButton')) {
    failAllianceDomContract('Overview tab row must render a#nestedTabFavorButton.');
}
if (str_contains($profile, 'tabFavorSubWrapper')) {
    failAllianceDomContract('Profile tab row must be a plain div.contentNavi.tabNavi.');
}
if (substr_count($options, 'class="a arrow') !== 5) {
    failAllianceDomContract('Only the five Settings links keep a.a.arrow; Actions and banner links are a.arrow.');
}
if (str_contains($bonusMain . $bonusOverview, 'roundedCornersBox')) {
    failAllianceDomContract('Bonus boxes must not carry roundedCornersBox.');
}
if (!str_contains($ctrl, "' allianceBonuses'")) {
    failAllianceDomContract('Bonuses content class must include allianceBonuses.');
}
foreach (['class="bonusBar"', 'levelIndicator', 'levelPercentages', 'class="levels"', 'class="front"', 'top5_contributors_', 'top5 row_table_data', 'hl own'] as $needle) {
    if (!str_contains($bonusAjax, $needle)) {
        failAllianceDomContract("Bonus overview renderer must emit $needle.");
    }
}
if (str_contains($bonusAjax, 'bonus-progress') || str_contains($bonusAjax, 'bonus-level')) {
    failAllianceDomContract('Legacy bonus-progress/bonus-level markup must be gone.');
}
if (!str_contains($ctrl, '<tr class="none"><td colspan="4">')) {
    failAllianceDomContract('Empty alliance reports row must be tr.none > td.');
}
if (!str_contains($lang, "['NewForum'] = 'New forum'") || !str_contains($lang, "['Alliance Founder'] = 'Alliance founder'")) {
    failAllianceDomContract('Copy must read "New forum" and "Alliance founder".');
}
if (!str_contains($forum, "'<div class=\"clear\"></div>'")) {
    failAllianceDomContract('Empty forum page must end with div.clear.');
}

echo "Alliance DOM contract passed.\n";
