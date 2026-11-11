<?php

declare(strict_types=1);

namespace Game\Admin;

/**
 * The generated state files of a world and what each one holds, for the Engine health page.
 * Files that carry credentials should be readable only by their owner (no group or other bits).
 */
final class StateFiles
{
    /** file => [what it is, holds a secret] */
    public const FILES = [
        'connection.local.php' => ['Database login and world rules', true],
        'globalConfig.php' => ['World configuration', false],
        'smtp.local.php' => ['Mail delivery credentials', true],
        'social.local.php' => ['Social sign-in credentials', true],
        'payments.local.php' => ['Payment credentials', true],
        'captcha.local.php' => ['CAPTCHA keys', true],
        'webhook.local.php' => ['Discord webhook', true],
        'admin-2fa.local.json' => ['Administrator two-factor enrolment', true],
        'metrics-token.local' => ['Metrics bearer token', true],
        'operations.local.json' => ['Backup, catch-up and audit retention settings', false],
    ];

    private function __construct()
    {
    }

    /**
     * @return list<array{file:string,what:string,state:string,tone:string}> tone is a notice tone
     */
    public static function inspect(string $directory): array
    {
        $rows = [];
        foreach (self::FILES as $file => [$what, $secret]) {
            $path = rtrim($directory, '/') . '/' . $file;
            if (!is_file($path)) {
                $rows[] = ['file' => $file, 'what' => $what, 'state' => 'not set up', 'tone' => 'info'];
                continue;
            }
            $mode = fileperms($path) & 0777;
            $open = $secret && ($mode & 0077) !== 0;
            $rows[] = [
                'file' => $file,
                'what' => $what,
                'state' => sprintf('present, mode %04o', $mode) . ($open ? ', readable by other accounts' : '') . (is_writable($path) ? '' : ', not writable'),
                'tone' => $open ? 'warning' : 'success',
            ];
        }

        return $rows;
    }
}
