<?php

declare(strict_types=1);

function failReportsMessagesParityContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$enUs = file_get_contents($root . '/game/include/resources/Translation/en-US.php');
$inbox = file_get_contents($root . '/game/include/resources/Templates/messages/list.php');
$write = file_get_contents($root . '/game/include/resources/Templates/messages/write.php');
$fixes = file_get_contents($root . '/assets/gpack/travian/mainPage/lang/overrides.css');
$reports = file_get_contents($root . '/game/include/resources/Templates/reports/All.php');
$overview = file_get_contents($root . '/game/include/resources/Templates/reports/overviewLayout.php');
$overflow = file_get_contents($root . '/game/include/resources/Templates/reports/reportOverflow.php');
$model = file_get_contents($root . '/game/include/Model/ReportsModel.php');
$ctrl = file_get_contents($root . '/game/include/Controller/ReportsCtrl.php');
$timezone = file_get_contents($root . '/game/include/Core/Helper/TimezoneHelper.php');
$reportLayout = file_get_contents($root . '/game/include/resources/Templates/reports/report_layout.php');
$reportRights = file_get_contents($root . '/game/include/Controller/Ajax/report/rights.php');
$apiRoutes = file_get_contents($root . '/game/deployment/include/api_legacy_routes.php');
$crypt = file_get_contents($root . '/game/deployment/public/game-client.js');

if (in_array(false, [$enUs, $inbox, $write, $fixes, $reports, $overview, $overflow, $model, $ctrl, $reportLayout, $reportRights, $apiRoutes, $crypt, $timezone], true)) {
    failReportsMessagesParityContract('Reports/messages parity sources must be readable.');
}

foreach ([
    "\$Definition['Reports']['Inbox retention'] = 'Inbox - All reports older than seven days will be deleted.'",
    "lcfirst(T('Global', 'today')) . ', ' . self::date(\$timeFormat, \$time)",
    "self::date(self::getDateFormat() . ', ' . \$timeFormat, \$time)",
    "new PHPBatchView('reports/reportOverflow')",
] as $needle) {
    if (!str_contains($enUs . $ctrl . $timezone, $needle)) {
        failReportsMessagesParityContract("Reports must retain reference time/inbox chrome: {$needle}");
    }
}

if (!str_contains($overflow, '<table id="inbox-size-table">')
    || !str_contains($overflow, "T('Reports', 'Inbox retention')")) {
    failReportsMessagesParityContract('Reports inbox-retention footer must use the native table and current retention policy.');
}

foreach ([
    "\$Definition['Messages']['select all'] = 'Select all'",
    "\$Definition['Messages']['Received'] = 'Received'",
    "\$Definition['Messages']['There are no messages available in the sentbox'] = 'There are no sent messages available.'",
    "\$Definition['Reports']['Received'] = 'Received'",
    "\$Definition['Reports']['Mark all as read'] = 'Mark all as read'",
    "\$Definition['Reports']['Mark selected as read'] = 'Mark selected as read'",
    "\$Definition['Reports']['Delete all reports'] = 'Delete all reports'",
    "\$Definition['Reports']['Delete selected reports'] = 'Delete selected reports'",
    "\$Definition['Reports']['bulkActions'] = [",
    "'deleteAllIntro' => 'This action will delete all reports.',",
    "'dontWarnAgain' => 'Don\\'t warn me again.',",
] as $needle) {
    if (!str_contains($enUs, $needle)) {
        failReportsMessagesParityContract("en-US must include reference wording: {$needle}");
    }
}

if (!str_contains(file_get_contents($root . '/game/include/Controller/MessagesCtrl.php'), "T('Messages', 'Received')")) {
    failReportsMessagesParityContract('Messages inbox date column must use Received.');
}

if (!str_contains($inbox, 'id="overview" class="index"')) {
    failReportsMessagesParityContract('Messages inbox overview table must use class index.');
}

if (!str_contains($inbox, '<th class="send"></th>')) {
    failReportsMessagesParityContract('Messages inbox sender header must be empty chrome like reference.');
}

if (substr_count($inbox, '<div id="markAll">') !== 1 || !str_contains($inbox, 'foreach ([1, 2] as $position)')) {
    failReportsMessagesParityContract('Messages list must render the #markAll wrapper in both footers.');
}

if (!str_contains($write, 'id="message_container" class="bbEditor"')
    || !str_contains($fixes, 'div.messages.messagesWrite #message_container')
    || !str_contains($fixes, 'padding-block-end: 4px;')) {
    failReportsMessagesParityContract('Messages Write must preserve the reference composer container and its four-pixel bottom baseline.');
}

if (preg_match('/class="administration"[^>]*>\\s*<div class="footer"/', $inbox) === 1) {
    failReportsMessagesParityContract('Messages inbox must not nest bottom select-all footer inside administration.');
}

foreach (['/messages/inbox', '/messages/sent', '/messages/archive'] as $listPath) {
    if (!str_contains(file_get_contents($root . '/game/include/Controller/MessagesCtrl.php'), "'" . $listPath . "'")) {
        failReportsMessagesParityContract("Messages list must serve {$listPath} through the shared list template.");
    }
}

if (!str_contains($reports, 'T("Reports", "Received")')) {
    failReportsMessagesParityContract('Reports overview date column must use Received.');
}

foreach ([
    'Mark all as read',
    'Delete all reports',
    'data-text-all',
    'data-text-selected',
    'dialogTemplates',
    'markAsReadDialog',
    'deleteDialog',
    'handleMarkAsRead',
    'handleDelete',
    'buttonWrapper',
] as $needle) {
    if (!str_contains($reports, $needle)) {
        failReportsMessagesParityContract("Reports overview must expose reference bulk/dialog controls: missing {$needle}");
    }
}

if (str_contains($reports, '<form method') || !str_contains($reports, '<div id="reportsForm"')) {
    failReportsMessagesParityContract('Reports must render div#reportsForm (not a form).');
}

$client = (string)file_get_contents($root . '/game/deployment/public/game-client.js');
foreach (['updateSelected: function', 'processDeleteIds: function', 'new Travian.Game.TwoStepAction("report/clean-archive")', '"report/update-status"'] as $needle) {
    if (!str_contains($client, $needle)) {
        failReportsMessagesParityContract("The reports client must use the reference bulk API: missing {$needle}");
    }
}

if (!str_contains($overview, 'class="check report"')) {
    failReportsMessagesParityContract('Report row checkboxes must carry class report for selection label switching.');
}

$routes = (string)file_get_contents(dirname(__DIR__, 2) . '/game/deployment/include/api_legacy_routes.php');
foreach ([
    "'PUT report/update-status' => ['report', 'update-status']",
    "'PUT report/mark-read' => ['report', 'mark-read']",
    "'POST report/mark-read' => ['report', 'mark-read']",
    "'PUT report/delete' => ['report', 'delete']",
    "'POST report/delete' => ['report', 'delete']",
    "'PUT report/delete-bulk' => ['report', 'delete-bulk']",
    "'POST report/delete-bulk' => ['report', 'delete-bulk']",
    "'PUT report/clean-archive' => ['report', 'clean-archive']",
    "'POST report/clean-archive' => ['report', 'clean-archive']",
] as $needle) {
    if (!str_contains($routes, $needle)) {
        failReportsMessagesParityContract("The reference report bulk routes must be registered: missing {$needle}");
    }
}
if (!str_contains($model, 'function setStatus(int $uid, array $ids, string $status): void')
    || !str_contains($model, 'function markTypesAsRead(')
    || !str_contains($model, 'function deleteTypes(')
    || !str_contains($model, 'function cleanArchive(')
    || str_contains($ctrl, "mark_all_as_read")
    || str_contains($ctrl, 'toggleState')
    || str_contains($ctrl, 'private function actions()')) {
    failReportsMessagesParityContract('Report bulk actions live in the API controllers; the page controller keeps no post/query action path.');
}
foreach ([
    "'pattern' => '#^report/rights/(?P<id>[1-9][0-9]*)$#D'",
    "'target' => ['report', 'rights']",
    'final class rights extends AjaxBase',
    'getOwnedReport($reportId, $uid)',
    'updateOwnedReportPermissions($reportId, $uid, $permissionMask, $description)',
    "'right1' => RIGHT_OPPONENT_HIDE",
    "'right2' => RIGHT_MYSELF_HIDE",
    "'right3' => RIGHT_HIDE_OWN_TROOPS",
    "'right4' => RIGHT_HIDE_OPPONENT_TROOPS",
    'showPermissionsButton',
    'Travian.Game.Reports.editRights(this, <?= (int)$vars[\'reportId\']; ?>)',
    'reportButton access',
    'Travian.Game.Reports.rightsText',
] as $needle) {
    if (!str_contains($apiRoutes . $reportRights . $model . $ctrl . $reportLayout . $crypt, $needle)) {
        failReportsMessagesParityContract("Reports permission sharing must retain {$needle}");
    }
}

// The reference row icon carries the report_icon_N title (the filter titles) and the Received sort link keeps the current page.
foreach ([
    [$overview, 'T("Reports", "reportFilterTitles." . $vars[\'type\']'],
    [$reports, '?page=<?=max(1, (int)$vars[\'page\']);?>&amp;opt='],
] as [$source, $needle]) {
    if (!str_contains($source, $needle)) {
        failReportsMessagesParityContract("Report list must keep the reference behaviour: {$needle}");
    }
}

echo "Reports/messages parity contract passed.\n";
