<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\SessionVar;
use Core\Helper\Mailer;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\SmtpConfig;
use Game\SupportContact;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;

/**
 * Local support-ticket endpoint. The player-facing Support controls reach this
 * one form and queue a message for the world operator's configured recipient.
 */
class SupportTicketCtrl extends OutOfGameCtrl
{
    /** @var array<string,string> */
    private array $form = [];

    /** @var array<string,string> */
    private array $errors = [];

    private string $formError = '';

    /** @var array<string,string> */
    private const CATEGORIES = [
        'general_questions' => 'General questions',
        'cannot_log_in' => 'I cannot log in',
        'cannot_register' => 'I cannot register an account',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->view = new OutOfGameView();
        $this->configurePublicHomepageLocale();
        $this->view->vars['titleInHeader'] = T('Support', 'Support');
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings support';
        $this->view->vars['contentCssClass'] = 'support';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        if (!$this->session->isValid()) {
            // A guest arrives from the local public shell, not a village. Do
            // not send its Close control through /dorf1.php (and therefore to
            // the login route); retain the temporary public locale instead.
            $this->view->vars['closeContentHref'] = $this->publicHomepageUrl('/homepage');
        }

        $this->form = $this->defaults();
        if (WebService::isPost()) {
            $this->handleSubmission();
        }

        $showSuccess = SessionVar::getVariable('supportTicketQueued', false, 300) === true;
        if ($showSuccess) {
            SessionVar::timeoutNow('supportTicketQueued');
        }
        $this->view->vars['content'] = $showSuccess
            ? PHPBatchView::render('support/success')
            : PHPBatchView::render('support/support', $this->templateData());
    }

    /** @return array<string,string> */
    private function defaults(): array
    {
        $isPlayer = $this->session->isValid();

        return [
            'gameworld' => 'please_select',
            'username' => $isPlayer ? (string) $this->session->getName() : '',
            'email' => $isPlayer ? (string) $this->session->getEmail() : '',
            'type' => 'please_select',
            'message' => '',
        ];
    }

    private function handleSubmission(): void
    {
        foreach (array_keys($this->form) as $field) {
            $this->form[$field] = $this->submittedValue($field, $field === 'message' ? 10000 : 254);
        }

        if (!Session::validateChecker()) {
            $this->formError = T('Support', 'errors.Invalid request');
            return;
        }

        $worldOptions = $this->worldOptions();
        if (!in_array($this->form['gameworld'], array_merge(['i_do_not_know'], array_keys($worldOptions)), true)) {
            $this->errors['gameworld'] = T('Support', 'errors.please select');
        }
        if ($this->form['username'] === '') {
            $this->errors['username'] = T('Support', 'errors.This field is necessary');
        }
        if ($this->form['email'] === '') {
            $this->errors['email'] = T('Support', 'errors.This field is necessary');
        } elseif (!SupportContact::isValid($this->form['email'])) {
            $this->errors['email'] = T('Support', 'errors.Invalid email address');
        }
        if (!array_key_exists($this->form['type'], self::CATEGORIES)) {
            $this->errors['type'] = T('Support', 'errors.please select');
        }
        if ($this->form['message'] === '') {
            $this->errors['message'] = T('Support', 'errors.This field is necessary');
        } elseif (mb_strlen($this->form['message']) <= 20) {
            $this->errors['message'] = T('Support', 'errors.Entry is too short');
        }

        $recipient = SupportContact::email();
        if ($recipient === '' || !SmtpConfig::isConfigured()) {
            $this->formError = T('Support', 'errors.Delivery is not configured');
        }
        if ($this->errors !== [] || $this->formError !== '') {
            return;
        }
        if (!throttle_public_form('support', 5, 3600)) {
            $this->formError = T('Login', 'tooManyRequests');
            return;
        }

        if (Mailer::sendEmail($recipient, $this->mailSubject(), $this->mailHtml(), 0, $this->form['email']) < 1) {
            $this->formError = T('Support', 'errors.Could not queue request');
            return;
        }

        SessionVar::setVariable('supportTicketQueued', true);
        $this->redirect($this->publicHomepageUrl('/support/ticket'));
    }

    private function submittedValue(string $field, int $maxLength): string
    {
        $submitted = $_POST['support'] ?? [];
        if (!is_array($submitted) || !isset($submitted[$field]) || !is_string($submitted[$field])) {
            return '';
        }

        $value = strip_tags($submitted[$field]);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $value) ?? '';
        $value = preg_replace('/[ \t]+/', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, $maxLength);
    }

    /** @return array<string,string> */
    private function worldOptions(): array
    {
        $worldId = (string) getWorldId();

        return [$worldId => $worldId];
    }

    private function mailSubject(): string
    {
        return 'Support request: ' . getWorldId() . ' — ' . T('Support', self::CATEGORIES[$this->form['type']]);
    }

    private function mailHtml(): string
    {
        $rows = [
            T('Support', 'Game world') => $this->form['gameworld'],
            T('Support', 'Username') => $this->form['username'],
            T('Support', 'Email') => $this->form['email'],
            T('Support', 'Category') => T('Support', self::CATEGORIES[$this->form['type']]),
            T('Support', 'Message') => $this->form['message'],
            'Received' => TimezoneHelper::date('Y-m-d H:i'),
        ];
        $html = '<table>';
        foreach ($rows as $label => $value) {
            $html .= '<tr><th>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th><td>'
                . nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</td></tr>';
        }

        return $html . '</table>';
    }

    /** @return array<string,mixed> */
    private function templateData(): array
    {
        $categories = [];
        foreach (self::CATEGORIES as $value => $translationKey) {
            $categories[$value] = T('Support', $translationKey);
        }

        return [
            'form' => $this->form,
            'errors' => $this->errors,
            'formError' => $this->formError,
            'worldOptions' => $this->worldOptions(),
            'categories' => $categories,
            'deliveryConfigured' => SupportContact::email() !== '' && SmtpConfig::isConfigured(),
            'supportUrl' => $this->publicHomepageUrl('/support/ticket'),
        ];
    }
}
