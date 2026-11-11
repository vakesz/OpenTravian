<?php

declare(strict_types=1);

namespace Core\Mail;

use Game\SupportContact;

/** Deliberate adapter around the image's maintained PHPMailer dependency. */
final class SmtpTransport
{
    private function __construct()
    {
    }

    /**
     * @param array{id:int,toEmail:string,subject:string,html:string,replyTo:string,unsubscribeUrl?:string} $message
     * @param array{host:string,port:int,encryption:string,from:string,auth:bool,username:string,password:string} $smtp
     */
    public static function send(array $message, array $smtp): bool
    {
        if (!self::loadPhpMailer()) {
            error_log('Mail transport is unavailable because the bundled PHPMailer dependency is missing.');

            return false;
        }

        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $smtp['host'];
            $mailer->Port = $smtp['port'];
            $mailer->SMTPAuth = $smtp['auth'];
            $mailer->SMTPAutoTLS = false;
            $mailer->Timeout = 15;
            $mailer->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
            $mailer->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $mailer->SMTPSecure = $smtp['encryption'] === 'smtps'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            if ($smtp['auth']) {
                $mailer->Username = $smtp['username'];
                $mailer->Password = $smtp['password'];
            }
            // Player-supplied email is never From or envelope sender.
            $mailer->setFrom($smtp['from']);
            $mailer->Sender = $smtp['from'];
            $mailer->addAddress($message['toEmail']);
            if ($message['replyTo'] !== '' && SupportContact::isValid($message['replyTo'])) {
                $mailer->addReplyTo($message['replyTo']);
            }
            // Bulk and notification mail carries a one-click unsubscribe (RFC 8058), the same link as in its footer.
            $unsubscribe = $message['unsubscribeUrl'] ?? '';
            if ($unsubscribe !== '' && str_starts_with($unsubscribe, 'http') && !preg_match('/[\s<>\r\n]/', $unsubscribe)) {
                $mailer->addCustomHeader('List-Unsubscribe', '<' . $unsubscribe . '>');
                $mailer->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }
            $mailer->isHTML(true);
            $mailer->Subject = $message['subject'];
            $mailer->Body = $message['html'];
            $mailer->AltBody = trim(html_entity_decode(strip_tags($message['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $mailer->send();
        } catch (\Throwable) {
            // Do not emit SMTP host/user/password or an SMTP-library diagnostic.
            error_log('Mail transport failed for queue message ' . (int) $message['id'] . '.');

            return false;
        }
    }

    private static function loadPhpMailer(): bool
    {
        $autoload = dirname(ROOT_PATH) . '/services/api/include/vendor/autoload.php';
        if (!is_file($autoload)) {
            return false;
        }
        require_once $autoload;

        return class_exists(\PHPMailer\PHPMailer\PHPMailer::class)
            && class_exists(\PHPMailer\PHPMailer\SMTP::class);
    }
}
