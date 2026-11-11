<?php

declare(strict_types=1);

namespace Controller\Ajax\report;

use Controller\Ajax\AjaxBase;
use Core\Session;
use Model\ReportsModel;

/**
 * PUT report/update-status {ids, status}: marks reports read or unread, archives them (Gold club)
 * or takes them out of the archive again.
 */
final class updateStatus extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in.', 403);
            return;
        }
        if ($this->method() !== 'PUT') {
            $this->error('Unsupported request.', 405);
            return;
        }
        if (!$this->session->checkSitterPermission(Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS)) {
            $this->error(T('Messages', 'You dont have permission here'), 403);
            return;
        }

        $body = $this->body();
        $status = is_string($body['status'] ?? null) ? $body['status'] : '';
        if (!in_array($status, ['read', 'unread', 'archived', 'unarchived'], true)) {
            $this->error('Invalid report status.');
            return;
        }
        if (in_array($status, ['archived', 'unarchived'], true) && !$this->session->hasGoldClub()) {
            $this->error(T('Reports', 'Archive||For this feature you need the Gold club activated'), 403);
            return;
        }

        $ids = [];
        foreach (is_array($body['ids'] ?? null) ? $body['ids'] : [] as $id) {
            $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                $ids[$id] = $id;
            }
        }
        (new ReportsModel())->setStatus((int)$this->session->getPlayerId(), array_values($ids), $status);
    }
}
