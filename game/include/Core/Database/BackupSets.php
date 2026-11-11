<?php

declare(strict_types=1);

namespace Core\Database;

/**
 * The backups in a directory. A backup is a set of files sharing one stamp: the world dump
 * backup-<ms>-<random>.sql.gz, its -account sibling and, when a state passphrase is set, the
 * encrypted -state.enc archive (see StateArchive). Used by tools/backup.php, the control
 * panel and the preflight, so the naming rules live in one place.
 */
final class BackupSets
{
    public const NAME_PATTERN = '/\Abackup-([0-9]+)-[a-f0-9]+\z/';

    private function __construct()
    {
    }

    public static function isName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    /**
     * Every backup in the directory, newest first.
     *
     * @return list<array{name: string, time: int, world: string, account: string|null, state: string|null, size: int}>
     */
    public static function find(string $directory): array
    {
        $sets = [];
        foreach (glob(rtrim($directory, '/') . '/backup-*.sql.gz') ?: [] as $file) {
            $name = basename($file, '.sql.gz');
            if (self::isName($name)) {
                $sets[] = self::describe($name, rtrim($directory, '/'));
            }
        }
        usort($sets, static fn (array $a, array $b): int => $b['time'] <=> $a['time'] ?: strcmp($b['name'], $a['name']));

        return $sets;
    }

    /**
     * @return array{name: string, time: int, world: string, account: string|null, state: string|null, size: int}|null
     */
    public static function resolve(string $name, string $directory): ?array
    {
        $directory = rtrim($directory, '/');

        return self::isName($name) && is_file($directory . '/' . $name . '.sql.gz') ? self::describe($name, $directory) : null;
    }

    /**
     * Why a dump file cannot be trusted, or null when it is a complete gzip dump: the archive must be
     * intact and the dump must end with the line mariadb-dump writes when it finishes, which a dump
     * cut short never reaches.
     */
    public static function problem(string $file): ?string
    {
        if (!is_file($file)) {
            return 'the file is missing';
        }
        if (!is_readable($file)) {
            return 'the file is not readable by this user';
        }
        $command = 'set -o pipefail; gzip -dc -- ' . escapeshellarg($file) . ' | tail -c 300';
        exec('bash -c ' . escapeshellarg($command) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            return 'the archive is damaged';
        }

        return str_contains(implode("\n", $output), '-- Dump completed') ? null : 'the dump is incomplete';
    }

    /**
     * @param array{name: string, time: int, world: string, account: string|null, state: string|null, size: int} $set
     * @return list<string> one line per damaged file; empty when the whole set is sound
     */
    public static function problems(array $set): array
    {
        $found = [];
        foreach (array_filter([$set['world'], $set['account']]) as $file) {
            $problem = self::problem($file);
            if ($problem !== null) {
                $found[] = basename($file) . ': ' . $problem;
            }
        }
        if ($set['state'] !== null) {
            $problem = StateArchive::problem($set['state'], StateArchive::passphrase());
            if ($problem !== null) {
                $found[] = basename($set['state']) . ': ' . $problem;
            }
        }

        return $found;
    }

    /**
     * @return array{name: string, time: int, world: string, account: string|null, state: string|null, size: int}
     */
    private static function describe(string $name, string $directory): array
    {
        preg_match(self::NAME_PATTERN, $name, $match);
        $world = $directory . '/' . $name . '.sql.gz';
        $account = $directory . '/' . $name . '-account.sql.gz';
        $state = $directory . '/' . $name . '-state.enc';

        return [
            'name' => $name,
            'time' => intdiv((int) $match[1], 1000),
            'world' => $world,
            'account' => is_file($account) ? $account : null,
            'state' => is_file($state) ? $state : null,
            'size' => (int) @filesize($world) + (is_file($account) ? (int) @filesize($account) : 0) + (is_file($state) ? (int) @filesize($state) : 0),
        ];
    }
}
