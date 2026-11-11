<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/Ajax/troop/mark.php');
$model = file_get_contents($root . '/game/include/Model/MovementsModel.php');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
$client = file_get_contents($root . '/game/deployment/public/game-client.js');

if ($controller === false
    || $model === false
    || $routes === false
    || $client === false
    || !str_contains($controller, 'final class mark extends AjaxBase')
    || !str_contains($controller, 'setMovementMarkState')
    || !str_contains($controller, "\$_POST['state']")
    || !str_contains($model, 'WHERE id=? AND to_kid=? AND mode=0 AND attack_type IN(3,4)')
    || !str_contains($routes, "'#^troop/(?P<id>[1-9][0-9]*)/mark\$#D'")
    || !str_contains($client, 'Travian.api("troop/" + e + "/mark"')) {
    fwrite(STDERR, "Incoming attack marker changes must be scoped to the active target village.\n");
    exit(1);
}

echo "Troop mark-attack API contract passed.\n";
