<?php

declare(strict_types=1);

namespace Game\Mail;

use Core\Database\DBI;

/** Engine job: the vacation-ending reminder, once per vacation, for players who opted in. */
final class Reminders
{
    /** A vacation ends "soon" within a day. */
    public const ENDING_WITHIN = 86400;

    /** Players handled per run; the job runs every ten minutes. */
    private const BATCH = 200;

    /** @return int reminders queued */
    public function vacationEnding(): int
    {
        $db = DBI::getInstance();
        $now = time();
        $due = $db->run(
            'SELECT u.id, u.vacationActiveTil FROM users u INNER JOIN email_preference p ON p.uid = u.id AND p.kind = ? AND p.enabled = 1'
            . ' WHERE u.vacationActiveTil > ? AND u.vacationActiveTil <= ? AND NOT EXISTS (SELECT 1 FROM email_reminder_sent r WHERE r.uid = u.id AND r.kind = p.kind AND r.ref = u.vacationActiveTil)'
            . ' ORDER BY u.vacationActiveTil LIMIT ' . self::BATCH,
            [EmailKind::VacationEnding->value, $now, $now + self::ENDING_WITHIN]
        )->fetchAll(\PDO::FETCH_ASSOC);
        $queued = 0;
        foreach ($due as $row) {
            // Marked first: a vacation is reminded once, even if the mail is dropped by the rate limit.
            $marked = $db->run(
                'INSERT IGNORE INTO email_reminder_sent (uid, kind, ref, sentAt) VALUES (?, ?, ?, ?)',
                [(int) $row['id'], EmailKind::VacationEnding->value, (int) $row['vacationActiveTil'], $now]
            );
            if ($marked->rowCount() === 1) {
                EventMail::vacationEnding((int) $row['id'], (int) $row['vacationActiveTil']);
                ++$queued;
            }
        }

        return $queued;
    }
}
