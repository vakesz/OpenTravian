<?php

declare(strict_types=1);

function failHomepageRouteContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/** @return array<string, mixed> */
function loadPublicHomepageCatalog(string $root): array
{
    $catalog = require $root . '/game/include/resources/Translation/HomepagePublicCatalog.php';
    if (!is_array($catalog)) {
        failHomepageRouteContract('The public Homepage locale snapshot did not return a catalogue.');
    }

    return $catalog;
}

/** @return array<string, mixed> */
function loadPublicHomepageArticles(string $root): array
{
    $articles = require $root . '/game/include/resources/HomepagePublicArticleCatalog.php';
    if (!is_array($articles)) {
        failHomepageRouteContract('The public Homepage article snapshot did not return a catalogue.');
    }

    return $articles;
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';
$sources = [
    'routes' => $root . '/game/include/mainInclude.php',
    'general' => $root . '/game/include/functions.general.php',
    'controller' => $root . '/game/include/Controller/HomepageCtrl.php',
    'articles' => $root . '/game/include/resources/HomepagePublicArticleCatalog.php',
    'template' => $root . '/game/include/resources/Templates/system/homepage.php',
    'head' => $root . '/game/include/resources/Templates/layout/head.php',
    'layout' => $root . '/game/include/resources/Templates/layout/Layout.php',
    'styles' => $root . '/assets/gpack/travian/mainPage/lang/overrides.css',
    'provenance' => $root . '/assets/gpack/travian/mainPage/ASSET_PROVENANCE.md',
];
foreach ($sources as $name => $path) {
    $sources[$name] = file_get_contents($path);
    if ($sources[$name] === false) {
        failHomepageRouteContract("The local Homepage {$name} source must be readable.");
    }
}

$routes = $sources['routes'];
$general = $sources['general'];
$controller = $sources['controller'];
$articlesSource = $sources['articles'];
$template = $sources['template'];
$head = $sources['head'];
$layout = $sources['layout'];
$styles = $sources['styles'];
$provenance = $sources['provenance'];

foreach ([
    "return '/homepage';",
    "'/homepage/news' => ['homepagePage' => 'news']",
    "'/homepage/news/2026/10/22/winter-time-the-effects-on-your-game/' => [",
    "'/homepage/news/2025/01/06/tournament/' => [",
    "redirect(\$requestPath . '/' . (\$homepageQuery !== '' ? '?' . \$homepageQuery : ''), 301);",
    "'homepageArticle' => 'winter-time-the-effects-on-your-game'",
    "'homepageArticle' => 'tournament'",
    "'/homepage/calendar' => ['homepagePage' => 'calendar']",
    "'/homepage/tournament' => ['homepagePage' => 'tournament']",
    "foreach (\$homepageRoutes[\$requestPath] as \$parameter => \$value)",
    "\$_GET[\$parameter] = \$value;",
    "\$_REQUEST[\$parameter] = \$value;",
    "'homepage' => 'HomepageCtrl'",
] as $required) {
    if (!str_contains($general . $routes, $required)) {
        failHomepageRouteContract("The self-hosted Homepage route is missing {$required}.");
    }
}
foreach ([
    '/homepage/news/winter-time-the-effects-on-your-game',
    "'/homepage/news/2026/10/22/winter-time-the-effects-on-your-game' => [",
    "'/homepage/news/2025/01/06/tournament' => [",
    "\$_GET[\$parameter] ??= \$value;",
    "\$_REQUEST[\$parameter] ??= \$value;",
] as $obsoleteRoute) {
    if (str_contains($routes, $obsoleteRoute)) {
        failHomepageRouteContract("Homepage must not retain obsolete public article route {$obsoleteRoute}.");
    }
}
// The stored SVG is formatted (attributes on separate lines); compare with whitespace collapsed.
$wordmarkSvg = file_get_contents($root . '/assets/gpack/travian/mainPage/img_ltr/homepage/public-wordmark.svg');
if (is_string($wordmarkSvg)) {
    $wordmarkSvg = preg_replace('/\s+/', ' ', $wordmarkSvg);
}
if (!is_string($wordmarkSvg)
    || !str_contains($wordmarkSvg, 'viewBox="0 0 172.7 60"')
    || !str_contains($wordmarkSvg, '>OPENTRAVIAN</text>')
    || str_contains($wordmarkSvg, 'data:image/')) {
    failHomepageRouteContract('The local public header must draw the OpenTravian wordmark, not an imported logo.');
}

foreach ([
    "PHPBatchView::render('system/homepage'",
    "['showGuestNavBar'] = false",
    "['containerCssClass'] = 'homepageFullBleed'",
    "['useGameMobileOptimizations'] = false",
    "['viewportContent'] = 'width=device-width, initial-scale=1'",
    "'publicDirection' => \$publicDirection",
    "\$this->view->renderLoginBox = false",
    "\$_GET['homepageLocale']",
    "require LOCALE_PATH . 'HomepagePublicCatalog.php'",
    "count(\$options) !== 48",
    'DEFAULT_PUBLIC_OPTION_ID',
    "'en-US' => 'en-EN'",
    "'fa-IR' => 'en-EN'",
    "'layoutDirection'",
    "'footerLabels'",
    "'homepageUrl'",
    "'footerLinks'",
    "\$homepageUrl = '/homepage?homepageLocale=' . rawurlencode(\$publicLanguage['id']);",
    'private function localGameworld(): ?array',
    'RegistrationWorld::summary()',
    "private const FEATURED_ARTICLE_ID = 'winter-time-the-effects-on-your-game'",
    "private const TOURNAMENT_ARTICLE_ID = 'tournament'",
    'private function articleCatalogue(): array',
    'HomepagePublicArticleCatalog.php',
    "\$articles[self::TOURNAMENT_ARTICLE_ID]['path']",
    '301,',
    "'/homepage?homepageLocale=' . rawurlencode(\$option['id'])",
    "'flag' => \$option['flag']",
    "\$option['langEnglish']",
    "\$option['countryEnglish']",
    'usort($orderedOptions',
    "'country' => \$option['countryNative']",
    "\$publicDocumentLanguage = \$publicLanguage['id'] === self::DEFAULT_PUBLIC_OPTION_ID",
] as $required) {
    if (!str_contains($controller, $required)) {
        failHomepageRouteContract("HomepageCtrl is missing {$required}.");
    }
}

if (str_contains($controller, "?lang=") || str_contains($template, "?lang=")) {
    failHomepageRouteContract('Homepage language controls must not mutate the game locale through lang=.');
}
if (str_contains($controller, 'setLanguage(')) {
    failHomepageRouteContract('HomepageCtrl must not mutate the game language for public language selection.');
}
foreach (['PUBLIC_LANGUAGE_OPTIONS', "require LOCALE_PATH . \$translationLocale", "array_key_exists('Definition', \$GLOBALS)"] as $obsolete) {
    if (str_contains($controller, $obsolete)) {
        failHomepageRouteContract("HomepageCtrl must not retain obsolete locale path {$obsolete}.");
    }
}

foreach ([
    'data-homepage-stage',
    'data-homepage-toggle="homepageNavDialog"',
    'data-homepage-toggle="homepageLanguageDialog"',
    'data-homepage-language-search',
    'homepageAccountDialog',
    '$registrationDialog',
    '$publicLanguageOptionId = $vars[\'publicLanguageOptionId\']',
    '$publicRoute = static function',
    '$homeUrl = $publicRoute(\'/homepage\')',
    '$newsUrl = $publicRoute(\'/homepage/news\')',
    '$publicRoute($news[\'path\'])',
    'class="homepageNewsFrame"',
    'class="newsPreview"',
    '$calendarOverviewUrl = $publicRoute(\'/homepage/calendar\')',
    '$tournamentUrl = $publicRoute(\'/homepage/tournament\')',
    '$renderArticleParts = static function',
    '$renderArticleBody = static function',
    'data-homepage-article=',
    'homepageArticleBanner',
    'homepageArticleContent',
    'rel="noopener noreferrer"',
    'class="homepageInfoAction" href="<?= $escape($calendarOverviewUrl); ?>"',
    'data-homepage-toggle="homepageConsentDialog"',
    'data-homepage-consent-accept',
    'data-homepage-language-route',
    'data-homepage-language-recommended-option',
    'homepageLanguageOptionFlag',
    'homepageLanguageRadio',
    'homepageLanguageOptionInput',
    'homepageLanguageSearchLabel',
    "'img_ltr/homepage/public-wordmark.svg'",
    'type="radio" name="recommended"',
    'type="radio" name="others"',
    'data-homepage-language-href',
    'window.location.assign(href)',
    'class="addition"',
    'href="/dorf1.php"',
    'preserveAspectRatio="xMidYMid meet"',
    'homepageCtaWrapper',
    'homepageHeadlineContainer',
    'homepageForeignObjectContainer',
    'homepageTribeStageFilterRoman',
    'data-homepage-short-date',
    "\$gameworldTribe['slug'] . '-small.png'",
    'Intl.DateTimeFormat',
    "timeZone: 'Europe/London'",
    'dir="<?= $escape($publicDirection); ?>"',
    'activeDialogTrigger',
    "event.key === 'Tab'",
    'aria-modal="true"',
    'viewBox="0 0 18 18"',
    'M9 9c-1.24 0-2.3-.44-3.18-1.32',
] as $required) {
    if (!str_contains($template, $required)) {
        failHomepageRouteContract("The self-hosted Homepage template is missing {$required}.");
    }
}

foreach ([
    'GO TO LOBBY',
    'href="/homepage#',
    'homepageLanguage"><img',
    'data-homepage-language-name',
    'brandTravian',
    'brandLegends',
    'Disciplined legions, advanced engineering',
    'Winter Time – The Effects on Your Game',
] as $forbidden) {
    if (str_contains($template, $forbidden)) {
        failHomepageRouteContract("The local Homepage template retains forbidden static copy or inert control {$forbidden}.");
    }
}

if (preg_match('/(?:src|href)=["\']https?:\/\//i', $template) === 1) {
    failHomepageRouteContract('The local Homepage template must not make an external runtime request.');
}
foreach (['href="/homepage"', 'href="/homepage/news"', 'href="/homepage/calendar"', 'href="/homepage/tournament"'] as $barePublicHref) {
    if (str_contains($template, $barePublicHref)) {
        failHomepageRouteContract('Homepage navigation must retain the selected transient public locale.');
    }
}
foreach (['Knowledge Base', 'Imprint', 'Terms', 'copyright'] as $needle) {
    if (str_contains($template, $needle)) {
        failHomepageRouteContract("The local Homepage template must not contain {$needle}.");
    }
}
if (str_contains($template, 'statusCard') || str_contains($controller, 'publicStatus')) {
    failHomepageRouteContract('Homepage must not retain the obsolete public status-card path.');
}

if (!str_contains($head, "\$documentTitle = (string) (\$vars['documentTitle']")
    || !str_contains($head, "\$documentLanguage = (string) (\$vars['documentLanguage']")
    || !str_contains($head, "\$viewportContent = (string) (\$vars['viewportContent']")
    || !str_contains($head, "\$vars['layoutDirection']")
    || !str_contains($head, 'dir="<?= $documentDirection ?>"')
    || !str_contains($head, 'get_gpack_link_and_hash("imports.css", false, $documentDirection)')) {
    failHomepageRouteContract('The shared head must accept scoped Homepage title, language, direction, and Gpack CSS overrides.');
}
foreach ([
    "\$layoutDirection = strtoupper((string) (\$vars['layoutDirection']",
    "\$footerLabels = is_array(\$vars['footerLabels']",
    "\$footerLinks = is_array(\$vars['footerLinks']",
    "\$homepageLink = \$vars['homepageUrl']",
    "\$homepageUrl",
    '\\Game\\FooterLinks::build($footerLabels, $footerLinks',
    "\$vars['publicHomepageLocale']",
    "htmlspecialchars(\$footerLink['label']",
] as $required) {
    if (!str_contains($layout, $required)) {
        failHomepageRouteContract("The shared Layout must use scoped Homepage direction/footer copy: {$required}.");
    }
}
if (!str_contains($layout, '<a id="logo" href="<?= htmlspecialchars(')
    || !str_contains($layout, '$homepageUrl,')) {
    failHomepageRouteContract(
        'The public Homepage wordmark must preserve its transient locale through the shared Layout.'
    );
}
if (!str_contains($general, '?string $directionOverride = null')
    || !str_contains($general, '$directionOverride ?? get_language_properties(\'direction\')')) {
    failHomepageRouteContract('Gpack stylesheet resolution must support a scoped direction override.');
}

if (preg_match('/\.publicHomepage\s*\{[^}]*direction\s*:\s*ltr/is', $styles) === 1) {
    failHomepageRouteContract('The public Homepage base selector must not force LTR.');
}
foreach ([
    '.publicHomepage[dir="rtl"] .homepageLobbyCta',
    '--homepage-scale-x: -1;',
    'transform: translateX(-50%) scaleX(var(--homepage-scale-x));',
    'scaleX(calc(var(--homepage-scale-x) * .3))',
    'direction: ltr; justify-content: flex-start;',
    'container: homepageTribeStage / size',
    'max-width: 2688px',
    'grid-template-columns: minmax(420px, 440px) minmax(420px, 460px) minmax(420px, 440px)',
    '.publicHomepage .homepageDialog',
    '.publicHomepage #homepageLanguageDialog .homepageDialogPanel',
    '.publicHomepage #homepageLanguageDialog .homepageLanguageOption[hidden]',
    '.publicHomepage #homepageLanguageDialog .homepageLanguageOptions.others',
    'inset-block-start: auto;',
    'column-gap: max(12px, 4vw) !important;',
    '.publicHomepage .homepageGameworldPreview .tribes { display: none; }',
    '.publicHomepage .homepageReferencePage',
    '.publicHomepage .homepageArticlePage',
    'news-background-1920x1080.jpg',
    '.publicHomepage .homepageArticleFrame',
    '.publicHomepage .homepageArticleBanner',
    '.publicHomepage .homepageArticleContent',
    '.publicHomepage .homepageArticleColumns',
    '.publicHomepage .homepageArticleTimeline',
    '@media screen and (max-width: 900px)',
] as $required) {
    if (!str_contains($styles, $required)) {
        failHomepageRouteContract("Homepage styles are missing {$required}.");
    }
}
foreach (['homepageMobileStageCopy', 'top: 53%;'] as $obsolete) {
    if (str_contains($styles, $obsolete)) {
        failHomepageRouteContract("Homepage styles must not retain obsolete responsive path {$obsolete}.");
    }
}

foreach ([
    'img_common/homepage/stage-layer-1.webp',
    'img_common/homepage/roman-1.webp',
    'img_common/homepage/teuton-1.webp',
    'img_common/homepage/gaul-1.webp',
    'img_common/homepage/winter-excerpt.jpeg',
    'img_common/homepage/winter-banner-1024x379.jpg',
    'img_common/homepage/ttsquare-160x160.jpg',
    'img_common/homepage/tournament-banner-1024x503.jpg',
    'img_common/homepage/news-background-1920x1080.jpg',
    'img_ltr/homepage/public-wordmark.svg',
    'img_common/homepage/egyptian-small.png',
    'img_common/homepage/hun-small.png',
    'img_common/homepage/cta-corner-top-left.png',
    'fonts/homepage/noto-sans-regular.ttf',
    'fonts/homepage/noto-sans-bold.ttf',
    'fonts/homepage/red-rose-bold.ttf',
    'img_common/homepage/stage-layer-1.webp',
    'img_common/homepage/winter-banner-1024x379.jpg',
    'img_common/homepage/ttsquare-160x160.jpg',
    'img_common/homepage/tournament-banner-1024x503.jpg',
    'img_common/homepage/news-background-1920x1080.jpg',
    'img_common/homepage/egyptian-small.png',
    'img_common/homepage/hun-small.png',
] as $asset) {
    if (!is_file($root . '/assets/gpack/travian/mainPage/' . $asset)) {
        failHomepageRouteContract("The self-hosted Homepage asset {$asset} is missing.");
    }
}

foreach ([
    'image/tribeStage/layer1.webp',
    'winter-excerpt-160x160.jpeg',
    'winter-banner-1024x379.jpg',
    'ttsquare-160x160.jpg',
    'Screenshot-2025-05-16-at-15.34.24-1024x503.jpg',
    'e31926cb7977b9f2a04a.jpg',
    'api/news/2026/10/22/winter-time-the-effects-on-your-game/?lang=en',
    'api/news/2025/01/06/tournament/?lang=en',
    'Original work drawn for this project',
    '9ab05beb031b68562995.ttf',
    '27d083f7f844fb896ae7.ttf',
    '0310e7badf741637965f.ttf',
    '49fe09f41ea7a3e66253.png',
    'localisation/{language}.json',
    '48 complete picker',
    '37 catalogues',
    '/international/ir',
    'fa-IR → en-US',
] as $required) {
    if (!str_contains($provenance, $required)) {
        failHomepageRouteContract("Homepage provenance must record {$required}.");
    }
}

$publicCatalog = loadPublicHomepageCatalog($root);
$publicOptions = $publicCatalog['options'] ?? null;
$publicTranslations = $publicCatalog['catalogues'] ?? null;
$publicKeys = $publicCatalog['source']['keys'] ?? null;
if (!is_array($publicOptions) || !is_array($publicTranslations) || !is_array($publicKeys)
    || count($publicOptions) !== 48 || count($publicTranslations) !== 37 || count($publicKeys) !== 89) {
    failHomepageRouteContract('The self-hosted public Homepage snapshot must contain 48 options, 37 catalogues, and 89 source keys.');
}
$requiredPublicKeys = [
    'homeTitle', 'Romans', 'Teutons', 'Gauls', 'To lobby', 'Play Now',
    'Next upcoming server', 'Show gameworld calendar', 'Show all news',
    'Login', 'Register', 'News & Updates', 'Gameworld Calendar', 'Home',
    'Language selection', 'Search for a language or country',
    'No language matches your search. Are you sure you typed it correctly?',
    'Cancel', 'Close window', 'Article image', 'To news overview',
    'Account settings', 'Logout', 'Tournament', 'Data consent', 'Accept',
    'Support',
];
foreach ($requiredPublicKeys as $key) {
    if (!in_array($key, $publicKeys, true)) {
        failHomepageRouteContract("The public Homepage snapshot is missing source key {$key}.");
    }
}
$optionIds = [];
$optionRoutes = [];
foreach ($publicOptions as $option) {
    if (!is_array($option)
        || !is_string($option['id'] ?? null)
        || !is_string($option['region'] ?? null)
        || !is_string($option['route'] ?? null)
        || !is_string($option['flag'] ?? null)
        || !is_string($option['language'] ?? null)
        || !is_string($option['locale'] ?? null)
        || !is_string($option['lang'] ?? null)
        || !is_string($option['langEnglish'] ?? null)
        || !is_string($option['langNative'] ?? null)
        || !is_string($option['countryEnglish'] ?? null)
        || !is_string($option['countryNative'] ?? null)
        || !is_string($option['hrefLang'] ?? null)
        || !in_array($option['direction'] ?? null, ['ltr', 'rtl'], true)
        || isset($optionIds[$option['id']])
        || isset($optionRoutes[$option['route']])
        || !isset($publicTranslations[$option['language']])
        || preg_match('/\\A[a-z0-9-]+\\z/', $option['flag']) !== 1
        || !is_file($root . '/assets/gpack/travian/mainPage/flags/' . $option['flag'] . '.svg')) {
        failHomepageRouteContract('Each public Homepage selector option must preserve its exact, unique source metadata.');
    }
    $optionIds[$option['id']] = $option;
    $optionRoutes[$option['route']] = true;
}
foreach (['en-EN' => 'international', 'ar-AE' => 'ae', 'el-GR' => 'gr', 'he-IL' => 'il', 'zh-CN' => 'china'] as $id => $route) {
    if (($optionIds[$id]['route'] ?? null) !== $route) {
        failHomepageRouteContract("The public Homepage option {$id} must retain source route {$route}.");
    }
}

$expected = [
    'en-US' => [
        'homeTitle' => 'OpenTravian – International | The Online Multiplayer Strategy Game',
        'Romans' => 'Romans', 'Play Now' => 'Play now', 'Support' => 'Support',
    ],
    'el-GR' => [
        'Romans' => 'Ρωμαίοι', 'Play Now' => 'Παίξτε τώρα',
        'Home' => 'Αρχική σελίδα', 'Support' => 'Υποστήριξη',
    ],
    'ar-AE' => [
        'Romans' => 'الرومان', 'Play Now' => 'العب الآن',
        'Home' => 'الرئيسية', 'Support' => 'الدعم',
    ],
    'he-IL' => [
        'Romans' => 'רומאים', 'Play Now' => 'שחקו עכשיו',
        'Home' => 'בית', 'Support' => 'תמיכה',
    ],
];
foreach ($expected as $locale => $values) {
    $catalog = $publicTranslations[$locale] ?? null;
    if (!is_array($catalog)) {
        failHomepageRouteContract("{$locale} must have a sourced public Homepage catalogue.");
    }
    foreach ($values as $key => $value) {
        if (($catalog[$key] ?? null) !== $value) {
            failHomepageRouteContract("{$locale} Homepage {$key} is not the exact sourced public value.");
        }
    }
}

$publicArticles = loadPublicHomepageArticles($root);
foreach ([
    'winter-time-the-effects-on-your-game' => [
        'id' => '2026/10/22/winter-time-the-effects-on-your-game/',
        'path' => '/homepage/news/2026/10/22/winter-time-the-effects-on-your-game/',
        'title' => 'Winter Time – The Effects on Your Game',
        'excerptAsset' => 'winter-excerpt.jpeg',
        'bannerAsset' => 'winter-banner-1024x379.jpg',
    ],
    'tournament' => [
        'id' => '2025/01/06/tournament/',
        'path' => '/homepage/news/2025/01/06/tournament/',
        'title' => 'Tournament',
        'excerptAsset' => 'ttsquare-160x160.jpg',
        'bannerAsset' => 'tournament-banner-1024x503.jpg',
    ],
] as $articleId => $expectedArticle) {
    $article = $publicArticles[$articleId] ?? null;
    if (!is_array($article)) {
        failHomepageRouteContract("The public Homepage article {$articleId} is missing.");
    }
    foreach ($expectedArticle as $key => $value) {
        if (($article[$key] ?? null) !== $value) {
            failHomepageRouteContract("The public Homepage article {$articleId} must retain sourced {$key}.");
        }
    }
    if (!is_array($article['body'] ?? null) || $article['body'] === []) {
        failHomepageRouteContract("The public Homepage article {$articleId} must retain its typed source body.");
    }
}
if (str_contains($articlesSource, 'fullHTMLText')
    || preg_match('/<\/?(?:h[1-6]|p|section|div|ul|li|table|a)\b/i', $articlesSource) === 1) {
    failHomepageRouteContract('Homepage articles must use typed local content rather than raw source HTML.');
}
foreach ([
    'How does the time change affect my game?',
    'Winter Time – The Effects on Your Game',
    'Where legends are made',
    'Tournament Phases',
    'Every Tournament Hall of Fames',
] as $sourceString) {
    if (!str_contains($articlesSource, $sourceString)) {
        failHomepageRouteContract("Homepage article snapshot is missing sourced copy {$sourceString}.");
    }
}

$reflection = new ReflectionClass(\Controller\HomepageCtrl::class);
$homepageController = $reflection->newInstanceWithoutConstructor();
$catalogMethod = $reflection->getMethod('publicCatalogue');
$runtimeCatalog = $catalogMethod->invoke($homepageController);
if (($runtimeCatalog['options'][0]['id'] ?? null) !== 'en-EN'
    || count($runtimeCatalog['options'] ?? []) !== 48) {
    failHomepageRouteContract('HomepageCtrl must consume the self-hosted 48-option public catalogue.');
}
$articleCatalogMethod = $reflection->getMethod('articleCatalogue');
$runtimeArticles = $articleCatalogMethod->invoke($homepageController);
if (($runtimeArticles['tournament']['path'] ?? null) !== '/homepage/news/2025/01/06/tournament/'
    || ($runtimeArticles['winter-time-the-effects-on-your-game']['bannerAsset'] ?? null) !== 'winter-banner-1024x379.jpg') {
    failHomepageRouteContract('HomepageCtrl must consume the self-hosted typed article snapshot.');
}
$languageOptionsMethod = $reflection->getMethod('languageOptions');
$sortedOptions = $languageOptionsMethod->invoke($homepageController, $publicOptions, 'ar-AE');
$selectedOptionIndex = array_search('ar-AE', array_column($sortedOptions, 'id'), true);
if (count($sortedOptions) !== 48
    || count(array_filter($sortedOptions, static fn(array $option): bool => $option['selected'])) !== 1
    || !is_int($selectedOptionIndex)
    || ($sortedOptions[$selectedOptionIndex]['selected'] ?? false) !== true) {
    failHomepageRouteContract('HomepageCtrl must retain all source selector options and exactly one selected option.');
}
$previousEnglish = '';
foreach ($sortedOptions as $option) {
    if (strcasecmp($previousEnglish, $option['english']) > 0) {
        failHomepageRouteContract('HomepageCtrl must sort the public selector by source langEnglish.');
    }
    $previousEnglish = $option['english'];
}
$rtlCss = get_gpack_link_and_hash('imports.css', false, 'RTL');
$ltrCss = get_gpack_link_and_hash('imports.css', false, 'LTR');
if (!str_contains($rtlCss, '/css_rtl/imports.css')
    || !str_contains($ltrCss, '/css_ltr/imports.css')) {
    failHomepageRouteContract('Scoped Homepage direction must resolve the matching local Gpack stylesheet tree.');
}

// The hero stage covers its box like the reference's (as wide as the box, or as tall as it when that is wider): a
// window wider than 16:9 crops the bottom instead of leaving dark bands at the sides.
if (preg_match('/\.publicHomepage \.homepageStageSvg \{[^}]*width: max\(100cqw, 100cqh \* 2688 \/ 1512\);[^}]*height: auto !important;/s', $styles) !== 1
    || preg_match('/\.publicHomepage \.homepageStageSvg \{[^}]*height: 100% !important/s', $styles) === 1) {
    failHomepageRouteContract('The public hero stage must cover its box, not fit its height.');
}

echo "Homepage route contract passed.\n";
