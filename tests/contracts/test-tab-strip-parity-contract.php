<?php

declare(strict_types=1);

/*
 * Tab strips must match div.contentNavi.subNavi (never <nav>), clean
 * hrefs, favorKey<slug> wrappers, favorActive only for a stored favourite.
 */

$root = dirname(__DIR__, 2);
$templates = $root . '/game/include/resources/Templates/';
$failures = [];
$read = static function (string $path) use (&$failures): string {
    $source = file_get_contents($path);
    if ($source === false) {
        $failures[] = "Unable to read {$path}";
        return '';
    }
    return $source;
};

// --- Render the shared helper and inspect the DOM -------------------------
define('TEMPLATES_PATH', $templates);
require_once $root . '/game/include/resources/View/PHPBatchView.php';
if (!function_exists('get_button_id')) {
    function get_button_id()
    {
        static $id = 0;
        return 'button' . ++$id;
    }
}

$render = static function (array $vars): DOMXPath {
    $html = \resources\View\PHPBatchView::render('layout/tabStrip', $vars);
    $document = new DOMDocument();
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>');
    libxml_clear_errors();
    return new DOMXPath($document);
};

$xp = $render([
    'tabs' => [
        ['slug' => 'inbox', 'label' => 'Inbox', 'href' => '/messages/inbox', 'active' => true],
        ['slug' => 'archive', 'label' => 'Archive', 'href' => '/messages/archive', 'gold' => true, 'dialog' => ['type' => 'goldclubDialog', 'config' => ['featureKey' => 'messageArchive']]],
    ],
    'favor' => true,
    'favorSlug' => 'archive',
    'favorButton' => ['name' => 'messages', 'key' => 'inbox', 'slug' => 'inbox', 'title' => 'fav', 'selector' => '.subNavi .favor', 'successGuard' => false],
]);
$strip = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " contentNavi ") and contains(concat(" ", normalize-space(@class), " "), " subNavi ")]');
if ($strip->length !== 1 || $xp->query('//nav')->length !== 0) {
    $failures[] = 'Helper must emit exactly one div.contentNavi.subNavi and no <nav>.';
} elseif (str_contains($strip->item(0)->getAttribute('class'), 'tabFavorWrapper')) {
    $failures[] = 'Favourite strips must not carry tabFavorWrapper unless requested.';
}
if ($xp->query('//a[@id="tabFavorButton"]')->length !== 1) {
    $failures[] = 'Helper must emit a#tabFavorButton when a favourite button is requested.';
}
$wrappers = $xp->query('//div[@class="scrollingContainer"]/div');
$classes = [];
foreach ($wrappers as $wrapper) {
    $classes[] = $wrapper->getAttribute('class');
}
if ($classes !== ['content favor favorKeyinbox', 'content favor favorKeyarchive favorActive']) {
    $failures[] = 'Wrapper classes differ from the expected: ' . json_encode($classes);
}
$gold = $xp->query('//a[contains(@class,"gold")]');
if ($gold->length !== 1 || $gold->item(0)->getAttribute('href') !== '#') {
    $failures[] = 'Gated tabs must render .gold with href="#".';
}
if ($xp->query('//a[contains(@class,"active") and contains(@class,"tabItem")]')->length !== 1) {
    $failures[] = 'Exactly one tab must be active.';
}
$activeTab = $xp->query('//a[contains(@class,"active") and contains(@class,"tabItem")]')->item(0);
if ($activeTab === null
    || $activeTab->getAttribute('class') !== 'tabItem active'
    || $activeTab->hasAttribute('aria-current')
    || !$activeTab->hasAttribute('title')
    || $activeTab->getAttribute('title') !== '') {
    $failures[] = 'Active regular tabs must use tabItem-first classes, an empty title, and no aria-current.';
}
$bare = $render([
    'tabs' => [['label' => 'Tasks', 'href' => '/tasks?t=village', 'active' => true]],
    'bareAnchors' => true,
]);
$bareTab = $bare->query('//a')->item(0);
if ($bareTab === null || $bareTab->hasAttribute('href') || $bareTab->hasAttribute('title')) {
    $failures[] = 'React-style bare tabs must omit href and title attributes.';
}
$none = $render(['tabs' => [['slug' => 'inbox', 'label' => 'Inbox', 'href' => '/messages/inbox', 'active' => true]], 'favor' => true, 'favorSlug' => null]);
if ($none->query('//*[contains(@class,"favorActive")]')->length !== 0) {
    $failures[] = 'No tab may carry favorActive while no favourite is stored.';
}
$plain = $render(['tabs' => [['label' => 'Game', 'href' => '/options/game', 'active' => true]]]);
if ($plain->query('//div[@class="content"]')->length !== 1) {
    $failures[] = 'Options-style strips use plain div.content wrappers.';
}

// --- Per-page expectations --------------------------------------------------
$expectations = [
    'messages/menu.php' => ['/messages/inbox', '/messages/write', '/messages/sent', '/messages/archive', '/messages/notes', '/messages/ignore', "'slug' => 'ignore'"],
    'options/menu.php' => ['/options/game', '/options/account', '/options/sitter', '/options/vacation'],
    'reports/menu.php' => ['/report/overview', '/report/archive', '/report/surrounding', "'slug' => 'surrounding'"],
    'production/menu.php' => ["'/production.php?t='", 'tabFavorWrapper'],
    'profile/menu.php' => ['"/profile"', '"/profile/edit"', 'tabFavorWrapper'],
    'village-statistics/menuEnabled.php' => ["'slug' => 'overview'", "'slug' => 'resources'", "'slug' => 'culturepoints'", "'slug' => 'troops'"],
    'village-statistics/menuDisabled.php' => ["'slug' => 'culturepoints'", "'type' => 'plusDialog'"],
    'tasks/main.php' => ['layout/tabStrip', '/tasks?t=general', 'tabFavorWrapper'],
    'referAFriend/main.php' => ['layout/tabStrip', '/referAFriend?t=overview', 'tabFavorWrapper'],
    'statistics/main.php' => ['layout/tabStrip', "'wonderoftheworld'"],
];
foreach ($expectations as $file => $needles) {
    $source = $read($templates . $file);
    if (str_contains($source, '<nav class="contentNavi')) {
        $failures[] = "{$file} still emits nav.contentNavi.";
    }
    if (!str_contains($source, 'layout/tabStrip')) {
        $failures[] = "{$file} must render through layout/tabStrip.";
    }
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $failures[] = "{$file} is missing: {$needle}";
        }
    }
}

// --- Legacy forms must be gone from emission -------------------------------
$legacy = [
    'messages/menu.php' => ['messages?t=', 'tabFavorWrapper', 'favorIcon'],
    'options/menu.php' => ['options?s='],
    'production/menu.php' => ["href = 'production.php"],
    'village-statistics/menuEnabled.php' => ['Warehouse', 'favorKey<?='],
    'village-statistics/menuDisabled.php' => ['Warehouse', 'favorKey<?='],
];
foreach ($legacy as $file => $needles) {
    $source = $read($templates . $file);
    foreach ($needles as $needle) {
        if (str_contains($source, $needle)) {
            $failures[] = "{$file} still contains legacy markup: {$needle}";
        }
    }
}
foreach (['messages/list.php', 'messages/showMsg.php', 'messages/write.php', 'options/Account.php', 'options/Sitters.php'] as $file) {
    if (preg_match('#/(messages|options)\?(s|t)=\d#', $read($templates . $file)) === 1) {
        $failures[] = "{$file} still emits /messages?t=N or /options?s=N.";
    }
}
// Nests Warehouse/Production/Capacity under /village/statistics/resources (B15).
foreach (['warehouse', 'production', 'capacity'] as $economyTab) {
    if (!str_contains($read($root . '/game/include/mainInclude.php'), "resources/{$economyTab}")) {
        $failures[] = "mainInclude.php must route the nested {$economyTab} village-statistics tab.";
    }
}

// --- Routes -------------------------------------------------------------------
$router = $read($root . '/game/include/mainInclude.php');
foreach ([
    '#^/messages/(inbox|write|sent|archive|notes|ignore)/?$#',
    '#^/options/(game|account|avatar|sitter|vacation)/?$#',
    "'/village/statistics/resources'",
    "'/village/statistics/culturepoints'",
    "'/village/statistics/troops'",
    "'/village/statistics/overview'",
] as $needle) {
    if (!str_contains($router, $needle)) {
        $failures[] = "mainInclude.php is missing route: {$needle}";
    }
}

// --- Favourite storage distinguishes unset from 0 ---------------------------
$session = $read($root . '/game/include/Core/Session.php');
if (!str_contains($session, 'function getStoredFavoriteTab') || !str_contains($session, 'favorTabsRaw')) {
    $failures[] = 'Session must expose getStoredFavoriteTab() backed by raw favourite tokens.';
}

$favoriteApi = $read($root . '/game/include/Controller/Ajax/favouriteTab.php');
if (!str_contains($favoriteApi, "'report' => ['overview', 'offensive', 'defensive', 'scouting', 'other', 'archive', 'surrounding']")
    || !str_contains($favoriteApi, "'messages' => ['inbox', 'write', 'sent', 'archive', 'notes', 'ignore']")
    || !str_contains($favoriteApi, "'report' => 'reports'")) {
    $failures[] = 'The report favourite must accept route keys while retaining the reports preference slot.';
}

$reportMenu = $read($root . '/game/include/resources/Templates/reports/menu.php');
foreach (["'name' => 'report'", "'key' => \$reportTabs[(int) \$vars['selectedTab']]['slug']", "'selector' => '.subNavi .favor'", "'successGuard' => false"] as $fragment) {
    if (!str_contains($reportMenu, $fragment)) {
        $failures[] = 'The report strip must post the keyed favourite payload: ' . $fragment;
    }
}
$messagesMenu = $read($root . '/game/include/resources/Templates/messages/menu.php');
foreach (["'name' => 'messages'", "'key' => \$messageTabs[(int) \$vars['selectedTab']]['slug']", "'selector' => '.subNavi .favor'", "'successGuard' => false"] as $fragment) {
    if (!str_contains($messagesMenu, $fragment)) {
        $failures[] = 'The messages strip must post the keyed favourite payload: ' . $fragment;
    }
}
if (!str_contains($read($root . '/database/world.sql'), "`favorTabs` VARCHAR(40) NOT NULL DEFAULT ',,,,,,,,,,,,,'")) {
    $failures[] = 'users.favorTabs must default to empty tokens (no stored favourite).';
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "Tab strip parity contract passed.\n";
