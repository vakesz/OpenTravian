<?php

declare(strict_types=1);

namespace Controller\Ajax\report;

use Model\ReportsModel;

/** Deletes the selected reports. */
final class deleteBulk extends ReportBulkAction
{
    protected function action(): string
    {
        return 'report/delete-bulk';
    }

    protected function scope(): array
    {
        return ['ids' => $this->integerList($this->body()['ids'] ?? [])];
    }

    protected function perform(ReportsModel $reports, int $uid, array $scope): void
    {
        $reports->deleteReports($uid, $scope['ids']);
    }
}
