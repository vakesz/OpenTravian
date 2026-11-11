<?php

declare(strict_types=1);

namespace Controller;

use Game\LegalMarkdown;
use Game\LegalPages;
use Game\PublicHomepageLabels;
use Game\PublicHomepageLocale;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;

/** Serves one operator-written footer page (/rules, /terms, /imprint, /privacy). */
final class LegalPageCtrl extends OutOfGameCtrl
{
    /** Footer translation key of each page title (reference footer wording). */
    private const TITLE_KEYS = [
        LegalPages::RULES => 'Footer.Game rules',
        LegalPages::TERMS => 'Footer.Terms',
        LegalPages::IMPRINT => 'Footer.Imprint',
    ];

    /** Public homepage catalogue key of each page title. */
    private const PUBLIC_KEYS = [
        LegalPages::RULES => 'Game Rules',
        LegalPages::TERMS => 'Terms & Conditions',
        LegalPages::IMPRINT => 'Imprint',
        LegalPages::PRIVACY => 'Privacy Policy',
    ];

    public function __construct()
    {
        parent::__construct();
        $key = (string) ($_GET['legalPage'] ?? '');
        $title = $this->title($key);

        $this->view = new OutOfGameView();
        $this->configurePublicHomepageLocale();
        $this->view->vars['titleInHeader'] = $title;
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings support';
        $this->view->vars['contentCssClass'] = 'support';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        if (!$this->session->isValid()) {
            $this->view->vars['closeContentHref'] = $this->publicHomepageUrl('/homepage');
        }
        $this->view->vars['content'] = PHPBatchView::render('legal/page', [
            'title' => $title,
            'html' => LegalMarkdown::render(LegalPages::body($key)),
        ]);
    }

    private function title(string $key): string
    {
        // A signed-in player sees the in-game footer wording; a guest sees the
        // public homepage wording in the transient public locale. Privacy has
        // no in-game footer key, so it uses the public wording in the player's language.
        if ($this->session->isValid() && isset(self::TITLE_KEYS[$key])) {
            return (string) T('Global', self::TITLE_KEYS[$key]);
        }

        return PublicHomepageLabels::get(
            self::PUBLIC_KEYS[$key] ?? '',
            PublicHomepageLocale::fromRequest(),
            (string) $this->session->getLanguage(),
        );
    }
}
