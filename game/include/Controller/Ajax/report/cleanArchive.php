<?php

declare(strict_types=1);

namespace Controller\Ajax\report;

use Model\ReportsModel;

/** Deletes every archived report. */
final class cleanArchive extends ReportBulkAction
{
    protected function action(): string
    {
        return 'report/clean-archive';
    }

    protected function scope(): array
    {
        return [];
    }

    protected function perform(ReportsModel $reports, int $uid, array $scope): void
    {
        $reports->cleanArchive($uid);
    }
}
