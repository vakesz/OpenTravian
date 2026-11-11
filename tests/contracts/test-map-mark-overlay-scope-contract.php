<?php

declare(strict_types=1);

// A map mark overlay is cached per viewer. Its key must keep uid and alliance id apart (uid 12 in
// alliance 3 is not uid 1 in alliance 23) and include the flag settings, which change the image.
// Alliance flags are rows with uid=0 and aid=<alliance> (Ajax/map/multiMark.php), personal flags
// have aid=0, so the alliance condition applies only to a player in an alliance and only to uid=0
// rows; otherwise "aid=0" would show every player's personal flags.

$root = dirname(__DIR__, 2);
$source = (string)file_get_contents($root . '/game/include/Controller/MapMarkCtrl.php');
$writer = (string)file_get_contents($root . '/game/include/Controller/Ajax/map/multiMark.php');
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (!str_contains($source, '_u{$own_uid}_a{$own_aid}_f"')
    || !str_contains($source, "(int)\$mapMarkSettings['ownMarks'] . (int)\$mapMarkSettings['allianceMarks'];")) {
    $fail('The map mark cache key must separate uid and alliance id and include both flag settings.');
}
if (!str_contains($writer, "VALUES (0,\$ownerId,\$targetId,'',\$color,\$storedType)")
    || !str_contains($writer, "VALUES (\$ownerId,0,\$targetId,'',\$color,\$storedType)")) {
    $fail('Flags are expected as personal (aid=0, uid=player) or alliance (aid=alliance, uid=0) rows; revisit the overlay condition.');
}
if (!str_contains($source, "if (\$mapMarkSettings['allianceMarks'] && \$own_aid > 0) {")
    || !str_contains($source, "\$cond[] = '(uid=0 AND aid=' . \$own_aid . ')';")
    || str_contains($source, "\$cond[] = 'aid=' . \$own_aid;")) {
    $fail('Alliance flags must be read only for a player in an alliance, and only from alliance (uid=0) rows.');
}
if (!str_contains($source, '$flags = $cond === [] ? [] : $db->query(')) {
    $fail('The overlay must not query flags when no flag setting applies.');
}

echo "Map mark overlay scope contract passed.\n";
