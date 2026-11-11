<?php

declare(strict_types=1);

namespace Controller\Ajax\report;

use Controller\Ajax\AjaxBase;
use Core\Session;
use Game\Gold\PurchaseNonce;
use Model\ReportsModel;

/**
 * The reference's two-step report actions: PUT signs the request (an X-Nonce header comes back),
 * POST with that X-Nonce executes it. The nonce is bound to the action, the player and the exact
 * scope that was signed, and the first execute attempt spends it.
 */
abstract class ReportBulkAction extends AjaxBase
{
    /** Route name, e.g. "report/delete". */
    abstract protected function action(): string;

    /**
     * The part of the request the nonce is bound to: the filter icons or the report ids.
     *
     * @return array<string, mixed>
     */
    abstract protected function scope(): array;

    /** @param array<string, mixed> $scope */
    abstract protected function perform(ReportsModel $reports, int $uid, array $scope): void;

    final public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            $this->error('You must be logged in.', 403);
            return;
        }
        if (!$this->session->checkSitterPermission(Session::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS)) {
            $this->error(T('Messages', 'You dont have permission here'), 403);
            return;
        }

        $uid = (int)$this->session->getPlayerId();
        $scope = $this->scope();
        $binding = ['action' => $this->action(), 'uid' => $uid, 'scope' => $scope];
        switch ($this->method()) {
            case 'PUT':
                header('X-Nonce: ' . PurchaseNonce::issue($this->action(), $binding));
                return;
            case 'POST':
                $submitted = $_SERVER['HTTP_X_NONCE'] ?? null;
                if (!PurchaseNonce::consume($this->action(), $binding, is_string($submitted) ? $submitted : null)) {
                    $this->error(T('map', 'please_resend_all_data'));
                    return;
                }
                $this->perform(new ReportsModel(), $uid, $scope);
                return;
            default:
                $this->error('Unsupported request.', 405);
        }
    }

    /**
     * Positive integers of a posted list, sorted and without duplicates.
     *
     * @return list<int>
     */
    protected function integerList(mixed $values): array
    {
        $numbers = [];
        foreach (is_array($values) ? $values : [] as $value) {
            $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($number !== false) {
                $numbers[$number] = $number;
            }
        }
        sort($numbers);

        return $numbers;
    }
}
