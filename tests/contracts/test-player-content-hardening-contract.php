<?php

declare(strict_types=1);

function failPlayerContentHardeningContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$alliance = file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
$bbcode = file_get_contents($root . '/game/include/Core/Helper/BBCode.php');
$reports = file_get_contents($root . '/game/include/Controller/ReportsCtrl.php');
$reportsList = file_get_contents($root . '/game/include/resources/Templates/reports/All.php');
$reportLayout = file_get_contents($root . '/game/include/resources/Templates/reports/report_layout.php');

if (in_array(false, [$alliance, $bbcode, $reports, $reportsList, $reportLayout], true)) {
    failPlayerContentHardeningContract('Player-content hardening sources must be readable.');
}

// Alliance forum links are shown to and followed by every member, so only
// external http(s) forums may be stored, rendered, or redirected to.
if (!str_contains($alliance, 'private function externalForumLink(string $link): string')
    || !str_contains($alliance, "in_array(\$scheme, ['http', 'https'], true) && \$host !== ''")
    || substr_count($alliance, '$this->externalForumLink(') < 3
    || str_contains($alliance, 'FILTER_SANITIZE_URL')) {
    failPlayerContentHardeningContract('Alliance forum links must be limited to external http(s) URLs on save, display, and redirect.');
}

if (!str_contains($bbcode, "str_replace(['<', '>', '\"', \"'\"], ['&lt;', '&gt;', '&quot;', '&#039;'], \$input)")
    || !str_contains($bbcode, "['http', 'https'], true)")
    || !str_contains($bbcode, "html_entity_decode(\$address)")) {
    failPlayerContentHardeningContract('BBCode links must escape their href and allow only http(s) schemes.');
}

// Report bulk actions mutate a player's reports and must not be triggerable from a cross-site link
// or image: they are PUT/POST API calls (bearer-token checked by the AJAX front controller), and the
// destructive two-step ones need a nonce bound to the player and the signed scope.
$bulk = (string)file_get_contents($root . '/game/include/Controller/Ajax/report/ReportBulkAction.php');
$status = (string)file_get_contents($root . '/game/include/Controller/Ajax/report/updateStatus.php');
$ajax = (string)file_get_contents($root . '/game/include/Controller/AjaxCtrl.php');
if (!str_contains($bulk, 'PurchaseNonce::issue(') || !str_contains($bulk, 'PurchaseNonce::consume(')
    || !str_contains($bulk, "'scope' => \$scope") || !str_contains($status, "\$this->method() !== 'PUT'")
    || !str_contains($ajax, "if (!in_array(\$cmd, ['news', 'configuration'], true)) {\n            \$this->checkAjaxToken(\$response);")
    || str_contains($reports, 'validateChecker')
    || str_contains($reportsList, 'data-checker-name')
    || str_contains($reportLayout, 'getCheckerForUrl')) {
    failPlayerContentHardeningContract('Report actions must be nonce-bound API calls, not link or form posts.');
}

echo "Player content hardening contract passed.\n";
