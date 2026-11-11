<?php

declare(strict_types=1);

namespace Controller\Ajax\report;

use Controller\Ajax\AjaxBase;
use Core\Helper\WebService;
use Game\NoticeHelper;
use Model\ReportsModel;

final class rights extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in to change report permissions.', 403);
            return;
        }

        $reportId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($reportId === false) {
            $this->error('The report is not available.');
            return;
        }

        $uid = (int)$this->session->getPlayerId();
        $model = new ReportsModel();
        $report = $model->getOwnedReport($reportId, $uid);
        if ($report === false || !$this->supportsPermissions($report)) {
            $this->error('The report is not available.', 404);
            return;
        }

        if (WebService::isPost()) {
            $description = (string)($_POST['description'] ?? '');
            $permissionMask = $this->permissionMask($_POST);
            if (!$model->updateOwnedReportPermissions($reportId, $uid, $permissionMask, $description)) {
                $this->error('The report permissions could not be saved.', 503);
                return;
            }

            $permissions = ['prem' => $permissionMask, 'desc' => $description];
        } else {
            $data = NoticeHelper::parseReport((int)$report['type'], $report['data']);
            $permissions = is_array($data) && isset($data['permissions']) && is_array($data['permissions'])
                ? $data['permissions']
                : ['prem' => 0, 'desc' => ''];
        }

        $mask = (int)($permissions['prem'] ?? 0);
        $this->response['right1'] = ($mask & RIGHT_OPPONENT_HIDE) !== 0;
        $this->response['right2'] = ($mask & RIGHT_MYSELF_HIDE) !== 0;
        $this->response['right3'] = ($mask & RIGHT_HIDE_OWN_TROOPS) !== 0;
        $this->response['right4'] = ($mask & RIGHT_HIDE_OPPONENT_TROOPS) !== 0;
        $this->response['description'] = (string)($permissions['desc'] ?? '');
    }

    /**
     * @param array<mixed> $payload
     */
    private function permissionMask(array $payload): int
    {
        $mask = 0;
        foreach ([
            'right1' => RIGHT_OPPONENT_HIDE,
            'right2' => RIGHT_MYSELF_HIDE,
            'right3' => RIGHT_HIDE_OWN_TROOPS,
            'right4' => RIGHT_HIDE_OPPONENT_TROOPS,
        ] as $field => $right) {
            if (in_array((string)($payload[$field] ?? ''), ['1', 'true', 'on'], true)) {
                $mask |= $right;
            }
        }

        return $mask;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function supportsPermissions(array $report): bool
    {
        return in_array((int)$report['type'], [
            NoticeHelper::TYPE_WON_AS_ATTACK_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_AS_ATTACK_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_ATTACKER,
            NoticeHelper::TYPE_WON_DEFENSE_WITHOUT_LOSSES,
            NoticeHelper::TYPE_WON_DEFENSE_WITH_LOSSES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_DEFENDER_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_SPY_WITHOUT_CASUALTIES,
            NoticeHelper::TYPE_WON_SPY_WITH_CASUALTIES,
            NoticeHelper::TYPE_LOST_AS_SPY,
            NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_REFUSED_ATTACKER_SPY,
            NoticeHelper::TYPE_WON_SPY_AS_DEFENDER_LETTING_ATTACKER_SPY,
        ], true);
    }
}
