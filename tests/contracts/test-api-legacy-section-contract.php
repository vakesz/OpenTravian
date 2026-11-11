<?php

declare(strict_types=1);

// api_legacy.php: the ajax section is chosen by the route table only. Executes the
// preamble to prove a client-supplied ?section= is dropped before routing.

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/game/deployment/public/api_legacy.php');
$unset = strpos($source, "unset(\$_GET['section'], \$_REQUEST['section'])");
$route = strpos($source, "\$route = \$legacyRoutes['routes']");
if ($unset === false || $route === false || $unset > $route) {
    fwrite(STDERR, "api_legacy.php must unset client-supplied section before routing.\n");
    exit(1);
}

$_GET = $_REQUEST = ['section' => 'admin', 'keep' => '1'];
eval('?>' . substr($source, 0, strpos($source, "\n", $unset)));
if (isset($_GET['section']) || isset($_REQUEST['section']) || ($_GET['keep'] ?? null) !== '1') {
    fwrite(STDERR, "A client-supplied section must be removed while other parameters survive.\n");
    exit(1);
}

echo "api_legacy section contract passed.\n";
