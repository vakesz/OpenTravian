<?php

declare(strict_types=1);

namespace Game\Admin;

/**
 * The control panel's navigation, as data. Every entry is [url, label, simple]: the Simple view
 * lists the few things most operators need, the Advanced view lists everything, in the same
 * groups. The page being shown is always listed, so opening an Advanced page by URL while in
 * Simple never leaves the sidebar without it.
 */
final class AdminMenu
{
    private function __construct()
    {
    }

    /**
     * @param array{staff: bool, gameWorldUrl: string, preregistration: bool, logoutToken: string, role?: string|null} $context
     * @return list<array{title: string, items: list<array{0: string, 1: string, 2: bool}>}>
     */
    public static function definition(array $context): array
    {
        $communication = [
            ['admin.php?action=publicInfobox', 'Public info box', false],
            ['admin.php?action=privateInfobox', 'Private info box', false],
            ['admin.php?action=sendPublicMessage', 'Public message', false],
            ['admin.php?action=sendPrivateMessage', 'Private message', false],
            ['admin.php?action=sendEmail', 'Newsletter', false],
        ];
        if ($context['staff']) {
            $communication[] = ['admin.php?action=inGameMessage', 'In-game message', false];
        }
        $overview = [['admin.php', 'Control panel home', true]];
        if ($context['staff']) {
            $overview[] = [$context['gameWorldUrl'], 'Server homepage', false];
            $overview[] = [$context['gameWorldUrl'] . 'dorf1.php', 'Return to server', true];
        }
        $groups = [
            ['title' => 'Overview', 'items' => $overview],
            ['title' => 'Players', 'items' => [
                ['admin.php?action=users', 'Users', true],
                ['admin.php?action=activationList', 'Activation list', false],
                ['admin.php?action=multiAccount', 'Multi-account users', false],
                ['admin.php?action=socialSignIns', 'Social sign-ins', false],
                ['admin.php?action=detector', 'Detector', false],
                ['admin.php?action=fakeUser', 'Fake users', false],
                ['admin.php?action=gifts', 'Gifts', false],
                ['admin.php?action=addVillage', 'Add village', false],
                ['admin.php?action=copyFarmlist', 'Copy farm list', false],
                ['admin.php?action=deleting', 'Delete users', false],
                ['admin.php?action=heroAuction', 'Hero auction', false],
                ['admin.php?action=heroAddItem', 'Add hero item', false],
            ]],
            ['title' => 'Moderation', 'items' => [
                ['admin.php?action=bannedList', 'Ban and unban', true],
                ['admin.php?action=playerAccusations', 'Player reports', true],
                ['admin.php?action=IPBan', 'IP bans', false],
                ['admin.php?action=blackListEmail', 'Email blacklist', false],
                ['admin.php?action=reportedMessages', 'Reported messages', false],
                ['admin.php?action=deleteAllMessages', 'Delete player messages', false],
                ['admin.php?action=filteredUrls', 'Filtered URLs', false],
                ['admin.php?action=badWords', 'Bad words', false],
                ['admin.php?action=blackListedNames', 'Blacklisted names', false],
            ]],
            ['title' => 'World', 'items' => [
                ['admin.php?action=configurationDetails', 'Info and settings', true],
                ['admin.php?action=worldRules', 'World rules', false],
                ['admin.php?action=truce', 'Truce', true],
                ['admin.php?action=convertOasis', 'Convert oasis', false],
                ['admin.php?action=addNatarsFarm', 'Add Natars', false],
                ['admin.php?action=seedFarmVillages', 'Seed farm villages', false],
                ['admin.php?action=minimap', 'Minimap', false],
            ]],
            ['title' => 'Operations', 'items' => [
                ['admin.php?action=engineHealth', 'Engine health', true],
                ['admin.php?action=backups', 'Backups', true],
                ['admin.php?action=operationsSettings', 'Operations settings', false],
                ['admin.php?action=sendTestEmail', 'Send test email', true],
                ['admin.php?action=roundReset', 'Round reset', false],
                ['admin.php?action=fixes', 'Fixes', false],
                ['admin.php?action=Cleanup', 'Cleanup', false],
            ]],
            ['title' => 'Security', 'items' => [
                ['admin.php?action=adminAccounts', 'Admin accounts', false],
                ['admin.php?action=twoFactor', 'Two-factor sign-in', true],
                ['admin.php?action=changePassword', 'Change password', true],
                ['admin.php?action=adminLog', 'Audit log', true],
                ['admin.php?action=logout&c=' . rawurlencode($context['logoutToken']), 'Log out', true],
            ]],
            ['title' => 'Communication', 'items' => $communication],
            ['title' => 'Integrations', 'items' => [
                ['admin.php?action=legalPages', 'Legal pages', false],
                ['admin.php?action=captcha', 'Activation CAPTCHA', false],
                ['admin.php?action=webhook', 'Discord webhook', false],
            ]],
            ['title' => 'Gold', 'items' => [
                ['admin.php?action=payments', 'Payments', false],
                ['admin.php?action=goldPackageCodeGenerator', 'Code generator', false],
                ['admin.php?action=giftGoldPackageCodeGenerator', 'Gift code generator', false],
            ]],
        ];
        if ($context['preregistration']) {
            $groups[] = ['title' => 'Registration', 'items' => [
                ['admin.php?action=preRegistrationByEmail', 'Pre-registration by email', false],
                ['admin.php?action=preRegistrationCodeBatch', 'Pre-registration batch', false],
            ]];
        }
        return $groups;
    }

    /** Pages reached from a list or a form rather than from the menu. */
    private const CONTEXTUAL_TITLES = [
        'editplayer' => 'Edit player',
        'editvillage' => 'Edit village',
        'editalliance' => 'Edit alliance',
        'upgradevillage' => 'Upgrade village',
    ];

    private const CONTEXTUAL_LEADS = [
        'editplayer' => 'Change account details, villages, gold and punishments for this player.',
        'editvillage' => 'Inspect and change one village: buildings, troops, resources and artifacts.',
        'editalliance' => 'Change alliance details, members and diplomacy.',
        'upgradevillage' => 'Raise a village population toward a target by adding buildings.',
    ];

    /**
     * The title of a page for its action (the label of its menu entry), so that every page opens
     * with an h1 even when its controller prints none; null when the action has no title.
     */
    public static function titleFor(string $action): ?string
    {
        $needle = strtolower($action === '' || $action === 'main' ? 'admin.php' : 'admin.php?action=' . $action);
        foreach (self::definition(['staff' => true, 'gameWorldUrl' => '/', 'preregistration' => true, 'logoutToken' => '']) as $group) {
            foreach ($group['items'] as [$url, $label]) {
                if (strtolower($url) === $needle) {
                    return $label;
                }
            }
        }

        return self::CONTEXTUAL_TITLES[strtolower($action)] ?? null;
    }

    /** One-sentence lead for contextual pages that rely on the Dispatcher title. */
    public static function leadFor(string $action): string
    {
        return self::CONTEXTUAL_LEADS[strtolower($action)] ?? '';
    }

    /** Panel pages the role may not open are not listed; other links (the game, the log-out link) always are. */
    private static function allowed(?string $role, string $url): bool
    {
        return preg_match('/^admin\.php\?action=([A-Za-z0-9]+)$/', $url, $match) !== 1 || AdminRoles::allows($role, $match[1]);
    }

    /**
     * The groups to show for a view: Simple keeps the flagged entries (and the current page),
     * Advanced everything; a group left empty is dropped.
     *
     * @param array{staff: bool, gameWorldUrl: string, preregistration: bool, logoutToken: string, role?: string|null} $context
     * @return list<array{title: string, items: list<array{0: string, 1: string}>}>
     */
    public static function groups(array $context, bool $advanced, string $currentUrl): array
    {
        $groups = [];
        foreach (self::definition($context) as $group) {
            $items = [];
            foreach ($group['items'] as [$url, $label, $simple]) {
                if (!self::allowed($context['role'] ?? AdminRoles::OWNER, $url)) {
                    continue;
                }
                if ($advanced || $simple || $url === $currentUrl) {
                    $items[] = [$url, $label];
                }
            }
            if ($items !== []) {
                $groups[] = ['title' => $group['title'], 'items' => $items];
            }
        }

        return $groups;
    }
}
