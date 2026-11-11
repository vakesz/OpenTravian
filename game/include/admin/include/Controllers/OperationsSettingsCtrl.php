<?php

use Core\Session;
use Game\AdminUi;
use Game\OperationsSettings;

/**
 * Backups, engine catch-up and audit-log retention. A value that was never saved here comes from its
 * environment variable (the deployment default), so saving only pins what the operator chose.
 */
class OperationsSettingsCtrl
{
    private ?string $error = null;
    private ?string $saved = null;

    public function __construct()
    {
        if (\Core\Helper\WebService::isPost() && Session::validateChecker()) {
            $this->handlePost();
        }
        Dispatcher::getInstance()->appendContent(AdminUi::page(
            'Operations settings',
            'How often the world is backed up and how long backups and the audit log are kept, and how the engine catches up after it was away. The engine reads these settings on its next round.',
            ($this->error !== null ? AdminUi::notice($this->error, 'error') : '')
            . ($this->saved !== null ? AdminUi::notice($this->saved, 'success') : '')
            . $this->form()
        ));
    }

    private function handlePost(): void
    {
        $parsed = OperationsSettings::fromSubmission($_POST);
        if ($parsed['errors'] !== []) {
            $this->error = implode(' ', $parsed['errors']);
        } elseif (!OperationsSettings::save($parsed['values'])) {
            $this->error = 'Unable to write operations.local.json in the state directory.';
        } else {
            \AdminLog::getInstance()->addLog('Changed the operations settings.');
            $this->saved = 'Operations settings saved.';
        }
    }

    private function form(): string
    {
        $values = OperationsSettings::all();
        $field = static function (string $key) use ($values): string {
            $definition = OperationsSettings::FIELDS[$key];

            return AdminUi::input($key, $definition['label'], (string) $values[$key], ['type' => 'number', 'min' => $definition['min'], 'max' => $definition['max']],
                'Default ' . OperationsSettings::fromEnvironment($key) . '.');
        };

        return '<form method="post" action="admin.php?action=operationsSettings" class="adminInline">' . Session::getCheckerInput()
            . AdminUi::box('Backups', AdminUi::checkbox('backupsEnabled', OperationsSettings::FIELDS['backupsEnabled']['label'], $values['backupsEnabled'] === 1) . $field('backupKeepLast') . $field('backupKeepDaily'))
            . AdminUi::box('Audit log', $field('auditRetentionDays'))
            . AdminUi::advanced(AdminUi::box('Catch-up after downtime', $field('catchupAfter') . $field('catchupRate') . $field('catchupMax')))
            . AdminUi::button('Save settings', 'green', ['name' => 'saveOperations', 'value' => '1']) . '</form>';
    }
}
