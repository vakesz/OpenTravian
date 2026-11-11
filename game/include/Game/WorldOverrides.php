<?php

declare(strict_types=1);

namespace Game;

/**
 * Whitelisted per-world setting overrides written by the setup wizard into the
 * generated connection file (its "overrides" key) and applied by
 * config/config.after.php. Only the keys listed here can be set; each maps to
 * a real property of the runtime config object.
 *
 * Every definition is [path, type, min, max, label]. Paths are
 * "section.property" or "section.group.property".
 */
final class WorldOverrides
{
    /** @var array<string,array{0:string,1:string,2:int,3:int,4:string}> */
    public const DEFINITIONS = [
        'vacationDays' => ['game.vacationDays', 'int', 0, 365, 'Vacation base days (divided by world speed; 0 disables)'],
        'startAdventures' => ['custom.startAdventures', 'int', 1, 20, 'Adventures available at the start'],
        'refLimit' => ['settings.advanced.refLimit', 'int', 0, 1000, 'Refer-a-friend reward limit'],
        'allianceMembersLimit' => ['custom.allianceMembersLimit', 'int', 1, 500, 'Alliance member limit'],
        'registerAutoClose' => ['settings.advanced.registerAutoClose', 'bool', 0, 1, 'Close registration automatically'],
        'voucherEnabled' => ['settings.advanced.voucherEnabled', 'bool', 0, 1, 'Gold vouchers enabled'],
        'disableSeasons' => ['display.disableSeasons', 'bool', 0, 1, 'Hide the Season Pass'],
        'wwPlansEnabled' => ['custom.wwPlansEnabled', 'bool', 0, 1, 'World Wonder construction plans'],
        'finishConstruction' => ['dynamic.finishConstruction', 'bool', 0, 1, 'Finish Palace and Residence construction with gold (speed worlds above 1×)'],
        'oneFarmListPerAccount' => ['custom.allowOneFarmListPerAccount', 'bool', 0, 1, 'One farm list per account'],
        'fakeAuctions' => ['auction.fakeAuction.enabled', 'bool', 0, 1, 'System-generated auctions'],
        'starvation' => ['game.starvation', 'bool', 0, 1, 'Troops starve without crop'],
        'speedShortcuts' => ['custom.speedShortcuts', 'bool', 0, 1, 'Speed-world shortcuts (suggested from x100): gold upgrade to max, train maximum, Smithy gold max'],
        'upgradeToMaxLevelGold' => ['extraSettings.upgradeToMaxLevel.coins', 'int', 1, 10000, 'Building: gold to upgrade instantly to max level'],
        'smithyMaxLevelGold' => ['extraSettings.smithyMaxLevel.coins', 'int', 1, 10000, 'Smithy: gold to upgrade one unit to max level'],
        'smithyUpgradeAllToMaxGold' => ['extraSettings.generalOptions.smithyUpgradeAllToMax.coins', 'int', 1, 10000, 'Smithy: gold to upgrade all units to max level'],
        'discordUrl' => ['dynamic.discordUrl', 'string', 0, 255, 'Discord invite URL (https; blank hides every Discord link)'],
    ];

    /**
     * Keys the installer writes once into the world's database instead of
     * applying on every request, so the admin panel stays the source of truth.
     */
    public const INSTALL_ONLY = ['discordUrl', 'finishConstruction'];

    /** https only; no quotes, whitespace or control characters. Blank means "not configured". */
    public const DISCORD_URL_PATTERN = '{^(?:|https://[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?(?::[0-9]{1,5})?(?:/[A-Za-z0-9._~:/?#\[\]@!$&()*+,;=%-]*)?)$}D';

    /** Accepted values for string overrides; a key without a rule is rejected. */
    private const STRING_RULES = [
        // Relative path or http(s) URL; no quotes, whitespace or control characters.
        'discordUrl' => self::DISCORD_URL_PATTERN,
    ];

    private function __construct()
    {
    }

    /**
     * Keeps only whitelisted keys with valid values. Unknown keys and invalid
     * values are dropped, so the result is always safe to write and apply.
     *
     * @param array<string,mixed> $input
     * @return array<string,int|bool|string>
     */
    public static function normalize(array $input): array
    {
        $clean = [];
        foreach (self::DEFINITIONS as $key => [, $type, $min, $max]) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if ($type === 'bool') {
                if (is_bool($value)) {
                    $clean[$key] = $value;
                } elseif ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
                    $clean[$key] = (string)$value === '1';
                }
                continue;
            }
            if ($type === 'string') {
                if (is_string($value) && strlen($value) <= max($max, 32)
                    && preg_match(self::STRING_RULES[$key] ?? '/^(?!)/', $value) === 1) {
                    $clean[$key] = $value;
                }
                continue;
            }
            if ((is_int($value) || (is_string($value) && preg_match('/^-?[0-9]{1,9}$/', $value) === 1))
                && (int)$value >= $min && (int)$value <= $max) {
                $clean[$key] = (int)$value;
            }
        }
        return $clean;
    }

    /** Applies normalised overrides to the runtime config object. */
    public static function apply(object $config, array $overrides): void
    {
        foreach (self::normalize($overrides) as $key => $value) {
            if (in_array($key, self::INSTALL_ONLY, true)) {
                continue;
            }
            $path = explode('.', self::DEFINITIONS[$key][0]);
            $target = $config;
            $last = array_pop($path);
            foreach ($path as $segment) {
                if (!isset($target->{$segment}) || !is_object($target->{$segment})) {
                    continue 2;
                }
                $target = $target->{$segment};
            }
            $target->{$last} = $value;
        }
    }

    /** Keys an operator can change after installation, in definition order. @return list<string> */
    public static function editable(): array
    {
        return array_values(array_diff(array_keys(self::DEFINITIONS), self::INSTALL_ONLY));
    }

    /** The value a key currently has on the runtime config object, or null when its path is missing. */
    public static function read(object $config, string $key): int|bool|string|null
    {
        $target = $config;
        foreach (explode('.', self::DEFINITIONS[$key][0]) as $segment) {
            if (!is_object($target) || !isset($target->{$segment})) {
                return null;
            }
            $target = $target->{$segment};
        }

        return is_scalar($target) ? $target : null;
    }
}
