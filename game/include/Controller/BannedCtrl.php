<?php

namespace Controller;

use Core\Database\DB;
use Core\Helper\SessionVar;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Locale;
use Core\Session;

class BannedCtrl
{
    /**
     * @param mixed $contentCssClass
     * @param mixed $content
     * @param bool $click
     */
    public function __construct(&$contentCssClass, &$content, $click = false)
    {
        Session::getInstance()->decreaseClicks();
        $contentCssClass = 'sysmsg';
        $content = '<div class="sysmsg">';
        $order = [];
        $order[] = Session::getInstance()->getName();
        $db = DB::getInstance();
        if ($click) {
            if (WebService::isPost()) {
                if (throttle_public_form('clickunblock', 5, 3600)) {
                    SessionVar::timeoutNow("sessionClicks");
                    SessionVar::timeoutNow("clickBlocked");
                    WebService::redirect("/dorf1.php");
                } else {
                    $content .= T("Login", "tooManyRequests");
                }
            }
            $content .= <<<HTML
<script type="text/javascript">
    function submitForm(){
        document.getElementById("verifyForm").submit();
    }
</script>
HTML;
            $content .= vsprintf(T("inGame", 'bannedClickPage'), $order);
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/dorf1.php';
            $requestUri = is_string($requestUri) ? $requestUri : '/dorf1.php';
            $content .= '<hr /><form id="verifyForm" action="' . htmlspecialchars($requestUri, ENT_QUOTES, 'UTF-8') . '" method="POST">';
            $content .= '<input type="hidden" name="reCaptchaVerify" value="1">';
            $content .= '<center>';
            $content .= '<button type="submit" class="textButtonV1 green " version="textButtonV1">' . T("Login", "continueButton") . '</button>';
            $content .= '</center>';
            $content .= '</form>';
        } else {
            $result = $db->query("SELECT * FROM banQueue WHERE uid=" . Session::getInstance()->getPlayerId() . " ORDER BY id DESC LIMIT 1");
            if ($result->num_rows) {
                $result = $result->fetch_assoc();
                if (!$result['time']) {
                    $content .= vsprintf(T("inGame", $click ? 'bannedClickPage' : "bannedPage"), $order);
                } else {
                    if (!empty($result['reason'])) {
                        $order[] = T("inGame", "infoBox.Reasons.{$result['reason']}");
                        $order[] = TimezoneHelper::autoDateString($result['end'], true);
                        $content .= vsprintf(T("inGame", 'bannedPageWithTime'), $order);
                    }
                }
            } else {
                $content .= vsprintf(T("inGame", 'bannedClickPage'), $order);
            }
        }
        if (!$click) {
            $content .= '<p class="f16" align="center"><a href="/dorf1.php?ok=1">» ' . T("inGame", "continue") . '</a></p>';
        } else {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/dorf1.php?ok=1';
            $requestUri = is_string($requestUri) ? $requestUri : '/dorf1.php?ok=1';
            $content .= '<p class="f16" align="center"><a href="' . htmlspecialchars($requestUri, ENT_QUOTES, 'UTF-8') . '">» ' . T("inGame", "continue") . '</a></p>';
        }
        $content .= '</div>';
    }
}
