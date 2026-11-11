<?php

declare(strict_types=1);

namespace Game\Mail;

use Core\Database\DB;
use Core\Database\DBI;
use Core\Helper\TimezoneHelper;
use Model\MovementsModel;

use function T;

/**
 * The event mails a player opts into in Options. Each method is called where the event happens and never lets a
 * mail problem reach the game: it queues through MailService (opt-in, address and rate limit checked there) and
 * swallows and logs any failure. Bodies name villages and people and give times, never message text or the attacker's
 * troops.
 */
final class EventMail
{
    private function __construct()
    {
    }

    /** A new attack or raid on a village: the number now on the way and the arrival of the first. */
    public static function attackIncoming(int $fromKid, int $toKid): void
    {
        self::guard(static function () use ($fromKid, $toKid): void {
            $db = DBI::getInstance();
            $village = $db->fetchSingleRow('SELECT owner, name FROM vdata WHERE kid=?', [$toKid]);
            $attacker = (int) $db->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$fromKid], 0);
            if (!is_array($village) || (int) $village['owner'] <= 2 || (int) $village['owner'] === $attacker) {
                return;
            }
            // Read over the game's own connection: the movement that triggered this may not be committed yet.
            $incoming = DB::getInstance()->run(
                'SELECT COUNT(id) AS amount, MIN(end_time) AS first FROM movement WHERE to_kid=? AND mode=0 AND attack_type IN (?, ?)',
                [$toKid, MovementsModel::ATTACKTYPE_NORMAL, MovementsModel::ATTACKTYPE_RAID]
            )->get_result()->fetch_assoc();
            $amount = is_array($incoming) ? (int) $incoming['amount'] : 0;
            if ($amount < 1) {
                return;
            }
            $owner = (int) $village['owner'];
            $name = self::escape(htmlspecialchars_decode((string) $village['name'], ENT_QUOTES));
            $arrival = self::time($owner, intdiv((int) $incoming['first'], 1000));
            $body = $amount === 1
                ? sprintf((string) T('Mail', 'An incoming attack on %s arrives at %s.'), $name, $arrival)
                : sprintf((string) T('Mail', '%d incoming attacks on %s. The first arrives at %s.'), $amount, $name, $arrival);
            MailService::queue($owner, EmailKind::Attack, MailService::subject(EmailKind::Attack), $body);
        });
    }

    /** A building reached a new level. */
    public static function constructionFinished(int $kid, int $gid, int $level): void
    {
        self::guard(static function () use ($kid, $gid, $level): void {
            $village = DBI::getInstance()->fetchSingleRow('SELECT owner, name FROM vdata WHERE kid=?', [$kid]);
            if (!is_array($village) || (int) $village['owner'] <= 2) {
                return;
            }
            $body = sprintf(
                (string) T('Mail', '%s in %s has been completed (level %d).'),
                self::escape((string) T('Buildings', $gid . '.title')),
                self::escape(htmlspecialchars_decode((string) $village['name'], ENT_QUOTES)),
                $level
            );
            MailService::queue((int) $village['owner'], EmailKind::Construction, MailService::subject(EmailKind::Construction), $body);
        });
    }

    /** A player's message reached another player: only the sender's name goes into the mail. */
    public static function newMessage(int $toUserId, int $fromUserId): void
    {
        self::guard(static function () use ($toUserId, $fromUserId): void {
            $sender = (string) DBI::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$fromUserId], '');
            if ($toUserId <= 2 || $fromUserId <= 2 || $sender === '') {
                return;
            }
            MailService::queue(
                $toUserId,
                EmailKind::Message,
                MailService::subject(EmailKind::Message),
                sprintf((string) T('Mail', 'You have a new message from %s.'), self::escape($sender))
            );
        });
    }

    /** The player's vacation mode has started (or been extended to) $until. */
    public static function vacationStarted(int $userId, int $until): void
    {
        self::guard(static function () use ($userId, $until): void {
            MailService::queue(
                $userId,
                EmailKind::VacationStarted,
                MailService::subject(EmailKind::VacationStarted),
                sprintf((string) T('Mail', 'Your vacation mode has started. It ends at %s.'), self::time($userId, $until))
            );
        });
    }

    /** The player's vacation mode ends at $until, within the day. */
    public static function vacationEnding(int $userId, int $until): void
    {
        self::guard(static function () use ($userId, $until): void {
            MailService::queue(
                $userId,
                EmailKind::VacationEnding,
                MailService::subject(EmailKind::VacationEnding),
                sprintf((string) T('Mail', 'Your vacation mode ends at %s.'), self::time($userId, $until))
            );
        });
    }

    /** A sitter signed in to the player's account just now. */
    public static function sitterSignedIn(int $ownerUserId, int $sitterUserId): void
    {
        self::guard(static function () use ($ownerUserId, $sitterUserId): void {
            $sitter = (string) DBI::getInstance()->fetchScalar('SELECT name FROM users WHERE id=?', [$sitterUserId], '');
            if ($sitter === '') {
                return;
            }
            MailService::queue(
                $ownerUserId,
                EmailKind::SitterSignIn,
                MailService::subject(EmailKind::SitterSignIn),
                sprintf((string) T('Mail', '%s signed in to your account at %s.'), self::escape($sitter), self::time($ownerUserId, time()))
            );
        });
    }

    /** A time in the player's own zone and style. */
    private static function time(int $userId, int $timestamp): string
    {
        $setting = (string) DBI::getInstance()->fetchScalar('SELECT timezone FROM users WHERE id=?', [$userId], 'default,0');

        return self::escape(TimezoneHelper::formatFor($setting, $timestamp));
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private static function guard(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $exception) {
            error_log('Event mail skipped: ' . get_class($exception));
        }
    }
}
