<?php

declare(strict_types=1);

// Body and #content class contract: the shell emits `<page> ltr mobileOptimized`
// (+ village1|2|3 on village pages), and controllers emit the
// content class tokens.

$root = dirname(__DIR__, 2);
$read = static function (string $rel) use ($root): string {
    $s = file_get_contents($root . '/' . $rel);
    if ($s === false) {
        fwrite(STDERR, "Cannot read {$rel}\n");
        exit(1);
    }
    return $s;
};
$fail = static function (string $m): never {
    fwrite(STDERR, "Body/content class contract failed: {$m}\n");
    exit(1);
};

$layout = $read('game/include/resources/Templates/layout/Layout.php');
foreach (['"v35"', '"webkit"', '"blink"', '"chrome"', 'get_locale()', 'global_css_class', 'buildingsV3'] as $bad) {
    if (str_contains($layout, $bad)) {
        $fail("Layout.php must not emit body class source {$bad}");
    }
}
foreach (['"perspectiveResources", "perspectiveBuildings"', '"mobileOptimized"', 'data-village-perspective'] as $need) {
    if (!str_contains($layout, $need)) {
        $fail("Layout.php missing {$need}");
    }
}
$head = $read('game/include/resources/Templates/layout/head.php');
if (str_contains($head, 'buildingsV3') || preg_match('/<html[^>]*\sclass=/', $head)) {
    $fail('<html> must carry no classes (has none)');
}

$C = 'game/include/Controller/';
$expect = [
    'ResourceFieldsCtrl.php' => ["'bodyCssClass'] = 'perspectiveResources village1'"],
    'VillageCenterCtrl.php' => ["'bodyCssClass'] = 'perspectiveBuildings village2'"],
    'VillageStatisticsCtrl.php' => ["'bodyCssClass'] = 'perspectiveResources village3'", "'contentCssClass'] = 'village3 village3' . match", "'Culturepoints'", "'Resources'", "'Troops'", "default => 'Overview'"],
    'ProductionCtrl.php' => ["'contentCssClass'] = 'production'"],
    'HeroInventoryCtrl.php' => ["'heroV2 heroV2Attributes'", "'heroV2 heroV2Inventory'"],
    'HeroFaceCtrl.php' => ["'heroV2 heroV2Appearance'"],
    'HeroAuctionCtrl.php' => ["'contentCssClass'] = 'auctions'", "default => ' auctionsBuy'"],
    'OptionCtrl.php' => ["'bodyCssClass'] = 'perspectiveResources options'", "'optionsAccount'", "'optionsSitter'", "'optionsVacation'", "'optionsGame'"],
    'MessagesCtrl.php' => ["'messages ' . \$contentTabToken", "\$selectedTab = 0;"],
];
foreach ($expect as $file => $needles) {
    $src = $read($C . $file);
    foreach ($needles as $n) {
        if (!str_contains($src, $n)) {
            $fail("{$file} missing {$n}");
        }
    }
}
$production = $read($C . 'ProductionCtrl.php');
if (preg_match('/production(Lumber|Clay|Iron|Crop|Balance)/', $production)) {
    $fail('ProductionCtrl must not emit per-tab production classes');
}
// the React shell (hydrated, 1920x1080) renders the buy view as `auctions auctionsBuy`.
echo "Body/content class contract passed.\n";
