<?php

declare(strict_types=1);

function failAllianceNestedRoutesContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$router = file_get_contents($root . '/game/include/mainInclude.php');
$controller = file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
$menu = file_get_contents($root . '/game/include/resources/Templates/alliance/menu.php');
$profile = file_get_contents($root . '/game/include/resources/Templates/alliance/Profile.php');
$forum = file_get_contents($root . '/game/include/Controller/AllianceForum.php');
$forumModel = file_get_contents($root . '/game/include/Model/ForumModel.php');
$translations = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$internal = file_get_contents($root . '/game/include/resources/Templates/alliance/internal.php');

if ($router === false || $controller === false || $menu === false || $profile === false || $forum === false || $forumModel === false || $translations === false || $internal === false) {
    failAllianceNestedRoutesContract('Alliance sources must be readable.');
}

if (!str_contains($forumModel, 'if (strlen($name) > 20)')
    || !str_contains($forumModel, 'if (strlen($desc) > 38)')) {
    failAllianceNestedRoutesContract('Forum validation must accept the complete native 20/38-character form limits.');
}

if (!str_contains($router, "preg_match('#^/alliance/(\\d+)")) {
    failAllianceNestedRoutesContract('Nested /alliance/{id}/… routes must dispatch in mainInclude.');
}

if (!str_contains($controller, "\$this->view->vars['bodyCssClass'] = 'alliance';")) {
    failAllianceNestedRoutesContract('Membered alliance pages must use body class alliance.');
}

if (!str_contains($controller, "\$this->view->vars['bodyCssClass'] = 'forum';")
    || !str_contains($controller, "\$this->view->vars['contentCssClass'] = 'forum forumForum';")) {
    failAllianceNestedRoutesContract('Alliance forum must use body forum and content forum forumForum.');
}

if (!str_contains($controller, "allianceOverview") || !str_contains($controller, "allianceOptions")) {
    failAllianceNestedRoutesContract('Overview/Options must append Gpack content classes allianceOverview/allianceOptions.');
}

$options = file_get_contents($root . '/game/include/resources/Templates/alliance/options.php');
if ($options === false || !str_contains($options, '/alliance/') || str_contains($options, 'allianz.php?s=5')) {
    failAllianceNestedRoutesContract('Alliance options links must use clean /alliance/{id}/options?o=… hrefs.');
}

if (!str_contains($options, 'class="round"') || !str_contains($options, "\$base . '/name'") || !str_contains($options, 'ownAllianceRoutes')) {
    failAllianceNestedRoutesContract('Alliance options headings must use round and named /alliance/options/… hrefs.');
}

if (!str_contains($options, '/alliance/options/banner') || !str_contains($options, "\$bannerBase . '/create'") || !str_contains($options, 'Alliance banner')) {
    failAllianceNestedRoutesContract('Alliance options must expose Create/My/Gallery banner links.');
}

$attacks = file_get_contents($root . '/game/include/resources/Templates/alliance/attacks.php');
if ($attacks === false
    || !str_contains($attacks, 'filterWrapper')
    || !str_contains($attacks, 'allianceReportFilterButton')
    || !str_contains($attacks, 'id="offs"')
    || str_contains($attacks, 'boxes boxesColor')) {
    failAllianceNestedRoutesContract('Alliance attacks must use V2 report-type filterWrapper + #offs without legacy boxes chrome.');
}

if (!str_contains($attacks, "? '/alliance/reports'")
    || !str_contains($attacks, ": ('/alliance/' . \$aid . '/attacks')")) {
    failAllianceNestedRoutesContract('Alliance report filter icons must use the canonical own- and outsider-alliance routes.');
}

if (!str_contains($controller, 'allianceReports')) {
    failAllianceNestedRoutesContract('Attacks tab must append content class allianceReports.');
}

if (!str_contains($controller, '$attacksBase = $this->isOwnAllianceRoute()')
    || !str_contains($controller, "? '/alliance/reports'")
    || !str_contains($controller, ": ('/alliance/' . \$this->selectedAllianceID . '/attacks');")) {
    failAllianceNestedRoutesContract('Own-alliance report-row filters must retain the canonical /alliance/reports route.');
}

if (!str_contains($controller, "in_array((int) \$row['type'], \$attack_types_32, true)")
    || str_contains($controller, 'in_array($attack_types, $attack_types_32)')) {
    failAllianceNestedRoutesContract('Alliance report rows must select defender data from the individual defense-report type.');
}

if (!str_contains($menu, '/overview') || !str_contains($menu, '/profile') || !str_contains($menu, '/forum') || !str_contains($menu, '/reports')) {
    failAllianceNestedRoutesContract('Alliance menu tabs must use clean /alliance/… hrefs including /reports for Attacks.');
}

if (!str_contains($menu, 'if ($isMy)')) {
    failAllianceNestedRoutesContract('Alliance menu must gate member-only tabs behind isMyAlliance.');
}

if (!str_contains($menu, '<div class="navigationSpacer"></div>')) {
    failAllianceNestedRoutesContract('Alliance navigation must preserve the native mobile spacer after the tab strip.');
}

if (str_contains($menu, 'tabFavorWrapper')) {
    failAllianceNestedRoutesContract('Alliance navigation must keep the reference subNavi class without a stale wrapper modifier.');
}

// Internal values such as `allianz.php?s=2` or `/alliance/forum` have no
// http(s) host, so externalForumLink() keeps them on the native forum.
if (!str_contains($controller, 'private function externalForumLink(string $link): string')
    || !str_contains($controller, "in_array(\$scheme, ['http', 'https'], true) && \$host !== ''")) {
    failAllianceNestedRoutesContract('Alliance forum routing must retain the clean native forum for old internal forumLink values.');
}

if (!str_contains($forum, 'private function forumRoute(): string')
    || !str_contains($forum, "return '/alliance/forum';")
    || str_contains($forum, 'allianz.php')) {
    failAllianceNestedRoutesContract('Alliance forum controls must use the maintained clean forum route rather than legacy dispatcher URLs.');
}

if (str_contains($forum, "csrfQuery() . '\\\\\"")) {
    failAllianceNestedRoutesContract('Alliance forum admin action URLs must not append a literal backslash to their CSRF token.');
}

foreach (['editTopic.php', 'forum_edit.php', 'forum_topic.php', 'newForum.php', 'newPost.php', 'new_topic.php', 'showTopic.php'] as $template) {
    $contents = file_get_contents($root . '/game/include/resources/Templates/alliance/' . $template);
    if ($contents === false || !str_contains($contents, 'forumRoute') || str_contains($contents, 'allianz.php')) {
        failAllianceNestedRoutesContract("Alliance forum template {$template} must use the clean forum route.");
    }
}

if (!str_contains($router, '/alliance/(overview|profile|forum|attacks|reports|bonuses|options)')) {
    failAllianceNestedRoutesContract('Own-alliance routes without id must dispatch in mainInclude.');
}

if (!str_contains($router, '/alliance/options/banner/(create|my|gallery)')) {
    failAllianceNestedRoutesContract('Alliance banner create/my/gallery routes must dispatch in mainInclude.');
}

if (!str_contains($profile, 'Ranks and level') || !str_contains($profile, 'class="transparent details"')) {
    failAllianceNestedRoutesContract('Alliance profile details must match the V2 ranks table shell.');
}

if (!str_contains($profile, 'roundV2 allianceBanner') || !str_contains($profile, 'bannerTemplateSVG')) {
    failAllianceNestedRoutesContract('Alliance profile must render the Alliance banner heading and template SVG shell.');
}

if (!str_contains($profile, 'accuseAlliance') || !str_contains($profile, 'isMyAlliance')) {
    failAllianceNestedRoutesContract('Outsider alliance profile must expose .accuseAlliance control gated by isMyAlliance.');
}

if (!str_contains($controller, 'showAllianceBanner') || !str_contains($controller, 'AllianceBannerModel')) {
    failAllianceNestedRoutesContract('AllianceCtrl must render banner screens via AllianceBannerModel.');
}

if (!str_contains($translations, "\$Definition['BBCode']['attack'] = 'attack';")
    || !str_contains($translations, "\$Definition['BBCode']['defense'] = 'defense';")) {
    failAllianceNestedRoutesContract('Alliance overview graph legends must use the lowercase reference wording.');
}

if (!str_contains($internal, '<div class="clear"></div>')) {
    failAllianceNestedRoutesContract('Alliance overview must clear its floated charts so the content frame encloses them.');
}

echo "Alliance nested routes contract passed.\n";
