<?php

declare(strict_types=1);

namespace Game\Admin;

/**
 * What each control panel role may open. Two roles: an owner can do everything; a moderator can do
 * everything the table below lists as moderator. The table is the one place to change that: an action that
 * is not listed needs an owner, so a new page is owner-only until someone lists it here. A contract keeps
 * every page of the panel in the table.
 */
final class AdminRoles
{
    public const OWNER = 'owner';
    public const MODERATOR = 'moderator';
    public const ALL = [self::OWNER, self::MODERATOR];

    /**
     * The lowest role per action, keyed by the lower-cased action name.
     *
     * @var array<string,string>
     */
    public const MINIMUM = [
        // Managing who can use the panel, and money: owners only.
        'adminaccounts' => self::OWNER,
        'payments' => self::OWNER,
        'roundreset' => self::OWNER,
        'goldpackagecodegenerator' => self::OWNER,
        'giftgoldpackagecodegenerator' => self::OWNER,
        // Everything else is open to a moderator.
        'main' => self::MODERATOR,
        'notfound' => self::MODERATOR,
        'activationlist' => self::MODERATOR,
        'addnatarsfarm' => self::MODERATOR,
        'addvillage' => self::MODERATOR,
        'adminlog' => self::MODERATOR,
        'backups' => self::MODERATOR,
        'badwords' => self::MODERATOR,
        'bannedlist' => self::MODERATOR,
        'blacklistemail' => self::MODERATOR,
        'blacklistednames' => self::MODERATOR,
        'captcha' => self::MODERATOR,
        'changepassword' => self::MODERATOR,
        'cleanup' => self::MODERATOR,
        'configurationdetails' => self::MODERATOR,
        'convertoasis' => self::MODERATOR,
        'copyfarmlist' => self::MODERATOR,
        'deleteallmessages' => self::MODERATOR,
        'deleting' => self::MODERATOR,
        'detector' => self::MODERATOR,
        'editalliance' => self::MODERATOR,
        'editplayer' => self::MODERATOR,
        'editvillage' => self::MODERATOR,
        'enginehealth' => self::MODERATOR,
        'fakeuser' => self::MODERATOR,
        'filteredurls' => self::MODERATOR,
        'fixes' => self::MODERATOR,
        'gifts' => self::MODERATOR,
        'heroadditem' => self::MODERATOR,
        'heroauction' => self::MODERATOR,
        'ingamemessage' => self::MODERATOR,
        'ipban' => self::MODERATOR,
        'legalpages' => self::MODERATOR,
        'minimap' => self::MODERATOR,
        'multiaccount' => self::MODERATOR,
        'operationssettings' => self::MODERATOR,
        'playeraccusations' => self::MODERATOR,
        'preregistrationbyemail' => self::MODERATOR,
        'preregistrationcodebatch' => self::MODERATOR,
        'privateinfobox' => self::MODERATOR,
        'publicinfobox' => self::MODERATOR,
        'reportedmessages' => self::MODERATOR,
        'seedfarmvillages' => self::MODERATOR,
        'sendemail' => self::MODERATOR,
        'sendprivatemessage' => self::MODERATOR,
        'sendpublicmessage' => self::MODERATOR,
        'sendtestemail' => self::MODERATOR,
        'socialsignins' => self::MODERATOR,
        'truce' => self::MODERATOR,
        'twofactor' => self::MODERATOR,
        'upgradevillage' => self::MODERATOR,
        'users' => self::MODERATOR,
        'webhook' => self::MODERATOR,
        'worldrules' => self::MODERATOR,
    ];

    private function __construct()
    {
    }

    public static function valid(string $role): bool
    {
        return in_array($role, self::ALL, true);
    }

    public static function label(string $role): string
    {
        return ucfirst($role);
    }

    public static function minimum(string $action): string
    {
        return self::MINIMUM[strtolower($action)] ?? self::OWNER;
    }

    /** Whether a signed-in admin with this role (null: none) may open the action. */
    public static function allows(?string $role, string $action): bool
    {
        if ($role === self::OWNER) {
            return true;
        }

        return $role === self::MODERATOR && self::minimum($action) === self::MODERATOR;
    }
}
