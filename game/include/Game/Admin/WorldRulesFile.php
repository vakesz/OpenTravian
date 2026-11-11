<?php

declare(strict_types=1);

namespace Game\Admin;

use Game\WorldOverrides;

/**
 * Reads and rewrites the "overrides" key of the generated connection file
 * (connection.local.php in the state directory), the same place the setup
 * wizard writes the world rules to. Only that key changes; the database login
 * stays as it was, and the file keeps its permissions.
 */
final class WorldRulesFile
{
    private function __construct()
    {
    }

    public static function path(): string
    {
        $state = defined('STATE_DIR') ? (string) constant('STATE_DIR') : (string) getenv('TRAVIAN_STATE_DIR');

        return $state === '' ? '' : rtrim($state, '/') . '/connection.local.php';
    }

    /** @return array<string,int|bool|string> the stored overrides, validated */
    public static function read(string $path): array
    {
        $connection = self::load($path);

        return is_array($connection) && is_array($connection['overrides'] ?? null) ? WorldOverrides::normalize($connection['overrides']) : [];
    }

    /**
     * Stores the editable rules and keeps whatever else the file held for the install-only keys.
     *
     * @param array<string,mixed> $rules
     */
    public static function write(string $path, array $rules): bool
    {
        $connection = self::load($path);
        if (!is_array($connection)) {
            return false;
        }
        $kept = array_intersect_key(
            WorldOverrides::normalize(is_array($connection['overrides'] ?? null) ? $connection['overrides'] : []),
            array_flip(WorldOverrides::INSTALL_ONLY)
        );
        $connection['overrides'] = $kept + array_intersect_key(WorldOverrides::normalize($rules), array_flip(WorldOverrides::editable()));
        $mode = (int) (fileperms($path) & 0777) ?: 0600;
        $owner = function_exists('fileowner') ? @fileowner($path) : false;
        $group = function_exists('filegroup') ? @filegroup($path) : false;
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, "<?php\n\$connection = " . var_export($connection, true) . ";\n", LOCK_EX) === false) {
            return false;
        }
        @chmod($temporary, $mode);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);

            return false;
        }
        // A root CLI rewrite must not leave the file unreadable by php-fpm (www-data).
        if (is_int($owner) && function_exists('chown')) {
            @chown($path, $owner);
        }
        if (is_int($group) && function_exists('chgrp')) {
            @chgrp($path, $group);
        }
        @chmod($path, $mode);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }

        return true;
    }

    /** @return array<string,mixed>|null */
    private static function load(string $path): ?array
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $read = static function (string $file): mixed {
            $connection = null;
            include $file;

            return $connection;
        };
        $connection = $read($path);

        return is_array($connection) ? $connection : null;
    }
}
