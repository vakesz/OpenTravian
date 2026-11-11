<?php

declare(strict_types=1);

namespace Core\Helper;

use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use function getWorldId;

class Notification
{
    public static function notify(string $subject, string $mainText): void
    {
        $mainText = nl2br($mainText);
        $text = '<b>' . $subject . '</b>';
        $text .= "<br />";
        $text .= $mainText;
        $text .= "<br />";
        $text .= '<a href="' . WebService::getJustSubDomain() . '">» Go to game (' . getWorldId() . ')</a>';
        $breaks = array("<br />", "<br>", "<br/>");
        $text = str_ireplace($breaks, "\r\n", $text);
        $db = DB::getInstance();
        $db->query("INSERT INTO `notificationQueue`(`message`, `time`) VALUES ('" . $db->real_escape_string($text) . "', '" . time() . "')");
    }

    public static function RealTimeNotify(string $subject, string $mainText): void
    {
        $mainText = nl2br($mainText);
        $text = '<b>' . $subject . '</b>';
        $text .= "<br />";
        $text .= $mainText;
        $text .= "<br />";
        $text .= '<a href="' . WebService::getJustSubDomain() . '">» Go to game (' . getWorldId() . ')</a>';
        $breaks = array("<br />", "<br>", "<br/>");
        $text = str_ireplace($breaks, "\r\n", $text);
        self::notifyReal($text);
    }

    public static function notifyReal(string $text): void
    {
        $db = GlobalDB::getInstance();
        $text = self::bbCode($text);
        $db->query("INSERT INTO `notifications`(`message`, `time`) VALUES ('" . $db->real_escape_string($text) . "', '" . time() . "')");
    }

    private static function bbCode(string $input): string
    {
        return (string) preg_replace_callback("#\[UID=(.*?)\]#is",
            function ($matches) {
                $uid = (int) $matches[1];
                $db = DB::getInstance();
                $find = $db->query("SELECT id, name FROM users WHERE id='$uid'");
                if (!$find || !$find->num_rows) {
                    return '<span style="font-style:italic;">' . T("Global", "Player not found") . '</span>';
                }
                $find = $find->fetch_assoc();
                return '<a href="' . profileUrl((int) $find['id']) . '">' . $find['name'] . '</a>';
            },
            $input);
    }
}