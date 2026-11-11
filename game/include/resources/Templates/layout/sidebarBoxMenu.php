<?php

$path = rtrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$isSupport = $path === '/support/ticket';
$isActivate = strtolower(basename($path)) === 'activate.php';
$sidebarFooterLinks = is_array($vars['footerLinks'] ?? null) ? $vars['footerLinks'] : [];
$localUrl = static function ($candidate, string $fallback): string {
    return is_string($candidate)
        && str_starts_with($candidate, '/')
        && !str_starts_with($candidate, '//')
        ? $candidate
        : $fallback;
};
$sidebarHomepageUrl = $localUrl(
    $vars['homepageUrl'] ?? ($sidebarFooterLinks['home'] ?? null),
    getHomepageUrl(),
);
$sidebarLoginUrl = $localUrl($vars['publicHomepageLoginUrl'] ?? null, '/');
$sidebarRegisterUrl = $localUrl($vars['publicHomepageRegisterUrl'] ?? null, '/homepage#registration');
$sidebarSupportUrl = $localUrl($sidebarFooterLinks['support'] ?? null, getSupportTicketUrl());
?>
<div id="sidebarBoxMenu" class="sidebarBox expanded">
    <div class="header noHeader"></div>
    <div class="content">
        <ul>
            <li class="first">
                <a href="<?= htmlspecialchars($sidebarHomepageUrl, ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars(T('Global', 'Footer.HomePage'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?= T('Global', 'Footer.HomePage'); ?></a>
            </li>
            <li<?= !$isSupport && !$isActivate ? ' class="active"' : ''; ?>>
                <a href="<?= htmlspecialchars($sidebarLoginUrl, ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars(T('Global', 'Login'), ENT_QUOTES, 'UTF-8'); ?>"><?= T('Global', 'Login'); ?></a>
            </li>
            <li<?= $isActivate ? ' class="active"' : ''; ?>>
                <a href="<?= htmlspecialchars($sidebarRegisterUrl, ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars(T('Global', 'Register'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?= T('Global', 'Register'); ?></a>
            </li>
            <?php if (getDiscordUrl() !== '') { ?>
            <li>
                <a href="<?= htmlspecialchars(getDiscordUrl(), ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars(T('Global', 'Footer.Discord'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?= T('Global', 'Footer.Discord'); ?></a>
            </li>
            <?php } ?>
            <li<?= $isSupport ? ' class="active"' : ''; ?>>
                <a href="<?= htmlspecialchars($sidebarSupportUrl, ENT_QUOTES, 'UTF-8'); ?>" title="<?= htmlspecialchars(T('Global', 'Footer.Support'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?= T('Global', 'Footer.Support'); ?></a>
            </li>
        </ul>
    </div>
</div>
