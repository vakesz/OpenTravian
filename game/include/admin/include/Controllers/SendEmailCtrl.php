<?php

use Core\Helper\WebService;
use Game\Admin\AuditLog;
use Game\AdminUi;
use Game\Mail\MailService;
use Game\Mail\Newsletter;
use resources\View\PHPBatchView;

/**
 * The newsletter: compose, preview, queue. It goes only to players who opted in (users.newsletter) with a verified
 * address, as one personalised mail each through the mail queue; queueing is not sending, and without SMTP the
 * page says so. Queueing is a POST like every panel mutation, limited to one campaign per hour and three per day,
 * and the audit log records how many were queued, never the text.
 */
class SendEmailCtrl
{
    private const MAX_SUBJECT = 200;
    private const MAX_MESSAGE = 100000;

    public function __construct()
    {
        $dispatcher = Dispatcher::getInstance();
        if (!getCustom('allowInterruptionInGame')) {
            $dispatcher->appendContent(AdminUi::page(
                'Newsletter',
                'Queue one personalised mail to every opted-in player with a verified address.',
                AdminUi::notice('Live-world changes are locked. Allow them with the toggle in the footer to use this page.', 'error')
            ));
            return;
        }
        $subject = is_string($_POST['subject'] ?? null) ? trim($_POST['subject']) : '';
        $message = is_string($_POST['message'] ?? null) ? $_POST['message'] : '';
        $step = is_string($_POST['do'] ?? null) ? $_POST['do'] : '';
        $notices = '';
        $preview = '';
        if (WebService::isPost() && $step !== '') {
            if ($subject === '' || trim($message) === '' || strlen($subject) > self::MAX_SUBJECT || strlen($message) > self::MAX_MESSAGE) {
                $notices .= AdminUi::notice('Please fill all inputs.', 'error');
            } elseif ($step === 'queue') {
                $notices .= $this->queue($subject, $message);
            } elseif ($step === 'preview') {
                $preview = $this->preview($subject, $message);
            }
        }
        $dispatcher->appendContent(AdminUi::page(
            'Newsletter',
            'Players who opted in and have a verified address receive one personalised mail each, with an unsubscribe link. '
            . count(Newsletter::recipients()) . ' players would receive it now.',
            (MailService::deliveryConfigured() ? '' : AdminUi::notice((string) T('Mail', 'Email delivery is not configured for this world. Messages stay queued until it is.'), 'warning'))
            . $notices . $preview . $this->form($subject, $message)
        ));
    }

    private function queue(string $subject, string $message): string
    {
        if (isServerFinished()) {
            return AdminUi::notice('The server has finished.', 'error');
        }
        if (!Newsletter::campaignAllowed()) {
            return AdminUi::notice('A newsletter was queued recently: at most one per hour and three per day.', 'error');
        }
        $result = Newsletter::queueTo(Newsletter::recipients(), $subject, $message);
        AuditLog::record('mail.newsletter-queued', '', $result['queued'] . ' queued, ' . $result['skipped'] . ' skipped, subject ' . substr(sha1($subject), 0, 10));

        return AdminUi::notice(
            $result['queued'] . ' newsletters were queued' . ($result['skipped'] > 0 ? ' (' . $result['skipped'] . ' skipped: mailed within the rate limit or no longer eligible)' : '') . '.'
            . (MailService::deliveryConfigured() ? '' : ' They are waiting in the queue, not sent.'),
            'success'
        );
    }

    private function preview(string $subject, string $message): string
    {
        $html = (string) PHPBatchView::render('mail/message', ['name' => 'Player', 'body' => $message, 'unsubscribeUrl' => '#']);

        return '<section class="adminBox"><h2 class="adminBoxTitle">Preview: ' . AdminUi::esc($subject) . '</h2><div class="adminBoxBody">'
            . '<iframe sandbox="" style="width:100%;height:420px;border:1px solid #ccc;background:#fff" srcdoc="' . AdminUi::esc($html) . '"></iframe>'
            . '<form method="post" action="admin.php?action=sendEmail" class="adminInline">' . getCheckerInput()
            . '<input type="hidden" name="subject" value="' . AdminUi::esc($subject) . '"><input type="hidden" name="message" value="' . AdminUi::esc($message) . '">'
            . '<input type="hidden" name="do" value="queue">' . AdminUi::button('Queue newsletter', 'green', ['type' => 'submit']) . '</form></div></section>';
    }

    private function form(string $subject, string $message): string
    {
        return '<form method="post" action="admin.php?action=sendEmail">' . getCheckerInput()
            . '<input type="hidden" name="do" value="preview">'
            . AdminUi::input('subject', 'Subject', $subject, ['maxlength' => (string) self::MAX_SUBJECT, 'required' => 'required'])
            . AdminUi::textarea('message', 'Message (HTML)', $message, ['required' => 'required', 'rows' => '12'])
            . AdminUi::button('Preview', 'grey', ['type' => 'submit']) . '</form>';
    }
}
