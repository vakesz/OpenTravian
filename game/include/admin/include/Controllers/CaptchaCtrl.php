<?php

use Core\Session;
use Game\AdminUi;
use Game\Captcha\CaptchaConfig;

/**
 * Operator page for the optional activation CAPTCHA (reCAPTCHA v3). The keys are
 * write-only and live in protected deployment state (captcha.local.php), never
 * in SQL. Without keys the activation wizard shows no CAPTCHA and loads no script.
 */
class CaptchaCtrl
{
    private ?string $error = null;
    private ?string $saved = null;

    public function __construct()
    {
        if (\Core\Helper\WebService::isPost() && Session::validateChecker()) {
            $this->handlePost();
        }
        Dispatcher::getInstance()->appendContent(AdminUi::page(
            'Activation CAPTCHA',
            'Optional reCAPTCHA v3 for the activation wizard and /respawn. Leave empty to keep CAPTCHA off.',
            ($this->error !== null ? AdminUi::notice($this->error, 'error') : '')
            . ($this->saved !== null ? AdminUi::notice($this->saved, 'success') : '')
            . AdminUi::box('Keys', $this->form())
        ));
    }

    private function handlePost(): void
    {
        $config = CaptchaConfig::fromEnvironment();
        $parsed = $config->fromSubmission($_POST);
        if ($parsed['errors'] !== []) {
            $this->error = implode(' ', $parsed['errors']);
        } elseif ($parsed['keys'] === null && !$parsed['remove']) {
            $this->saved = 'Nothing to save.';
        } elseif (!$config->save($parsed['keys'])) {
            $this->error = 'Unable to save the protected CAPTCHA configuration.';
        } else {
            AdminLog::getInstance()->addLog($parsed['remove'] ? 'Removed the activation CAPTCHA keys.' : 'Changed the activation CAPTCHA keys.');
            $this->saved = $parsed['remove'] ? 'CAPTCHA keys removed. The wizard no longer asks for a CAPTCHA.' : 'CAPTCHA keys saved.';
        }
    }

    private function form(): string
    {
        $values = CaptchaConfig::fromEnvironment()->formValues();

        return '<form method="post" action="admin.php?action=captcha" class="adminInline">' . Session::getCheckerInput()
            . '<fieldset><legend>reCAPTCHA v3' . ($values['configured'] ? ' (configured)' : ' (not configured)') . '</legend>'
            . AdminUi::input('captcha_site_key', 'Site key', $values['site_key'], ['autocomplete' => 'off'])
            . AdminUi::secret('captcha_secret_key', 'Secret key', ['placeholder' => $values['configured'] ? 'Set. Leave blank to keep.' : ''])
            . AdminUi::checkbox('captcha_remove', 'Remove the keys (turns the CAPTCHA off)')
            . '</fieldset>' . AdminUi::button('Save', 'green', ['name' => 'saveCaptcha', 'value' => '1']) . '</form>'
            . '<small>The new-player activation wizard and /respawn ask for a score-based reCAPTCHA only while both keys are set. '
            . 'Keys are stored only in protected deployment state, never in SQL.</small>';
    }
}
