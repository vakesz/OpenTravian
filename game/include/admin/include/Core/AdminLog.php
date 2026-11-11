<?php

use Game\Admin\AuditLog;

/**
 * Panel-side entry to the audit trail (Game\Admin\AuditLog). Controllers keep
 * calling addLog() with a sentence describing what they did; the row carries the
 * acting administrator, the panel action that was running, the IP and the time.
 */
class AdminLog
{
    private static $_self;
    private static string $action = 'panel';

    public static function getInstance()
    {
        if (!(self::$_self instanceof self)) {
            self::$_self = new self();
        }
        return self::$_self;
    }

    /** The panel action the Dispatcher is running; the default action of addLog(). */
    public static function setAction(string $action): void
    {
        self::$action = $action;
    }

    /**
     * @param string $text what was done
     * @param string $action overrides the running panel action (for example sign-in or sign-out)
     */
    public function addLog($text, string $action = '')
    {
        AuditLog::record($action !== '' ? $action : self::$action, '', (string) $text);
    }
}
