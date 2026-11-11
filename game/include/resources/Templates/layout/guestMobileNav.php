<?php

declare(strict_types=1);

/**
 * Public-page mobile header. This deliberately stays separate from the
 * authenticated navigation in Layout.php: guests have no village, currency,
 * profile, or logout state to render.
 *
 * @var array<string, mixed> $vars
 */
$guestFooterLinks = is_array($vars['footerLinks'] ?? null) ? $vars['footerLinks'] : [];
$localUrl = static function ($candidate, string $fallback): string {
    return is_string($candidate)
        && str_starts_with($candidate, '/')
        && !str_starts_with($candidate, '//')
        ? $candidate
        : $fallback;
};
$guestHomepageUrl = $localUrl(
    $vars['homepageUrl'] ?? ($guestFooterLinks['home'] ?? null),
    getHomepageUrl(),
);
$guestSupportUrl = $localUrl($guestFooterLinks['support'] ?? null, getSupportTicketUrl());
$guestLoginUrl = $localUrl($vars['publicHomepageLoginUrl'] ?? null, '/');
$guestRegisterUrl = $localUrl($vars['publicHomepageRegisterUrl'] ?? null, '/homepage#registration');
?>
<div id="header" class="referAFriend">
    <input type="checkbox" id="mobileMenuState"/>

    <nav id="mobileMenu">
        <ul></ul>
        <svg viewBox="0 0 200 10" class="divider">
            <path d="m200 5-78.7-2.5c.2.75.54 1.57.67 2.35h-2.49c-.08-1.31-1.16-2.35-2.48-2.35s-2.41 1.04-2.48 2.35h-9.67L100 0l-4.85 4.85h-9.67C85.4 3.54 84.32 2.5 83 2.5s-2.41 1.04-2.48 2.35h-2.49c.13-.78.47-1.6.67-2.35L0 5l78.7 2.5c-.22-.74-.55-1.58-.67-2.35h2.49C80.6 6.46 81.68 7.5 83 7.5s2.41-1.04 2.48-2.35h9.67L100 10l4.85-4.85h9.67c.08 1.31 1.16 2.35 2.48 2.35s2.41-1.04 2.48-2.35h2.49c-.12.77-.46 1.61-.67 2.35L200 5Z"></path>
        </svg>
        <ul>
            <li><a class="mainpage" href="<?= htmlspecialchars($guestHomepageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" title=""><?= T('Global', 'Footer.HomePage') ?></a></li>
            <?php if (getDiscordUrl() !== ''): ?>
            <li><a class="forum" href="<?= htmlspecialchars(getDiscordUrl(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= T('Global', 'Footer.Discord') ?></a></li>
            <?php endif; ?>
            <li><a class="support" href="<?= htmlspecialchars($guestSupportUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank"><?= T('Global', 'Footer.Support') ?></a></li>
        </ul>
    </nav>

    <div id="navigation">
        <label class="mobileMenuButton" for="mobileMenuState"></label>
    </div>

    <nav id="mobileMenu" class="">
        <ul>
            <li class="mainpageItem"><a class="mainpage" href="<?= htmlspecialchars($guestHomepageUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(T('Global', 'Footer.HomePage'), ENT_QUOTES, 'UTF-8') ?>" target="_blank">HOMEPAGE</a></li>
            <li class="loginItem"><a class="login" href="<?= htmlspecialchars($guestLoginUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(T('Global', 'Login'), ENT_QUOTES, 'UTF-8') ?>">LOGIN</a></li>
            <li class="registerItem"><a class="register" href="<?= htmlspecialchars($guestRegisterUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(T('Global', 'Register'), ENT_QUOTES, 'UTF-8') ?>" target="_blank">REGISTER</a></li>
            <?php if (getDiscordUrl() !== ''): ?>
            <li><a class="forum" href="<?= htmlspecialchars(getDiscordUrl(), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(T('Global', 'Footer.Discord'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">DISCORD</a></li>
            <?php endif; ?>
            <li><a class="support" href="<?= htmlspecialchars($guestSupportUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(T('Global', 'Footer.Support'), ENT_QUOTES, 'UTF-8') ?>" target="_blank">SUPPORT</a></li>
        </ul>
    </nav>
</div>
