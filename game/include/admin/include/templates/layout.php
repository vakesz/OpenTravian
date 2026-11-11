<?php

use Game\AdminUi;

/** @var array $params */
$gpackUrl = get_gpack_cdn_mainPage_url();
$cssVersion = static function (string $file): int {
    $path = dirname(__DIR__, 5) . '/assets/gpack/travian/mainPage/lang/' . $file;
    return is_file($path) ? (int)filemtime($path) : 0;
};
$handler = (string)($params['handler'] ?? 'main');
$currentUrl = $handler === 'main' ? 'admin.php' : 'admin.php?action=' . $handler;
$direction = strtoupper((string)get_language_properties('direction')) === 'RTL' ? 'rtl' : 'ltr';
$adminUid = (int) Core\Session::getInstance()->getAdminUid();
$staff = $adminUid === 2;
$uidLabel = $adminUid === 0 ? 'Administrator' : ($adminUid === 2 ? 'Multihunter' : Game\Admin\AdminRoles::label((string) Game\Admin\AdminAccounts::role($adminUid)));
?>
<!DOCTYPE html>
<html lang="en" dir="<?= $direction; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Control panel - <?= AdminUi::esc(Core\Config::getProperty('settings', 'serverName')); ?></title>
    <link rel="stylesheet" href="<?= AdminUi::esc(get_gpack_link_and_hash('imports.css')); ?>">
    <link rel="stylesheet" href="<?= AdminUi::esc($gpackUrl . 'lang/admin.css?v=' . $cssVersion('admin.css')); ?>">
    <script src="/js/default/jquery-4.0.0.js"></script>
</head>
<body class="<?= $direction; ?> adminPanel" data-mode="<?= AdminUi::esc($params['view'] ?? 'simple'); ?>">
<div class="adminPage">
    <header class="adminHeader">
        <a class="adminBrand" href="admin.php">
            <img src="<?= AdminUi::esc($gpackUrl); ?>logo.png" alt="" width="40">
            <span>
                <strong>Control panel</strong>
                <small><?= AdminUi::esc(Core\Config::getProperty('settings', 'serverName')); ?></small>
            </span>
        </a>
        <div class="adminHeaderMeta">
            <?= AdminUi::viewToggle((string)($params['view'] ?? 'simple'), Game\AdminView::withoutParameter((string)($_SERVER['REQUEST_URI'] ?? 'admin.php'))); ?>
            <span><?= AdminUi::esc(Core\Session::getInstance()->getName()); ?> (<?= $uidLabel; ?>)</span>
            <a class="adminHeaderLink" href="<?= AdminUi::esc($params['gameWorldUrl']); ?>">Open the game</a>
        </div>
    </header>
    <div class="adminShell">
        <nav class="adminMenu" aria-label="Control panel">
            <button type="button" class="adminMenuAll" hidden>Collapse all</button>
            <?php foreach ($params['menuGroups'] as $group):
                $groupId = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $group['title']));
                $hasCurrent = in_array($currentUrl, array_column($group['items'], 0), true);
                ?>
                <div class="adminMenuGroup" data-menu-group="<?= AdminUi::esc($groupId); ?>"<?= $hasCurrent ? ' data-current="1"' : ''; ?>>
                    <h2 class="adminMenuTitle">
                        <button type="button" class="adminMenuToggle" aria-expanded="true" aria-controls="adminMenuList-<?= AdminUi::esc($groupId); ?>">
                            <span class="adminMenuChevron" aria-hidden="true"></span>
                            <span class="adminMenuTitleText"><?= AdminUi::esc($group['title']); ?></span>
                        </button>
                    </h2>
                    <div class="adminMenuList" id="adminMenuList-<?= AdminUi::esc($groupId); ?>">
                        <?php foreach ($group['items'] as [$url, $label]): ?>
                            <a href="<?= AdminUi::esc($url); ?>"<?= $url === $currentUrl ? ' class="current" aria-current="page"' : ''; ?>><?= AdminUi::esc($label); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>
        <main class="adminMain contentV2">
            <?= $params['content']; ?>
        </main>
        <?php if ($params['infoSide']): ?>
            <aside class="adminAside"><?= $params['infoSide']; ?></aside>
        <?php endif; ?>
    </div>
    <footer class="adminFooter">
        <span><?= sprintf('Calculated in %s ms', round(1000 * (microtime(true) - $params['loadStartTime']), 2)); ?></span>
        <span>Server time <b><?= AdminUi::esc($params['currentTime']); ?></b></span>
        <form method="post" action="admin.php" class="adminInline"><?= Core\Session::getCheckerInput(); ?><input type="hidden" name="toggleInterruption" value="1"><?= AdminUi::button(getCustom('allowInterruptionInGame') ? 'Live-world changes allowed' : 'Live-world changes locked', 'grey'); ?></form>
    </footer>
</div>
<script src="/admin-menu.js?v=<?= (int)filemtime(PUBLIC_INTERNAL_PATH . 'admin-menu.js'); ?>"></script>
<script>
    jQuery(function ($) {
        $(document).on('click', '[data-confirm]', function (event) {
            if (!window.confirm(this.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
        $(document).on('click', '.adminSecretToggle', function () {
            var input = document.getElementById(this.getAttribute('data-target'));
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            this.setAttribute('aria-pressed', String(reveal));
            this.setAttribute('aria-label', reveal ? 'Hide value' : 'Show value');
        });
    });
</script>
</body>
</html>
