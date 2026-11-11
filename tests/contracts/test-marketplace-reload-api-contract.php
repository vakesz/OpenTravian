<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/Ajax/marketplace/reload.php');
$model = file_get_contents($root . '/game/include/Model/MarketModel.php');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');

if ($controller === false
    || $model === false
    || $routes === false
    || !str_contains($controller, 'final class reload extends AjaxBase')
    || !str_contains($controller, "['merchantsAvailable']")
    || !str_contains($controller, "['storage']")
    || !str_contains($controller, "['deliveries']")
    || !str_contains($controller, 'getDeliveryGroups(')
    || !str_contains($model, 'function getDeliveryGroups(int $kid): array')
    || !str_contains($model, 's.mode=0 AND (s.kid=? OR s.to_kid=?)')
    || !str_contains($model, 's.mode=1 AND s.to_kid=?')
    || !str_contains($model, "['incoming' => [], 'outgoing' => []]")
    || str_contains($controller, "['merchantsOnTheWay']")
    || str_contains($controller, 'renderMovement(')
    || !str_contains($controller, '(int)floor(Formulas::merchantCAP(')
    || !str_contains($routes, "'marketplace/reload' => ['marketplace', 'reload']")) {
    fwrite(STDERR, "Marketplace reloads must refresh resources and merchant capacity without reviving legacy movement markup.\n");
    exit(1);
}

echo "Marketplace reload API contract passed.\n";
