<?php

declare(strict_types=1);

// Trade Office raises merchant capacity by 10% of the tribe base per level;
// the Roman Trade Office is twice as effective (Travian support article 213).
$root = dirname(__DIR__, 2);
$formulas = file_get_contents($root . '/game/include/Game/Formulas.php');

if ($formulas === false
    || !str_contains($formulas, 'public static function TradeOfficeValue($race, $lvl)')
    || !str_contains($formulas, 'return 100 + $lvl * ($race == 1 ? 20 : 10);')
    || !str_contains($formulas, 'self::TradeOfficeValue($race, $tradeOfficeLevel) / 100')
    || str_contains($formulas, '($bid18 * 10)')) {
    fwrite(STDERR, "Merchant capacity must apply the tribe-specific Trade Office bonus (Romans 20%/level, others 10%/level).\n");
    exit(1);
}

$callers = shell_exec('grep -rn "TradeOfficeValue(" ' . escapeshellarg($root . '/game/include') . ' 2>/dev/null') ?: '';
foreach (explode("\n", trim($callers)) as $line) {
    if ($line !== '' && !str_contains($line, 'function TradeOfficeValue') && !preg_match('/TradeOfficeValue\([^,()]+(\([^()]*\))?,/', $line)) {
        fwrite(STDERR, "TradeOfficeValue callers must pass the player's tribe: {$line}\n");
        exit(1);
    }
}

echo "Trade Office capacity contract passed.\n";
