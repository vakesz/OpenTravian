<?php

declare(strict_types=1);

// The time-zone select is ordered like the reference's: by the UTC offset in force now, the configured order
// within an offset, with the current offset in each label.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Timezone options runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Helper\TimezoneHelper;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$zones = [
    'America/Nassau' => '(UTC-04:00) Detroit, Havana, New York, Toronto',
    'Europe/Andorra' => '(UTC+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna',
    'Pacific/Rarotonga' => '(UTC-10:00) Honolulu, Rarotonga, Tahiti',
    'America/Adak' => '(UTC-09:00) Adak',
];
$winter = TimezoneHelper::timezoneOptions($zones, gmmktime(12, 0, 0, 11, 8, 2026));
if (array_keys($winter) !== ['Pacific/Rarotonga', 'America/Adak', 'America/Nassau', 'Europe/Andorra']) {
    $fail('Zones must follow the offset in force now (Adak is on UTC-10:00 in November): ' . implode(', ', array_keys($winter)));
}
if ($winter['America/Nassau'] !== '(UTC-05:00) Detroit, Havana, New York, Toronto' || $winter['America/Adak'] !== '(UTC-10:00) Adak') {
    $fail('The label must carry the current offset.');
}
$summer = TimezoneHelper::timezoneOptions($zones, gmmktime(12, 0, 0, 7, 8, 2026));
if (array_keys($summer) !== ['Pacific/Rarotonga', 'America/Adak', 'America/Nassau', 'Europe/Andorra'] || $summer['America/Nassau'] !== '(UTC-04:00) Detroit, Havana, New York, Toronto') {
    $fail('In summer the zones follow their summer offsets.');
}
$all = TimezoneHelper::timezoneOptions((array) \Core\Config::getInstance()->timezones);
if (count($all) !== count((array) \Core\Config::getInstance()->timezones)) {
    $fail('No zone may be lost.');
}
// The configured order is the reference's own (captured 2026-11-08); the ties it breaks are not derivable
// from the offsets, so the ones that used to differ are pinned.
$november = array_keys(TimezoneHelper::timezoneOptions((array) \Core\Config::getInstance()->timezones, gmmktime(12, 0, 0, 11, 8, 2026)));
$position = static fn (string $zone): int => (int) array_search($zone, $november, true);
if (!($position('America/Eirunepe') < $position('America/Nassau') && $position('America/Nassau') < $position('America/Winnipeg') && $position('America/Winnipeg') < $position('Pacific/Easter'))) {
    $fail('The UTC-05:00 zones must be in the reference order: Eirunepe, Nassau, Winnipeg, Easter.');
}
if (!($position('Atlantic/Stanley') < $position('America/Miquelon') && $position('America/Miquelon') < $position('America/Asuncion'))) {
    $fail('The UTC-03:00 zones must be in the reference order: Stanley, Miquelon, Asuncion.');
}
echo "Timezone options runtime passed.\n";
