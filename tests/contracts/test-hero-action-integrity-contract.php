<?php

declare(strict_types=1);

/** @return string */
function heroActionIntegritySource(string $path): string
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }

    return $source;
}

function assertHeroActionIntegrity(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$troopSend = heroActionIntegritySource('game/include/Controller/Ajax/troop/send.php');
$heroAdventure = heroActionIntegritySource('game/include/Controller/HeroAdventureCtrl.php');
$heroInventory = heroActionIntegritySource('game/include/Controller/HeroInventoryCtrl.php');
$heroInventoryTemplate = heroActionIntegritySource('game/include/resources/Templates/hero/hero_inventory.php');
$session = heroActionIntegritySource('game/include/Core/Session.php');

assertHeroActionIntegrity(
    str_contains($troopSend, "case 'PUT':")
        && str_contains($troopSend, "case 'POST':")
        && str_contains($troopSend, 'PurchaseNonce::issue(')
        && str_contains($troopSend, 'PurchaseNonce::consume(')
        && str_contains($troopSend, "'HTTP_X_NONCE'")
        && str_contains($troopSend, '$session->banned()')
        && str_contains($troopSend, 'serverFinished')
        && str_contains($troopSend, 'isInVacationMode()')
        && str_contains($troopSend, 'STATUS_HOME')
        && str_contains($troopSend, 'getHeroVillageRallyPoint('),
    'Adventure starts must be a signed PUT plus a POST with its nonce, and keep the banned, finished, vacation, hero-at-home and Rally Point blocks.'
);
assertHeroActionIntegrity(
    !str_contains($heroAdventure, 'skip=1')
        && !str_contains($heroAdventure, 'start_adventure.php')
        && !is_file(dirname(__DIR__, 2) . '/game/include/Controller/StartAdventure.php'),
    'Adventure starts must not retain a state-changing GET route or a separate start page.'
);
assertHeroActionIntegrity(
    str_contains($heroInventory, 'WebService::isPost()')
        && str_contains($heroInventory, "isset(\$_POST['a'])")
        && str_contains($heroInventory, 'Session::claimRequest()')
        && str_contains($heroInventory, 'Session::getInstance()->banned()'),
    'Hero revival must reject non-POST and unchecked mutations while retaining the banned-session block.'
);
assertHeroActionIntegrity(
    str_contains($heroInventoryTemplate, 'method="post"')
        && str_contains($heroInventoryTemplate, '\\Core\\Session::getCheckerInput()'),
    'Hero revival form must submit the checker token.'
);
assertHeroActionIntegrity(
    str_contains($session, 'public static function claimAction(') && !str_contains($session, 'changeChecker'),
    'Game actions must claim the stable session token so an immediate replay is rejected.'
);

foreach (['moveItem', 'useItem'] as $endpoint) {
    $source = heroActionIntegritySource("game/include/Controller/Ajax/hero/{$endpoint}.php");
    assertHeroActionIntegrity(
        str_contains($source, '$this->session->isValid()')
            && str_contains($source, '$this->session->isSitter()')
            && str_contains($source, '$this->session->banned()')
            && str_contains($source, 'FROM vdata WHERE kid=? AND owner=?'),
        "hero/v2/inventory {$endpoint} must reject invalid, sitter and banned sessions and foreign villages."
    );
}
assertHeroActionIntegrity(
    !is_file(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/inventory.php'),
    'The retired single inventory endpoint must stay deleted.'
);

echo "Hero action integrity contract passed.\n";
