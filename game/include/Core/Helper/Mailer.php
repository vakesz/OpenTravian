<?php

declare(strict_types=1);

namespace Core\Helper;

use Core\Database\GlobalDB;
use Core\Mail\MailQueueSchema;
use Game\SupportContact;

class Mailer
{
    public static function sendAdminReport(string $subject, string $html): int
    {
        global $globalConfig;
        $adminEmail = isset($globalConfig['staticParameters']['adminEmail']) ? trim((string)$globalConfig['staticParameters']['adminEmail']) : '';
        if ($adminEmail === '') {
            error_log('Mailer: admin report skipped, staticParameters.adminEmail is empty.');
            return 0;
        }

        return self::sendEmail($adminEmail, $subject, $html);
    }

    /**
     * @param array<array-key, mixed>|string $to recipient addresses; anything that is not a valid address is skipped
     */
    public static function sendBatch(array|string $to, string $subject, string $html): int
    {
        $to = array_values(array_filter(
            (array)$to,
            static fn($address): bool => is_string($address) && SupportContact::isValid($address)
        ));
        if (!$to) {
            return 0;
        }
        $db = GlobalDB::getInstance();
        if (!MailQueueSchema::isReady($db)) {
            return 0;
        }
        $affected = 0;
        // 3 placeholders per row; chunk to stay far below the 65,535 placeholder limit
        foreach (array_chunk($to, 1000) as $chunk) {
            $parameters = [];
            foreach ($chunk as $address) {
                array_push($parameters, (string)$address, (string)$subject, (string)$html);
            }
            $placeholders = implode(",", array_fill(0, count($chunk), "(?, ?, ?)"));
            $statement = $db->run("INSERT INTO mailServer (toEmail, subject, html) VALUES " . $placeholders, $parameters);
            if ($statement !== false) {
                $affected += $statement->affected_rows;
            }
        }

        return $affected;
    }

    public static function sendEmail(string $to, string $subject, string $html, int $priority = 0, string $replyTo = '', string $unsubscribeUrl = ''): int
    {
        $to = SupportContact::normalize((string)$to);
        if (!SupportContact::isValid($to)) {
            return 0;
        }
        $replyTo = SupportContact::normalize((string)$replyTo);
        if (!SupportContact::isValid($replyTo)) {
            $replyTo = '';
        }
        $db = GlobalDB::getInstance();
        if (!MailQueueSchema::isReady($db)) {
            return 0;
        }
        $statement = $db->run(
            "INSERT INTO mailServer (toEmail, subject, html, priority, replyTo, unsubscribeUrl) VALUES (?, ?, ?, ?, ?, ?)",
            [$to, (string)$subject, (string)$html, (int)$priority, $replyTo, $unsubscribeUrl]
        );
        if ($statement === false) {
            return 0;
        }

        return $statement->affected_rows;
    }
}
