<?php

declare(strict_types=1);

namespace Controller;

use Core\Config;
use Core\Session;
use Game\PublicHomepageLocale;
use Game\PublicRegistration;
use Game\RegistrationWorld;
use Game\Tribes;
use Game\WorldStatsSnapshot;
use Game\SocialAuth\OAuthFlowFactory;
use Game\SocialAuth\ProviderConfig;
use Game\SocialAuth\SocialFlash;
use Game\SocialAuth\SocialProviderList;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;
use function get_gpack_cdn_mainPage_url;

/**
 * Self-hosted public landing route for the in-game Homepage controls.
 *
 * The landing page uses the rendered public Travian localisation catalogue,
 * while every navigation target stays in this local world. Persian is the
 * deliberately narrow exception: the live `/ir` route resolves to the
 * international en-US page, so this public route reproduces that LTR fallback
 * without changing the rest of the local Persian game UI.
 */
final class HomepageCtrl extends OutOfGameCtrl
{
    private const DEFAULT_PUBLIC_OPTION_ID = 'en-EN';

    /**
     * Game locales whose public default is not the option with the same id.
     * `en-US` is the international English option (`en-EN`), and `fa-IR`
     * deliberately follows the live `/ir` redirect to International English
     * rather than inventing a Persian public catalogue. Every other game
     * locale that has a public option of its own opens that option.
     *
     * @var array<string, string>
     */
    private const GAME_LANGUAGE_DEFAULT_OPTIONS = [
        'en-US' => 'en-EN',
        'fa-IR' => 'en-EN',
    ];

    private const FEATURED_ARTICLE_ID = 'winter-time-the-effects-on-your-game';
    private const TOURNAMENT_ARTICLE_ID = 'tournament';

    /**
     * Wording of the local-only world stats page. The public catalogue has no
     * such page, so these few English labels are local; every label the catalogue
     * does have (Speed, Artefacts, Construction plans, Date, ...) is reused.
     *
     * @var array<string, string>
     */
    private const WORLD_LABELS = [
        'title' => 'World statistics',
        'description' => 'Age, players, alliances and World Wonder progress of this gameworld.',
        'Players' => 'Players',
        'Alliances' => 'Alliances',
        'Top players' => 'Top players',
        'Top alliances' => 'Top alliances',
        'Rank' => 'Rank',
        'Alliance' => 'Alliance',
        'Population' => 'Population',
        'World Wonder' => 'World Wonder',
        'Level' => 'Level',
        'Finished' => 'Finished',
    ];
    public function __construct()
    {
        parent::__construct();

        $isAuthenticated = $this->session->isValid();
        $requestedPage = $_GET['homepagePage'] ?? 'home';
        $page = $this->resolvePage(is_string($requestedPage) ? $requestedPage : 'home');
        $catalogue = $this->publicCatalogue();
        $languageCode = (string) $this->session->getLanguage();
        $requestedHomepageLocale = $_GET['homepageLocale'] ?? '';
        $requestedHomepageLocale = is_string($requestedHomepageLocale)
            ? $requestedHomepageLocale
            : '';
        $publicLanguage = $this->resolvePublicLanguage(
            $catalogue,
            $languageCode,
            $requestedHomepageLocale,
        );
        $publicDirection = $publicLanguage['direction'];
        // The articles follow the public language, as the reference's lobby news
        // API does; a language without a translation keeps the English article.
        $articles = $this->localizedArticles($this->articleCatalogue(), (string) $publicLanguage['lang']);
        $featuredArticle = $articles[self::FEATURED_ARTICLE_ID];
        $requestedArticleId = $_GET['homepageArticle'] ?? self::FEATURED_ARTICLE_ID;
        $requestedArticleId = is_string($requestedArticleId)
            ? $requestedArticleId
            : self::FEATURED_ARTICLE_ID;
        $article = $articles[$requestedArticleId] ?? $featuredArticle;
        $copy = $this->copy($catalogue['catalogues'][$publicLanguage['language']], $catalogue['source']['keys']);
        $publicIntlLocale = is_string($publicLanguage['intlAlias'] ?? null)
            ? $publicLanguage['intlAlias']
            : $publicLanguage['locale'];
        // Public route documents use their regional option locale, except the
        // reference International route, whose rendered document stays en-US.
        $publicDocumentLanguage = $publicLanguage['id'] === self::DEFAULT_PUBLIC_OPTION_ID
            ? $publicLanguage['language']
            : $publicLanguage['locale'];

        // The reference Tournament control is a permanent redirect to its
        // dated news article. Retain the temporary public locale while
        // following that same local, root-absolute destination.
        if ($page === 'tournament') {
            $this->redirect(
                $articles[self::TOURNAMENT_ARTICLE_ID]['path']
                . '?homepageLocale=' . rawurlencode($publicLanguage['id']),
                301,
            );
        }

        $registrationDialog = '';
        if (!$isAuthenticated) {
            $registration = PublicRegistration::handle($copy);
            if ($registration['redirect'] !== null) {
                $this->redirect($registration['redirect']);
            }
            $registrationDialog = PHPBatchView::render('system/registrationDialog', $registration + [
                'registerAction' => '/homepage?homepageLocale=' . rawurlencode($publicLanguage['id']) . '#registration',
                'loginUrl' => PublicHomepageLocale::append('/', $publicLanguage['id']),
                'closeLabel' => $copy['Close window'],
                'copy' => $copy,
                // Only fully configured providers; an empty list renders no
                // social row at all. The error is the one-shot SocialFlash
                // set by the OAuth routes (existing translation keys only).
                'socialProviders' => $registration['social'] ? [] : SocialProviderList::build(
                    ProviderConfig::fromEnvironment(),
                    OAuthFlowFactory::baseUrl(),
                    $publicLanguage['id'],
                    PublicRegistration::referralCode(),
                ),
                'socialError' => $this->socialErrorMessage(),
                'referral' => PublicRegistration::referralCode(),
            ]);
        }

        $this->view = new OutOfGameView();
        $this->view->vars['bodyCssClass'] = 'localHomepage publicHomepageShell';
        $this->view->vars['contentCssClass'] = 'homepageContent';
        $this->view->vars['containerCssClass'] = 'homepageFullBleed';
        $this->view->vars['showGuestNavBar'] = false;
        // The public reference owns its responsive viewport. Do not let the
        // in-game MobileOptimizations runtime replace it with its 620px shell.
        $this->view->vars['useGameMobileOptimizations'] = false;
        $this->view->vars['viewportContent'] = 'width=device-width, initial-scale=1';
        $titleKey = match ($page) {
            'news', 'article' => 'newsTitle',
            'calendar' => 'calendarTitle',
            default => 'homeTitle',
        };
        $descriptionKey = $page === 'home' ? 'homeDescription' : ($page === 'calendar' ? 'calendarDescription' : 'newsDescription');
        // A dated article is titled and described by the article itself.
        $this->view->vars['documentTitle'] = $page === 'article' ? $article['title'] : $copy[$titleKey];
        $this->view->vars['metaDescription'] = $page === 'article' ? $article['preview'] : $copy[$descriptionKey];
        if ($page === 'world') {
            $this->view->vars['documentTitle'] = self::WORLD_LABELS['title'];
            $this->view->vars['metaDescription'] = self::WORLD_LABELS['description'];
        }
        $this->view->vars['documentLanguage'] = $publicDocumentLanguage;
        $this->view->vars['layoutDirection'] = strtoupper($publicDirection);
        // Footer labels come from the selected public source catalogue;
        // Discord is the invariant product name, shown only when configured.
        $this->view->vars['footerLabels'] = [
            'home' => $copy['Home'],
            'discord' => 'Discord',
            'news' => $copy['News & Updates'],
            'support' => $copy['Support'],
            'rules' => $copy['Game Rules'],
            'terms' => $copy['Terms & Conditions'],
            'imprint' => $copy['Imprint'],
            'privacy' => $copy['Privacy Policy'],
        ];
        $this->view->vars['publicHomepageLocale'] = $publicLanguage['id'];
        // Keep the temporary public choice when the local-shell Home footer
        // or shared wordmark is followed. This remains a local route and
        // deliberately does not persist or mutate the player's game language.
        $homepageUrl = '/homepage?homepageLocale=' . rawurlencode($publicLanguage['id']);
        $loginUrl = PublicHomepageLocale::append('/', $publicLanguage['id']);
        $registrationUrl = PublicHomepageLocale::registrationUrl($publicLanguage['id']);
        $supportUrl = PublicHomepageLocale::append('/support/ticket', $publicLanguage['id']);
        $this->view->vars['homepageUrl'] = $homepageUrl;
        $this->view->vars['footerLinks'] = [
            'home' => $homepageUrl,
            'support' => $supportUrl,
        ];
        $this->view->vars['content'] = PHPBatchView::render('system/homepage', [
            'copy' => $copy,
            'page' => $page,
            'publicDirection' => $publicDirection,
            'publicIntlLocale' => $publicIntlLocale,
            // Public navigation retains this transient route choice without
            // consulting or changing the authenticated game locale.
            'publicLanguageOptionId' => $publicLanguage['id'],
            'publicLanguageName' => $publicLanguage['langNative'],
            'publicLanguageFlag' => $publicLanguage['flag'],
            'languageOptions' => $this->languageOptions(
                $catalogue['options'],
                $publicLanguage['id'],
            ),
            'isAuthenticated' => $isAuthenticated,
            'playerName' => $isAuthenticated ? (string) $this->session->getName() : '',
            'entryUrl' => $isAuthenticated ? '/dorf1.php' : $registrationUrl,
            'loginUrl' => $loginUrl,
            'registrationUrl' => $registrationUrl,
            'registrationDialog' => $isAuthenticated ? '' : $registrationDialog,
            'accountUrl' => '/options/account',
            'logoutUrl' => Session::logoutUrl(),
            'tribes' => [
                [
                    'key' => 'romans',
                    'name' => $copy['Romans'],
                    'description' => $copy['RomeDescription'],
                ],
                [
                    'key' => 'teutons',
                    'name' => $copy['Teutons'],
                    'description' => $copy['TeutonsDescription'],
                ],
                [
                    'key' => 'gauls',
                    'name' => $copy['Gauls'],
                    'description' => $copy['GaulsDescription'],
                ],
            ],
            'news' => $featuredArticle,
            'articles' => $articles,
            'article' => $article,
            'gameworld' => $this->localGameworld(),
            'worldStats' => $page === 'world' ? WorldStatsSnapshot::get() : null,
            'worldLabels' => self::WORLD_LABELS,
        ]);
        // The public start page has its own reference-shaped header. Do not
        // render the legacy in-game guest sidebar behind it.
        $this->view->renderLoginBox = false;
    }

    /**
     * This local world as the public calendar entry, or null once it is no
     * longer upcoming or running today (the calendar then shows its empty state).
     *
     * @return ?array{name:string,speed:int,startsAt:int,artifactsAt:int,plansAt:int,tribes:list<array{slug:string,name:string}>}
     */
    private function localGameworld(): ?array
    {
        $world = RegistrationWorld::summary();
        // Listed while players can still join (upcoming or running with open
        // registration), like the reference calendar's recently started worlds.
        if (!in_array($world['status'], ['upcoming', 'open'], true)) {
            return null;
        }
        $timers = Config::getInstance()->timers;
        $tribes = [];
        foreach (Tribes::playable() as $tribe) {
            $tribes[] = ['slug' => Tribes::slug($tribe), 'name' => Tribes::NAMES[$tribe]];
        }

        return [
            'name' => $world['title'],
            'speed' => $world['speed'],
            'startsAt' => $world['startTime'],
            'artifactsAt' => (int) $timers->ArtifactsReleaseTime,
            'plansAt' => (int) $timers->wwPlansReleaseTime,
            'tribes' => $tribes,
        ];
    }

    /** One-shot message for a failed social sign-in, using existing canonical translation keys. */
    private function socialErrorMessage(): string
    {
        $key = SocialFlash::fromGlobals()->pullTranslationKey();

        return $key === null ? '' : (string) T($key[0], $key[1]);
    }

    private function resolvePage(string $page): string
    {
        return in_array($page, ['home', 'news', 'article', 'calendar', 'tournament', 'world'], true)
            ? $page
            : 'home';
    }

    /**
     * @param array<string, array<string, mixed>> $articles
     * @return array<string, array<string, mixed>>
     */
    private function localizedArticles(array $articles, string $lang): array
    {
        static $overrides;
        if (!is_array($overrides)) {
            $overrides = require __DIR__ . '/../resources/HomepagePublicArticleLocales.php';
            if (!is_array($overrides)) {
                throw new \RuntimeException('Homepage public article locale snapshot is invalid.');
            }
        }
        foreach ($articles as $key => $article) {
            $override = $overrides[$key][$lang] ?? null;
            if (is_array($override)) {
                $articles[$key]['preview'] = $override['preview'];
                $articles[$key]['body'] = $override['body'];
            }
        }

        return $articles;
    }

    /**
     * @return array<string, array{
     *   id:string,
     *   path:string,
     *   title:string,
     *   preview:string,
     *   publishedAt:int,
     *   excerptAsset:string,
     *   bannerAsset:string,
     *   body:list<array<string, mixed>>
     * }>
     */
    private function articleCatalogue(): array
    {
        static $catalogue;
        if (is_array($catalogue)) {
            return $catalogue;
        }

        $catalogue = require __DIR__ . '/../resources/HomepagePublicArticleCatalog.php';
        if (!is_array($catalogue)
            || !isset($catalogue[self::FEATURED_ARTICLE_ID], $catalogue[self::TOURNAMENT_ARTICLE_ID])) {
            throw new \RuntimeException('Homepage public article snapshot is invalid.');
        }

        foreach ($catalogue as $article) {
            if (!is_array($article)) {
                throw new \RuntimeException('Homepage public article snapshot is invalid.');
            }
            foreach (['id', 'path', 'title', 'preview', 'excerptAsset', 'bannerAsset'] as $key) {
                if (!isset($article[$key]) || !is_string($article[$key]) || $article[$key] === '') {
                    throw new \RuntimeException('Homepage public article snapshot is invalid.');
                }
            }
            if (!str_starts_with($article['path'], '/homepage/news/')
                || !isset($article['publishedAt'])
                || !is_int($article['publishedAt'])
                || !isset($article['body'])
                || !is_array($article['body'])
                || $article['body'] === []) {
                throw new \RuntimeException('Homepage public article snapshot is invalid.');
            }
        }

        return $catalogue;
    }

    /**
     * Every source key of the self-hosted public snapshot, exactly as published,
     * plus short aliases for the three tribe descriptions.
     *
     * @param array<string, mixed> $catalog
     * @param list<string> $sourceKeys
     * @return array<string, string>
     */
    private function copy(array $catalog, array $sourceKeys): array
    {
        $copy = [];
        foreach ($sourceKeys as $key) {
            if (!isset($catalog[$key]) || !is_string($catalog[$key])) {
                throw new \RuntimeException('Homepage localisation key is missing.');
            }
            $copy[$key] = $catalog[$key];
        }

        return $copy + [
            'RomeDescription' => $copy['Disciplined legions, advanced engineering, and strategic brilliance define Rome. Lead your legionaries to expand your empire through military might and cultural dominance.'],
            'TeutonsDescription' => $copy['Fierce raiders, ruthless warriors, and unrelenting aggression define the Teutons. Lead your savage forces to pillage enemies and strike fear into their hearts with lightning-fast attacks.'],
            'GaulsDescription' => $copy['Swift defenders, cunning traps, and hidden crannies protect Gaul resources. Lead your tribe to outlast foes with resilience and guile.'],
        ];
    }

    /** @return array<string, mixed> */
    private function publicCatalogue(): array
    {
        static $catalogue;
        if (is_array($catalogue)) {
            return $catalogue;
        }

        $catalogue = require LOCALE_PATH . 'HomepagePublicCatalog.php';
        $options = $catalogue['options'] ?? null;
        $catalogues = $catalogue['catalogues'] ?? null;
        $sourceKeys = $catalogue['source']['keys'] ?? null;
        if (!is_array($options) || !is_array($catalogues) || !is_array($sourceKeys) || count($options) !== 48) {
            throw new \RuntimeException('Homepage public locale snapshot is invalid.');
        }

        $optionIds = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                throw new \RuntimeException('Homepage public locale option is invalid.');
            }
            foreach ([
                'id', 'region', 'route', 'flag', 'language', 'locale', 'lang',
                'langNative', 'langEnglish', 'countryNative', 'countryEnglish', 'direction', 'hrefLang',
            ] as $field) {
                if (!isset($option[$field]) || !is_string($option[$field]) || $option[$field] === '') {
                    throw new \RuntimeException('Homepage public locale option is invalid.');
                }
            }
            if (!in_array($option['direction'], ['ltr', 'rtl'], true)
                || isset($optionIds[$option['id']])
                || !isset($catalogues[$option['language']])
                || !is_array($catalogues[$option['language']])) {
                throw new \RuntimeException('Homepage public locale option is invalid.');
            }
            $optionIds[$option['id']] = true;
        }

        return $catalogue;
    }

    /**
     * @param array<string, mixed> $catalogue
     * @return array<string, string>
     */
    private function resolvePublicLanguage(
        array $catalogue,
        string $gameLanguage,
        string $requestedOptionId,
    ): array {
        $optionsById = [];
        foreach ($catalogue['options'] as $option) {
            $optionsById[$option['id']] = $option;
        }
        $defaultOptionId = self::GAME_LANGUAGE_DEFAULT_OPTIONS[$gameLanguage]
            ?? (isset($optionsById[$gameLanguage]) ? $gameLanguage : self::DEFAULT_PUBLIC_OPTION_ID);
        $selectedOptionId = isset($optionsById[$requestedOptionId])
            ? $requestedOptionId
            : $defaultOptionId;

        return $optionsById[$selectedOptionId];
    }

    /**
     * @param list<array<string, string>> $catalogueOptions
     * @return list<array{id: string, route: string, flag: string, english: string, language: string, country: string, search: string, href: string, selected: bool}>
     */
    private function languageOptions(array $catalogueOptions, string $selectedOptionId): array
    {
        $orderedOptions = [];
        foreach ($catalogueOptions as $index => $option) {
            $orderedOptions[] = ['index' => $index, 'option' => $option];
        }
        // The public component sorts by langEnglish and keeps source order for
        // equal labels. Retain that stable tie-break without altering the
        // catalogue's source identifiers or locale selection semantics.
        usort($orderedOptions, static function (array $left, array $right): int {
            $comparison = strcasecmp(
                $left['option']['langEnglish'],
                $right['option']['langEnglish'],
            );

            return $comparison !== 0
                ? $comparison
                : $left['index'] <=> $right['index'];
        });

        $options = [];
        foreach ($orderedOptions as ['option' => $option]) {
            $options[] = [
                'id' => $option['id'],
                'route' => $option['route'],
                'flag' => $option['flag'],
                'english' => $option['langEnglish'],
                'language' => $option['langNative'],
                'country' => $option['countryNative'],
                'search' => implode(' ', [
                    $option['route'],
                    $option['langEnglish'],
                    $option['langNative'],
                    $option['countryEnglish'],
                    $option['countryNative'],
                ]),
                'href' => '/homepage?homepageLocale=' . rawurlencode($option['id']),
                'selected' => $option['id'] === $selectedOptionId,
            ];
        }

        return $options;
    }
}
