<?php

declare(strict_types=1);

namespace Game\Mail;

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DBI;
use Core\Helper\Mailer;
use Game\SmtpConfig;
use resources\View\PHPBatchView;

/**
 * The one way the game mails a player outside the registration and support flows. A mail is queued (never sent
 * here) only for a player who opted into its kind, with a verified address, who is not banned or in deletion, and
 * within the kind's rate limit. Every mail ends with the opt-in footer and a signed unsubscribe link, which also
 * goes into the queue row so the transport adds the List-Unsubscribe headers.
 */
final class MailService
{
    private function __construct()
    {
    }

    /** Whether the queue is delivered at all: without SMTP mail stays queued and the UI must say so. */
    public static function deliveryConfigured(): bool
    {
        return SmtpConfig::current() !== null;
    }

    /** `<world> – <kind label>`, the subject of the event mails. */
    public static function subject(EmailKind $kind): string
    {
        return Config::getProperty('settings', 'worldId') . ' – ' . $kind->label();
    }

    /**
     * @param string $bodyHtml the message body, already escaped where it carries player text
     * @param bool $requireOptIn false only for the newsletter's own recipient list, which is already opted in
     * @return bool whether a mail was queued
     */
    public static function queue(int $userId, EmailKind $kind, string $subject, string $bodyHtml, bool $requireOptIn = true): bool
    {
        $db = DBI::getInstance();
        $user = $db->fetchSingleRow('SELECT name, email, email_verified, access FROM users WHERE id=? AND id>2', [$userId]);
        if (!is_array($user) || (int) $user['email_verified'] !== 1 || (int) $user['access'] === 0 || trim((string) $user['email']) === '') {
            return false;
        }
        if ((int) $db->fetchScalar('SELECT COUNT(uid) FROM deleting WHERE uid=?', [$userId], 0) > 0) {
            return false;
        }
        if ($requireOptIn && !EmailPreferences::enabled($userId, $kind)) {
            return false;
        }
        [$limit, $window] = $kind->rateLimit();
        try {
            if (!Caching::getInstance()->incrementWithinLimit(throttle_key('mail-' . $kind->value, 'player', (string) $userId), $limit, $window)) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }
        $unsubscribe = UnsubscribeToken::url($userId, $kind);
        $html = PHPBatchView::render('mail/message', [
            'name' => htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8'),
            'body' => $bodyHtml,
            'unsubscribeUrl' => $unsubscribe,
        ]);

        return Mailer::sendEmail((string) $user['email'], $subject, (string) $html, 0, '', $unsubscribe) > 0;
    }
}
