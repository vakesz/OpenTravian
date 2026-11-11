<?php

declare(strict_types=1);

/**
 * @var array{
 *   copy:array<string,string>,
 *   page:string,
 *   publicDirection:string,
 *   publicIntlLocale:string,
 *   publicLanguageOptionId:string,
 *   publicLanguageName:string,
 *   publicLanguageFlag:string,
 *   languageOptions:list<array{id:string,route:string,flag:string,english:string,language:string,country:string,search:string,href:string,selected:bool}>,
 *   isAuthenticated:bool,
 *   playerName:string,
 *   entryUrl:string,
 *   loginUrl:string,
 *   registrationUrl:string,
 *   registrationDialog:string,
 *   accountUrl:string,
 *   logoutUrl:string,
 *   tribes:list<array{key:string,name:string,description:string}>,
 *   news:array{id:string,path:string,title:string,preview:string,publishedAt:int,excerptAsset:string,bannerAsset:string,body:list<array<string,mixed>>},
 *   article:array{id:string,path:string,title:string,preview:string,publishedAt:int,excerptAsset:string,bannerAsset:string,body:list<array<string,mixed>>},
 *   articles:array<string,array<string,mixed>>,
 *   gameworld:?array{name:string,speed:int,startsAt:int,artifactsAt:int,plansAt:int,tribes:list<array{slug:string,name:string}>},
 *   worldStats:?array{startTime:int,ageDays:int,speed:int,players:int,alliances:int,topPlayers:list<array{rank:int,population:int}>,topAlliances:list<array{rank:int,name:string,tag:string,population:int}>,wonder:array{level:int,allianceName:string,allianceTag:string,artefactsAt:int,plansAt:int,finished:bool}},
 *   worldLabels:array<string,string>
 * } $vars
 */
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$asset = static fn(string $name): string => get_gpack_cdn_mainPage_url() . gpack_image_path('homepage/' . $name, $vars['publicDirection']);
$flagAssetUrl = get_gpack_cdn_mainPage_url() . 'flags/';
$flagAsset = static fn(string $flag): string => $flagAssetUrl . rawurlencode($flag) . '.svg';
// The header wordmark is drawn as an inline svg (xMinYMid slice) so the page fonts apply and the mobile header can crop it to the emblem.
$wordmarkFile = dirname(__DIR__, 5) . '/assets/gpack/travian/mainPage/' . 'img_ltr/homepage/public-wordmark.svg';
$wordmarkSvg = is_file($wordmarkFile)
    ? (string) preg_replace(
        ['/^<\?xml[^>]*\?>\s*/', '/<svg\b/'],
        ['', '<svg class="logo" preserveAspectRatio="xMinYMid slice" aria-hidden="true" focusable="false"'],
        (string) file_get_contents($wordmarkFile),
        1
    )
    : '';
$copy = $vars['copy'];
$page = $vars['page'];
$publicDirection = $vars['publicDirection'];
$publicIntlLocale = $vars['publicIntlLocale'];
$publicLanguageOptionId = $vars['publicLanguageOptionId'];
$publicLanguageName = $vars['publicLanguageName'];
$publicLanguageFlag = $vars['publicLanguageFlag'];
$languageOptions = $vars['languageOptions'];
$isAuthenticated = $vars['isAuthenticated'];
$playerName = $vars['playerName'];
$entryUrl = $vars['entryUrl'];
$loginUrl = $vars['loginUrl'];
$registrationDialog = (string)($vars['registrationDialog'] ?? '');
$registrationUrl = (string)$vars['registrationUrl'];
$accountUrl = $vars['accountUrl'];
$logoutUrl = $vars['logoutUrl'];
$tribes = $vars['tribes'];
$news = $vars['news'];
$article = $vars['article'];
$articles = $vars['articles'];
$gameworld = $vars['gameworld'];
$worldStats = $vars['worldStats'];
$worldLabels = $vars['worldLabels'];
$number = static function (int $value) use ($publicIntlLocale): string {
    return (string) (new \NumberFormatter($publicIntlLocale, \NumberFormatter::DECIMAL))->format($value);
};
/**
 * Keep the public locale as explicit, transient view state across every local
 * public route. It deliberately is not a `lang` parameter and never changes
 * the player's in-game language preference.
 *
 * @param array<string, string> $query
 */
$publicRoute = static function (string $path, array $query = []) use ($publicLanguageOptionId): string {
    return $path . '?' . http_build_query(
        ['homepageLocale' => $publicLanguageOptionId] + $query,
        '',
        '&',
        PHP_QUERY_RFC3986,
    );
};
$homeUrl = $publicRoute('/homepage');
$newsUrl = $publicRoute('/homepage/news');
$calendarUrl = $publicRoute('/homepage/calendar') . '#calendarGameworldDetails';
$calendarOverviewUrl = $publicRoute('/homepage/calendar');
$tournamentUrl = $publicRoute('/homepage/tournament');
$tribesJson = json_encode(
    $tribes,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
);
$renderArticleParts = static function (array $parts) use ($escape): string {
    $html = '';
    foreach ($parts as $part) {
        if (!is_array($part)
            || !isset($part['type'], $part['text'])
            || !is_string($part['type'])
            || !is_string($part['text'])) {
            throw new \LogicException('Homepage article snapshot has an invalid inline part.');
        }

        $text = $escape($part['text']);
        switch ($part['type']) {
            case 'text':
                $html .= $text;
                break;
            case 'strong':
                $html .= '<strong>' . $text . '</strong>';
                break;
            case 'strongEm':
                $html .= '<strong><em>' . $text . '</em></strong>';
                break;
            case 'link':
                $href = $part['href'] ?? null;
                if (!is_string($href) || preg_match('#\Ahttps://[^\s]+\z#', $href) !== 1) {
                    throw new \LogicException('Homepage article snapshot has an invalid link.');
                }
                $content = !empty($part['strong']) ? '<strong>' . $text . '</strong>' : $text;
                $html .= '<a href="' . $escape($href) . '" target="_blank" rel="noopener noreferrer">'
                    . $content . '</a>';
                break;
            default:
                throw new \LogicException('Homepage article snapshot has an unknown inline part.');
        }
    }

    return $html;
};
$renderArticleBody = null;
$renderArticleBody = static function (array $blocks) use (&$renderArticleBody, $escape, $renderArticleParts): string {
    $html = '';
    foreach ($blocks as $block) {
        if (!is_array($block) || !isset($block['type']) || !is_string($block['type'])) {
            throw new \LogicException('Homepage article snapshot has an invalid block.');
        }

        switch ($block['type']) {
            case 'heading':
                $level = $block['level'] ?? null;
                $text = $block['text'] ?? null;
                if (!is_int($level) || !is_string($text) || !in_array($level, [1, 2, 3, 4], true)) {
                    throw new \LogicException('Homepage article snapshot has an invalid heading.');
                }
                $html .= '<h' . $level . '>' . $escape($text) . '</h' . $level . '>';
                break;
            case 'paragraph':
                $parts = $block['parts'] ?? null;
                if (!is_array($parts)) {
                    throw new \LogicException('Homepage article snapshot has an invalid paragraph.');
                }
                $html .= '<p>' . $renderArticleParts($parts) . '</p>';
                break;
            case 'list':
                $items = $block['items'] ?? null;
                if (!is_array($items)) {
                    throw new \LogicException('Homepage article snapshot has an invalid list.');
                }
                $html .= '<ul>';
                foreach ($items as $parts) {
                    if (!is_array($parts)) {
                        throw new \LogicException('Homepage article snapshot has an invalid list item.');
                    }
                    $html .= '<li>' . $renderArticleParts($parts) . '</li>';
                }
                $html .= '</ul>';
                break;
            case 'columns':
                $items = $block['items'] ?? null;
                if (!is_array($items)) {
                    throw new \LogicException('Homepage article snapshot has invalid columns.');
                }
                $html .= '<div class="homepageArticleColumns">';
                foreach ($items as $item) {
                    if (!is_array($item) || !isset($item['title'], $item['parts'])
                        || !is_string($item['title']) || !is_array($item['parts'])) {
                        throw new \LogicException('Homepage article snapshot has an invalid column.');
                    }
                    $html .= '<div class="homepageArticleColumn"><h3>' . $escape($item['title']) . '</h3><p>'
                        . $renderArticleParts($item['parts']) . '</p><p></p></div>';
                }
                $html .= '<p></p></div>';
                break;
            case 'timeline':
                $items = $block['items'] ?? null;
                if (!is_array($items)) {
                    throw new \LogicException('Homepage article snapshot has an invalid timeline.');
                }
                $html .= '<table class="homepageArticleTimeline"><tbody><tr>';
                foreach ($items as $item) {
                    if (!is_array($item) || !isset($item['title'], $item['subtitle'])
                        || !is_string($item['title']) || !is_string($item['subtitle'])) {
                        throw new \LogicException('Homepage article snapshot has an invalid timeline item.');
                    }
                    $html .= '<td><div class="homepageArticleTimelineDot"></div><div class="homepageArticleTimelineLabel">'
                        . $escape($item['title']) . '<br><small>' . $escape($item['subtitle']) . '</small></div></td>';
                }
                $html .= '</tr></tbody></table>';
                break;
            case 'featureList':
                $items = $block['items'] ?? null;
                if (!is_array($items)) {
                    throw new \LogicException('Homepage article snapshot has an invalid feature list.');
                }
                $html .= '<ul class="homepageArticleFeatureList">';
                foreach ($items as $item) {
                    if (!is_array($item) || !isset($item['title'], $item['description'])
                        || !is_string($item['title']) || !is_string($item['description'])) {
                        throw new \LogicException('Homepage article snapshot has an invalid feature item.');
                    }
                    $html .= '<li><strong>' . $escape($item['title']) . '</strong><br><span>'
                        . $escape($item['description']) . '</span></li>';
                }
                $html .= '</ul>';
                break;
            case 'section':
                $spacing = $block['spacing'] ?? null;
                $sectionBlocks = $block['blocks'] ?? null;
                if (!is_string($spacing)
                    || !in_array($spacing, ['medium', 'large'], true)
                    || !is_array($sectionBlocks)) {
                    throw new \LogicException('Homepage article snapshot has an invalid section.');
                }
                $html .= '<section class="homepageArticleSection" data-homepage-article-spacing="'
                    . $escape($spacing) . '">' . $renderArticleBody($sectionBlocks) . '</section>';
                break;
            default:
                throw new \LogicException('Homepage article snapshot has an unknown block.');
        }
    }

    return $html;
};
?>
<main class="publicHomepage" dir="<?= $escape($publicDirection); ?>" aria-label="<?= $escape($copy['homeTitle']); ?>">
    <header class="homepageHeader">
        <div class="homepageHeaderStart">
            <button class="homepageMenuButton" type="button" data-homepage-toggle="homepageNavDialog" aria-controls="homepageNavDialog" aria-expanded="false">
                <span class="homepageMenuIcon" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="visuallyHidden"><?= $escape($copy['Home']); ?></span>
            </button>
            <button class="homepageLanguage" type="button" data-homepage-toggle="homepageLanguageDialog" aria-controls="homepageLanguageDialog" aria-expanded="false">
                <span class="homepageLanguageContent">
                    <img src="<?= $escape($flagAsset($publicLanguageFlag)); ?>" alt="" aria-hidden="true">
                    <span class="homepageLanguageName"><?= $escape($publicLanguageName); ?></span>
                </span>
            </button>
        </div>
        <a class="homepageWordmark" href="<?= $escape($homeUrl); ?>" aria-label="<?= $escape($copy['homeTitle']); ?>">
            <?= $wordmarkSvg; ?>
        </a>
        <div class="homepageAccount">
            <?php if ($isAuthenticated): ?>
                <button class="homepageAccountLink" type="button" data-homepage-toggle="homepageAccountDialog" aria-controls="homepageAccountDialog" aria-expanded="false">
                    <span class="homepageAccountContent">
                        <span class="homepageAccountIcon" aria-hidden="true"><svg viewBox="0 0 18 18"><path d="M9 9c-1.24 0-2.3-.44-3.18-1.32C4.94 6.8 4.5 5.74 4.5 4.5s.44-2.3 1.32-3.18C6.7.44 7.76 0 9 0s2.3.44 3.18 1.32c.88.88 1.32 1.94 1.32 3.18s-.44 2.3-1.32 3.18C11.3 8.56 10.24 9 9 9ZM.02 18c.08-1.24-.29-3.84.49-4.9.33-.53.76-.94 1.3-1.22 2.28-1.14 4.67-1.74 7.18-1.74 2.52 0 4.91.6 7.18 1.74.54.28.98.69 1.3 1.22.79 1.05.41 3.68.49 4.9H.02Zm2.25-2.24h13.47c0-.62.12-1.53-.56-1.85a13.4 13.4 0 0 0-12.35 0c-.68.31-.56 1.24-.56 1.85ZM9 6.75c.62 0 1.15-.22 1.59-.66.44-.44.66-.97.66-1.59s-.22-1.15-.66-1.59c-.44-.44-.97-.66-1.59-.66s-1.15.22-1.59.66c-.44.44-.66.97-.66 1.59s.22 1.15.66 1.59c.44.44.97.66 1.59.66Z"/></svg></span>
                        <span class="homepageAccountName"><?= $escape($playerName); ?></span>
                        <span class="homepageChevron" aria-hidden="true"></span>
                    </span>
                </button>
            <?php else: ?>
                <button class="homepageAuthLink" type="button" data-homepage-href="<?= $escape($loginUrl); ?>"><span class="homepageAuthContent"><svg viewBox="0 0 18 18" aria-hidden="true" focusable="false"><path d="M9 18v-2h7V2H9V0h7c.55 0 1.02.2 1.41.59.39.39.59.86.59 1.41v14c0 .55-.2 1.02-.59 1.41-.37.38-.88.59-1.41.59H9Zm-2-4-1.38-1.45L8.17 10H0V8h8.18L5.63 5.45 7.01 4l5 5-5 5Z"/></svg><span><?= $escape($copy['Login']); ?></span></span></button>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($page === 'home'): ?>
        <section class="homepageHero" aria-labelledby="homepageCurrentTribe">
            <div class="homepageStage" data-homepage-stage data-tribe="<?= $escape($tribes[0]['key']); ?>">
                <svg class="homepageStageSvg" viewBox="0 0 2688 1512" preserveAspectRatio="xMidYMid meet" direction="<?= $escape($publicDirection); ?>" aria-hidden="true">
                    <defs>
                        <filter id="homepageTribeStageFilterRoman" color-interpolation-filters="sRGB"><feFlood flood-color="#c21c1f" flood-opacity=".25" result="redFill"/><feComposite in="redFill" in2="SourceAlpha" operator="in" result="redTint"/><feBlend in="SourceGraphic" in2="redTint" mode="multiply" result="blended"/><feComponentTransfer><feFuncR type="linear" slope="1.2"/><feFuncG type="linear" slope=".6"/><feFuncB type="linear" slope=".6"/></feComponentTransfer></filter>
                        <filter id="homepageTribeStageFilterTeuton" color-interpolation-filters="sRGB"><feFlood flood-color="#47726e" flood-opacity=".2" result="blueFill"/><feComposite in="blueFill" in2="SourceAlpha" operator="in" result="blueTint"/><feBlend in="SourceGraphic" in2="blueTint" mode="multiply" result="blended"/><feComponentTransfer><feFuncR type="linear" slope="1.6" intercept="-.35"/><feFuncG type="linear" slope=".9" intercept=".02"/><feFuncB type="linear" slope=".9" intercept=".02"/></feComponentTransfer></filter>
                        <filter id="homepageTribeStageFilterGaul" color-interpolation-filters="sRGB"><feFlood flood-color="#c25b07" flood-opacity=".3" result="orangeFill"/><feComposite in="orangeFill" in2="SourceAlpha" operator="in" result="orangeTint"/><feBlend in="SourceGraphic" in2="orangeTint" mode="multiply" result="blended"/><feComponentTransfer><feFuncR type="linear" slope="1.15" intercept=".11"/><feFuncG type="linear" slope="1"/><feFuncB type="linear" slope=".5"/></feComponentTransfer></filter>
                    </defs>
                    <image width="2688" height="1512" href="<?= $escape($asset('stage-layer-1.webp')); ?>"></image>
                    <image width="2688" height="1512" href="<?= $escape($asset('stage-layer-2.webp')); ?>" class="homepageStageTint"></image>
                    <image width="2688" height="871" x="0" y="641" href="<?= $escape($asset('stage-layer-3.webp')); ?>" class="homepageStageTint"></image>

                    <g class="homepageSceneLayer" data-tribe="romans">
                        <image width="366" height="632" x="2000" y="571" href="<?= $escape($asset('gaul-2.webp')); ?>"></image>
                        <image width="247" height="380" x="713" y="918" href="<?= $escape($asset('teuton-3.webp')); ?>"></image>
                    </g>
                    <g class="homepageSceneLayer" data-tribe="teutons">
                        <image width="416" height="608" x="1987" y="575" href="<?= $escape($asset('roman-2.webp')); ?>"></image>
                        <image width="218" height="375" x="694" y="925" href="<?= $escape($asset('gaul-3.webp')); ?>"></image>
                    </g>
                    <g class="homepageSceneLayer" data-tribe="gauls">
                        <image width="389" height="601" x="1981" y="585" href="<?= $escape($asset('teuton-2.webp')); ?>"></image>
                        <image width="271" height="396" x="701" y="899" href="<?= $escape($asset('roman-3.webp')); ?>"></image>
                    </g>

                    <image width="2688" height="398" x="0" y="1114" href="<?= $escape($asset('stage-layer-4.webp')); ?>" class="homepageStageTint"></image>
                    <image width="2688" height="653" x="0" y="859" href="<?= $escape($asset('stage-layer-5.webp')); ?>" class="homepageStageTint"></image>
                    <image width="635" height="58" x="1067" y="1406" href="<?= $escape($asset('unit-shadow.webp')); ?>" opacity=".8"></image>

                    <?php foreach ($tribes as $tribe): ?>
                        <g class="homepageTribeLayer" data-tribe="<?= $escape($tribe['key']); ?>">
                            <g class="homepageHeadlineContainer"><text x="15%" y="10%" dominant-baseline="middle" text-anchor="start" class="homepageStageHeadline"><?= $escape($tribe['name']); ?></text></g>
                            <?php if ($tribe['key'] === 'romans'): ?>
                                <image width="909" height="1327" x="983" y="140" href="<?= $escape($asset('roman-1.webp')); ?>"></image>
                            <?php elseif ($tribe['key'] === 'teutons'): ?>
                                <image width="828" height="1280" x="998" y="175" href="<?= $escape($asset('teuton-1.webp')); ?>"></image>
                            <?php else: ?>
                                <image width="770" height="1331" x="998" y="129" href="<?= $escape($asset('gaul-1.webp')); ?>"></image>
                            <?php endif; ?>
                            <g class="homepageForeignObjectContainer"><foreignObject x="15.5%" y="17%" width="100%" height="50%"><div xmlns="http://www.w3.org/1999/xhtml" class="homepageStageDescription"><?= $escape($tribe['description']); ?></div></foreignObject></g>
                        </g>
                    <?php endforeach; ?>
                </svg>

                <div class="homepageStageControls">
                    <button class="homepageStageControl homepageStageControlBack" type="button" data-homepage-tribe-step="-1" aria-label="<?= $escape($tribes[2]['name']); ?>">
                        <svg viewBox="0 0 20 36" aria-hidden="true"><path d="M20 36 2 18 20 0v36ZM4.95 18l12.97 12.97V5.03L4.95 18Z"></path><path d="m6 24-6-6 6-6 .41.41L.83 18l5.58 5.59L6 24z"></path></svg>
                    </button>
                    <button class="homepageStageControl homepageStageControlForward" type="button" data-homepage-tribe-step="1" aria-label="<?= $escape($tribes[1]['name']); ?>">
                        <svg viewBox="0 0 20 36" aria-hidden="true"><path d="M20 36 2 18 20 0v36ZM4.95 18l12.97 12.97V5.03L4.95 18Z"></path><path d="m6 24-6-6 6-6 .41.41L.83 18l5.58 5.59L6 24z"></path></svg>
                    </button>
                </div>
            </div>

            <div class="visuallyHidden" aria-live="polite">
                <h1 id="homepageCurrentTribe" data-homepage-tribe-name><?= $escape($tribes[0]['name']); ?></h1>
                <p data-homepage-tribe-description><?= $escape($tribes[0]['description']); ?></p>
            </div>

            <div class="homepageCtaWrapper homepageInfoGrid">
                <article class="homepageInfoCard homepageInfoBox homepageNewsCard" id="news">
                    <div class="homepageNewsFrame">
                        <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--start-start" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                        <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--start-end" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                        <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--end-start" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                        <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--end-end" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                        <div class="newsPreview">
                            <div class="previewImage"><img alt="<?= $escape($copy['Article image']); ?>" src="<?= $escape($asset($news['excerptAsset'])); ?>"></div>
                            <h3 class="title"><?= $escape($news['title']); ?></h3>
                            <div class="previewText"><?= $escape($news['preview']); ?></div>
                            <div class="date" data-homepage-date-split data-homepage-timestamp="<?= $escape((string) $news['publishedAt']); ?>"></div>
                            <div class="linkToFullArticle"><a href="<?= $escape($publicRoute($news['path'])); ?>"><span class="full"><?= $escape($copy['Read full story']); ?></span><span class="short"><?= $escape($copy['Read']); ?></span><svg viewBox="0 0 20 18" aria-hidden="true"><path fill="currentColor" d="M10 0 8.6 1.4 15.2 8H0v2h15.2l-6.6 6.6L10 18l9-9z"/></svg></a></div>
                        </div>
                    </div>
                    <a class="homepageInfoAction" href="<?= $escape($newsUrl); ?>"><span><?= $escape($copy['Show all news']); ?></span><svg class="homepageInfoActionIcon" viewBox="0 0 20 18" aria-hidden="true"><path d="M2 18c-.55 0-1.02-.2-1.41-.59C.2 17.04 0 16.53 0 16V0l1.67 1.67L3.32 0l1.67 1.67L6.67 0l1.65 1.67L10 0l1.68 1.67L13.33 0l1.68 1.67L16.68 0l1.65 1.67L20.01 0v16c0 .55-.2 1.02-.59 1.41-.37.38-.88.59-1.41.59H2Zm0-2h7v-6H2v6Zm9 0h7v-2h-7v2Zm0-4h7v-2h-7v-2h7v2ZM2 8h16V5H2v3Z"/></svg></a>
                </article>

                <button class="homepageLobbyCta" type="button"<?= $isAuthenticated ? ' data-homepage-href="' . $escape($entryUrl) . '"' : ' data-homepage-toggle="homepageRegistrationDialog" aria-controls="homepageRegistrationDialog" aria-expanded="false"' ?>><span><?= $escape($isAuthenticated ? $copy['To lobby'] : $copy['Play Now']); ?></span></button>

                <?php if ($gameworld !== null): ?>
                <article class="homepageInfoCard homepageInfoBox homepageWorldCard" id="game-worlds">
                    <a class="homepageInfoBodyLink" href="<?= $escape($calendarUrl); ?>" aria-label="<?= $escape($gameworld['name']); ?>">
                        <div class="homepageGameworldPreview">
                            <h3><?= $escape($copy['Next upcoming server']); ?></h3>
                            <div class="day"><div data-homepage-short-date data-homepage-timestamp="<?= $escape((string) $gameworld['startsAt']); ?>"></div></div>
                            <div class="title"><div class="text"><span class="name"><?= $escape($gameworld['name']); ?></span></div></div>
                            <div class="speed">×<?= $escape((string) $gameworld['speed']); ?></div>
                            <div class="tribes" aria-hidden="true"><div class="tribeImages">
                                <?php foreach ($gameworld['tribes'] as $gameworldTribe): ?>
                                    <img class="tribe <?= $escape($gameworldTribe['slug']); ?>" src="<?= $escape($asset($gameworldTribe['slug'] . '-small.png')); ?>" alt="">
                                <?php endforeach; ?>
                            </div></div>
                        </div>
                    </a>
                    <a class="homepageInfoAction" href="<?= $escape($calendarOverviewUrl); ?>"><span><?= $escape($copy['Show gameworld calendar']); ?></span><svg class="homepageInfoActionIcon" viewBox="0 0 18 20" aria-hidden="true"><path d="M1.96 20c-.538 0-1-.196-1.384-.587A1.945 1.945 0 0 1 0 18V4c0-.55.192-1.02.576-1.413A1.87 1.87 0 0 1 1.961 2h.98V0h1.961v2h7.843V0h1.96v2h.981c.54 0 1.001.196 1.385.587.384.392.576.863.576 1.413v14c0 .55-.192 1.02-.576 1.413a1.87 1.87 0 0 1-1.385.587H1.961Zm0-2h13.726V8H1.961v10Zm0-12h13.726V4H1.961v2Zm6.864 6a.939.939 0 0 1-.699-.287.978.978 0 0 1-.282-.713c0-.283.094-.52.282-.713A.939.939 0 0 1 8.824 10c.277 0 .51.096.698.287.188.192.282.43.282.713s-.094.52-.282.713a.939.939 0 0 1-.698.287Zm-3.922 0a.939.939 0 0 1-.699-.287.978.978 0 0 1-.281-.713c0-.283.094-.52.281-.713A.939.939 0 0 1 4.902 10c.278 0 .51.096.698.287.188.192.282.43.282.713s-.094.52-.282.713a.939.939 0 0 1-.698.287Zm7.843 0a.939.939 0 0 1-.698-.287.978.978 0 0 1-.282-.713c0-.283.094-.52.282-.713a.939.939 0 0 1 .698-.287c.278 0 .51.096.699.287.188.192.281.43.281.713s-.093.52-.281.713a.939.939 0 0 1-.699.287Zm-3.921 4a.939.939 0 0 1-.699-.287.978.978 0 0 1-.282-.713c0-.283.094-.52.282-.713A.939.939 0 0 1 8.824 14c.277 0 .51.096.698.287.188.192.282.43.282.713s-.094.52-.282.713a.939.939 0 0 1-.698.287Zm-3.922 0a.939.939 0 0 1-.699-.287.978.978 0 0 1-.281-.713c0-.283.094-.52.281-.713A.939.939 0 0 1 4.902 14c.278 0 .51.096.698.287.188.192.282.43.282.713s-.094.52-.282.713a.939.939 0 0 1-.698.287Zm7.843 0a.939.939 0 0 1-.698-.287.978.978 0 0 1-.282-.713.978.978 0 0 1 .282-.713.939.939 0 0 1 .698-.287c.278 0 .51.096.699.287.188.192.281.43.281.713s-.093.52-.281.713a.939.939 0 0 1-.699.287Z"/></svg></a>
                </article>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($page === 'news'): ?>
        <section class="homepageReferencePage homepageNewsPage" aria-labelledby="homepageNewsHeading">
            <h1 id="homepageNewsHeading"><?= $escape($copy['News & Updates']); ?></h1>
            <?php
            $newsList = $articles;
            uasort($newsList, static fn(array $left, array $right): int => $right['publishedAt'] <=> $left['publishedAt']);
            ?>
            <?php foreach ($newsList as $listed): ?>
                <div class="homepageNewsFrame">
                    <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--start-start" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                    <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--start-end" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                    <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--end-start" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                    <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--end-end" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                    <div class="newsPreview">
                        <div class="previewImage"><img alt="<?= $escape($copy['Article image']); ?>" src="<?= $escape($asset($listed['excerptAsset'])); ?>"></div>
                        <h3 class="title"><?= $escape($listed['title']); ?></h3>
                        <div class="previewText"><?= $escape($listed['preview']); ?></div>
                        <div class="date" data-homepage-date-split data-homepage-timestamp="<?= $escape((string) $listed['publishedAt']); ?>"></div>
                        <div class="linkToFullArticle"><a href="<?= $escape($publicRoute($listed['path'])); ?>"><span class="full"><?= $escape($copy['Read full story']); ?></span><span class="short"><?= $escape($copy['Read']); ?></span><svg viewBox="0 0 20 18" aria-hidden="true"><path fill="currentColor" d="M10 0 8.6 1.4 15.2 8H0v2h15.2l-6.6 6.6L10 18l9-9z"/></svg></a></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="buttonWrapper"><span><?= $escape($copy['You have reached the end - no more news found.']); ?></span></div>
        </section>
    <?php elseif ($page === 'article'): ?>
        <section class="homepageReferencePage homepageArticlePage" aria-labelledby="homepageNewsHeading">
            <h1 id="homepageNewsHeading"><?= $escape($copy['News & Updates']); ?></h1>
            <div class="homepageArticleFrame">
                <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--start-start" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--start-end" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--end-start" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                <svg class="homepageArticleFrameCorner homepageArticleFrameCorner--end-end" viewBox="0 0 11 11" aria-hidden="true" focusable="false"><path fill="none" stroke="#7c694b" d="m9.5.5-9 9v-9h9Z"/><path fill="#cba467" d="M4 8V4h4L4 8Z"/></svg>
                <a class="homepageBackAction" href="<?= $escape($newsUrl); ?>"><svg viewBox="0 0 12 7.4" aria-hidden="true"><path d="M10.6 0 6 4.6 1.4 0 0 1.4l6 6 6-6L10.6 0z"/></svg><span><?= $escape($copy['To news overview']); ?></span></a>
                <article class="homepageArticle" data-homepage-article="<?= $escape($article['id']); ?>">
                    <img class="homepageArticleBanner" src="<?= $escape($asset($article['bannerAsset'])); ?>" alt="<?= $escape($copy['Article image']); ?>">
                    <h3 class="homepageArticleTitle"><?= $escape($article['title']); ?></h3>
                    <time class="homepageArticleDate" data-homepage-date datetime="<?= $escape(gmdate('c', $article['publishedAt'])); ?>" data-homepage-timestamp="<?= $escape((string) $article['publishedAt']); ?>"></time>
                    <div class="homepageArticleContent"><?= $renderArticleBody($article['body']); ?></div>
                </article>
            </div>
        </section>
    <?php elseif ($page === 'calendar'): ?>
        <section class="homepageReferencePage" id="gameworldCalendar" aria-labelledby="homepageCalendarHeading">
            <h1 id="homepageCalendarHeading"><?= $escape($copy['Gameworld Calendar']); ?></h1>
            <?php if ($gameworld === null): ?>
                <div class="noResults"><?= $escape($copy['Currently no entries']); ?></div>
            <?php else: ?>
                <h2 class="calendarMonth" data-homepage-fmt="month-year" data-homepage-timestamp="<?= $escape((string) $gameworld['startsAt']); ?>"></h2>
                <div class="calendarGameworld gameworldDetails">
                    <div class="day active"><div class="weekday" data-homepage-fmt="weekday" data-homepage-timestamp="<?= $escape((string) $gameworld['startsAt']); ?>"></div><div data-homepage-fmt="day" data-homepage-timestamp="<?= $escape((string) $gameworld['startsAt']); ?>"></div></div>
                    <div class="title tag normal"><div class="text"><span class="name"><?= $escape($gameworld['name']); ?></span></div></div>
                    <div class="speed"><i class="speed"></i>×<?= $escape((string) $gameworld['speed']); ?></div>
                    <div class="tribes"><div class="tribeImages">
                        <?php foreach ($gameworld['tribes'] as $gameworldTribe): ?>
                            <i class="tribe <?= $escape($gameworldTribe['slug']); ?>" title="tribe<?= $escape(strtoupper($gameworldTribe['name'])); ?>"></i>
                        <?php endforeach; ?>
                    </div></div>
                    <div class="startTime"><i class="serverStart"></i><div><span data-homepage-fmt="time" data-homepage-timestamp="<?= $escape((string) $gameworld['startsAt']); ?>"></span>&nbsp;<span>(UTC+0)</span></div></div>
                    <div class="artefacts"><i class="artefacts"></i><span data-homepage-fmt="short-date" data-homepage-timestamp="<?= $escape((string) $gameworld['artifactsAt']); ?>"></span></div>
                    <div class="constructionPlans"><i class="constructionPlans"></i><span data-homepage-fmt="short-date" data-homepage-timestamp="<?= $escape((string) $gameworld['plansAt']); ?>"></span></div>
                    <div class="endCondition"><i class="ww"></i><span>100</span></div>
                    <button class="green buttonFramed action rectangle withText" type="button" data-homepage-toggle="homepageCalendarDetailsDialog" aria-controls="homepageCalendarDetailsDialog" aria-expanded="false" aria-label="<?= $escape($gameworld['name']); ?>"><div><svg viewBox="0 0 14 18" aria-hidden="true"><g class="icon"><path d="M1.134 1.456.75 1.212v16.183l.384-.244 12-7.637.332-.21-.332-.212-12-7.636Z"></path></g></svg></div></button>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($page === 'world' && $worldStats !== null): ?>
        <?php $wonder = $worldStats['wonder']; ?>
        <section class="homepageReferencePage homepageWorldPage" aria-labelledby="homepageWorldHeading">
            <h1 id="homepageWorldHeading"><?= $escape($worldLabels['title']); ?></h1>
            <dl class="worldFacts">
                <div><dt><?= $escape($copy['Server age in days']); ?></dt><dd><?= $escape($number($worldStats['ageDays'])); ?></dd></div>
                <div><dt><?= $escape($copy['Date']); ?></dt><dd data-homepage-fmt="date" data-homepage-timestamp="<?= $escape((string) $worldStats['startTime']); ?>"></dd></div>
                <div><dt><?= $escape($copy['Speed']); ?></dt><dd>×<?= $escape($number($worldStats['speed'])); ?></dd></div>
                <div><dt><?= $escape($worldLabels['Players']); ?></dt><dd><?= $escape($number($worldStats['players'])); ?></dd></div>
                <div><dt><?= $escape($worldLabels['Alliances']); ?></dt><dd><?= $escape($number($worldStats['alliances'])); ?></dd></div>
            </dl>
            <div class="worldTables">
                <table class="worldTable">
                    <caption><?= $escape($worldLabels['Top alliances']); ?></caption>
                    <thead><tr><th scope="col"><?= $escape($worldLabels['Rank']); ?></th><th scope="col"><?= $escape($worldLabels['Alliance']); ?></th><th scope="col"><?= $escape($worldLabels['Population']); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($worldStats['topAlliances'] as $row): ?>
                            <tr><td><?= $escape($number($row['rank'])); ?></td><td><?= $escape($row['name']); ?> [<?= $escape($row['tag']); ?>]</td><td><?= $escape($number($row['population'])); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <table class="worldTable">
                    <caption><?= $escape($worldLabels['Top players']); ?></caption>
                    <thead><tr><th scope="col"><?= $escape($worldLabels['Rank']); ?></th><th scope="col"><?= $escape($worldLabels['Population']); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($worldStats['topPlayers'] as $row): ?>
                            <tr><td><?= $escape($number($row['rank'])); ?></td><td><?= $escape($number($row['population'])); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h2 class="worldWonderHeading"><?= $escape($worldLabels['World Wonder']); ?></h2>
            <dl class="worldFacts">
                <div><dt><?= $escape($worldLabels['Level']); ?></dt><dd><?= $escape($number($wonder['level'])); ?> / 100</dd></div>
                <?php if ($wonder['allianceTag'] !== ''): ?>
                    <div><dt><?= $escape($worldLabels['Alliance']); ?></dt><dd><?= $escape($wonder['allianceName']); ?> [<?= $escape($wonder['allianceTag']); ?>]</dd></div>
                <?php endif; ?>
                <?php if ($wonder['artefactsAt'] > 0): ?>
                    <div><dt><?= $escape($copy['Artefacts']); ?></dt><dd data-homepage-fmt="date" data-homepage-timestamp="<?= $escape((string) $wonder['artefactsAt']); ?>"></dd></div>
                <?php endif; ?>
                <?php if ($wonder['plansAt'] > 0): ?>
                    <div><dt><?= $escape($copy['Construction plans']); ?></dt><dd data-homepage-fmt="date" data-homepage-timestamp="<?= $escape((string) $wonder['plansAt']); ?>"></dd></div>
                <?php endif; ?>
                <?php if ($wonder['finished']): ?>
                    <div><dt><?= $escape($copy['Gameworld end']); ?></dt><dd><?= $escape($worldLabels['Finished']); ?></dd></div>
                <?php endif; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if ($page === 'calendar' && $gameworld !== null): ?>
    <div class="homepageDialog homepageRegistrationDialog" id="homepageCalendarDetailsDialog" role="dialog" aria-modal="true" aria-labelledby="homepageCalendarDetailsHeading" hidden>
        <div class="homepageDialogPanel" id="calendarGameworldDetails">
            <button class="homepageDialogClose" type="button" data-homepage-close aria-label="<?= $escape($copy['Close window']); ?>">×</button>
            <div class="header"><div class="background <?= $gameworld['speed'] > 1 ? 'defaultSpeed' : 'default' ?>"></div><div class="content"><div class="title"><div class="gameworldName" id="homepageCalendarDetailsHeading"><?= $escape($gameworld['name']); ?></div><div class="subtitle"></div></div><div class="tribes">
                <?php foreach ($gameworld['tribes'] as $gameworldTribe): ?><i class="tribe <?= $escape($gameworldTribe['slug']); ?>" title="tribe<?= $escape(strtoupper($gameworldTribe['name'])); ?>"></i><?php endforeach; ?>
            </div></div></div>
            <section class="details hasAction gameworldDetails">
                <div class="serverStart"><i class="serverStart"></i><span><?= $escape($copy['Date']); ?>:<strong><span data-homepage-fmt="date-time" data-homepage-timestamp="<?= $escape((string) $gameworld['startsAt']); ?>"></span>&nbsp;<span>(UTC+0)</span></strong></span></div>
                <div class="speed" title="<?= $escape($copy['Speed']); ?>"><i class="speed"></i><span><?= $escape($copy['Speed']); ?>:<strong>×<?= $escape((string) $gameworld['speed']); ?></strong></span></div>
                <div class="artefacts"><i class="artefacts"></i><span><?= $escape($copy['Artefacts']); ?>:<strong data-homepage-fmt="date" data-homepage-timestamp="<?= $escape((string) $gameworld['artifactsAt']); ?>"></strong></span></div>
                <div class="constructionPlans"><i class="constructionPlans"></i><span><?= $escape($copy['Construction plans']); ?>:<strong data-homepage-fmt="date" data-homepage-timestamp="<?= $escape((string) $gameworld['plansAt']); ?>"></strong></span></div>
                <div class="endCondition"><i class="serverEnd"></i><span><?= $escape($copy['Gameworld end']); ?>:<strong><?= $escape($copy['Wonder of the World, level 100']); ?></strong></span></div>
            </section>
            <section class="action"><a class="green buttonFramed rectangle withText" href="<?= $escape($isAuthenticated ? '/dorf1.php' : $registrationUrl); ?>"><div><?= $escape($copy['Join Gameworld']); ?></div></a></section>
        </div>
    </div>
    <?php endif; ?>

    <div class="homepageDialog" id="homepageNavDialog" role="dialog" aria-modal="true" aria-labelledby="homepageNavHeading" hidden>
        <div class="homepageDialogPanel">
            <nav aria-labelledby="homepageNavHeading">
                <h2 class="visuallyHidden" id="homepageNavHeading"><?= $escape($copy['Home']); ?></h2>
                <a href="<?= $escape($homeUrl); ?>"><svg viewBox="0 0 18 20" aria-hidden="true" focusable="false"><path d="M2.25 17.78h3.38v-6.67h6.75v6.67h3.38v-10l-6.75-5-6.76 5v10ZM0 20V6.67L9 0l9 6.67V20h-7.88v-6.67H7.87V20H0Z"/></svg><span><?= $escape($copy['Home']); ?></span><svg class="homepageNavChevron" viewBox="0 0 12 7.4" aria-hidden="true" focusable="false"><path d="M10.6 0L6 4.6 1.4 0 0 1.4l6 6 6-6L10.6 0z"/></svg></a>
                <a href="<?= $escape($newsUrl); ?>"><svg viewBox="0 0 20 18" aria-hidden="true" focusable="false"><path d="M2 18c-.55 0-1.02-.2-1.41-.59C.2 17.04 0 16.53 0 16V0l1.67 1.67L3.32 0l1.67 1.67L6.67 0l1.65 1.67L10 0l1.68 1.67L13.33 0l1.68 1.67L16.68 0l1.65 1.67L20.01 0v16c0 .55-.2 1.02-.59 1.41-.37.38-.88.59-1.41.59H2Zm0-2h7v-6H2v6Zm9 0h7v-2h-7v2Zm0-4h7v-2h-7v2ZM2 8h16V5H2v3Z"/></svg><span><?= $escape($copy['News & Updates']); ?></span><svg class="homepageNavChevron" viewBox="0 0 12 7.4" aria-hidden="true" focusable="false"><path d="M10.6 0L6 4.6 1.4 0 0 1.4l6 6 6-6L10.6 0z"/></svg></a>
                <a href="<?= $escape($calendarOverviewUrl); ?>"><svg viewBox="0 0 18 20" aria-hidden="true" focusable="false"><path d="M1.96 20c-.538 0-1-.196-1.384-.587A1.945 1.945 0 0 1 0 18V4c0-.55.192-1.02.576-1.413A1.87 1.87 0 0 1 1.961 2h.98V0h1.961v2h7.843V0h1.96v2h.981c.54 0 1.001.196 1.385.587.384.392.576.863.576 1.413v14c0 .55-.192 1.02-.576 1.413a1.87 1.87 0 0 1-1.385.587H1.961Zm0-2h13.726V8H1.961v10Zm0-12h13.726V4H1.961v2Zm6.864 6a.939.939 0 0 1-.699-.287.978.978 0 0 1-.282-.713c0-.283.094-.52.282-.713A.939.939 0 0 1 8.824 10c.277 0 .51.096.698.287.188.192.282.43.282.713s-.094.52-.282.713a.939.939 0 0 1-.698.287Zm-3.922 0a.939.939 0 0 1-.699-.287.978.978 0 0 1-.281-.713c0-.283.094-.52.281-.713A.939.939 0 0 1 4.902 10c.278 0 .51.096.698.287.188.192.282.43.282.713s-.094.52-.282.713a.939.939 0 0 1-.698.287Zm7.843 0a.939.939 0 0 1-.698-.287.978.978 0 0 1-.282-.713c0-.283.094-.52.282-.713a.939.939 0 0 1 .698-.287c.278 0 .51.096.699.287.188.192.281.43.281.713s-.093.52-.281.713a.939.939 0 0 1-.699.287Zm-3.921 4a.939.939 0 0 1-.699-.287.978.978 0 0 1-.282-.713c0-.283.094-.52.282-.713A.939.939 0 0 1 8.824 14c.277 0 .51.096.698.287.188.192.282.43.282.713s-.094.52-.282.713a.939.939 0 0 1-.698.287Zm-3.922 0a.939.939 0 0 1-.699-.287.978.978 0 0 1-.281-.713c0-.283.094-.52.281-.713A.939.939 0 0 1 4.902 14c.278 0 .51.096.698.287.188.192.282.43.282.713s-.094.52-.282.713a.939.939 0 0 1-.698.287Zm7.843 0a.939.939 0 0 1-.698-.287.978.978 0 0 1-.282-.713c0-.283.094-.52.282-.713a.939.939 0 0 1 .698-.287c.278 0 .51.096.699.287.188.192.281.43.281.713s-.093.52-.281.713a.939.939 0 0 1-.699.287Z"/></svg><span><?= $escape($copy['Gameworld Calendar']); ?></span><svg class="homepageNavChevron" viewBox="0 0 12 7.4" aria-hidden="true" focusable="false"><path d="M10.6 0L6 4.6 1.4 0 0 1.4l6 6 6-6L10.6 0z"/></svg></a>
                <a href="<?= $escape($tournamentUrl); ?>"><svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M4.44 20v-2.22h4.45v-3.44c-.89-.19-1.72-.59-2.43-1.15a4.922 4.922 0 0 1-1.57-2.12C3.5 10.9 2.34 10.3 1.4 9.25S0 6.98 0 5.57V4.46c0-.61.22-1.13.65-1.57.41-.43.98-.66 1.57-.65h2.22V0h11.11v2.22h2.22c.59 0 1.16.23 1.57.65.43.43.65.96.65 1.57v1.11c0 1.41-.47 2.64-1.4 3.68s-2.1 1.65-3.49 1.82c-.32.84-.86 1.57-1.57 2.12-.71.56-1.54.96-2.43 1.15v3.44h4.44v2.22H4.44Zm0-11.33V4.45H2.22v1.11c0 .7.2 1.34.61 1.9.41.57.94.97 1.61 1.21ZM10 12.22c.93 0 1.71-.32 2.36-.97s.97-1.43.97-2.36V2.22H6.66v6.67c0 .93.32 1.71.97 2.36.65.65 1.43.97 2.36.97Zm5.56-3.55c.65-.23 1.21-.65 1.61-1.21.41-.56.61-1.2.61-1.9V4.45h-2.22v4.22Z"/></svg><span><?= $escape($copy['Tournament']); ?></span><svg class="homepageNavChevron" viewBox="0 0 12 7.4" aria-hidden="true" focusable="false"><path d="M10.6 0L6 4.6 1.4 0 0 1.4l6 6 6-6L10.6 0z"/></svg></a>
                <button class="homepageDialogLinkButton" type="button" data-homepage-toggle="homepageConsentDialog" aria-controls="homepageConsentDialog" aria-expanded="false"><svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M9.99 20c-1.38 0-2.68-.26-3.9-.79-1.22-.52-2.27-1.24-3.17-2.13-.9-.9-1.61-1.96-2.13-3.17a9.814 9.814 0 0 1-.79-3.9c0-1.25.24-2.47.72-3.67s1.16-2.27 2.02-3.21c.87-.94 1.91-1.7 3.12-2.27S8.41 0 9.86 0c.35 0 .71.02 1.07.05.37.03.74.09 1.12.17-.15.75-.1 1.46.15 2.12.25.67.62 1.22 1.12 1.66.5.44 1.09.74 1.79.91.69.17 1.4.12 2.13-.12-.43.98-.37 1.92.19 2.82.56.9 1.39 1.36 2.48 1.4.02.18.03.35.04.51 0 .16.01.33.01.51 0 1.36-.26 2.65-.79 3.86-.52 1.21-1.24 2.26-2.13 3.17-.9.91-1.96 1.62-3.17 2.15s-2.51.79-3.9.79ZM8.49 8.01c.42 0 .77-.15 1.06-.44.29-.29.44-.65.44-1.06s-.15-.77-.44-1.06c-.29-.29-.64-.44-1.06-.44s-.77.15-1.06.44c-.29.29-.44.65-.44 1.06s.15.77.44 1.06c.29.29.64.44 1.06.44Zm-2 5c.42 0 .77-.15 1.06-.44.29-.29.44-.65.44-1.06s-.15-.77-.44-1.06c-.29-.29-.65-.44-1.06-.44s-.77.15-1.06.44c-.29.29-.44.64-.44 1.06s.15.77.44 1.06c.29.29.65.44 1.06.44Zm6.49 1c.28 0 .52-.1.71-.29.19-.19.29-.43.29-.71s-.1-.52-.29-.71c-.19-.19-.43-.29-.71-.29s-.52.1-.71.29c-.19.19-.29.43-.29.71s.1.52.29.71c.19.19.43.29.71.29ZM9.99 18c2.03 0 3.83-.7 5.41-2.1 1.57-1.4 2.43-3.18 2.58-5.34-.83-.37-1.49-.87-1.96-1.5a5.45 5.45 0 0 1-.96-2.12 5.668 5.668 0 0 1-3.3-1.65 5.95 5.95 0 0 1-1.7-3.3c-1.33-.03-2.5.21-3.51.72-1.01.52-1.85 1.18-2.52 1.99-.67.81-1.18 1.69-1.52 2.63C2.17 8.28 2 9.17 2 10c0 2.21.78 4.1 2.33 5.66 1.56 1.56 3.44 2.33 5.66 2.33Z"/></svg><span><?= $escape($copy['Data consent']); ?></span><svg class="homepageNavChevron" viewBox="0 0 12 7.4" aria-hidden="true" focusable="false"><path d="M10.6 0L6 4.6 1.4 0 0 1.4l6 6 6-6L10.6 0z"/></svg></button>
            </nav>
        </div>
    </div>

    <div class="homepageDialog" id="homepageLanguageDialog" role="dialog" aria-modal="true" aria-labelledby="homepageLanguageHeading" hidden>
        <div class="homepageDialogPanel">
            <button class="homepageDialogClose" type="button" data-homepage-close aria-label="<?= $escape($copy['Close window']); ?>">×</button>
            <h2 id="homepageLanguageHeading"><?= $escape($copy['Language selection']); ?></h2>
            <label class="homepageLanguageSearchLabel" for="homepageLanguageSearch"><?= $escape($copy['Search for a language or country']); ?></label>
            <input id="homepageLanguageSearch" class="homepageLanguageSearch" type="search" placeholder="" data-homepage-language-search>
            <p class="homepageNoLanguageMatch" data-homepage-language-empty hidden><?= $escape($copy['No language matches your search. Are you sure you typed it correctly?']); ?></p>
            <div class="homepageLanguageGroups">
                <div class="homepageLanguageOptions languages recommended" data-homepage-language-recommended>
                    <?php foreach ($languageOptions as $language): ?>
                        <label class="homepageLanguageOption radio language"
                            data-homepage-language-recommended-option
                            data-homepage-language-search="<?= $escape($language['search']); ?>"
                            data-homepage-language-route="<?= $escape($language['route']); ?>"<?= $language['selected'] ? '' : ' hidden'; ?>>
                            <input class="homepageLanguageOptionInput" type="radio" name="recommended" value="<?= $escape($language['id']); ?>" data-homepage-language-select data-homepage-language-href="<?= $escape($language['href']); ?>"<?= $language['selected'] ? ' checked' : ''; ?>>
                            <span class="homepageLanguageRadio" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M10,0A10,10,0,1,0,20,10,10,10,0,0,0,10,0Z"></path><circle cx="10" cy="10" r="5"></circle></svg></span>
                            <img class="homepageLanguageOptionFlag" src="<?= $escape($flagAsset($language['flag'])); ?>" alt="" aria-hidden="true">
                            <span class="homepageLanguageOptionText"><?= $escape($language['language']); ?> <span class="addition">(<?= $escape($language['country']); ?>)</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="homepageLanguageOptions languages others" data-homepage-language-others>
                    <?php foreach ($languageOptions as $language): ?>
                        <label class="homepageLanguageOption radio language"
                            data-homepage-language-option
                            data-homepage-language-search="<?= $escape($language['search']); ?>"
                            data-homepage-language-route="<?= $escape($language['route']); ?>">
                            <input class="homepageLanguageOptionInput" type="radio" name="others" value="<?= $escape($language['id']); ?>" data-homepage-language-select data-homepage-language-href="<?= $escape($language['href']); ?>"<?= $language['selected'] ? ' checked' : ''; ?>>
                            <span class="homepageLanguageRadio" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M10,0A10,10,0,1,0,20,10,10,10,0,0,0,10,0Z"></path><circle cx="10" cy="10" r="5"></circle></svg></span>
                            <img class="homepageLanguageOptionFlag" src="<?= $escape($flagAsset($language['flag'])); ?>" alt="" aria-hidden="true">
                            <span class="homepageLanguageOptionText"><?= $escape($language['language']); ?> <span class="addition">(<?= $escape($language['country']); ?>)</span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="homepageDialog homepageConsentDialog" id="homepageConsentDialog" role="dialog" aria-modal="true" aria-labelledby="homepageConsentHeading" hidden>
        <div class="homepageDialogPanel">
            <button class="homepageDialogClose" type="button" data-homepage-close aria-label="<?= $escape($copy['Close window']); ?>">×</button>
            <h2 id="homepageConsentHeading"><?= $escape($copy['Data consent']); ?></h2>
            <div class="homepageConsentActions">
                <button class="homepageConsentButton" type="button" data-homepage-consent-accept><?= $escape($copy['Accept']); ?></button>
                <button class="homepageConsentButton homepageConsentButtonSecondary" type="button" data-homepage-close><?= $escape($copy['Cancel']); ?></button>
            </div>
        </div>
    </div>

    <?php if ($isAuthenticated): ?>
        <div class="homepageDialog homepageAccountDialog" id="homepageAccountDialog" role="dialog" aria-modal="true" aria-label="<?= $escape($playerName); ?>" hidden>
            <div class="homepageDialogPanel">
                <button class="homepageDialogClose" type="button" data-homepage-close aria-label="<?= $escape($copy['Close window']); ?>">×</button>
                <a href="<?= $escape($accountUrl); ?>"><?= $escape($copy['Account settings']); ?></a>
                <a href="/dorf1.php"><?= $escape($copy['To lobby']); ?></a>
                <a href="<?= $escape($logoutUrl); ?>" rel="nofollow"><?= $escape($copy['Logout']); ?></a>
            </div>
        </div>
    <?php endif; ?>
    <?= $registrationDialog ?>
</main>

<script>
(() => {
    const root = document.querySelector('.publicHomepage');
    if (!root) {
        return;
    }

    // Like the reference's SVG label, a long Play now label shrinks to fit the button face instead of wrapping.
    const ctaLabel = root.querySelector('.homepageLobbyCta span');
    const fitCtaLabel = () => {
        if (!ctaLabel) {
            return;
        }
        ctaLabel.style.fontSize = '';
        const style = window.getComputedStyle(ctaLabel);
        const available = ctaLabel.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        const range = document.createRange();
        range.selectNodeContents(ctaLabel);
        const used = range.getBoundingClientRect().width;
        if (used > available && available > 0) {
            ctaLabel.style.fontSize = (parseFloat(style.fontSize) * available / used) + 'px';
        }
    };
    fitCtaLabel();
    window.addEventListener('resize', fitCtaLabel);
    window.addEventListener('load', fitCtaLabel);
    document.fonts?.addEventListener('loadingdone', fitCtaLabel);
    document.fonts?.ready.then(fitCtaLabel);

    let activeDialog = null;
    let activeDialogTrigger = null;
    const closeDialogs = (restoreFocus = true) => {
        root.querySelectorAll('[data-homepage-toggle]').forEach((control) => {
            control.setAttribute('aria-expanded', 'false');
        });
        root.querySelectorAll('.homepageDialog').forEach((dialog) => {
            dialog.hidden = true;
        });
        if (['#registration', '#calendarGameworldDetails'].includes(window.location.hash)) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
        const trigger = activeDialogTrigger;
        activeDialog = null;
        activeDialogTrigger = null;
        if (restoreFocus && trigger && document.contains(trigger)) {
            trigger.focus();
        }
    };

    root.querySelectorAll('[data-homepage-toggle]').forEach((control) => {
        control.addEventListener('click', () => {
            const dialog = root.querySelector(`#${control.dataset.homepageToggle}`);
            if (!dialog) {
                return;
            }
            const opensDialog = dialog.hidden;
            closeDialogs(false);
            if (opensDialog) {
                dialog.hidden = false;
                control.setAttribute('aria-expanded', 'true');
                activeDialog = dialog;
                activeDialogTrigger = control;
                (dialog.querySelector('input:not([type="radio"]):not([type="hidden"]):not([disabled])') ?? dialog.querySelector('button, a'))?.focus();
            }
        });
    });
    root.querySelectorAll('[data-homepage-href]').forEach((control) => {
        control.addEventListener('click', () => {
            window.location.assign(control.dataset.homepageHref);
        });
    });
    const calendarDetails = root.querySelector('#homepageCalendarDetailsDialog');
    if (calendarDetails && window.location.hash === '#calendarGameworldDetails') {
        calendarDetails.hidden = false;
        activeDialog = calendarDetails;
        calendarDetails.querySelector('button, a')?.focus();
    }
    const registration = root.querySelector('#homepageRegistrationDialog');
    if (registration) {
        const syncRegistration = () => {
            if (window.location.hash !== '#registration' || !registration.hidden) {
                return;
            }
            closeDialogs(false);
            registration.hidden = false;
            activeDialog = registration;
            (registration.querySelector('input:not([disabled]):not([type="hidden"])') ?? registration.querySelector('button:not([disabled]), a'))?.focus();
        };
        window.addEventListener('hashchange', syncRegistration);
        if (registration.hidden) {
            syncRegistration();
        } else {
            activeDialog = registration;
        }
    }
    root.querySelectorAll('[data-homepage-close]').forEach((control) => {
        control.addEventListener('click', closeDialogs);
    });
    root.querySelectorAll('.homepageDialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialogs();
            }
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeDialog) {
            closeDialogs();
            return;
        }
        if (event.key === 'Tab' && activeDialog) {
            // Only what a keyboard can reach now: hidden steps, filtered-out options and
            // disabled controls are skipped, and a radio group counts once (its checked
            // member, else its first), since Tab visits one radio per group.
            const reachable = Array.from(activeDialog.querySelectorAll(
                'a[href], button, input:not([type="hidden"]), select, textarea, [tabindex]',
            )).filter((element) => !element.disabled
                && element.tabIndex >= 0
                && element.getClientRects().length > 0
                && getComputedStyle(element).visibility !== 'hidden');
            const radioGroups = new Map();
            const focusable = reachable.filter((element) => {
                if (element.type !== 'radio' || !element.name) {
                    return true;
                }
                const members = radioGroups.get(element.name) ?? reachable.filter(
                    (other) => other.type === 'radio' && other.name === element.name,
                );
                radioGroups.set(element.name, members);

                return element === (members.find((other) => other.checked) ?? members[0]);
            });
            if (!focusable.length) {
                event.preventDefault();
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (!activeDialog.contains(document.activeElement)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    const languageSearch = root.querySelector('[data-homepage-language-search]');
    const languageEmpty = root.querySelector('[data-homepage-language-empty]');
    root.querySelectorAll('[data-homepage-language-select]').forEach((input) => {
        input.addEventListener('change', () => {
            if (!input.checked) {
                return;
            }
            const href = input.dataset.homepageLanguageHref;
            if (href && href.startsWith('/homepage?homepageLocale=')) {
                window.location.assign(href);
            }
        });
    });
    if (languageSearch && languageEmpty) {
        languageSearch.addEventListener('input', () => {
            const query = languageSearch.value.trim().toLocaleLowerCase();
            let hasMatch = false;
            root.querySelectorAll('[data-homepage-language-recommended-option]').forEach((option) => {
                const matches = (option.dataset.homepageLanguageSearch || '')
                    .toLocaleLowerCase()
                    .includes(query);
                option.hidden = query === ''
                    ? !option.querySelector('[data-homepage-language-select]:checked')
                    : !matches;
                hasMatch = hasMatch || matches;
            });
            languageEmpty.hidden = query === '' || hasMatch;
        });
    }

    root.querySelectorAll('[data-homepage-consent-accept]').forEach((control) => {
        control.addEventListener('click', () => {
            document.cookie = 'homepage_data_consent=accepted; Path=/; Max-Age=31536000; SameSite=Lax';
            closeDialogs();
        });
    });

    const stage = root.querySelector('[data-homepage-stage]');
    const name = root.querySelector('[data-homepage-tribe-name]');
    const description = root.querySelector('[data-homepage-tribe-description]');
    const tribes = <?= $tribesJson; ?>;
    if (stage && name && description && tribes.length) {
        stage.querySelectorAll('[data-homepage-tribe-step]').forEach((control) => {
            control.addEventListener('click', () => {
                const current = tribes.findIndex(({key}) => key === stage.dataset.tribe);
                const step = Number(control.dataset.homepageTribeStep);
                const next = tribes[(current + step + tribes.length) % tribes.length];
                stage.dataset.tribe = next.key;
                name.textContent = next.name;
                description.textContent = next.description;
            });
        });
    }

    const locale = <?= json_encode($publicIntlLocale, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const dateFormat = new Intl.DateTimeFormat(locale, {
        timeZone: 'Europe/London',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
    const timeFormat = new Intl.DateTimeFormat(locale, {
        timeZone: 'Europe/London',
        hour: '2-digit',
        minute: '2-digit',
    });
    const shortDateFormat = new Intl.DateTimeFormat(locale, {
        timeZone: 'Europe/London',
        day: '2-digit',
        month: '2-digit',
    });
    root.querySelectorAll('[data-homepage-date]').forEach((element) => {
        const date = new Date(Number(element.dataset.homepageTimestamp) * 1000);
        element.textContent = `${dateFormat.format(date)} | ${timeFormat.format(date)}`;
    });
    const utc = (options) => new Intl.DateTimeFormat(locale, {timeZone: 'UTC', ...options});
    const formatters = {
        'month-year': utc({month: 'long', year: 'numeric'}),
        'weekday': utc({weekday: 'short'}),
        'day': utc({day: '2-digit'}),
        'time': utc({hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}),
        'short-date': utc({day: '2-digit', month: '2-digit'}),
        'date': utc({day: '2-digit', month: '2-digit', year: 'numeric'}),
    };
    root.querySelectorAll('[data-homepage-fmt]').forEach((element) => {
        const date = new Date(Number(element.dataset.homepageTimestamp) * 1000);
        const kind = element.dataset.homepageFmt;
        element.textContent = kind === 'date-time'
            ? `${formatters.date.format(date)}, ${formatters.time.format(date)}`
            : formatters[kind].format(date);
    });
    root.querySelectorAll('[data-homepage-date-split]').forEach((element) => {
        const date = new Date(Number(element.dataset.homepageTimestamp) * 1000);
        const day = document.createElement('span');
        day.textContent = dateFormat.format(date);
        const time = document.createElement('span');
        time.textContent = timeFormat.format(date);
        element.replaceChildren(day, document.createTextNode(' | '), time);
    });
    root.querySelectorAll('[data-homepage-short-date]').forEach((element) => {
        const date = new Date(Number(element.dataset.homepageTimestamp) * 1000);
        element.textContent = shortDateFormat.format(date);
    });
})();
</script>
