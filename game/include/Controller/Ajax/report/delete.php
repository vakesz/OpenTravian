<?php

declare(strict_types=1);

namespace Controller\Ajax\report;

use Model\ReportsModel;

/** Deletes every unarchived report of the filter's types. */
final class delete extends ReportBulkAction
{
    protected function action(): string
    {
        return 'report/delete';
    }

    protected function scope(): array
    {
        return ['icons' => $this->integerList($this->body()['icons'] ?? [])];
    }

    protected function perform(ReportsModel $reports, int $uid, array $scope): void
    {
        $reports->deleteTypes($uid, $scope['icons']);
    }
}
