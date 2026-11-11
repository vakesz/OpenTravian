<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/Ajax/autocomplete/villagename.php');
$playerController = file_get_contents($root . '/game/include/Controller/Ajax/autocomplete/playername.php');
$routes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
$bundles = [
    $root . '/game/deployment/public/game-client.js',
];

if ($controller === false
    || $playerController === false
    || $routes === false
    || !str_contains($controller, 'class villagename extends playername')
    || !str_contains($controller, "\$_POST['context'] = 'villagename';")
    || !str_contains($controller, 'parent::dispatch();')
    || !str_contains($routes, "'autocomplete/villagename' => ['autocomplete', 'villagename']")) {
    fwrite(STDERR, "Village autocomplete must dispatch through the scoped village-name search endpoint.\n");
    exit(1);
}

if (!str_contains($playerController, 'FROM vdata v INNER JOIN wdata w ON w.id=v.kid')
    || !str_contains($playerController, 'v.kid!=?')) {
    fwrite(STDERR, "Village autocomplete must qualify village fields after joining map rows.\n");
    exit(1);
}

foreach ($bundles as $bundlePath) {
    $bundle = file_get_contents($bundlePath);
    if (!is_string($bundle)
        || !str_contains($bundle, 'Travian.Game.AutoCompleter.UserName = function')
        || !str_contains($bundle, 'Object.assign({ multiple: !1 }, t)')) {
        fwrite(STDERR, "Player-name autocomplete must expose the native single-recipient UserName helper in both graphics bundles.\n");
        exit(1);
    }
}

// The reference answers did, name, x, y, mapId, uid, playerName, aid, allianceName and typ (1 capital, 0 other);
// its nearby-villages option also lists the current village.
if (!str_contains($playerController, "'v.kid AS did, v.name, w.x, w.y, v.kid AS mapId, u.id AS uid, u.name AS playerName, u.aid, a.name AS allianceName, v.capital AS typ'")
    || str_contains($playerController, 'WHERE v.owner!=? AND v.kid!=? AND $distance')) {
    fwrite(STDERR, "Village suggestions must carry did and typ and the nearby option must include the current village.\n");
    exit(1);
}

echo "Village autocomplete API contract passed.\n";
