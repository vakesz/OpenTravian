<?php

use Core\Config;
use Core\Session;
use Game\AdminUi;
use Game\Admin\WorldRulesFile;
use Game\WorldOverrides;

/**
 * World rules chosen at install time (vacation days, alliance size, registration, seasons ...),
 * editable afterwards. They are stored in the generated connection file, so a change applies to
 * the web side on the next request and to the engine after it restarts.
 */
class WorldRulesCtrl
{
    private ?string $error = null;
    private ?string $saved = null;

    public function __construct()
    {
        if (\Core\Helper\WebService::isPost() && Session::validateChecker()) {
            $this->handlePost();
        }
        Dispatcher::getInstance()->appendContent(AdminUi::page(
            'World rules',
            'Rules set when the world was installed. A change is stored in the connection file in the state directory and applies to the website on the next request; restart the engine for the rules it applies itself.',
            ($this->error !== null ? AdminUi::notice($this->error, 'error') : '')
            . ($this->saved !== null ? AdminUi::notice($this->saved, 'success') : '')
            . $this->form()
        ));
    }

    private function handlePost(): void
    {
        $submitted = [];
        foreach (WorldOverrides::editable() as $key) {
            $type = WorldOverrides::DEFINITIONS[$key][1];
            $submitted[$key] = $type === 'bool' ? (($_POST[$key] ?? '') === '1' ? 1 : 0) : ($_POST[$key] ?? null);
        }
        $clean = WorldOverrides::normalize($submitted);
        if (count($clean) !== count($submitted)) {
            $bad = array_map(static fn (string $key): string => WorldOverrides::DEFINITIONS[$key][4], array_keys(array_diff_key($submitted, $clean)));
            $this->error = 'Not saved. Check: ' . implode(', ', $bad) . '.';
        } elseif (!WorldRulesFile::write(WorldRulesFile::path(), $clean)) {
            $this->error = 'Unable to write the connection file in the state directory.';
        } else {
            \AdminLog::getInstance()->addLog('Changed the world rules.');
            $this->saved = 'World rules saved. Restart the engine for the rules it applies itself.';
        }
    }

    private function form(): string
    {
        $config = Config::getInstance();
        $stored = WorldRulesFile::read(WorldRulesFile::path());
        $fields = '';
        foreach (WorldOverrides::editable() as $key) {
            [, $type, $min, $max, $label] = WorldOverrides::DEFINITIONS[$key];
            $value = $stored[$key] ?? WorldOverrides::read($config, $key);
            $fields .= $type === 'bool'
                ? AdminUi::checkbox($key, $label, (bool) $value)
                : AdminUi::input($key, $label, (string) $value, ['type' => 'number', 'min' => $min, 'max' => $max], 'From ' . $min . ' to ' . $max . '.');
        }

        return '<form method="post" action="admin.php?action=worldRules" class="adminInline">' . Session::getCheckerInput()
            . AdminUi::box('Rules', $fields)
            . AdminUi::button('Save world rules', 'green', ['name' => 'saveRules', 'value' => '1']) . '</form>';
    }
}
