<?php

declare(strict_types=1);

// A new auction lasts floor(24 / speed) hours, never under one hour: the vendor table's 24 / 12 / 8 / 4 / 2 h at
// x1 / x2 / x3 / x5 / x10. Checks the rule at every band and that the booted world wires it into the auction
// configuration and the fake-auction end time.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Auction duration runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Core\Config;
use Game\WorldSpeedProfile;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Auction duration runtime failed: ' . $message . "\n");
    exit(1);
};

foreach ([1 => 24, 2 => 12, 3 => 8, 4 => 6, 5 => 4, 6 => 4, 10 => 2, 11 => 2, 12 => 2, 13 => 1, 20 => 1, 100 => 1, 1000000 => 1] as $speed => $hours) {
    if (WorldSpeedProfile::auctionDuration($speed) !== $hours * 3600) {
        $fail("x{$speed} must last {$hours} h, got " . WorldSpeedProfile::auctionDuration($speed) / 3600 . ' h.');
    }
}
$previous = PHP_INT_MAX;
foreach (range(1, 60) as $speed) {
    $duration = WorldSpeedProfile::auctionDuration($speed);
    if ($duration > $previous) {
        $fail("the auction length rises from x" . ($speed - 1) . " to x{$speed}.");
    }
    $previous = $duration;
}

$config = Config::getInstance();
$worldSpeed = (int)$config->game->speed;
if ((int)$config->auction->auctionTime !== WorldSpeedProfile::auctionDuration($worldSpeed)) {
    $fail("the booted world at x{$worldSpeed} carries auctionTime {$config->auction->auctionTime}, expected " . WorldSpeedProfile::auctionDuration($worldSpeed) . '.');
}

echo "Auction duration runtime passed (floor(24 / speed) hours, one hour at the least, world x{$worldSpeed} wired).\n";
