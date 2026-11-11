<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$templates = [
    'game/include/resources/Templates/build/mainBuilding.php',
    'game/include/resources/Templates/build/prepare_button.php',
    'game/include/resources/Templates/build/building/MarketplaceSendResources.php',
    'game/include/resources/Templates/build/building/MarketplaceOffer.php',
    'game/include/resources/Templates/build/building/TroopBuildingLayout.php',
    'game/include/resources/Templates/build/building/EmbassyFound.php',
    'game/include/resources/Templates/build/building/ResidenceChangeCapital.php',
    'game/include/resources/Templates/build/wwChangeName.php',
    'game/include/resources/Templates/build/admin_level_set.php',
];

foreach ($templates as $template) {
    $contents = file_get_contents($root . '/' . $template);
    if ($contents === false) {
        fwrite(STDERR, "Cannot read {$template}.\n");
        exit(1);
    }
    $usesNativeHelper = str_contains($contents, 'getButton(');
    $usesNativeV2Markup =
        ($template === 'game/include/resources/Templates/build/building/MarketplaceSendResources.php'
        && str_contains($contents, 'textButtonV2 buttonFramed')
        && str_contains($contents, 'contentV2 sendResourcesView formV2'))
        || ($template === 'game/include/resources/Templates/build/building/MarketplaceOffer.php'
        && str_contains($contents, 'id="marketplaceOffer" class="contentV2"')
        && str_contains($contents, 'textButtonV2 buttonFramed rectangle withText green'));
    $usesReferenceSubmit =
        $template === 'game/include/resources/Templates/build/mainBuilding.php'
        && str_contains($contents, 'type="submit"')
        && str_contains($contents, 'textButtonV1');
    if (!$usesNativeHelper && !$usesNativeV2Markup && !$usesReferenceSubmit) {
        fwrite(STDERR, "{$template} must render its action through the shared native button helper.\n");
        exit(1);
    }
    if (str_contains($contents, 'button-container addHoverClick')) {
        fwrite(STDERR, "{$template} still embeds deprecated V1 button chrome.\n");
        exit(1);
    }
}

$newBuildingMenu = file_get_contents($root . '/game/include/resources/Templates/build/newBuildingMenu.php');
if ($newBuildingMenu === false
    || !str_contains($newBuildingMenu, 'TabScrollNavigation')
    || str_contains($newBuildingMenu, "trigger('tabClicked'")) {
    fwrite(STDERR, "New-building navigation must use the V2 scrolling tab contract without the legacy click proxy.\n");
    exit(1);
}

echo "Building native control contract passed.\n";
