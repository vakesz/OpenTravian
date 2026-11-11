<?php

declare(strict_types=1);

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/message/spamReport.php');
$model = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/MessageModel.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/game/include/resources/Templates/messages/showMsg.php');

if (
    $controller === false
    || $model === false
    || $template === false
    || !str_contains($controller, 'SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS')
    || !str_contains($controller, "'advertisement' => 1")
    || !str_contains($controller, "'misc' => 4")
    || !str_contains($controller, 'reportReceivedMessage')
    || !str_contains($model, 'FOR UPDATE')
    || !str_contains($model, 'INSERT INTO messages_report')
    || !str_contains($model, 'if ($report === false)')
    || !str_contains($model, '$reportedUid === $recipientId')
    || !str_contains($model, 'AND to_uid=$recipientId')
    || !str_contains($template, "\$vars['canReportSpam']")
) {
    fwrite(
        STDERR,
        "Spam reports must validate requests, report only owned inbox messages, and respect sitter permissions.\n"
    );
    exit(1);
}

echo "Message spam-report API contract passed.\n";
