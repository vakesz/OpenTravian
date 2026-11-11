<?php

use Core\Helper\TimezoneHelper;
use Model\PlayerAccusationModel;

/** Reports players filed from a profile ("Report player"). Marking one handled goes through the CSRF confirmation. */
class PlayerAccusationsCtrl
{
    public function __construct()
    {
        $model = new PlayerAccusationModel();
        if (is_scalar($_GET['handle'] ?? null)) {
            $model->markHandled((int) $_GET['handle']);
        }

        $rows = '';
        foreach ($model->list() as $report) {
            $rows .= '<tr>'
                . '<td>' . $report['id'] . '.</td>'
                . '<td><a href="admin.php?action=editPlayer&uid=' . $report['reporterId'] . '">' . \Game\AdminUi::esc($report['reporter']) . '</a></td>'
                . '<td><a href="admin.php?action=editPlayer&uid=' . $report['accusedId'] . '">' . \Game\AdminUi::esc($report['accused']) . '</a></td>'
                . '<td>' . \Game\AdminUi::esc(T('Profile', 'accusationReason.' . $report['reason'])) . '</td>'
                . '<td>' . \Game\AdminUi::esc($report['message']) . '</td>'
                . '<td>' . TimezoneHelper::autoDateString($report['time'], true) . '</td>'
                . '<td class="center">' . ($report['handledTime'] > 0
                    ? 'Handled ' . TimezoneHelper::autoDateString($report['handledTime'], true)
                    : '<a href="admin.php?action=playerAccusations&handle=' . $report['id'] . '">Mark handled</a>') . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="center adminMuted">No reports!</td></tr>';
        }
        Dispatcher::getInstance()->appendContent(
            Template::getInstance()->load(['content' => $rows], 'tpl/playerAccusations.tpl')->getAsString()
        );
    }
}
