<?php

declare(strict_types=1);

namespace Game\Admin;

/**
 * The operator's setup checklist on the control panel home: the preflight results (the same checks
 * as tools/preflight.php and Engine health), ordered by what needs attention first and linked to
 * the page where each one is dealt with.
 */
final class SetupChecklist
{
    /** Preflight check id => the page that deals with it. */
    private const PAGES = [
        'smtp' => ['admin.php?action=configurationDetails', 'Email settings'],
        'twoFactor' => ['admin.php?action=twoFactor', 'Two-factor sign-in'],
        'backups' => ['admin.php?action=backups', 'Backups'],
        'export' => ['admin.php?action=backups', 'Backups'],
        'engine' => ['admin.php?action=engineHealth', 'Engine health'],
        'redis' => ['admin.php?action=engineHealth', 'Engine health'],
        'database' => ['admin.php?action=engineHealth', 'Engine health'],
        'schema' => ['admin.php?action=engineHealth', 'Engine health'],
        'dbPrivileges' => ['admin.php?action=engineHealth', 'Engine health'],
        'slowLog' => ['admin.php?action=engineHealth', 'Engine health'],
        'disk' => ['admin.php?action=engineHealth', 'Engine health'],
        'secrets' => ['admin.php?action=engineHealth', 'Engine health'],
    ];

    private const ORDER = [Preflight::FAIL => 0, Preflight::WARN => 1, Preflight::INFO => 2, Preflight::OK => 3];

    private function __construct()
    {
    }

    /**
     * @param list<array{id: string, level: string, message: string}> $results Preflight::evaluate()
     * @return list<array{id: string, level: string, message: string, href: string|null, linkLabel: string|null}> worst first, the order of the checks kept within a level
     */
    public static function items(array $results): array
    {
        $items = [];
        foreach ($results as $index => $result) {
            $page = self::PAGES[$result['id']] ?? null;
            $items[] = ['sort' => [self::ORDER[$result['level']] ?? 3, $index], 'id' => $result['id'], 'level' => $result['level'], 'message' => $result['message'], 'href' => $page[0] ?? null, 'linkLabel' => $page[1] ?? null];
        }
        usort($items, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_map(static function (array $item): array {
            unset($item['sort']);

            return $item;
        }, $items);
    }

    /** Checks that are only a note, unless they are about something an operator is expected to set up. */
    private const RECOMMENDATIONS = ['smtp', 'twoFactor', 'installed'];

    /**
     * Whether the check asks the operator to do something: a failure or a warning, and a note about
     * email, two-factor sign-in or a missing world; the other notes (such as the slow-query log
     * being off) and the ok lines are only listed in the Advanced view.
     */
    public static function needsAttention(string $id, string $level): bool
    {
        return $level === Preflight::FAIL || $level === Preflight::WARN || ($level === Preflight::INFO && in_array($id, self::RECOMMENDATIONS, true));
    }
}
