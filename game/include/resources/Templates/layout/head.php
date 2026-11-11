<?php
use Core\Config;
use Core\Session;
use Core\Helper\PreferencesHelper;

$gpackUrl = htmlspecialchars(get_gpack_cdn_mainPage_url(), ENT_QUOTES, "UTF-8");
$fixesPath =
    __DIR__ .
    "/../../../../../assets/gpack/travian/mainPage/lang/overrides.css";
$fixesVersion = is_file($fixesPath)
    ? filemtime($fixesPath)
    : Config::getInstance()->dynamic->lastMedalsGiven;
$tasksCssPath =
    __DIR__ .
    "/../../../../../assets/gpack/travian/mainPage/lang/tasks.css";
$tasksCssVersion = is_file($tasksCssPath)
    ? filemtime($tasksCssPath)
    : $fixesVersion;
$heroCssPath =
    __DIR__ .
    "/../../../../../assets/gpack/travian/mainPage/lang/hero.css";
$heroCssVersion = is_file($heroCssPath)
    ? sprintf(
        "%d-%s",
        filemtime($heroCssPath),
        substr(sha1_file($heroCssPath), 0, 12)
    )
    : $fixesVersion;
$headSession = Session::getInstance();
$headDisplay = $headSession->isValid()
    ? (array) $headSession->getDisplay()
    : [];
$usesHeroCss =
    strpos((string) ($vars["contentCssClass"] ?? ""), "heroV2") !== false ||
    strpos((string) ($vars["containerCssClass"] ?? ""), "heroPageShell") !==
        false;
$contextualHelpRestartableGroup = (string) ($vars['contextualHelpRestartableGroup'] ?? '');
$documentLanguage = (string) ($vars['documentLanguage'] ?? get_locale());
$documentDirection = strtoupper((string) ($vars['layoutDirection'] ?? getDirection()));
$documentDirection = $documentDirection === 'RTL' ? 'rtl' : 'ltr';
$viewportContent = (string) ($vars['viewportContent'] ?? 'width=device-width');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?= htmlspecialchars($documentLanguage, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $documentDirection ?>">
<head>
    <?php
    // Public reference-shaped routes may supply their own canonical document
    // title. Every other view retains the generated world title unchanged.
    $documentTitle = (string) ($vars['documentTitle'] ?? get_language_properties('title'));
    ?>
    <title><?= htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8') ?></title>
<?php if (is_string($vars['metaDescription'] ?? null) && $vars['metaDescription'] !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($vars['metaDescription'], ENT_QUOTES, 'UTF-8') ?>"/>
<?php endif; ?>
    <meta http-equiv="cache-control" content="max-age=0"/>
    <meta http-equiv="pragma" content="no-cache"/>
    <meta http-equiv="expires" content="0"/>
    <meta http-equiv="imagetoolbar" content="no"/>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
    <meta name="content-language" content="<?= htmlspecialchars($documentLanguage, ENT_QUOTES, 'UTF-8') ?>"/>
    <meta name="viewport" content="<?= htmlspecialchars($viewportContent, ENT_QUOTES, 'UTF-8') ?>"/>
    <!-- Player-facing clean routes are nested. Keep the shared runtime,
         sprites and legacy controller targets rooted at the world origin. -->
    <base href="/"/>

    <script type="text/javascript">
        // Uses hash-only and empty anchors for controls that are
        // cancelled by their client handler. The local shell needs a root
        // base URL for clean-route assets; without this guard a handler error
        // would make the same fallback navigate to the login front controller.
        // Cancelling the fallback keeps the reference's no-navigation result
        // while allowing the control's normal handler to run.
        document.addEventListener('click', function (event) {
            var target = event.target;
            var anchor = target && typeof target.closest === 'function'
                ? target.closest('a[href="#"], a[href=""]')
                : null;
            if (anchor) {
                event.preventDefault();
            }
        }, true);
    </script>


    <link href="<?= htmlspecialchars(
        get_gpack_link_and_hash("imports.css", false, $documentDirection),
        ENT_QUOTES,
        "UTF-8"
    ) ?>" rel="stylesheet" type="text/css"/>
    <?php
    $contentCssClass = (string) ($vars["contentCssClass"] ?? "");
    $loadsTasksCss =
        str_contains($contentCssClass, "tasksPage") ||
        preg_match('/(?:^|\\s)tasks(?:\\s|$)/', $contentCssClass) === 1;
    ?>
    <?php if ($loadsTasksCss): ?>
        <link href="<?= htmlspecialchars(
            $gpackUrl . "lang/tasks.css?v=" . $tasksCssVersion,
            ENT_QUOTES,
            "UTF-8"
        ) ?>" rel="stylesheet" type="text/css"/>
    <?php endif; ?>
    <?php // Always available: system dark mode and the night-hours opt-in both
    // drive body[data-theme=night] selectors from this stylesheet. ?>
    <link href="<?= htmlspecialchars(
        get_gpack_link_and_hash("night_mood.css", false, $documentDirection),
        ENT_QUOTES,
        "UTF-8"
    ) ?>" rel="stylesheet" type="text/css"/>
    <meta name="color-scheme" content="light dark"/>

    <link href="<?= htmlspecialchars(
        $gpackUrl . "lang/overrides.css?v=" . $fixesVersion,
        ENT_QUOTES,
        "UTF-8"
    ) ?>" rel="stylesheet" type="text/css"/>
    <?php if ($usesHeroCss): ?>
        <link href="<?= htmlspecialchars(
            $gpackUrl . "lang/hero.css?v=" . $heroCssVersion,
            ENT_QUOTES,
            "UTF-8"
        ) ?>" rel="stylesheet" type="text/css"/>
    <?php endif; ?>




    <script type="text/javascript">
        window.ajaxToken = '<?= isset($vars["ajaxToken"])
            ? $vars["ajaxToken"]
            : null ?>';
        window._player_uuid = '<?= isset($vars["_player_uuid"])
            ? $vars["_player_uuid"]
            : null ?>';
    </script>
	<script type="text/javascript" src="js/default/jquery-4.0.0.js" data-cmp-info="2" data-cmp-ab="1"></script>
	<script type="text/javascript" src="js/default/jquery.md5.js" data-cmp-info="2" data-cmp-ab="1"></script>
	<script type="text/javascript" src="js/default/d3/d3.js" data-cmp-info="2" data-cmp-ab="1"></script>
	<script type="text/javascript" src="js/default/d3/d3pie.js" data-cmp-info="2" data-cmp-ab="1"></script>
	<script type="text/javascript" src="js/default/ChartJs/Chart.js" data-cmp-info="2" data-cmp-ab="1"></script>
	<script type="text/javascript" src="js/default/simplebar.js" data-cmp-info="2" data-cmp-ab="1"></script>
	<script type="text/javascript" src="/js/default/popper.js"></script>
	<script type="text/javascript" src="/js/default/tippy.js"></script>
	<script type="text/javascript" src="js/default/PixiJS/pixi.js" data-cmp-info="2" data-cmp-ab="1"></script>
	<script type="text/javascript" src="js/Game/General/General.js" data-cmp-info="2" data-cmp-ab="1"></script>
    <script type="text/javascript">
        <?php $data = [
            "Map" => [
                "Size" => [
                    "width" => 2 * MAP_SIZE + 1,
                    "height" => 2 * MAP_SIZE + 1,
                    "left" => -MAP_SIZE,
                    "right" => MAP_SIZE,
                    "bottom" => -MAP_SIZE,
                    "top" => MAP_SIZE,
                ],
            ],
            "Season" => detect_season(),
        ]; ?>
        window.TravianDefaults = Object.assign(<?= json_encode(
            $data
        ) ?>, false || {});
    </script>
    <script type="text/javascript" src="<?= get_crypt_js_link() ?>"></script>
    <script type="text/javascript" src="/farm-list.js?v=<?= filemtime(PUBLIC_INTERNAL_PATH . 'farm-list.js') ?>"></script>
    <script type="text/javascript" src="<?= htmlspecialchars(get_client_strings_link(), ENT_QUOTES, 'UTF-8') ?>"></script>
    <link rel="icon" href="favicon.ico" type="image/x-icon"/>
    <script type="text/javascript">
        <?php if (!(isset($vars["autoReload"]) && $vars["autoReload"] == 1)): ?>
        window.reload_enabled = false;
        <?php endif; ?>
        <?php $buttonTemplate = '<button ></button>';
// if (!isset($vars['autoReload']) OR $vars['autoReload'] == 0) {
//     $eventJamHtml = '<a href="javascript:void" onclick="document.location.reload();"><img src="img/refresh.png"></a>';
// }
//
?>
        Travian.Game.version = '4.6';
        Travian.Game.worldId = '<?= getWorldId() ?>';
        Travian.Game.speed = <?= getGameSpeed() ?>;
        Travian.Game.country = '<?= get_language_properties("country") ?>';
        <?php
        $travianTemplates = [
            'ButtonV1' => "<button >\n\t</button>\n",
            'ButtonV2' => "<button >\n\t<div>\n\t\t\t</div>\n</button>\n",
            'DialogV1' => <<<'HTML'
<div class="dialogOverlay">
    <div class="dialogWrapper dialogV1" data-context="">
        <div class="dialog">
            <div class="dialogContainer">
                <div class="dialogContents">
                    <form action="" method="get" accept-charset="UTF-8">
                        <div class="dialogDragBar"></div>
                        <div class="dialogCancelButton smallIconButton cancel"></div>
                        <div class="title"></div>
                        <div class="content" id="dialogContent"></div>
                        <div class="buttons"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
HTML,
            'DialogV2' => <<<'HTML'
<div class="dialogOverlay">
    <div class="dialogWrapper dialogV2" data-context="">
        <div class="dialog">
            <div class="dialogContainer">
                <div class="dialogContents">
                    <form action="" method="get" accept-charset="UTF-8">
                        <div class="dialogDragBar"></div>
                        <div class="dialogCancelButton buttonFramed green withIcon rectangle cancel" title="Close">
                            <svg viewBox="0 0 20 20"><g class="outline">
  <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>
</g><g class="icon">
  <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>
</g></svg>
                        </div>
                        <div class="title"></div>
                        <div class="content" id="dialogContent"></div>
                        <div class="buttons"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
HTML,
        ];
        ?>
        Travian.Templates = <?= json_encode($travianTemplates) ?>;
		Travian.Templates.ButtonTemplate = '<?= $buttonTemplate ?>';
        Travian.Form.UnloadHelper.message = '<?= T(
            "Global",
            "UnloadHelper.message"
        ) ?>';
        <?php
        $preferences = PreferencesHelper::getPreferences();
        if (isset($preferences["allianceBonusesOverview"])) {
            $preferences["allianceBonusesOverview"] = json_encode(
                $preferences["allianceBonusesOverview"]
            );
        }
        ?>
		Travian.apiToken = window === parent && '<?= isset(
      $vars["ajaxToken"]
  )
      ? $vars["ajaxToken"]
      : null ?>'
        Travian.Game.Preferences.initialize(<?= json_encode($preferences) ?>);
		Travian.Game.setLanguage("<?= get_locale() ?>");
		Travian.Game.contextHelpData = <?=json_encode([
            'groupName' => '',
            'steps' => [],
            'restartableGroup' => $contextualHelpRestartableGroup !== ''
                ? $contextualHelpRestartableGroup
                : null,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);?>;
		<?php if ((int) ($headDisplay[7] ?? 0) !== 1): ?>
        Travian.Game.ContextualHelp.initialize();
		<?php endif; ?>
        Travian.Game.PaymentWizardEventListener.defaultOptions = {"shopUIVersion":5,"version":2,"cssClass":"paymentShopV5"};
    </script>
</head>
