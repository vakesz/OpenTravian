<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = require $root . '/game/deployment/include/api_legacy_routes.php';
$controller = file_get_contents($root . '/game/include/Controller/Ajax/plus.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');
$build = file_get_contents($root . '/game/include/Controller/BuildCtrl.php');
$translation = file_get_contents($root . '/game/include/resources/Translation/en-US.php');

if (($routes['routes']['plus'] ?? null) !== [null, 'plus']) {
    fwrite(STDERR, "The Plus dialog API route must resolve to its dialog controller.\n");
    exit(1);
}

if ($controller === false || $client === false || $build === false || $translation === false) {
    fwrite(STDERR, "The Plus dialog implementation sources must be readable.\n");
    exit(1);
}

foreach (['class plus extends AjaxBase', "'goldButton' => \$helper->getPlusButton(false, false, true)", "'features' => [", "'furtherInfos' => sprintf"] as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "The Plus dialog controller must return {$needle}.\n");
        exit(1);
    }
}

// Plus dialog strings.
foreach ([
    "\$Definition['plusPopup']['title'] = 'OpenTravian Plus';",
    "\$Definition['plusPopup']['subHeadLine'] = 'In order to use this feature, you need to activate OpenTravian Plus.';",
    "\$Definition['plusPopup']['buttonSubtitle'] = 'Bonus duration in days: %s';",
    "\$Definition['plusPopup']['furtherInfos'] = 'OpenTravian Plus enables the above mentioned features for %s days and can be extended at any time. If you select the automatic extension of this feature, it will be extended one day before it runs out and the gold will also be deducted then. For further information, click the \"i\".';",
] as $expected) {
    if (!str_contains($translation, $expected)) {
        fwrite(STDERR, "The Plus dialog must retain this copy: {$expected}.\n");
        exit(1);
    }
}

if (str_contains($controller, 'Travian PLUS')
    || str_contains($controller, 'Travian PLUS enables the above mentioned features')) {
    fwrite(STDERR, "The Plus dialog must not retain handwritten stale PLUS copy.\n");
    exit(1);
}

if (!str_contains($client, "Travian.Dialog.Ajax.call(\n        this,\n        \"plus\",")
    || !str_contains($build, '"plusDialog" => ["featureKey" => \'buildingQueue\'')) {
    fwrite(STDERR, "The queue advert must keep opening the supported Plus dialog endpoint.\n");
    exit(1);
}

// The Activate buttons of both dialogs call the reference's PremiumFeature functions, which start the one
// way of payment (no second handler next to the button's own wayOfPayment data).
$root = dirname(__DIR__, 2);
$helper = (string)file_get_contents($root . '/game/include/Game/GoldHelper.php');
$client = (string)file_get_contents($root . '/game/deployment/public/game-client.js');
foreach ([
    'Travian.Game.PremiumFeature.PlusAccount(); return false;',
    'Travian.Game.PremiumFeature.GoldClub(); return false;',
] as $needle) {
    if (!str_contains($helper, $needle)) {
        fwrite(STDERR, "The Activate buttons must carry onclick $needle\n");
        exit(1);
    }
}
foreach (['new Travian.Game.WayOfPayment("plus", "plus")', 'new Travian.Game.WayOfPayment("goldclub", "goldclub")'] as $needle) {
    if (!str_contains($client, $needle)) {
        fwrite(STDERR, "PremiumFeature must start the one way of payment: $needle\n");
        exit(1);
    }
}

echo "Plus dialog API contract passed.\n";
