<?php

use Core\Config;
use Core\Session;
use Core\Helper\TimezoneHelper;
use Core\Helper\VillagePerspective;

$layoutSession = Session::getInstance();
$tasksUrl =
    "/tasks?t=village&amp;kid=" .
    ($layoutSession->isValid() ? (int) $layoutSession->getKid() : 0);

?>

<?php require __DIR__ . "/head.php"; ?>

<?php
// Controllers still tag their village perspective inside bodyCssClass. The
// body never emits it as a class (it uses <page> + village1|2|3), so it is read
// here for data-village-perspective and stripped from the emitted body class.
$villagePerspective =
    strpos((string) $vars["bodyCssClass"], "perspectiveResources") !== false
        ? "perspectiveResources"
        : "perspectiveBuildings";
$bodyClassTokens = array_values(array_diff(
    preg_split("/\s+/", trim((string) $vars["bodyCssClass"])) ?: [],
    ["perspectiveResources", "perspectiveBuildings", ""]
));
$layoutDirection = strtoupper((string) ($vars['layoutDirection'] ?? getDirection()));
$layoutDirection = $layoutDirection === 'RTL' ? 'RTL' : 'LTR';
$footerLabels = is_array($vars['footerLabels'] ?? null)
    ? $vars['footerLabels']
    : [];
$footerLinks = is_array($vars['footerLinks'] ?? null)
    ? $vars['footerLinks']
    : [];
$homepageLink = $vars['homepageUrl'] ?? null;
$homepageUrl = is_string($homepageLink)
    && str_starts_with($homepageLink, '/')
    && !str_starts_with($homepageLink, '//')
    ? $homepageLink
    : getHomepageUrl();
$footerLinks['home'] ??= $homepageUrl;
$footerRow = \Game\FooterLinks::build($footerLabels, $footerLinks, $vars['publicHomepageLocale'] ?? null);
$footerVersion = \Game\FooterLinks::version();
$useGameMobileOptimizations = !array_key_exists('useGameMobileOptimizations', $vars)
    || (bool) $vars['useGameMobileOptimizations'];
// The logged-in desktop body is e.g. `village1 ltr` without mobileOptimized,
// while the guest shell always carries the class. For authenticated players it
// remains a capability gate for MobileOptimizations → mobileForced under narrow
// portrait, so only mobile UAs opt in.
$mobileUa = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$emitMobileOptimized = $useGameMobileOptimizations
    && (!$layoutSession->isValid()
        || (bool) preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i', $mobileUa));
// The reference login shell is already excluded from the native swipe handler.
// Local public pages that use its checkbox drawer without the `login` body
// token must use the same native guard, otherwise a horizontal gesture opens
// the duplicate generic sidebar behind that drawer.
$disableGuestSwipeNavigation =
    !empty($vars["showGuestNavBar"]) && !in_array("login", $bodyClassTokens, true);
$bodyClasses = implode(" ", array_filter([
    implode(" ", $bodyClassTokens),
    $vars["colorBlind"] ? "colorBlind" : "",
    $layoutDirection === "RTL" ? "rtl" : "ltr",
    $emitMobileOptimized ? "mobileOptimized" : "",
    $disableGuestSwipeNavigation ? "preventMobileSwipeNavigation" : "",
]));
$closeContentHref = (string) (
    $vars["closeContentHref"]
    ?? VillagePerspective::closeContentHref()
);
?>

<?php
$nightMoodEnabled =
    $layoutSession->isValid() &&
    (int) ($layoutSession->getDisplay()[6] ?? 0) === 1;
$nightMoodUsesServerTime = !$layoutSession->isValid() || $nightMoodEnabled;
$nightMoodServerTheme = \Core\Helper\NightMood::themeForPage(
    $layoutSession->isValid(),
    $nightMoodEnabled,
);
?>

<body class="<?= htmlspecialchars($bodyClasses, ENT_QUOTES, "UTF-8") ?>" data-theme="<?= htmlspecialchars(
     $nightMoodServerTheme,
     ENT_QUOTES,
     "UTF-8"
 ) ?>" data-night-mood-time-based="<?= $nightMoodUsesServerTime
     ? "1"
     : "0" ?>" data-night-mood-server-theme="<?= htmlspecialchars(
     $nightMoodServerTheme,
     ENT_QUOTES,
     "UTF-8"
 ) ?>" data-village-perspective="<?= $villagePerspective ?>">
<script type="text/javascript">
(function () {
    var body = document.body;
    if (!body) {
        return;
    }
    function resolveTheme() {
        try {
            if (window.matchMedia) {
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'night' : 'default';
            }
        } catch (e) {}
        var usesServerTime = body.getAttribute('data-night-mood-time-based') === '1';
        var serverTheme = body.getAttribute('data-night-mood-server-theme') || 'default';
        return usesServerTime ? serverTheme : 'default';
    }
    function applyTheme() {
        var theme = resolveTheme();
        document.documentElement.setAttribute('data-theme', theme);
        body.setAttribute('data-theme', theme);
    }
    applyTheme();
    try {
        var media = window.matchMedia('(prefers-color-scheme: dark)');
        if (typeof media.addEventListener === 'function') {
            media.addEventListener('change', applyTheme);
        } else if (typeof media.addListener === 'function') {
            media.addListener(applyTheme);
        }
    } catch (e) {}
})();
</script>

<div id="reactPortalRoot"></div>
<div id="reactDialogWrapper"></div>

<div id="background" class="<?= !empty($vars['isShore']) ? 'isShore' : ''; ?>">

    <?php if ($vars["headerBar"]) {
        echo '<div id="headerBar"></div>';
    } ?>

        <div id="topBar">

            <a id="logo" href="<?= htmlspecialchars(
                $homepageUrl,
                ENT_QUOTES,
                'UTF-8'
            ) ?>" target="_blank"

               title="<?= T("Global", "Travian") ?>">

				<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 172.7 60">

				  <path d="M52.9 34.4c-.2-.3-.3-.7-.3-1.3 0-.8-.1-1.2-.1-1.3l-.1-3.9.1-6.6c0-1.1.1-2.2.1-3.3.1-1.5.2-2.4.3-2.8.1 0 .3 0 .6-.1h.4c2.6 0 4.1.3 4.4.8.1.1.1.4.3.8.1.4.2.5.2.5s.1 0 .2-.1V17c0-.4.1-.9.2-1.4.2-.5.3-1 .5-1.6l-.4-.1c-.9.2-1.9.4-3.1.4h-5l-6.5-.3c-.3 0-.5.4-.8 1.2-.1.5-.3 1-.4 1.4-.2.5-.3.8-.3.8v.2h.2c.1-.4.3-.7.6-1 .3-.3.6-.5.9-.6.3-.1.9-.2 2.1-.4 1.3-.2 2.1-.3 2.5-.3.3 0 .4 1.5.4 4.5l-.1 8.6c-.1 2.2-.3 3.7-.3 4.6-.1.9-.2 1.5-.4 1.8-.1.1-.2.2-.4.2-.2.1-.3.2-.3.3l.5.1c.2-.1.4-.1.5-.1 1.1-.1 1.8-.2 1.9-.2l2.8.2c.2 0 .3-.1.4-.2 0 0-.3-.1-.8-.2-.4-.2-.7-.3-.8-.5zm19.7-.1c.6.7 1.4 1 2.3 1h.9c1.2-.1 1.8-.2 1.9-.3-.1 0-.4-.1-.8-.3-.2-.1-.5-.2-.6-.4-.5-.4-1.1-1.2-1.7-2.2-.5-.9-1-1.8-1.4-2.7-.3-.5-.7-1.3-1.4-2.4-.4-.8-.7-1.2-.7-1.2 0-.1.4-.5 1-1.1.7-.7 1.3-1.4 1.6-2.1.6-1 .9-2.2.9-3.4 0-1.8-.7-3.1-2.1-3.9-1-.6-2.3-1-3.7-1l-2.8.1c-.9 0-1.6.1-2.1.1l-1.5.1c-.1 0-.3.1-.6.2l.3.1c.2.1.2.1.3.2.4 1 .6 2 .6 3l.1 3.9v2s0 .3-.1.8c0 .5-.1.7-.1.8v2l-.2 4.2c0 1.9-.1 3-.2 3.3-.3.4-.5.6-.6.7 1.2-.2 2-.3 2.3-.3h2.2s.3-.1.9-.2c-.1-.1-.2-.1-.4-.1-.9 0-1.4-1-1.4-3v-5.4c0-.2.1-.4.3-.4 1.2 0 2 .2 2.4.6.5.5 1 1.3 1.5 2.1.4.7.8 1.5 1.2 2.2.8 1.7 1.4 2.7 1.7 3zm-5.2-8.2h-1.8l.1-11.3h.9c1.4 0 2.6.8 3.6 2.5.9 1.5 1.4 2.9 1.4 4.3 0 1.9-.3 3.1-.9 3.6-.4.7-1.5.9-3.3.9zm33.6 8.6c-.4-.2-.7-.5-.9-.9-1.2-2-2.5-4.9-3.8-8.6-.9-2.6-1.7-5.1-2.6-7.7-.2-.7-.5-1.3-.7-2-.4-1-.9-1.5-1.3-1.5h-3.2c-.2 0-.4.1-.6.1-.2 0-.3.1-.3.2.1.1.2.1.2.1h.5c.3 0 .5.1.8.3.2.2.3.5.3.8 0 .5-.9 3-2.6 7.5-1.6 4.1-2.7 6.9-3.4 8.5-.7 1.6-1.5 2.8-2.5 3.6.3 0 .8 0 1.4.1.7.1 1.1.1 1.4.1l3.6-.5c-.7-.2-1.4-.5-2.1-.8-.6-.3-.9-.8-.9-1.7 0-.8.4-2.2 1.1-4.1l.6-1.8c.4-1 .8-1.6 1-1.6h5.4c.8 1.8 1.4 3.2 1.8 4.2.4 1.1.9 2.3 1.3 3.5.8 1.7 1.7 2.6 2.7 2.6l3-.2c.3.1.1 0-.2-.2zm-8.5-10.3h-5.4l2.8-7.4c.4.7.6 1.3.8 1.7l1.8 5.4v.3zm20.7-10.2c.3-.1.6.2.8.7.2.4.2.8.2 1.3 0 1.3-.8 4.2-2.3 8.5-1 2.8-1.8 4.9-2.5 6.4-1.6-3.7-2.8-6.9-3.8-9.4-.8-2.3-1.5-4-1.8-5.1-.5-1.4-.8-2.1-.8-2.1-.3-.1-.8-.2-1.6-.2h-2.6c-.3 0-.5.1-.6.3.2.1.4.1.6.2.2.1.3.2.5.3 1.1 1.5 2.3 3.7 3.5 6.7.7 1.5 1.6 3.9 2.8 7.1 1.1 3 1.7 4.6 1.8 4.9.6 1.2.9 1.8 1 1.9 2.8-7.3 5.8-14.6 9-21.8h-4.2v.3zm7.8 3.6c0 .3.1.6.1.8v6.8l-.1 4.9-.5 5.1h4.2c.1-.1.1-.2.1-.2-.5-.2-.7-.3-.7-.3-.2-.2-.3-.5-.3-.9 0-.3-.1-.6-.1-1l.1-9.6c.1-3.6.2-6.7.5-9.4h-.3c.1 0-.1 0-.6.1s-1 .1-1.7.1h-1.5c.5.5.7 1.1.8 1.8-.1.5-.1 1.2 0 1.8zm27 16.9c-.4-.2-.7-.5-.9-.9-1.2-2-2.5-4.9-3.8-8.6-.9-2.6-1.7-5.1-2.6-7.7-.2-.7-.5-1.3-.7-2-.4-1-.9-1.5-1.3-1.5l-3.1.1c-.2 0-.4.1-.6.1-.2 0-.3.1-.3.2.1.1.2.1.2.1h.5c.3 0 .5.1.8.3.2.2.3.5.3.8 0 .5-.9 3-2.6 7.5-1.6 4.1-2.7 6.9-3.4 8.5-.7 1.6-1.5 2.8-2.5 3.6.3 0 .8 0 1.4.1.7.1 1.1.1 1.4.1l3.6-.5c-.7-.2-1.4-.5-2.1-.8-.6-.3-.9-.8-.9-1.7 0-.8.4-2.2 1.1-4.1l.6-1.8c.4-1 .8-1.6 1-1.6h5.4c.8 1.8 1.4 3.2 1.8 4.2.4 1.1.9 2.3 1.3 3.5.8 1.7 1.7 2.6 2.7 2.6l3-.2c.2 0 .1-.1-.3-.3zm-8.4-10.3h-5.4L137 17c.4.7.6 1.3.8 1.7l1.8 5.4v.3zm29.7-10.2c-.4 0-1 .2-2 .5-.8.3-1.3.5-1.4.6.2 0 .8 0 1.7-.1.2 0 .3.1.3.2.1 1.4.2 2.5.2 3.2.1 2.1.2 4.4.3 6.6l.1 4.6c0 .2 0 .3-.1.4-3.4-3.8-5.7-6.3-6.8-7.6-2.8-3.3-4.8-6.1-6.2-8.6-2.1.2-3.8.3-4.9.5 1.3.6 2.1 1.4 2.5 2.4.3.6.4 1.6.4 3l-.2 3.7v1.7c0 3.8-.2 6.4-.6 7.9-.1.4-.3.7-.7 1-.5.4-.8.7-.9.7.5.1 1.1.2 2 .2.7 0 1.5-.1 2.5-.3 1.3-.3 2-.5 2-.5-.6-.1-1.1-.2-1.4-.2-.5 0-.9-.1-1.4-.4-.1-.1-.2-.3-.3-.5 0-.1-.1-.3-.1-.7-.2-1.9-.3-3.2-.3-4-.1-3.9-.1-7.1 0-9.7 1.1 1.3 3.1 3.5 5.8 6.7 1.8 2.1 4.3 4.9 7.5 8.4.9.9 1.3 1.4 1.4 1.4.1 0 .1 0 .2-.1.1-1.8.2-3.7.3-5.5.2-3.6.3-5.6.3-6.1v-1.4c0-.3.2-2.6.6-6.9.2-.1.7-.3 1.3-.5.6-.2.9-.3 1.1-.5l-3.2-.1zm-112 27.6H56v9.5h6v-1.2h-4.7m14.5-3.2h5.3v-1.1h-5.3v-2.9h5.7v-1.1h-7v9.5h7.2v-1.2h-5.9m19.1-2.6h2.8v1.8c-.3.2-.7.5-1.2.7s-1.1.3-1.6.3c-.6 0-1.2-.1-1.8-.4-.6-.3-1-.7-1.3-1.3-.3-.6-.4-1.3-.4-2.1 0-.7.1-1.3.4-1.9.1-.3.3-.7.6-.9.3-.3.6-.5 1-.7.4-.2.9-.3 1.5-.3.5 0 .9.1 1.3.2s.7.4.9.7c.2.3.4.7.5 1.1l1.2-.3c-.2-.6-.4-1.2-.7-1.6s-.8-.7-1.3-.9c-.6-.2-1.2-.3-1.9-.3-1 0-1.8.2-2.5.6s-1.3 1-1.7 1.8c-.4.8-.6 1.7-.6 2.6 0 .9.2 1.8.6 2.5s1 1.3 1.8 1.7 1.6.6 2.6.6c.7 0 1.4-.1 2.1-.4.7-.2 1.3-.6 1.9-1.1v-3.5H91v1.1zm14.2-.6h5.4v-1.1h-5.4v-2.9h5.7v-1.1h-7v9.5h7.3v-1.2h-6m21-.9l-5.1-7.4h-1.3v9.5h1.3v-7.5l5 7.5h1.3v-9.5h-1.2m16.8.8c-.4-.3-.9-.6-1.4-.7-.4-.1-1-.1-1.7-.1h-3.3v9.5h3.5c.6 0 1.1-.1 1.6-.2s.8-.3 1.2-.5c.3-.2.6-.5.9-.9.3-.4.5-.8.7-1.4.2-.6.3-1.2.3-1.9 0-.8-.1-1.6-.4-2.3-.5-.5-.9-1.1-1.4-1.5zm-.1 5.7c-.2.5-.4.9-.7 1.2-.2.2-.5.4-.9.5s-.9.2-1.5.2h-2.1V43h2c.8 0 1.3.1 1.7.2.5.2.9.5 1.2 1.1.3.5.5 1.3.5 2.3.1.6 0 1.2-.2 1.7zm15.9-2c-.4-.2-1.1-.4-2.1-.6-1-.2-1.7-.5-1.9-.7-.2-.2-.4-.5-.4-.8 0-.4.2-.7.5-1 .4-.3.9-.4 1.7-.4.7 0 1.3.2 1.7.5.4.3.6.8.7 1.4l1.2-.1c0-.6-.2-1.1-.5-1.5-.3-.4-.7-.8-1.3-1-.5-.2-1.2-.3-1.9-.3-.6 0-1.2.1-1.8.3-.5.2-.9.5-1.2.9-.3.4-.4.9-.4 1.3s.1.8.3 1.2c.2.4.6.6 1 .9.4.2 1 .4 1.9.6.9.2 1.5.4 1.7.5.4.2.7.3.9.6.2.2.3.5.3.8 0 .3-.1.6-.3.8-.2.2-.5.4-.8.6-.4.1-.8.2-1.3.2s-1-.1-1.5-.3-.8-.4-1-.7c-.2-.3-.3-.7-.4-1.2l-1.2.1c0 .6.2 1.2.5 1.7s.8.9 1.4 1.1 1.3.4 2.2.4c.7 0 1.3-.1 1.9-.4.6-.2 1-.6 1.3-1 .3-.4.4-.9.4-1.4 0-.5-.1-1-.4-1.3-.2-.7-.6-1-1.2-1.2z"></path>

				  <image width="156" height="250" transform="matrix(.24 0 0 .24 .22 .14)" xlink:href="<?= htmlspecialchars(
          get_gpack_cdn_mainPage_url() . "logo.png",
          ENT_QUOTES,
          "UTF-8"
      ) ?>"></image>

				</svg>

			</a>

			<?php if ($vars["showNavBar"]) { ?>

			<div id="header" class="referAFriend">

				<input type="checkbox" id="mobileMenuState"/>

					<div id="navigation">

						<a class="village resourceView"

						   href="/dorf1.php"

						   accesskey="1"

						   title="<?= T("inGame", "Navigation.Resources") ?>||"></a>

						<a class="village buildingView"

						   href="/dorf2.php"

						   accesskey="2"

						   title="<?= T("inGame", "Navigation.Buildings") ?>||"></a>

						<a class="map" href="/karte.php" accesskey="3"

						   title="<?= T("inGame", "Navigation.Map") ?>||"></a>

						<a class="statistics" href="/statistics" accesskey="4"

						   title="<?= T("inGame", "Navigation.Statistics") ?>||"></a>

						<a class="reports" href="/report" accesskey="5"

						   title="<?= T("inGame", "Navigation.Reports") ?>||<?= T(
    "inGame",

    "Navigation.newReports"
) ?>: <?= $vars["newReportsCount"] ?>">

						<?php if ($vars["newReportsCount"]): ?>

							<div class="indicator"><?= $vars["newReportsCount"] > 99
           ? "+99"
           : $vars["newReportsCount"] ?></div>

						<?php endif; ?></a>

						<a class="messages" href="/messages" accesskey="6"

						   title="<?= T("inGame", "Navigation.Messages") ?>||<?= T(
    "inGame",

    "Navigation.newMessages"
) ?>: <?= $vars["newMessagesCount"] ?>">

						<?php if ($vars["newMessagesCount"]): ?>

							<div class="indicator"><?= $vars["newMessagesCount"] > 99
           ? "+99"
           : $vars["newMessagesCount"] ?></div>

						<?php endif; ?></a>

						<a class="dailyQuests" href="#" accesskey="7"

						   onclick="Travian.React.openDailyQuestsDialog(); return false;"

						   title="<?= T("DailyQuest", "Daily Quests") ?>||<?= T("DailyQuest", "Collect daily rewards") ?>">

							<?php if (!empty($vars["dailyQuestsIndicator"])): ?><div class="indicator">!</div><?php endif; ?>

						</a>



						<label class="mobileMenuButton" for="mobileMenuState"></label>

						<a class="mobileShopButton" href="#" accesskey="8" title="" onclick="jQuery(window).trigger('startPaymentWizard', {data:{activeTab:'buyGold'}}); this.blur(); return false;"></a>

					</div>

					<div class="currency">
						<?php
                        // Gold SVG, gold value, silver SVG, silver value — same
                        // order so the 4-column currency grid places
                        // amounts beside the coins instead of wrapping under.
                        $currencyCoinKind = 'gold';
                        require __DIR__ . '/currencyCoin.php';
                        ?>
						<div class="value ajaxReplaceableGoldAmount">‭<?= htmlspecialchars((string) number_format_x($vars["goldCount"]), ENT_QUOTES, "UTF-8") ?>‬</div>
						<?php
                        $currencyCoinKind = 'silver';
                        require __DIR__ . '/currencyCoin.php';
                        ?>
						<div class="value ajaxReplaceableSilverAmount" data-load-tooltip="free-silver" title="<?= htmlspecialchars(T("inGame", "silver"), ENT_QUOTES, "UTF-8") ?>">‭<?= htmlspecialchars((string) number_format_x($vars["silverCount"]), ENT_QUOTES, "UTF-8") ?>‬</div>
					</div>

					<a class="shop" href="#" accesskey="8" title="<?= T("inGame", "Navigation.Buy gold") ?>" onclick="jQuery(window).trigger('startPaymentWizard', {data:{activeTab:'buyGold'}}); this.blur(); return false;"></a>
					<?php if (!empty($vars["shopNotificationCount"])): ?><div class="shopNotification"><?= (int) $vars["shopNotificationCount"] ?></div><?php endif; ?>

					<a id="<?= htmlspecialchars(get_button_id(), ENT_QUOTES, 'UTF-8'); ?>"
					   class="layoutButton buttonFramed withIcon round referAFriend green"
					   href="/referAFriend"
					   title="<?= htmlspecialchars(T('ReferAFriend', 'title'), ENT_QUOTES, 'UTF-8'); ?>">
						<svg viewBox="0 0 18.08 20" class="referAFriend"><g class="outline">
						  <path class="human" d="M5.86 9a1.26 1.26 0 01-1.14-1.31V6.36a.72.72 0 01.55-.75.67.67 0 000-.42 4.87 4.87 0 01.2-2.51 1.63 1.63 0 01.28-.52c.29-.38.63-.73.94-1.09A3.84 3.84 0 0111.4.58a4.16 4.16 0 011.86 4.2 5.2 5.2 0 000 1c.63.3.41.88.39 1.38 0 .2-.05.41 0 .63a1.76 1.76 0 01-.09.75c-.15.43-.17.45-.59.6a3.31 3.31 0 01-.71 1.6c-.24.28-.16.65-.24 1s-.13.61-.21.91a1.56 1.56 0 00.83 2 6.14 6.14 0 011.63 1.14 4.54 4.54 0 01.91 1.38c.19.42.06.61-.4.61H.5c-.46 0-.51 0-.5-.52a4.13 4.13 0 012-3.48A6.1 6.1 0 013.57 13a4.77 4.77 0 002.07-1.21 1.2 1.2 0 00.36-1c-.06-.56-.1-1.16-.14-1.79z"></path>
						  <path class="plus" d="M8.38 16.43v-2.56a.71.71 0 01.77-.57h2.27v-2.32c0-.53.17-.68.69-.68h2.09c.7 0 .88.15.89.9v2.1h2.17c.62 0 .81.19.82.81v2c0 .71-.17.88-.87.88h-2.12v2.21c0 .72-.19.8-.81.81h-2c-.69 0-.85-.17-.85-.85v-2.17H9.25c-.42.01-.75-.1-.87-.56z"></path>
						</g><g class="icon">
						  <path class="human" d="M5.86 9a1.26 1.26 0 01-1.14-1.31V6.36a.72.72 0 01.55-.75.67.67 0 000-.42 4.87 4.87 0 01.2-2.51 1.63 1.63 0 01.28-.52c.29-.38.63-.73.94-1.09A3.84 3.84 0 0111.4.58a4.16 4.16 0 011.86 4.2 5.2 5.2 0 000 1c.63.3.41.88.39 1.38 0 .2-.05.41 0 .63a1.76 1.76 0 01-.09.75c-.15.43-.17.45-.59.6a3.31 3.31 0 01-.71 1.6c-.24.28-.16.65-.24 1s-.13.61-.21.91a1.56 1.56 0 00.83 2 6.14 6.14 0 011.63 1.14 4.54 4.54 0 01.91 1.38c.19.42.06.61-.4.61H.5c-.46 0-.51 0-.5-.52a4.13 4.13 0 012-3.48A6.1 6.1 0 013.57 13a4.77 4.77 0 002.07-1.21 1.2 1.2 0 00.36-1c-.06-.56-.1-1.16-.14-1.79z"></path>
						  <path class="plus" d="M8.38 16.43v-2.56a.71.71 0 01.77-.57h2.27v-2.32c0-.53.17-.68.69-.68h2.09c.7 0 .88.15.89.9v2.1h2.17c.62 0 .81.19.82.81v2c0 .71-.17.88-.87.88h-2.12v2.21c0 .72-.19.8-.81.81h-2c-.69 0-.85-.17-.85-.85v-2.17H9.25c-.42.01-.75-.1-.87-.56z"></path>
						</g></svg>
					</a>



					<?php if (isset($vars["stockBar"]) && $vars["showStockbar"]): ?>
					<?php
                    // Gpack hides bare `.productionBoost` (`display:none`); the active
                    // gold/plus boost must carry `.premiumFeature` to paint the arrow.
                    $productionBoostArrow = static fn (bool $boosted): string => \Game\ProductionBoostArrow::forResourceBar($boosted);
                    ?>

                    <div id="stockBar">

						<div class="warehouse">

							<div class="capacity" title="<?= T("Buildings", "10.title") ?>">

								<i class="warehouse_medium"></i>

								<div class="value">‭<?= htmlspecialchars(
            (string) $vars["stockBar"]["maxstoreString"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>‬</div>

							</div>

							<a class="stockBarButton resource1" href="/production.php?t=lumber" title="<?= htmlspecialchars($vars["stockBar"]["titles"][0], ENT_QUOTES, "UTF-8") ?>">

								<i class="lumber_small"></i>

								<?= $productionBoostArrow((bool) $vars["stockBar"]["productionBoost"][0]) ?>

								<div id="l1" class="value"><?= $vars["stockBar"]["storageString"][0] ?></div>

								<div class="barBox">

									<div id="lbar1" class="bar stock<?= $vars["stockBar"][
             "storageClass"
         ][0] ?>" style="width: <?= $vars["stockBar"][
    "percents"
][0] ?>%;"></div>

								</div>

							</a>

							<a class="stockBarButton resource2" href="/production.php?t=clay" title="<?= htmlspecialchars($vars["stockBar"]["titles"][1], ENT_QUOTES, "UTF-8") ?>">

								<i class="clay_small"></i>

								<?= $productionBoostArrow((bool) $vars["stockBar"]["productionBoost"][1]) ?>

								<div id="l2" class="value"><?= $vars["stockBar"]["storageString"][1] ?></div>

								<div class="barBox">

									<div id="lbar2" class="bar stock<?= $vars["stockBar"][
             "storageClass"
         ][1] ?>" style="width: <?= $vars["stockBar"][
    "percents"
][1] ?>%;"></div>

								</div>

							</a>

							<a class="stockBarButton resource3" href="/production.php?t=iron" title="<?= htmlspecialchars($vars["stockBar"]["titles"][2], ENT_QUOTES, "UTF-8") ?>">

								<i class="iron_small"></i>

								<?= $productionBoostArrow((bool) $vars["stockBar"]["productionBoost"][2]) ?>

								<div id="l3" class="value"><?= $vars["stockBar"]["storageString"][2] ?></div>

								<div class="barBox">

									<div id="lbar3" class="bar stock<?= $vars["stockBar"][
             "storageClass"
         ][2] ?>" style="width: <?= $vars["stockBar"][
    "percents"
][2] ?>%;"></div>

								</div>

							</a>



						</div>

						<div class="granary">

							<div class="capacity" title="<?= T("Buildings", "11.title") ?>">

								<i class="granary_medium"></i>

								<div class="value"><?= htmlspecialchars(
            (string) $vars["stockBar"]["maxcropString"],
            ENT_QUOTES,
            "UTF-8"
        ) ?></div>

							</div>

							<a class="stockBarButton resource4" href="/production.php?t=crop" title="<?= htmlspecialchars($vars["stockBar"]["titles"][3], ENT_QUOTES, "UTF-8") ?>">

								<i class="crop_small"></i>

								<?= $productionBoostArrow((bool) $vars["stockBar"]["productionBoost"][3]) ?>

								<div id="l4" class="value <?= $vars["stockBar"]["production"][3] < 0
            ? "alert"
            : "" ?>"><?= $vars["stockBar"]["storageString"][3] ?></div>

								<div class="barBox">

									<div id="lbar4" class="bar stock<?= $vars["stockBar"][
             "storageClass"
         ][3] ?>" style="width:<?= $vars["stockBar"]["percents"][3] ?>%;"></div>

								</div>

							</a>

							<a class="stockBarButton" href="/production.php?t=balance" title="<?= htmlspecialchars($vars["stockBar"]["titles"][4], ENT_QUOTES, "UTF-8") ?>">

								<i class="freeCrop_small"></i>

								<div id="stockBarFreeCrop" class="value">‭<?= htmlspecialchars(
            (string) $vars["stockBar"]["freeCropString"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>‬</div>

							</a>

						</div>

                    </div>



                    <script type="text/javascript">

                        var resources = {};



                        resources.production = {

                            "l1": <?= $vars["stockBar"]["production"][0] ?>,

                            "l2": <?= $vars["stockBar"]["production"][1] ?>,

                            "l3": <?= $vars["stockBar"]["production"][2] ?>,

                            "l4": <?= $vars["stockBar"]["production"][3] ?>,

                            "l5": <?= $vars["stockBar"]["production"][4] ?>

                        };

                        resources.storage = {

                            "l1": <?= $vars["stockBar"]["storage"][0] ?>,

                            "l2": <?= $vars["stockBar"]["storage"][1] ?>,

                            "l3": <?= $vars["stockBar"]["storage"][2] ?>,

                            "l4": <?= $vars["stockBar"]["storage"][3] ?>

                        };

                        resources.maxStorage = {

                            "l1": <?= $vars["stockBar"]["maxstore"] ?>,

                            "l2": <?= $vars["stockBar"]["maxstore"] ?>,

                            "l3": <?= $vars["stockBar"]["maxstore"] ?>,

                            "l4": <?= $vars["stockBar"]["maxcrop"] ?>

                        };

                        Travian.formatStockBarValue = function (value) {
                            var amount = Number(value);
                            if (!Number.isFinite(amount)) {
                                return String(value);
                            }

                            var absolute = Math.abs(amount);
                            if (absolute < 1000000) {
                                return new Travian.Formatter({forceDecimal: false}).getFormattedNumber(amount);
                            }

                            var units = [
                                [1000000000000000, 'Q'],
                                [1000000000000, 'T'],
                                [1000000000, 'B'],
                                [1000000, 'M']
                            ];
                            var unit = units.find(function (candidate) {
                                return absolute >= candidate[0];
                            });
                            var scaled = amount / unit[0];
                            var decimals = Math.abs(scaled) >= 100 ? 0 : (Math.abs(scaled) >= 10 ? 1 : 2);
                            return scaled.toFixed(decimals).replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1') + unit[1];
                        };

                    </script>

                <?php endif; ?>

				<nav id="mobileMenu">

					<ul>

						<li>

							<a class="dailyQuests" href="#" accesskey="7"

							   onclick="Travian.React.openDailyQuestsDialog(); return false;"
							   title="<?= T("DailyQuest", "Daily Quests") ?>||<?= T("DailyQuest", "Collect daily rewards") ?>">

								<div class="inlineIcon " title="">

								<svg viewBox="0 0 40 130.22" class="dailyQuests ">

									<rect width="40" height="89.11" rx="10.33"></rect>

									<ellipse cx="19.67" cy="115.67" rx="17.22" ry="14.56"></ellipse>

								</svg>

								<span class="value "><?= T("DailyQuest", "Daily Quests") ?></span></div>

							</a>

						</li>

						<li>

							<a class="statistics" href="/statistics" title="<?= T("inGame", "Navigation.Statistics") ?>||">

								<div class="inlineIcon " title="">

								<svg viewBox="0 0 120.56 137.33" class="statistics">

									<path d="M1.67 70.67h32.67V130H1.67zM43.56 35.56h32.67V130H43.56zM86.46 0h32.67v130H86.46zM0 133.56h120.56v3.78H0z"></path>

								</svg>

								<span class="value "><?= T("inGame", "Navigation.Statistics") ?></span></div>

							</a>

						</li>

						<li>

							<a class="referAFriend" href="/referAFriend" title="<?= T("ReferAFriend", "title") ?>">

								<div class="inlineIcon " title="">

								<svg viewBox="0 0 18.08 20" class="referAFriend">

									<path class="human" d="M5.86 9a1.26 1.26 0 01-1.14-1.31V6.36a.72.72 0 01.55-.75.67.67 0 000-.42 4.87 4.87 0 01.2-2.51 1.63 1.63 0 01.28-.52c.29-.38.63-.73.94-1.09A3.84 3.84 0 0111.4.58a4.16 4.16 0 011.86 4.2 5.2 5.2 0 000 1c.63.3.41.88.39 1.38 0 .2-.05.41 0 .63a1.76 1.76 0 01-.09.75c-.15.43-.17.45-.59.6a3.31 3.31 0 01-.71 1.6c-.24.28-.16.65-.24 1s-.13.61-.21.91a1.56 1.56 0 00.83 2 6.14 6.14 0 011.63 1.14 4.54 4.54 0 01.91 1.38c.19.42.06.61-.4.61H.5c-.46 0-.51 0-.5-.52a4.13 4.13 0 012-3.48A6.1 6.1 0 013.57 13a4.77 4.77 0 002.07-1.21 1.2 1.2 0 00.36-1c-.06-.56-.1-1.16-.14-1.79z"></path>

									<path class="plus" d="M8.38 16.43v-2.56a.71.71 0 01.77-.57h2.27v-2.32c0-.53.17-.68.69-.68h2.09c.7 0 .88.15.89.9v2.1h2.17c.62 0 .81.19.82.81v2c0 .71-.17.88-.87.88h-2.12v2.21c0 .72-.19.8-.81.81h-2c-.69 0-.85-.17-.85-.85v-2.17H9.25c-.42.01-.75-.1-.87-.56z"></path>

								</svg>

								<span class="value "><?= T("ReferAFriend", "title") ?></span></div>

							</a>

						</li>

						<li>

							<a class="profile" href="/profile" title="<?= htmlspecialchars(T("inGame", "Profile.Profile") . "||" . T("inGame", "Profile.edit profile description"), ENT_QUOTES, "UTF-8") ?>">

								<div class="inlineIcon " title="">

								<svg viewBox="0 0 15.76 21" class="profile">

								  <path d="M7.88 1.77c2.1 0 3.8 2.09 3.8 4.65s-1.7 4.65-3.8 4.65S4.08 9 4.08 6.42s1.71-4.65 3.8-4.65m0-1.77c-3 0-5.49 2.88-5.49 6.42s2.46 6.42 5.49 6.42 5.49-2.84 5.49-6.42S10.92 0 7.88 0zm7.88 21a11.81 11.81 0 0 0-2.51-7 7.17 7.17 0 0 1-5.37 2.46A7.17 7.17 0 0 1 2.52 14 11.82 11.82 0 0 0 0 21z"></path>

								</svg>

								<span class="value "><?= T("inGame", "Profile.Profile") ?></span></div>

							</a>

						</li>

						<li>

							<a class="options" href="/options" title="<?= htmlspecialchars(T("inGame", "Options.Options") . "||" . T("inGame", "Options.edit account settings"), ENT_QUOTES, "UTF-8") ?>">

								<div class="inlineIcon " title="">

								<svg viewBox="0 0 20 20" class="settings">

								  <path d="M9 20l-.24-3.26-.57-.16A7.21 7.21 0 0 1 6.66 16l-.52-.29-2.47 2.1-1.48-1.48 2.14-2.47-.33-.52a7.21 7.21 0 0 1-.62-1.49l-.16-.57L0 11V9l3.26-.24.16-.57A7.21 7.21 0 0 1 4 6.66l.29-.52-2.1-2.47 1.48-1.48 2.47 2.14.52-.33a7.21 7.21 0 0 1 1.49-.62l.57-.16L9 0h2l.24 3.26.57.16a7.21 7.21 0 0 1 1.53.58l.52.29 2.47-2.14 1.48 1.48-2.14 2.51.29.52a7.21 7.21 0 0 1 .62 1.49l.16.57L20 9v2l-3.26.24-.16.57a7.21 7.21 0 0 1-.58 1.53l-.29.52 2.14 2.47-1.48 1.48-2.47-2.14-.52.29a7.21 7.21 0 0 1-1.49.62l-.57.16L11 20zm1-15a5 5 0 1 0 5 5 5 5 0 0 0-5-5z"></path>

								</svg>

								<span class="value "><?= T("inGame", "Options.Options") ?></span></div>

							</a>

						</li>

<li>
							<a class="help" onclick="Travian.Game.Manual.open(); return false;" title="<?= htmlspecialchars(T("inGame", "helpButtonTitle") . "||" . T("inGame", "helpButtonDescription"), ENT_QUOTES, "UTF-8") ?>">
								<div class="inlineIcon " title="">
								<svg viewBox="0 0 12.24 20" class="help">
								  <path d="M3.73 13.1v-.52c0-2.8 1.47-3.8 2.89-4.76 1-.72 2.14-1.46 2.14-2.9s-1.13-2.55-3-2.55A5.39 5.39 0 0 0 2 4.12L0 2.61A8.15 8.15 0 0 1 6.24 0c3 0 6 1.4 6 4.52 0 2.42-1.4 3.38-2.88 4.4-1.33.91-2.15 1.52-2.15 3.16v1zm-.51 4.24A2.37 2.37 0 0 1 5.55 15a2.31 2.31 0 0 1 2.32 2.33A2.31 2.31 0 0 1 5.55 19.7a2.37 2.37 0 0 1-2.33-2.36z"></path>
								</svg>
								<span class="value "><?= T("inGame", "helpButtonTitle") ?></span></div>
							</a>
						</li>
<?php if (getDiscordUrl() !== '') { ?>
						<li>

							<a class="discord" target="_blank" rel="noopener" href="<?= htmlspecialchars(getDiscordUrl(), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(T("inGame", "Discord.Discord") . "||" . T("inGame", "Discord.Meet other players on Discord"), ENT_QUOTES, "UTF-8") ?>">

								<div class="inlineIcon " title="">

								<svg viewBox="0 0 20 18.71" class="discord">

								  <path d="M0 2.91v10.18A2.92 2.92 0 002.91 16h12.71a.93.93 0 01.65.27l2.17 2.17a.91.91 0 001.56-.65V2.91A2.92 2.92 0 0017.09 0H2.91A2.92 2.92 0 000 2.91zm15.72 9.59H4.28a.78.78 0 01-.78-.78V4.28a.78.78 0 01.78-.78h11.44a.78.78 0 01.78.78v7.44a.78.78 0 01-.78.78z"></path>

								</svg>

								<span class="value "><?= T("inGame", "Discord.Discord") ?></span></div>

							</a>

						</li>
<?php } ?>

						<li>

							<a class="logout" href="<?= htmlspecialchars(\Core\Session::logoutUrl(), ENT_QUOTES, 'UTF-8'); ?>" rel="nofollow" title="<?= htmlspecialchars(T("inGame", "Logout.Logout") . "||" . T("inGame", "Logout.Log out from the game"), ENT_QUOTES, "UTF-8") ?>">

								<div class="inlineIcon " title="">

								<svg viewBox="0 0 20 20" class="logout">

								  <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>

								</svg>

							<span class="value "><?= T("inGame", "Logout.Logout") ?></span></div>

							</a>

						</li>

					</ul>

					<svg viewBox="0 0 217.28 10.2" class="divider">

						<path d="M78.66 7.51L0 5.1l78.66-2.41a18.49 18.49 0 0 0-.49 2.42 14.58 14.58 0 0 0 .49 2.4z"></path>

						<path d="M75.94 6.08l-32.69-1 32.69-1a5.76 5.76 0 0 0-.27 1 4.64 4.64 0 0 0 .27 1zM139 7.51l78.32-.69L139 2.69a14.63 14.63 0 0 1 .74 2.56 9.31 9.31 0 0 1-.74 2.26z"></path>

						<path d="M141.86 6.08l32.69-1-32.69-1a5.76 5.76 0 0 1 .27 1 4.64 4.64 0 0 1-.27 1zM84.82 5.1a2.31 2.31 0 1 1-4.6 0 2.31 2.31 0 1 1 4.6 0zM132.9 5.1a2.32 2.32 0 1 0 4.61 0 2.32 2.32 0 1 0-4.61 0zM104.142 5.095l5.099-5.098 5.098 5.098-5.098 5.098z"></path>

						<path d="M42.23 4.96h128.66v.27H42.23z"></path>

					</svg>

					<ul>

						<li>

							<a class="mainpage" href="<?= htmlspecialchars(getHomepageUrl(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" title=""><?= T(
    "Global",
    "Footer.HomePage"
) ?></a>

						</li>

                    </ul>

				</nav>

			</div>

			<?php require __DIR__ . '/outOfGame.php'; ?>

		<?php } elseif (!empty($vars["showGuestNavBar"])) { ?>

			<?php require __DIR__ . '/guestMobileNav.php'; ?>

		<?php } elseif ($vars["headerBar"]) { ?>

			<nav id="outOfGame">
				<a id="<?= htmlspecialchars(get_button_id(), ENT_QUOTES, 'UTF-8'); ?>"
				   class="layoutButton buttonFramed withIcon round logout grey logoutOnly"
				   href="<?= htmlspecialchars(\Core\Session::logoutUrl(), ENT_QUOTES, 'UTF-8'); ?>"
				   rel="nofollow"
				   title="<?= htmlspecialchars(T('inGame', 'Logout.Logout') . '||' . T('inGame', 'Logout.Log out from the game'), ENT_QUOTES, 'UTF-8'); ?>">
					<svg viewBox="0 0 20 20" class="logout"><g class="outline">
					  <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>
					</g><g class="icon">
					  <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>
					</g></svg>
				</a>
			</nav>


			<?php } ?>

		</div>

        <div id="topBarHeroWrapper">

			<?= $vars["heroBox"] ?>

		</div>

		<div id="center">
			<?php
			// Gpack paints walls/harbour under #background .behindContent on
			// perspectiveBuildings. Village pages clear containerCssClass, so the
			// shell must still emit the node (resources perspective hides it).
			?>
			<div class="behindContent"><?= $vars["behindContent"] ?? ""; ?></div>
			<?php $showHeaderFavourite =
        isset($vars["headerFavoriteTab"]) &&
        is_numeric($vars["headerFavoriteTab"]);
    $headerButtonCount =
        ($showHeaderFavourite ? 1 : 0) +
        (!empty($vars["showCloseButton"]) ? 1 : 0) +
        (!empty($vars["contextualHelpBuildingId"]) ? 1 : 0); ?>



			<div id="sidebarBeforeContent" class="sidebar beforeContent">
                <div class="sidebarBoxWrapper">
                    <?php if (!empty($vars["showTime"])) {
                        if (!isset($vars["dateTime"])) {
                            $vars["dateTime"] = time();
                        }
                        $tzTop = \Core\Session::getInstance()->isValid()
                            ? \Core\Session::getInstance()->getTimezone()
                            : [0, 0, 0];
                        $useLocalTimeOnTop = !empty($tzTop[2]);
                        $timeLabel = $useLocalTimeOnTop
                            ? T("inGame", "localTime", "Local time")
                            : T("inGame", "serverTime");
                        $clockText = $useLocalTimeOnTop
                            ? \Core\Helper\TimezoneHelper::date('H:i:s')
                            : gmdate('H:i:s'); ?>
                    <div id="servertime">
                        <span><?= htmlspecialchars((string) $timeLabel, ENT_QUOTES, "UTF-8") ?>:</span>
                        <span><span format="24h" class="timer" counting="up" value="<?= (int) $vars["dateTime"] ?>"><?= htmlspecialchars($clockText, ENT_QUOTES, "UTF-8") ?></span>&nbsp;(UTC)</span>
                    </div>
                    <?php } ?>
                    <?= $vars["sidebarBeforeContent"] ?>
                </div>
            </div>

            <div id="contentOuterContainer" class="<?= $vars[
                "containerCssClass"
            ] ?>">

				<?php if ($vars["showHeaderBar"]): ?>

				<div class="contentTitle buttonCount<?= $headerButtonCount ?>">

				  <?php if (
          isset($vars["headerFavoriteTab"]) &&
          is_numeric($vars["headerFavoriteTab"])
      ):

          $favoriteTabNumber = (int) $vars["headerFavoriteTab"];

          $favoriteTabName = (string) ($vars["headerFavoriteName"] ?? "hero");

          $favoriteTabTitle = T("inGame", "Select this tab as fav");
          ?>

					<a id="tabFavorButton" class="contentTitleButton buttonFramed green withIcon rectangle"

					   href="#" title="<?= htmlspecialchars(
            $favoriteTabTitle,
            ENT_QUOTES,
            "UTF-8"
        ) ?>"

					   aria-label="<?= htmlspecialchars($favoriteTabTitle, ENT_QUOTES, "UTF-8") ?>"

					   onclick="Travian.api('favourite-tab', {data: {name: '<?= htmlspecialchars(
            $favoriteTabName,
            ENT_QUOTES,
            "UTF-8"
        ) ?>', number: '<?= $favoriteTabNumber ?>'}, success: function(data) {if (data.success) {jQuery('.favor').removeClass('favorActive'); jQuery('.favor.favorKey<?= $favoriteTabNumber ?>').addClass('favorActive');}}}); return false;"></a>

				  <?php
      endif; ?>

				  <?php if ($vars["showCloseButton"]): ?>

					<a id="closeContentButton" class="contentTitleButton buttonFramed green withIcon rectangle"

					   href="<?= htmlspecialchars($closeContentHref, ENT_QUOTES, "UTF-8") ?>"

					   title="<?= T("Global", "General.closeWindow") ?>">

						<svg viewBox="0 0 20 20">

							<g class="outline">

							   <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>

							</g>

							<g class="icon">

							   <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>

							</g>

						</svg></a>

				  <?php endif; ?>

				  <?php if (!empty($vars["contextualHelpBuildingId"])): ?>
					<?= \resources\View\PHPBatchView::render('layout/buildingHelp', ['buildingId' => (int)$vars["contextualHelpBuildingId"]]) ?>
				  <?php endif; ?>

				</div>

				<?php endif; ?>

				<?php if (
        strpos((string) ($vars["containerCssClass"] ?? ""), "contentPage") !==
        false
    ): ?>

				<div class="contentContainer">

					<?php if (
         !$vars["showHeaderBar"]
     ): ?><div class="contentTitle"></div><?php endif; ?>

					<div id="content" class="<?= $vars["contentCssClass"] ?>">

					<?php if ($vars["titleInHeader"]) {
         echo '<h1 class="titleInHeader">' . $vars["titleInHeader"] . "</h1>";
     } ?>

					<?php echo $vars["content"]; ?>

					</div>

					</div>

				<?php else: ?>

					<div class="<?= $vars["contentCssClass"] ?>">

					<?php echo $vars["content"]; ?>

					</div>

				<?php endif; ?>

            </div>

            <div id="sidebarAfterContent" class="sidebar afterContent">
                <div class="sidebarBoxWrapper">
                    <?= $vars["sidebarAfterContent"] ?>
                </div>
            </div>





          </div>









        <div id="footer">
            <div id="pageLinks">
                <?php foreach ($footerRow as $footerLink) { ?>
                <a href="<?= htmlspecialchars($footerLink['href'], ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank"<?= $footerLink['external'] ? ' rel="noopener"' : '' ?>><?= htmlspecialchars($footerLink['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php } ?>
                <?php if ($footerVersion !== null && $footerVersion['href'] !== null) { ?>
                <a class="footerVersion" href="<?= htmlspecialchars($footerVersion['href'], ENT_QUOTES, 'UTF-8') ?>"
                   target="_blank" rel="noopener"><?= htmlspecialchars($footerVersion['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php } elseif ($footerVersion !== null) { ?>
                <span class="footerVersion"><?= htmlspecialchars($footerVersion['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php } ?>
                <div class="clear"></div>
            </div>
        </div>

    <div id="ce"></div>

</div>

<?php if ($useGameMobileOptimizations): ?>
<script type="text/javascript">
    // The Gpack's mobile controls need the rendered sidebars and content
    // containers.  Initializing them from <head> bound touch handling to an
    // empty document, so mobile navigation and its viewport sizing never
    // became active.
    jQuery(function () {
        Travian.Game.Layout.MobileOptimizations.init();
    });
</script>
<?php endif; ?>

<script type="text/javascript">

    <?php $feature_flags = [
        "vacationMode" => true,

        "territory" => false,

        "heroitems" => true,

        "allianceBonus" => true,

        "boostedStart" => false,

        "pushingProtectionAlways" => false,

        "tribesEgyptiansAndHuns" => true,

        "hideFoolsArtifacts" => false,

        "welcomeScreen" => false,

        "multi_language" => true,

        "resourcesInHeroBag" => true,

        "useAdventureSpawnTime" => true,

        "adventuresDecay" => false,

        "contextHelp" => true,

        "sittingOnlyFriends" => true,

        "lockingRegionsAgain" => true,

        "factions" => false,

        "progressiveTasks" => true,

        "rearrangeBuildings" => true,

        "travelOverTheWorldEdge" => true,

        "cities" => false,

        "allianceBanner" => true,
    ]; ?>



    var T4_feature_flags = <?= json_encode($feature_flags) ?>



</script>



</body>

</html>

<!---- This page was generated in <?= round(
    1000 * (microtime(true) - $GLOBALS["start_time"]),
    2
) ?> ms ---->
