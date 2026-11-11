<?php

declare(strict_types=1);

/**
 * The game deliberately uses <base href="/"> for clean routes. A bare query
 * therefore navigates to the login front controller instead of preserving the
 * current authenticated page. Player-facing controls must use an explicit
 * route whenever they change local page state.
 */
function failBaseHrefPlayerRouteContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$bareQueryDestinationPattern = '/(?:href|action|formaction|window\\.location(?:\\.href)?|(?:window\\.)?location\\.(?:assign|replace))\\s*(?:=|\\()\\s*["\']\\?/';
$head = file_get_contents($root . '/game/include/resources/Templates/layout/head.php');
if ($head === false
    || !str_contains($head, '<base href="/"/>')
    || !str_contains($head, "document.addEventListener('click', function (event)")
    || !str_contains($head, "target.closest('a[href=\"#\"], a[href=\"\"]')")
    || str_contains($head, 'action="?"')
    || !str_contains($head, 'action=""')
    || !str_contains($head, 'event.preventDefault();')) {
    failBaseHrefPlayerRouteContract(
        'The shared layout must preserve empty-control navigation under <base href="/">.'
    );
}

$sources = [
    'game/include/resources/Templates/build/building/MarketplaceTradeRouteAdd.php',
    'game/include/resources/Templates/build/building/MarketplaceTradeRouteShow.php',
    'game/include/resources/Templates/rallypoint/overview.php',
    'game/include/resources/Templates/reports/report_layout.php',
    'game/include/resources/Templates/system/recaptcha.php',
    'game/include/Controller/RallyPoint/RallyPoint.php',
    'game/include/Controller/Build/ResidencePalaceCtrl.php',
    'game/include/Controller/AllianceForum.php',
    'game/include/Controller/BannedCtrl.php',
];

foreach ($sources as $source) {
    $contents = file_get_contents($root . '/' . $source);
    if ($contents === false) {
        failBaseHrefPlayerRouteContract("Cannot read {$source}.");
    }

    if (preg_match($bareQueryDestinationPattern, $contents) === 1) {
        failBaseHrefPlayerRouteContract("{$source} still contains a player-facing bare-query route that breaks under <base href=\"/\">.");
    }
}

$templateRoot = $root . '/game/include/resources/Templates';
$templateIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($templateIterator as $templateFile) {
    if (!$templateFile->isFile() || $templateFile->getExtension() !== 'php') {
        continue;
    }

    $templatePath = $templateFile->getPathname();
    $relativePath = substr($templatePath, strlen($root) + 1);
    if (str_starts_with($relativePath, 'game/include/resources/Templates/admin/')
        || $relativePath === 'game/include/resources/Templates/system/login.php') {
        continue;
    }

    $contents = file_get_contents($templatePath);
    if ($contents === false) {
        failBaseHrefPlayerRouteContract("Cannot read {$relativePath}.");
    }

    if (preg_match($bareQueryDestinationPattern, $contents) === 1) {
        failBaseHrefPlayerRouteContract("{$relativePath} still contains a player-facing bare-query route that breaks under <base href=\"/\">.");
    }

    if (preg_match('/<a\\b[^>]*\\bhref\\s*=\\s*["\']#[^"\']+/', $contents) === 1) {
        failBaseHrefPlayerRouteContract(
            "{$relativePath} contains a non-empty fragment link that resolves to the login front controller under <base href=\"/\">."
        );
    }
}

foreach ([
    'game/include/Controller',
    'game/include/Model',
    'game/include/resources/View',
] as $generatedRoot) {
    $generatedIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root . '/' . $generatedRoot,
            FilesystemIterator::SKIP_DOTS
        )
    );
    foreach ($generatedIterator as $generatedFile) {
        if (!$generatedFile->isFile() || $generatedFile->getExtension() !== 'php') {
            continue;
        }

        $generatedPath = $generatedFile->getPathname();
        $relativePath = substr($generatedPath, strlen($root) + 1);
        if (str_contains($relativePath, '/admin/')) {
            continue;
        }

        $contents = file_get_contents($generatedPath);
        if ($contents === false) {
            failBaseHrefPlayerRouteContract("Cannot read {$relativePath}.");
        }

        if (preg_match($bareQueryDestinationPattern, $contents) === 1) {
            failBaseHrefPlayerRouteContract("{$relativePath} still emits a player-facing bare-query route that breaks under <base href=\"/\">.");
        }

        if (preg_match('/<a\\b[^>]*\\bhref\\s*=\\s*["\']#[^"\']+/', $contents) === 1) {
            failBaseHrefPlayerRouteContract(
                "{$relativePath} emits a non-empty fragment link that resolves to the login front controller under <base href=\"/\">."
            );
        }
    }
}

// A relative PHP destination happens to resolve today, but it is fragile around
// client-side navigation and Ajax overlays. Keep every player query destination
// explicitly root-absolute while the shared document base is root-relative.
$playerRouteNames = '(?:allianz|berichte|build|cropfinder|dorf1|dorf2|dorf3|dorf4|hero|karte|linklist|messages|options|position_details|report|reports|spieler|statistiken|start_adventure|verify)';
$relativePlayerQueryPattern = '/(?<![\/:A-Za-z0-9_-])' . $playerRouteNames . '\\.php\\?/';
$relativePlayerRedirectPattern = '/\\$this->response\\[[\'\"]redirectTo[\'\"]\\]\\s*=\\s*[\'\"](?!\\/)' . $playerRouteNames . '\\.php(?:\\?[^\'\"]*)?[\'\"]/';

foreach ([
    'game/include/Controller',
    'game/include/Model',
    'game/include/Core',
    'game/include/resources/Templates',
    'game/include/resources/Translation',
] as $playerRouteRoot) {
    $playerRouteIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $playerRouteRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($playerRouteIterator as $playerRouteFile) {
        if (!$playerRouteFile->isFile() || $playerRouteFile->getExtension() !== 'php') {
            continue;
        }

        $playerRoutePath = $playerRouteFile->getPathname();
        $relativePath = substr($playerRoutePath, strlen($root) + 1);
        if (str_contains($relativePath, '/admin/') || str_contains($relativePath, '/Plugins/')) {
            continue;
        }

        $contents = file_get_contents($playerRoutePath);
        if ($contents === false) {
            failBaseHrefPlayerRouteContract("Cannot read {$relativePath}.");
        }

        if (preg_match($relativePlayerQueryPattern, $contents) === 1) {
            failBaseHrefPlayerRouteContract(
                "{$relativePath} contains a relative player query destination; use a root-absolute route under <base href=\"/\">."
            );
        }

        if (preg_match($relativePlayerRedirectPattern, $contents) === 1) {
            failBaseHrefPlayerRouteContract(
                "{$relativePath} emits a relative player redirect; use a root-absolute route under <base href=\"/\">."
            );
        }
    }
}

$mapTemplate = file_get_contents($root . '/game/include/resources/Templates/map/normal.php');
if ($mapTemplate === false) {
    failBaseHrefPlayerRouteContract('Cannot read the map context-menu template.');
}

if (str_contains($mapTemplate, 'href="#flag"')) {
    failBaseHrefPlayerRouteContract(
        'Map context-menu controls must not use a fragment fallback that resolves to the login front controller under <base href="/">.'
    );
}

foreach ([
    'contextMenuSendTroops',
    'contextMenuSendTraders',
    'contextMenuMarkPlayerAlliance',
    'contextMenuMarkPlayerPlayer',
    'contextMenuFlagPlayer',
    'contextMenuMarkAllianceAlliance',
    'contextMenuMarkAlliancePlayer',
    'contextMenuFlagAlliance',
] as $contextMenuAction) {
    $actionPattern = sprintf(
        '/id="%s"[^>]*>.*?<a href="#">/s',
        preg_quote($contextMenuAction, '/')
    );
    if (preg_match($actionPattern, $mapTemplate) !== 1) {
        failBaseHrefPlayerRouteContract(
            "Map context-menu action {$contextMenuAction} must retain the protected empty-control href."
        );
    }
}

$marketController = file_get_contents($root . '/game/include/Controller/Build/MarketPlaceCtrl.php');
if ($marketController === false
    || !str_contains($marketController, '$view->vars[\'index\'] = $this->view->vars[\'index\'];')
    || !str_contains($marketController, '/build.php?id=')) {
    failBaseHrefPlayerRouteContract('Marketplace trade routes must retain their exact building field in every action URL.');
}

$rallyController = file_get_contents($root . '/game/include/Controller/RallyPoint/RallyPoint.php');
if ($rallyController === false
    || preg_match('/new\\s+PageNavigator\\s*\\([^;]*?,\\s*["\']build\\.php["\']\\s*\\)/s', $rallyController) === 1) {
    failBaseHrefPlayerRouteContract(
        'Rally Point movement paginators must use a root-absolute build route under <base href="/">.'
    );
}

// The troop and hero availability counts are client-filled inputs. Their
// fallback must still stay inside the authenticated Rally Point route when a
// handler is unavailable; href="#" resolves to the login front controller
// under the shared document base.
$sendTroops = file_get_contents($root . '/game/include/Controller/RallyPoint/SendTroops.php');
$troopsTableStart = $sendTroops === false ? false : strpos($sendTroops, 'public function getTroopsTable()');
$troopsTableEnd = $troopsTableStart === false
    ? false
    : strpos($sendTroops, 'public function removeA2b', $troopsTableStart);
$troopsTable = $troopsTableStart === false || $troopsTableEnd === false
    ? ''
    : substr($sendTroops, $troopsTableStart, $troopsTableEnd - $troopsTableStart);
if ($troopsTable === ''
    || !str_contains($troopsTable, 'href="/build.php?gid=16&amp;tt=2"')
    || !str_contains($troopsTable, "trigger(\\'focus\\'); return false\"")) {
    failBaseHrefPlayerRouteContract(
        'Rally Point troop and hero count controls must keep a root-absolute fallback and cancel default navigation.'
    );
}

// profileUrl() is already root-absolute. Prefixing it with another slash
// produces a scheme-relative //profile/<id> target that leaves the local
// world instead of opening the player's local profile.
$ignorePlayer = file_get_contents($root . '/game/include/Controller/Ajax/player/ignore.php');
if ($ignorePlayer === false
    || !str_contains($ignorePlayer, "'<td><a href=\"' . profileUrl((int) \$row['ignore_id'])")
    || str_contains($ignorePlayer, "'<td><a href=\"/' . profileUrl((int) \$row['ignore_id'])")) {
    failBaseHrefPlayerRouteContract(
        'Ignored-player list profile links must use profileUrl() directly, without a scheme-relative double slash.'
    );
}

echo "Base-href player route contract passed.\n";
