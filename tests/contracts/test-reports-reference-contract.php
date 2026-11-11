<?php

declare(strict_types=1);

use Game\Hero\HeroItemTile;

// Reports markup the reference shows and the 2026-11-08 walk matched: the locked Archive tab sends a player
// without the Gold club to /report, an adventure row carries the reference tooltips, and the bounty of an
// adventure report is drawn as the item tile.

$root = dirname(__DIR__, 2);
require_once $root . '/game/include/Game/Hero/HeroItems.php';
require_once $root . '/game/include/Game/Hero/HeroItemTile.php';

function expectReportsReference(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$reports = (string) file_get_contents($root . '/game/include/Controller/ReportsCtrl.php');
expectReportsReference(str_contains($reports, "if (\$selectedTabIndex == 5 && !\$this->session->hasGoldClub()) {\n            // The reference sends a player without the Gold club from the locked Archive tab to /report.\n            WebService::redirect('/report');"), 'The locked Archive tab redirects to /report.');
expectReportsReference(str_contains($reports, "T('Reports', 'Resources') . ': ' . (array_sum(\$bounty) - 2)") && str_contains($reports, "' (' . \"\\u{202D}\\u{202D}\" . \$this->number_format(\$bounty[3]) . \"\\u{202C}×\\u{202C}\" ."), 'Adventure rows carry the reference tooltips: "Resources: n" and "Item (n×)" with its bidi isolates.');
expectReportsReference(str_contains($reports, 'HeroItemTile::standalone((int) $bounty[1], (int) $bounty[2], (int) $bounty[3])'), 'The bounty of an adventure report is the item tile.');
expectReportsReference(str_starts_with(trim((string) file_get_contents($root . '/game/include/resources/Templates/reports/report_layout.php')), '<?php') && str_contains((string) file_get_contents($root . '/game/include/resources/Templates/reports/report_layout.php'), "<div class=\"navigationSpacer\"></div>\n<div id=\"reportWrapper\">"), 'A report opens after the tab strip spacer, like the list pages.');

$tile = HeroItemTile::standalone(11, 106, 20);
expectReportsReference(str_starts_with($tile, '<div id="standaloneItem"><div class="heroItem consumable neutral bag heroItemV1"><svg')
    && str_contains($tile, '<div class="item item106 neutral"></div><div class="count">20</div></div></div>'), 'The tile is the reference StandaloneItem markup.');
// Reference report 22909367 (bounty 1 Artwork) draws the count 1 and the bare item name. The earlier needle that a
// single item has no count was not backed by an opened report; 'Ointment (n×)' is the report list row tooltip only.
expectReportsReference(str_contains(HeroItemTile::standalone(11, 106, 1), '<div class="count">1</div>'), 'A single item shows the count 1.');
expectReportsReference(str_contains($reports, "(int) \$bounty[3])\n                                . ' ' . \$item['name'];"), 'The bounty cell prints the bare item name.');
expectReportsReference(str_contains(HeroItemTile::standalone(3, 31, 1), 'tier1'), 'Equipment is a tier tile, not a consumable.');

$layout = (string) file_get_contents($root . '/game/include/resources/Templates/reports/report_layout.php');
expectReportsReference(str_contains($layout, "            <?php endif; ?>\n        </div>\n        <div class=\"clear\"></div>\n    </div>\n    <div class=\"body\">"), 'The header clear sits after the tool list, as in the reference.');
expectReportsReference(!str_contains((string) file_get_contents($root . '/game/include/Controller/HeroAuctionCtrl.php'), 'private const ARROWS_SVG'), 'The auction pages share the tile arrows.');

echo "Reports reference contract passed.\n";
