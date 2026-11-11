<?php

use Core\Session;
use Game\AdminUi;
use Game\LegalPages;

/**
 * Footer pages: Markdown for Game rules, Terms, Imprint and Privacy policy
 * (each optionally replaced by an https URL) and the source-repository link.
 */
class LegalPagesCtrl
{
    private const TITLES = [
        LegalPages::RULES => 'Game rules',
        LegalPages::TERMS => 'Terms',
        LegalPages::IMPRINT => 'Imprint',
        LegalPages::PRIVACY => 'Privacy policy',
        LegalPages::SOURCE => 'Source repository',
    ];

    /** @var array<string,string> */
    private array $errors = [];

    private string $saved = '';

    public function __construct()
    {
        if (Core\Helper\WebService::isPost() && Session::validateChecker()) {
            $this->handleSave();
        }
        $body = '';
        if ($this->saved !== '') {
            $body .= AdminUi::notice($this->saved . ' saved.', 'success');
        }
        foreach (array_keys(self::TITLES) as $key) {
            $body .= $this->form($key);
        }
        Dispatcher::getInstance()->appendContent(AdminUi::page(
            'Legal pages',
            'Footer pages and the source-repository link shown in the game and public shell.',
            $body
        ));
    }

    private function handleSave(): void
    {
        $key = is_string($_POST['page'] ?? null) ? $_POST['page'] : '';
        if (!isset(self::TITLES[$key])) {
            return;
        }
        $isPage = LegalPages::isPage($key);
        $body = $isPage && is_string($_POST['body'] ?? null) ? str_replace("\r\n", "\n", trim($_POST['body'])) : '';
        $url = is_string($_POST['url'] ?? null) ? trim($_POST['url']) : '';
        if (strlen($body) > LegalPages::MAX_BODY_LENGTH || str_contains($body, "\0")) {
            $this->errors[$key] = 'The text is too long (up to ' . LegalPages::MAX_BODY_LENGTH . ' characters).';
        } elseif (!LegalPages::isValidUrl($url)) {
            $this->errors[$key] = 'Enter an https:// URL (up to ' . \Game\DiscordLink::MAX_LENGTH . ' characters) or leave it blank.';
        } else {
            LegalPages::save($key, $body, $url);
            AdminLog::getInstance()->addLog('Changed the ' . strtolower(self::TITLES[$key]) . ' footer page.');
            $this->saved = self::TITLES[$key];
        }
    }

    private function form(string $key): string
    {
        $isPage = LegalPages::isPage($key);
        $posted = ($_POST['page'] ?? null) === $key && isset($this->errors[$key]);
        $body = $posted ? (string)($_POST['body'] ?? '') : LegalPages::body($key);
        $url = $posted ? (string)($_POST['url'] ?? '') : LegalPages::url($key);
        $html = '<form method="post" action="admin.php?action=legalPages">'
            . Session::getCheckerInput()
            . '<input type="hidden" name="page" value="' . AdminUi::esc($key) . '">';
        if ($isPage) {
            $html .= AdminUi::textarea('body', 'Markdown (headings #, lists, **bold**, *italic*, [text](link))', $body, [
                'id' => 'f_body_' . $key, 'rows' => 12, 'maxlength' => LegalPages::MAX_BODY_LENGTH,
            ], 'Served at /' . $key . '. Blank hides the page unless a URL is set.');
        }
        $html .= AdminUi::input('url', $isPage ? 'Replace with an external https URL (optional)' : 'Repository URL (shown on the version label)', $url, [
            'id' => 'f_url_' . $key, 'type' => 'url', 'maxlength' => \Game\DiscordLink::MAX_LENGTH,
            'placeholder' => 'https://example.com/',
        ]);
        if (isset($this->errors[$key])) {
            $html .= AdminUi::notice($this->errors[$key], 'error');
        }
        $html .= '<div class="adminActions">' . AdminUi::button('Save', 'green') . '</div></form>';

        return AdminUi::box(self::TITLES[$key], $html);
    }
}
