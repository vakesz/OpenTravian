<?php

use Core\Session;
use Game\AdminUi;
use Game\Webhook\WebhookConfig;
use Game\Webhook\WorldEvents;

/**
 * Operator page for the Discord world-event webhook. The webhook URL is write-only and lives in protected
 * deployment state (webhook.local.php), never in SQL. The webhook is off until it is switched on here and
 * announces only the ticked events, each as a short public message (see WebhookMessage).
 */
class WebhookCtrl
{
    private ?string $error = null;
    private ?string $saved = null;

    public function __construct()
    {
        if (\Core\Helper\WebService::isPost() && Session::validateChecker()) {
            $this->handlePost();
        }
        Dispatcher::getInstance()->appendContent(AdminUi::page(
            'Discord webhook',
            'Post selected world events to a Discord webhook. The URL stays in protected state, never SQL.',
            ($this->error !== null ? AdminUi::notice($this->error, 'error') : '')
            . ($this->saved !== null ? AdminUi::notice($this->saved, 'success') : '')
            . AdminUi::box('Settings', $this->form())
        ));
    }

    private function handlePost(): void
    {
        $config = WebhookConfig::fromEnvironment();
        if (isset($_POST['sendWebhookTest'])) {
            if (WorldEvents::default()->enqueueTest()) {
                AdminLog::getInstance()->addLog('Queued a Discord webhook test message.');
                $this->saved = 'Test message queued. The engine posts it within a few seconds.';
            } else {
                $this->error = 'Switch the webhook on and save a valid URL first.';
            }

            return;
        }
        $parsed = $config->fromSubmission($_POST);
        if ($parsed['errors'] !== []) {
            $this->error = implode(' ', $parsed['errors']);
        } elseif (!$config->save($parsed['config'])) {
            $this->error = 'Unable to save the protected webhook configuration.';
        } else {
            AdminLog::getInstance()->addLog('Changed the Discord webhook settings.');
            $this->saved = 'Webhook settings saved.';
        }
    }

    private function form(): string
    {
        $values = WebhookConfig::fromEnvironment()->formValues();
        $events = '';
        foreach (WebhookConfig::EVENTS as $id => $label) {
            $events .= AdminUi::checkbox('webhook_events[]', $label, in_array($id, $values['events'], true), $id);
        }

        return '<form method="post" action="admin.php?action=webhook" class="adminInline">' . Session::getCheckerInput()
            . '<fieldset><legend>Webhook' . ($values['configured'] ? ' (URL set ' . htmlspecialchars($values['suffix'], ENT_QUOTES, 'UTF-8') . ')' : ' (no URL)') . '</legend>'
            . AdminUi::checkbox('webhook_enabled', 'Post world events to Discord (off by default)', $values['enabled'])
            . AdminUi::secret('webhook_url', 'Webhook URL', ['placeholder' => $values['configured'] ? 'Set. Leave blank to keep.' : 'https://discord.com/api/webhooks/…'])
            . AdminUi::checkbox('webhook_remove', 'Remove the URL (turns the webhook off)')
            . '</fieldset><fieldset><legend>Events to announce</legend>' . $events . '</fieldset>'
            . '<fieldset><legend>World Wonder milestones</legend>'
            . AdminUi::input('webhook_milestones', 'Levels', $values['milestones'], [], 'Comma-separated World Wonder levels from 1 to 100.')
            . '</fieldset>' . AdminUi::button('Save', 'green', ['name' => 'saveWebhook', 'value' => '1'])
            . ' ' . AdminUi::button('Send test message', 'grey', ['name' => 'sendWebhookTest', 'value' => '1']) . '</form>'
            . '<small>Messages hold only public facts: the world name, a World Wonder level, or an alliance name and tag. '
            . 'They are queued and posted by the engine, at most one every five seconds. The URL is stored only in protected deployment state, never in SQL.</small>';
    }
}
