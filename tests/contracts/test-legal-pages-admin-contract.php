<?php

declare(strict_types=1);

function failLegalAdmin(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$ctrl = $read('game/include/admin/include/Controllers/LegalPagesCtrl.php');

foreach ([
    'Session::validateChecker()' => 'check the CSRF token before saving',
    'Session::getCheckerInput()' => 'carry the CSRF token in every form',
    'LegalPages::save(' => 'save through LegalPages',
    'LegalPages::isValidUrl(' => 'validate the https URL',
    'LegalPages::MAX_BODY_LENGTH' => 'limit the Markdown length',
    'AdminUi::esc(' => 'escape echoed values',
    'AdminLog::getInstance()->addLog(' => 'log changes',
    'LegalPages::SOURCE' => 'edit the source-repository URL',
] as $needle => $why) {
    str_contains($ctrl, $needle) || failLegalAdmin("LegalPagesCtrl must {$why}.");
}
foreach (['LegalPages::RULES', 'LegalPages::TERMS', 'LegalPages::IMPRINT', 'LegalPages::PRIVACY'] as $page) {
    str_contains($ctrl, $page) || failLegalAdmin("LegalPagesCtrl must edit {$page}.");
}
// Posted values reach the page only through AdminUi helpers (which escape), never echoed raw.
if (preg_match('/(echo|print)\s*\(?\s*\$_(POST|GET|REQUEST)|\.\s*\$_(POST|GET|REQUEST)\[/', $ctrl) === 1) {
    failLegalAdmin('LegalPagesCtrl must not concatenate request values into HTML.');
}
str_contains($read('game/include/Game/Admin/AdminMenu.php'), "action=legalPages'")
    || failLegalAdmin('The admin menu must link the legal pages editor.');
(!str_contains($read('game/include/admin/include/Core/RequestGuard.php'), "'legalpages'"))
    || failLegalAdmin('Saving is POST-only; legalpages must not be a mutating GET.');

echo "Legal pages admin contract passed.\n";
