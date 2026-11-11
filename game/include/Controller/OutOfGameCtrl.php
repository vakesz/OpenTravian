<?php

namespace Controller;

use Game\PublicHomepageLocale;

class OutOfGameCtrl extends AbstractCtrl
{
    /**
     * Pass a verified public-start-page locale through local guest routes.
     *
     * This is presentation state only. It deliberately does not call the
     * game-language/session APIs, so returning to the game keeps the player's
     * configured language unchanged.
     */
    protected function configurePublicHomepageLocale(): void
    {
        $locale = PublicHomepageLocale::fromRequest();
        if ($locale === null || !is_object($this->view) || !is_array($this->view->vars)) {
            return;
        }

        $homepageUrl = PublicHomepageLocale::append('/homepage', $locale);
        $supportUrl = PublicHomepageLocale::append('/support/ticket', $locale);
        $footerLinks = is_array($this->view->vars['footerLinks'] ?? null)
            ? $this->view->vars['footerLinks']
            : [];
        $footerLinks['home'] = $homepageUrl;
        $footerLinks['support'] = $supportUrl;

        $this->view->vars['homepageUrl'] = $homepageUrl;
        $this->view->vars['footerLinks'] = $footerLinks;
        $this->view->vars['publicHomepageLocale'] = $locale;
        $this->view->vars['publicHomepageLoginUrl'] = PublicHomepageLocale::append('/', $locale);
        $this->view->vars['publicHomepageRecoveryUrl'] = PublicHomepageLocale::append('/?forgotPassword=true', $locale);
        $this->view->vars['publicHomepageRegisterUrl'] = PublicHomepageLocale::registrationUrl($locale);
        $this->view->vars['publicHomepageSupportUrl'] = $supportUrl;
    }

    protected function publicHomepageUrl(string $path): string
    {
        return PublicHomepageLocale::append($path);
    }

    /**
     * @return mixed
     */
    public function render()
    {
        $output = '';
        $this->beforeRender();
        if ($this->view) {
            $output = $this->view->output();
        }
        $this->afterRender();

        return $output;
    }

    protected function beforeRender(): void
    {
    }

    protected function afterRender(): void
    {
    }
}
