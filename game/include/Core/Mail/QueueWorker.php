<?php

declare(strict_types=1);

namespace Core\Mail;

use Core\Database\GlobalDB;
use Game\SmtpConfig;

/**
 * Bounded at-least-once queue delivery. A lease prevents concurrent engine
 * workers from sending the same row at once; successful rows are deleted so
 * support-ticket PII is retained only while delivery is pending or failed.
 */
final class QueueWorker
{
    private const BATCH_SIZE = 10;
    private const LEASE_SECONDS = 90;
    private const MAX_ATTEMPTS = 8;
    private const RETRY_BASE_SECONDS = 30;
    private const RETRY_MAX_SECONDS = 3600;

    /** The last blocked-state message logged; it is logged again only after the state changes or recovers. */
    private ?string $lastWarning = null;

    public function drain(): int
    {
        $smtp = SmtpConfig::current();
        if ($smtp === null) {
            $this->warnOnce('Mail queue is waiting for valid SMTP configuration.');

            return 0;
        }
        $schemaConnection = GlobalDB::getAConnection();
        try {
            if (!MailQueueSchema::isReady($schemaConnection)) {
                $this->warnOnce('Mail queue schema is missing; reinstall the world.');

                return 0;
            }
        } finally {
            $schemaConnection->close();
        }

        $this->lastWarning = null;
        $delivered = 0;
        for ($i = 0; $i < self::BATCH_SIZE; ++$i) {
            $message = $this->claimNext();
            if ($message === null) {
                break;
            }
            if (SmtpTransport::send($message, $smtp)) {
                if ($this->complete($message)) {
                    ++$delivered;
                }
                continue;
            }
            $this->retryOrFail($message);
        }

        return $delivered;
    }

    /** @return array{id:int,toEmail:string,subject:string,html:string,replyTo:string,unsubscribeUrl:string,attempts:int,leaseToken:string}|null */
    private function claimNext(): ?array
    {
        $db = GlobalDB::getAConnection();
        try {
            if (!$db->begin_transaction()) {
                return null;
            }
            $now = time();
            $staleBefore = $now - self::LEASE_SECONDS;
            $result = $db->query(
                "SELECT id, toEmail, subject, html, replyTo, unsubscribeUrl, attempts FROM mailServer"
                . " WHERE (status='queued' AND availableAt<={$now})"
                . " OR (status='processing' AND lockedAt<={$staleBefore})"
                . ' ORDER BY priority DESC, id ASC LIMIT 1 FOR UPDATE SKIP LOCKED'
            );
            if (!$result instanceof \mysqli_result || !($row = $result->fetch_assoc())) {
                $db->commit();

                return null;
            }
            $result->free();
            $leaseToken = bin2hex(random_bytes(16));
            $attempts = (int) $row['attempts'] + 1;
            $updated = $db->run(
                "UPDATE mailServer SET status='processing', attempts=?, lockedAt=?, leaseToken=?, lastError='' WHERE id=?",
                [$attempts, $now, $leaseToken, (int) $row['id']]
            );
            if ($updated === false || $updated->affected_rows !== 1 || !$db->commit()) {
                if ($db->inTransaction()) {
                    $db->rollback();
                }

                return null;
            }

            return [
                'id' => (int) $row['id'],
                'toEmail' => (string) $row['toEmail'],
                'subject' => (string) $row['subject'],
                'html' => (string) $row['html'],
                'replyTo' => (string) $row['replyTo'],
                'unsubscribeUrl' => (string) $row['unsubscribeUrl'],
                'attempts' => $attempts,
                'leaseToken' => $leaseToken,
            ];
        } catch (\Throwable) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('Could not claim a mail queue message.');

            return null;
        } finally {
            $db->close();
        }
    }

    /** @param array{id:int,leaseToken:string} $message */
    private function complete(array $message): bool
    {
        $db = GlobalDB::getAConnection();
        try {
            $deleted = $db->run(
                "DELETE FROM mailServer WHERE id=? AND leaseToken=? AND status='processing'",
                [$message['id'], $message['leaseToken']]
            );

            return $deleted !== false && $deleted->affected_rows === 1;
        } finally {
            $db->close();
        }
    }

    /** @param array{id:int,attempts:int,leaseToken:string} $message */
    private function retryOrFail(array $message): void
    {
        $failed = $message['attempts'] >= self::MAX_ATTEMPTS;
        $status = $failed ? 'failed' : 'queued';
        $delay = min(
            self::RETRY_MAX_SECONDS,
            self::RETRY_BASE_SECONDS * (2 ** max(0, $message['attempts'] - 1))
        );
        $availableAt = $failed ? 0 : time() + $delay;
        $lastError = $failed ? 'SMTP delivery failed after the maximum retry count.' : 'SMTP delivery failed; retry scheduled.';
        $db = GlobalDB::getAConnection();
        try {
            $db->run(
                "UPDATE mailServer SET status=?, availableAt=?, lockedAt=0, leaseToken='', lastError=?"
                . " WHERE id=? AND leaseToken=? AND status='processing'",
                [$status, $availableAt, $lastError, $message['id'], $message['leaseToken']]
            );
        } finally {
            $db->close();
        }
    }

    private function warnOnce(string $message): void
    {
        if ($this->lastWarning === $message) {
            return;
        }
        $this->lastWarning = $message;
        error_log($message);
    }
}
