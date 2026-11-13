<?php

// Building tab strips use the root-absolute `/build.php?id=X&gid=Y&<param>=N`
// URLs and carry no per-tab favourite image. The top bar formats currency like
// the stock bar; generic help controls are intentionally absent.

$root = dirname(__DIR__, 2) . '/game/include';
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    return $contents;
};
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

require_once $root . '/functions.general.php';

if (buildTabUrl(39, 16, 'tt', 99) !== '/build.php?id=39&gid=16&tt=99'
    || embassyTabUrl('villages_nearby') !== '/build.php?gid=18&tt=villages_nearby'
    || embassyTabUrl('join', ['a' => 3, 'd' => 7]) !== '/build.php?gid=18&tt=join&a=3&d=7') {
    $fail('Building tab URL helpers changed shape.');
}

$strips = [
    'Templates/rallypoint/navigation.php' => "buildTabUrl(39, 16, 'tt'",
    'Templates/build/building/MarketplaceNavigation.php' => "buildTabUrl(\$buildingIndex, (int)\$vars['gid'], 't'",
    'Templates/build/building/Treasury.php' => "buildTabUrl(\$buildingIndex, (int)\$vars['gid'], 's'",
    'Templates/build/building/ResidencePalaceNavigation.php' => "buildTabUrl(\$buildingIndex, (int)\$vars['gid'], 's'",
    'Templates/build/building/Embassy.php' => 'embassyTabUrl(',
];
foreach ($strips as $path => $fragment) {
    $template = $read('resources/' . $path);
    if (!str_contains($template, $fragment)) {
        $fail("{$path} must build tab hrefs with {$fragment}.");
    }
    // The Embassy keeps the nested `.tabNavi` strip, whose tabs fire tabClicked.
    $usesTabClicked = !str_contains($path, 'Embassy.php') && str_contains($template, "trigger('tabClicked'");
    if (str_contains($template, 'favorIcon') || $usesTabClicked) {
        $fail("{$path} must not render per-tab favourite images or legacy tabClicked handlers.");
    }
    if (preg_match('~href="build\.php~', $template) === 1) {
        $fail("{$path} must use root-absolute build.php hrefs.");
    }
}
if (!str_contains($read('resources/Templates/rallypoint/navigation.php'), "'overviewRallyPoint'")) {
    $fail('The Rally Point overview tab container must carry overviewRallyPoint.');
}

$embassy = $read('resources/Templates/build/building/Embassy.php');
foreach (['contentNavi tabNavi ', 'class="container ', 'nestedTabFavorButton', "embassyBuildingSubTabs", "trigger('tabClicked'"] as $fragment) {
    if (!str_contains($embassy, $fragment)) {
        $fail("The Embassy tab strip is missing {$fragment}.");
    }
}
if (str_contains($embassy, 'tabFavorWrapper')) {
    $fail('The Embassy must keep the nested .tabNavi strip, not nav.tabFavorWrapper.');
}
$embassyCtrl = $read('Controller/Build/EmbassyCtrl.php');
if (!str_contains($embassyCtrl, "['info', 'villages_nearby', 'join', 'leave']")
    || !str_contains($embassyCtrl, "'confirmation_leave'")
    || !str_contains($embassyCtrl, "case 'leave':")
    || !str_contains($embassyCtrl, "case 'info':")
    || preg_match("~case '(find|found)'~", $embassyCtrl) === 1) {
    $fail('The Embassy must expose only info / villages_nearby / join tabs and normalize tt=leave into typed confirmation.');
}
foreach (['EmbassyLeave', 'EmbassyFind'] as $removed) {
    if (is_file($root . "/resources/Templates/build/building/{$removed}.php")) {
        $fail("{$removed}.php is obsolete and must stay deleted.");
    }
}
$embassyJoin = $read('resources/Templates/build/building/EmbassyJoin.php');
if (!str_contains($embassyJoin, 'showJoinAllianceDialog')
    || !str_contains($embassyJoin, 'id="allianceInvitationForm" method="post"')
    || !str_contains($embassyJoin, 'getCheckerInput()')
    || !str_contains($embassyJoin, "submitAllianceInvitation('join'")
    || !str_contains($embassyJoin, "submitAllianceInvitation('refuse'")) {
    $fail('Embassy invitations must join through the confirmation dialog and POST with the session checker.');
}
if (str_contains($embassyCtrl, '&tt=info&action=alliance&do=join&d=')
    || !str_contains($embassyCtrl, 'guardInvitationRequest()')
    || !str_contains($embassyCtrl, 'WebService::isPost()')) {
    $fail('Embassy join/refuse must be POST + checker only; GET join links are obsolete.');
}

$build = $read('Controller/BuildCtrl.php');
if (str_contains($build, 'getAnswerIdForBuilding')
    || str_contains($build, "vars['answerId']")) {
    $fail('Build pages must not restore the retired answer-id help path.');
}

$dorf2 = $read('resources/Templates/village-center/main.php');
foreach ([
    "'/build.php?id=' . (int)\$map['index'] . '&gid=' . (int)\$map['item_id']",
    "\$buildingHref = str_replace('&', '&amp;', \$buildingUrl)",
    'href="<?=$buildingHref;?>" class="level',
] as $fragment) {
    if (!str_contains($dorf2, $fragment)) {
        $fail("Dorf2 built-slot URL parity is missing {$fragment}.");
    }
}
// Five building hit-area kinds plus the Wonder, which draws one stage-selected outline.
if (substr_count($dorf2, '$shapeGroups(') < 6
    || !str_contains($dorf2, "window.location.href=\\'' . \$url . '\\'")
    || str_contains($dorf2, 'fastUP')
    || str_contains($dorf2, "href=\"/build.php?id=<?= \$map['index']; ?>")) {
    $fail('Dorf2 must preserve gid in every occupied map hit-area and only the occupied wall label stays linkless.');
}
if (strpos($build, "if (isset(\$_REQUEST['id'])") === false
    || strpos($build, "if (isset(\$_REQUEST['gid'])") === false
    || strpos($build, "if (isset(\$_REQUEST['id'])") > strpos($build, "if (isset(\$_REQUEST['gid'])")) {
    $fail('BuildCtrl must prefer an explicit valid field id over descriptive gid.');
}

$layout = $read('resources/Templates/layout/Layout.php');
foreach (['$headerHelpButtonId', '$showHeaderHelp', 'knowledgeBaseButton', 'contextualHelpButton'] as $forbidden) {
    if (str_contains($layout, $forbidden)) {
        $fail("The shared layout must not render a generic Knowledge Base control ({$forbidden}).");
    }
}
foreach (['goldCount', 'silverCount'] as $key) {
    if (!str_contains($layout, "number_format_x(\$vars[\"{$key}\"])")) {
        $fail("Top-bar {$key} must use number_format_x like the stock bar.");
    }
}
if (substr_count($layout, "\u{202D}<?= htmlspecialchars((string) number_format_x") !== 2) {
    $fail('Top-bar currency values must be wrapped in LRO/PDF marks like the stock bar.');
}

echo "Building tab URL contract passed.\n";
