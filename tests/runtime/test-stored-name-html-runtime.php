<?php

declare(strict_types=1);

// Village (and other player-entered) names are stored HTML-escaped; the surfaces that escape on output must not
// escape them a second time ("&amp;amp;"). stored_name_html() decodes first, so stored-escaped and raw names both
// come out single-escaped, with the characters & < > " ' covered.

require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

$cases = [
    // stored (what the rename handler writes: an apostrophe becomes a backtick) => expected HTML text
    'Tom &amp; Jerry' => 'Tom &amp; Jerry',
    '&quot;Quoted&quot; name' => '&quot;Quoted&quot; name',
    'a &lt; b &gt; c' => 'a &lt; b &gt; c',
    'it`s &amp; &quot;fine&quot; &lt;ok&gt;' => 'it`s &amp; &quot;fine&quot; &lt;ok&gt;',
    '15-001' => '15-001',
    // raw legacy values
    'Tom & Jerry' => 'Tom &amp; Jerry',
    'say "hi" <b>' => 'say &quot;hi&quot; &lt;b&gt;',
    "it's" => 'it&#039;s',
];
foreach ($cases as $stored => $expected) {
    $actual = stored_name_html((string)$stored);
    if ($actual !== $expected) {
        fwrite(STDERR, "stored_name_html('$stored') = '$actual', expected '$expected'.\n");
        exit(1);
    }
    if (stored_name_html($actual) !== $actual) {
        fwrite(STDERR, "stored_name_html must be idempotent: '$stored'.\n");
        exit(1);
    }
}

// The surfaces that print a village name through the helper.
$root = '/travian/game/include';
foreach ([
    'resources/Templates/layout/sidebarBoxActiveVillages.php',
    'resources/View/GameView.php',
    'Game/Hero/HeroHud.php',
    'resources/Templates/build/building/MarketplaceSendResources.php',
    'resources/Templates/village-center/rearrange.php',
] as $file) {
    $source = (string)file_get_contents("$root/$file");
    if (!str_contains($source, 'stored_name_html(')) {
        fwrite(STDERR, "$file must print village names through stored_name_html().\n");
        exit(1);
    }
}
if (preg_match('/htmlspecialchars\(\(string\)\$vars\[.villageName.\]/', (string)file_get_contents("$root/resources/Templates/layout/sidebarBoxActiveVillages.php")) === 1) {
    fwrite(STDERR, "The sidebar village name must not be escaped a second time.\n");
    exit(1);
}

echo "Stored name HTML runtime test passed.\n";
