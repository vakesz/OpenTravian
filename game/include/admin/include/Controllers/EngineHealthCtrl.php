<?php

use Core\Database\DB;
use Core\Jobs\QueueDepths;
use Core\Helper\TimezoneHelper;
use Core\Jobs\EngineHeartbeat;
use Core\Jobs\TransactionalTask;
use Core\Session;
use Game\Admin\AuditLog;
use Game\Admin\Preflight;
use Game\Admin\StateFiles;
use Game\Metrics\MetricsToken;
use Game\AdminUi;

/**
 * Engine health: the engine heartbeat, the work queues and the operator
 * preflight checks in one read-only page. Nothing here changes state.
 */
class EngineHealthCtrl
{
    private const QUEUE_LABELS = [
        'construction' => 'Overdue construction',
        'research' => 'Overdue research',
        'movement' => 'Overdue troop movements',
        'trade' => 'Overdue merchant trips',
        'quarantined' => 'Quarantined tasks',
        'mail' => 'Mail waiting to be sent',
    ];

    private const TONES = [Preflight::OK => 'success', Preflight::INFO => 'info', Preflight::WARN => 'warning', Preflight::FAIL => 'error'];

    /** The quarantined-task tables a player-facing job type is named after. */
    private const TASK_TYPES = [
        'building_upgrade' => 'Construction',
        'demolition' => 'Demolition',
        'movement' => 'Troop movement',
        'research' => 'Research',
        'send' => 'Merchant trip',
    ];

    private ?string $notice = null;
    private string $noticeTone = 'success';

    public function __construct()
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $this->requeue();
        }
        $now = time();
        $beat = EngineHeartbeat::read();
        $status = EngineHeartbeat::describe($beat, $now);

        $engine = [['Status', AdminUi::esc($status['text'])]];
        if ($beat !== null) {
            $engine[] = ['Process', (int) ($beat['pid'] ?? 0)];
            $started = (int) ($beat['started_at'] ?? 0);
            $engine[] = ['Started', $started > 0 ? AdminUi::esc(TimezoneHelper::autoDateString($started, true)) : 'unknown'];
            $engine[] = ['Maintenance pause requested', EngineHeartbeat::pauseRequested() ? 'yes' : 'no'];
        }
        $round = EngineHeartbeat::lastRound();
        $engine[] = ['Last automation round', $round === null
            ? '<span class="adminMuted">not recorded yet</span>'
            : AdminUi::esc($round['job']) . ' finished ' . AdminUi::esc(TimezoneHelper::autoDateString($round['ended_at'], true)) . ', took ' . $round['duration_ms'] . ' ms'];

        $queues = [];
        foreach (QueueDepths::read($now) as $queue => $count) {
            $queues[] = [self::QUEUE_LABELS[$queue], $count === null ? '<span class="adminMuted">unavailable</span>' : (string) $count];
        }

        $checks = '';
        foreach (Preflight::evaluate(Preflight::collect()) as $check) {
            $checks .= AdminUi::notice(strtoupper($check['level']) . ': ' . $check['message'], self::TONES[$check['level']]);
        }

        Dispatcher::getInstance()->appendContent(
            '<h1>Engine health</h1>' . ($this->notice !== null ? AdminUi::notice($this->notice, $this->noticeTone) : '') . '<p class="adminLead">The engine heartbeat, the work queues and the preflight checks. Reload the page to refresh it. The same checks run from <code>tools/preflight.php</code>.</p>'
            . AdminUi::box('Engine', $this->table($engine))
            . AdminUi::box('Queues', $this->table($queues) . '<p class="adminMuted">Overdue means more than ' . QueueDepths::OVERDUE_GRACE . ' seconds past the due time.</p>')
            . AdminUi::box('Quarantined tasks', $this->quarantineList())
            . AdminUi::box('Preflight', $checks)
            . AdminUi::advanced(AdminUi::box('State files', $this->stateFiles()) . AdminUi::box('Metrics', $this->metrics()))
        );
    }

    /** Puts one quarantined task back through TransactionalTask::requeue(), the same path tools/README.md documents. */
    private function requeue(): void
    {
        $table = is_string($_POST['table'] ?? null) ? $_POST['table'] : '';
        $id = is_string($_POST['id'] ?? null) && ctype_digit($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!isset(self::TASK_TYPES[$table]) || $id < 1) {
            $this->notice = 'That task could not be identified.';
            $this->noticeTone = 'error';

            return;
        }
        $target = 'table=' . $table . ' id=' . $id;
        try {
            $done = TransactionalTask::requeue($table, $id);
        } catch (\Throwable $exception) {
            AuditLog::record('requeueTask', $target, 'failed');
            $this->notice = 'The task could not be requeued: ' . $exception->getMessage();
            $this->noticeTone = 'error';

            return;
        }
        AuditLog::record('requeueTask', $target, $done ? 'requeued' : 'nothing quarantined');
        $this->notice = $done ? self::TASK_TYPES[$table] . ' task #' . $id . ' was requeued.' : 'Nothing is quarantined under that task any more.';
        $this->noticeTone = $done ? 'success' : 'warning';
    }

    private function quarantineList(): string
    {
        try {
            $result = DB::getInstance()->query(
                'SELECT task_table, task_id, attempts, last_error, quarantined_at FROM scheduled_task_failures
                 WHERE quarantined_at IS NOT NULL ORDER BY quarantined_at DESC, task_id DESC LIMIT 100'
            );
        } catch (\Throwable $exception) {
            $result = false;
        }
        if ($result === false) {
            return '<span class="adminMuted">The quarantine list is unavailable.</span>';
        }
        $rows = '';
        while ($row = $result->fetch_assoc()) {
            $type = self::TASK_TYPES[$row['task_table']] ?? $row['task_table'];
            $form = isset(self::TASK_TYPES[$row['task_table']])
                ? '<form method="post" action="admin.php?action=engineHealth" class="adminInline">'
                    . '<input type="hidden" name="table" value="' . AdminUi::esc($row['task_table']) . '">'
                    . '<input type="hidden" name="id" value="' . (int) $row['task_id'] . '">'
                    . Session::getCheckerInput() . AdminUi::button('Requeue', 'green') . '</form>'
                : '';
            $rows .= '<tr><td>' . AdminUi::esc($type) . '</td><td>' . (int) $row['task_id'] . '</td><td>' . (int) $row['attempts']
                . '</td><td>' . AdminUi::esc($row['last_error']) . '</td><td>' . AdminUi::esc(TimezoneHelper::autoDateString((int) $row['quarantined_at'], true))
                . '</td><td>' . $form . '</td></tr>';
        }
        if ($rows === '') {
            return '<span class="adminMuted">No task is quarantined.</span>';
        }

        return '<table class="adminTable"><thead><tr><th>Type</th><th>Task</th><th>Attempts</th><th>Error</th><th>Quarantined</th><th></th></tr></thead><tbody>'
            . $rows . '</tbody></table><p class="adminMuted">Requeue puts the task back in its queue; fix the cause first, or it will fail and be quarantined again.</p>';
    }

    /** @param list<array{0: string, 1: int|string}> $rows */
    private function table(array $rows): string
    {
        $html = '';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><th scope="row">' . AdminUi::esc($label) . '</th><td>' . $value . '</td></tr>';
        }

        return '<table class="adminTable"><tbody>' . $html . '</tbody></table>';
    }

    private function stateFiles(): string
    {
        $rows = '';
        foreach (StateFiles::inspect(rtrim((string) STATE_DIR, '/')) as $row) {
            $rows .= '<tr><th scope="row">' . AdminUi::esc($row['file']) . '</th><td>' . AdminUi::esc($row['what']) . '</td><td>'
                . AdminUi::esc($row['state']) . '</td></tr>';
        }

        return '<table class="adminTable"><tbody>' . $rows . '</tbody></table>';
    }

    private function metrics(): string
    {
        return MetricsToken::read() === null
            ? '<p>Prometheus metrics are off. Create a token with <code>tools/metrics-token.php</code> to turn them on; see the operations guide.</p>'
            : '<p>Prometheus metrics are on: the token exists and <code>/metrics</code> is served on the internal listener only, never on the public port.</p>';
    }
}
