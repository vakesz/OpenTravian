<?php

declare(strict_types=1);

use Game\AdminUi;
use Game\AdminView;

// The control panel and the setup wizard share one Simple / Advanced mechanism and one dark palette.

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/AdminUi.php';
require_once $root . '/game/include/Game/AdminView.php';

function expectAdminView(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

// The remembered view: Simple by default, a valid ?view= wins and is stored, anything else is ignored.
expectAdminView(AdminView::resolve([], []) === ['mode' => 'simple', 'remember' => null], 'The first visit is Simple.');
expectAdminView(AdminView::resolve(['adminView' => 'advanced'], []) === ['mode' => 'advanced', 'remember' => null], 'The cookie keeps the choice.');
expectAdminView(AdminView::resolve(['adminView' => 'advanced'], ['view' => 'simple']) === ['mode' => 'simple', 'remember' => 'simple'], 'A choice in the query replaces the cookie and is stored.');
expectAdminView(AdminView::resolve(['adminView' => 'bogus'], ['view' => ['x']]) === ['mode' => 'simple', 'remember' => null], 'Invalid values fall back to Simple and store nothing.');
expectAdminView(AdminView::resolve(['setupMode' => 'advanced'], ['mode' => 'simple'], 'setupMode', 'mode') === ['mode' => 'simple', 'remember' => 'simple'], 'The wizard uses its own cookie and parameter.');
expectAdminView(AdminView::withoutParameter('/admin.php?action=backups&view=advanced&page=2') === '/admin.php?action=backups&page=2' && AdminView::withoutParameter('/admin.php?view=simple') === '/admin.php', 'The view parameter is dropped from the URL to return to.');
$source = (string) file_get_contents($root . '/game/include/Game/AdminView.php');
expectAdminView(str_contains($source, "'httponly' => true") && str_contains($source, "'samesite' => 'Lax'"), 'The cookie is HttpOnly and SameSite=Lax.');

// Helpers.
expectAdminView(AdminUi::page('Backups', 'Lead.', '<p>x</p>') === '<h1>Backups</h1><p class="adminLead">Lead.</p><p>x</p>', 'page() opens with a title and a lead.');
expectAdminView(!str_contains(AdminUi::page('T', '', ''), 'adminLead'), 'An empty lead is left out.');
expectAdminView(str_contains(AdminUi::empty('Nothing <here>'), 'Nothing &lt;here&gt;') && str_contains(AdminUi::empty('x'), 'adminEmpty'), 'empty() escapes its message.');
expectAdminView(AdminUi::advanced('<p>a</p>') === '<div class="advancedOnly"><p>a</p></div>' && AdminUi::simple('<p>s</p>') === '<div class="simpleOnly"><p>s</p></div>', 'advanced() and simple() wrap in the classes the CSS hides.');
$confirm = AdminUi::confirmButton('Restore', 'Replace "everything"?');
expectAdminView(str_contains($confirm, 'data-confirm="Replace &quot;everything&quot;?"') && str_contains($confirm, '<button'), 'confirmButton() carries its escaped message.');
$toggle = AdminUi::viewToggle('advanced', '/admin.php?action=backups');
expectAdminView(str_contains($toggle, 'href="/admin.php?action=backups&amp;view=simple"') && preg_match('~aria-current="true">Advanced~', $toggle) === 1, 'The toggle links to the other view and marks the current one.');

// The stylesheet: one palette in two schemes, the same hiding rule for the panel and the wizard.
$css = (string) file_get_contents($root . '/assets/gpack/travian/mainPage/lang/admin.css');
preg_match('~:root \{(.*?)\n\}~s', $css, $light);
preg_match('~@media \(prefers-color-scheme: dark\) \{\s*body\.adminPanel,\s*body\.setupPage \{(.*?)\n    \}~s', $css, $dark);
expectAdminView(isset($light[1], $dark[1]), 'admin.css must define the palette in :root and a dark variant.');
preg_match_all('~--(a-[a-z0-9-]+):~', $light[1], $lightTokens);
preg_match_all('~--(a-[a-z0-9-]+):~', $dark[1], $darkTokens);
expectAdminView($lightTokens[1] === $darkTokens[1], 'Every colour token needs a dark value (and no more): ' . implode(',', array_diff($lightTokens[1], $darkTokens[1])));
preg_match_all('~var\(--(a-[a-z0-9-]+)\)~', $css, $used);
expectAdminView(array_diff(array_unique($used[1]), $lightTokens[1]) === [], 'Every colour token in use is defined.');
expectAdminView(str_contains($css, '[data-mode="simple"] .advancedOnly') && str_contains($css, '[data-mode="advanced"] .simpleOnly'), 'One hiding rule serves the panel and the wizard.');

// Wiring.
$dispatcher = (string) file_get_contents($root . '/game/include/admin/include/Core/Dispatcher.php');
$layout = (string) file_get_contents($root . '/game/include/admin/include/templates/layout.php');
expectAdminView(str_contains($dispatcher, 'AdminView::resolve($_COOKIE, $_GET)') && strpos($dispatcher, 'AdminView::resolve') > strpos($dispatcher, "isAdmin()"), 'The view is resolved after the administrator check.');
expectAdminView(str_contains($layout, 'data-mode="<?= AdminUi::esc($params[\'view\']') && str_contains($layout, 'AdminUi::viewToggle(') && str_contains($layout, 'name="color-scheme"') && str_contains($layout, 'data-confirm'), 'The layout carries the mode, the toggle, the colour scheme and the confirm handler.');

echo "Admin view contract passed.\n";
