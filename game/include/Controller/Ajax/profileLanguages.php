<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Core\Helper\WebService;
use Game\ProfileLanguages as ProfileLanguageCatalog;

final class profileLanguages extends AjaxBase
{
    public function dispatch(): void
    {
        if (!WebService::isGet()) {
            $this->error('Unsupported profile language operation.', 405);
            return;
        }
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to edit profile languages.', 403);
            return;
        }

        $requested = $_GET['languages'] ?? [];
        $selected = ProfileLanguageCatalog::normalize($requested, $this->profileLocale());
        $this->response['html'] = ProfileLanguageCatalog::pickerHtml($selected);
        // game-client.js explicitly requests this search fragment as raw HTML.
        $this->response['_rawHtml'] = true;
    }

    private function profileLocale(): string
    {
        $language = $this->session->getLanguage();
        $available = \Core\Config::getProperty('settings', 'availableLanguages');
        return isset($available->{$language}->locale) ? (string)$available->{$language}->locale : 'en-US';
    }
}
