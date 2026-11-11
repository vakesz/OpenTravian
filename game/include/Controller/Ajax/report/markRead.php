<?php

declare(strict_types=1);

namespace Controller\Ajax\report;

use Model\ReportsModel;

/** Marks every unarchived report of the filter's types as read. */
final class markRead extends ReportBulkAction
{
    protected function action(): string
    {
        return 'report/mark-read';
    }

    protected function scope(): array
    {
        return ['icons' => $this->integerList($this->body()['icons'] ?? [])];
    }

    protected function perform(ReportsModel $reports, int $uid, array $scope): void
    {
        $reports->markTypesAsRead($uid, $scope['icons']);
    }
}
