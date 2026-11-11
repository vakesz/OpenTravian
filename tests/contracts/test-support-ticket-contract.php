<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

/** @param mixed $condition */
function assertSupportTicket($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . '/' . $path);
    assertSupportTicket(is_string($contents), 'Could not read ' . $path . '.');

    return $contents;
};

$router = $read('game/include/mainInclude.php');
$helpers = $read('game/include/functions.general.php');
$controller = $read('game/include/Controller/SupportTicketCtrl.php');
$form = $read('game/include/resources/Templates/support/support.php');
$success = $read('game/include/resources/Templates/support/success.php');
$contact = $read('game/include/Game/SupportContact.php');
$session = $read('game/include/Core/Session.php');
$schema = $read('database/world.sql');
$update = $read('game/include/update.php');
$setup = $read('game/deployment/public/setup.php') . $read('game/include/Game/Installer/InstallInput.php') . $read('game/include/Game/Installer/WorldInstaller.php');
$setupView = $read('game/deployment/include/setup-view.php');
$admin = $read('game/include/admin/include/Controllers/ConfigurationDetailsCtrl.php');

assertSupportTicket(!is_file($root . '/game/include/Controller/SupportHomeCtrl.php'), 'The retired SupportHome controller must be removed.');
assertSupportTicket(!str_contains($router, "'/support/home'"), 'The retired /support/home route must not remain.');
foreach ([
    "['/support/ticket', '/support/ticket/']",
    "\$page = 'support_ticket';",
    "'support_ticket' => 'SupportTicketCtrl'",
] as $needle) {
    assertSupportTicket(str_contains($router, $needle), 'Support ticket router is missing ' . $needle . '.');
}
assertSupportTicket(str_contains($helpers, "return '/support/ticket';"), 'All public Support controls must resolve to the local ticket route.');

foreach ([
    'Session::validateChecker()',
    "throttle_public_form('support', 5, 3600)",
    'SupportContact::email()',
    'SmtpConfig::isConfigured()',
    'Mailer::sendEmail(',
    "SessionVar::setVariable('supportTicketQueued', true)",
    "SessionVar::timeoutNow('supportTicketQueued')",
] as $needle) {
    assertSupportTicket(str_contains($controller, $needle), 'Support ticket controller is missing ' . $needle . '.');
}
foreach (['KnowledgeBase', 'searchArticles', '@gmail.com', '?success=1'] as $retired) {
    assertSupportTicket(!str_contains($controller, $retired), 'Support ticket controller retains retired or unsafe path: ' . $retired . '.');
}
assertSupportTicket(
    preg_match('/(?<![A-Za-z0-9_:])mail\\s*\\(/i', $controller) !== 1,
    'Support ticket controller must not bypass the queue with direct PHP mail().'
);

foreach ([
    'getCheckerInput()',
    '$supportUrl',
    'action="<?= $escape($supportUrl) ?>"',
    "T('Support', 'send request')",
    "T('Support', 'errors.Delivery is not configured')",
    'deliveryConfigured',
] as $needle) {
    assertSupportTicket(str_contains($form, $needle), 'Support form is missing ' . $needle . '.');
}
assertSupportTicket(
    str_contains($controller, "\$this->redirect(\$this->publicHomepageUrl('/support/ticket'))")
        && str_contains($controller, "['closeContentHref'] = \$this->publicHomepageUrl('/homepage')"),
    'Guest Support must retain a public locale after submission and return its Close control to the local homepage.'
);
assertSupportTicket(str_contains($success, "T('Support', 'Request queued')"), 'Support success must state queueing, not claim delivery.');
assertSupportTicket(
    str_contains($session, 'public function getChecker(): string') && !str_contains($session, 'Village::getInstance()->getChecker()'),
    'Guest Support forms must use the session-backed checker without dereferencing a player record.'
);
foreach (['filter_var($email, FILTER_VALIDATE_EMAIL)', "preg_match('/[\\r\\n\\0]/'", 'MAX_LENGTH = 254'] as $needle) {
    assertSupportTicket(str_contains($contact, $needle), 'Support recipient validation is missing ' . $needle . '.');
}

foreach ([
    '`supportEmail` VARCHAR(254) NOT NULL DEFAULT',
    "currentVersion < 109",
    'supportEmail` VARCHAR(254)',
    "'support_email' => getenv('TRAVIAN_SUPPORT_EMAIL') ?: ''",
    'SupportContact::isValid($values[\'support_email\'])',
    'supportEmail',
    "AdminUi::input('support_email', 'Support recipient email'",
    'saveSupportEmail',
    'supportEmailForm()',
] as $needle) {
    $haystacks = $schema . $update . $setup . $setupView . $admin;
    assertSupportTicket(str_contains($haystacks, $needle), 'Support recipient configuration is missing ' . $needle . '.');
}

foreach (['en-US.php', 'ar-AE.php', 'fa-IR.php', 'el-GR.php'] as $locale) {
    $translation = $read('game/include/resources/Translation/' . $locale);
    foreach (["'Request queued'", "'Delivery is not configured'", "'Invalid request'", "'Could not queue request'"] as $key) {
        assertSupportTicket(str_contains($translation, $key), $locale . ' is missing Support translation ' . $key . '.');
    }

    $supportStart = strpos($translation, "\$Definition['Support'] = [");
    $inGameSupportStart = strpos($translation, "\$Definition['inGameSupport'] = [");
    assertSupportTicket(
        $supportStart !== false && $inGameSupportStart !== false && $inGameSupportStart > $supportStart,
        $locale . ' must retain separate Support and in-game support catalogues.'
    );
    $supportTranslations = substr($translation, $supportStart, $inGameSupportStart - $supportStart);
    assertSupportTicket(
        preg_match("/'errors'\\s*=>\\s*\\[\\s*'please select'\\s*=>/u", $supportTranslations) === 1,
        $locale . ' must key Support.errors.please select for the ticket validation flow.'
    );
}

// The shell draws the window title in the header bar (nothing hides it, no card repeats it), the selects say "Choose",
// and the window's height counts in the layout so the footer sits below it.
$css = $read('assets/gpack/travian/mainPage/lang/overrides.css');
foreach (['support/support.php', 'support/success.php', 'legal/page.php', 'system/unsubscribe.php'] as $template) {
    assertSupportTicket(!str_contains($read('game/include/resources/Templates/' . $template), 'supportCardHeading'), $template . ' must not repeat the header title.');
}
assertSupportTicket(!str_contains($css, '.supportCardHeading') && preg_match('/body\.support[^{]*h1\.titleInHeader\s*\{[^}]*display:\s*none/', $css) !== 1, 'No rule may hide the support window title.');
assertSupportTicket(preg_match('/body\.support #center #contentOuterContainer,\s*body\.support div#center div#contentOuterContainer \{[^}]*margin-top: 104px;/', $css) === 1
    && preg_match('/body\.support #center #contentOuterContainer,\s*body\.support div#center div#contentOuterContainer \{[^}]*(?<![-\w])top:/', $css) !== 1, 'The support window is offset by a margin, not a top offset, so the footer follows it.');
foreach (['SupportTicketCtrl', 'LegalPageCtrl', 'UnsubscribeCtrl'] as $name) {
    assertSupportTicket(str_contains($read('game/include/Controller/' . $name . '.php'), "vars['titleInHeader']"), $name . ' must set its title in the header.');
}
assertSupportTicket(substr_count($form, "T('Support', 'Choose')") === 2 && !str_contains($form, 'please select'), 'Both selects must use the Choose placeholder.');
foreach (['en-US.php', 'ar-AE.php', 'fa-IR.php', 'el-GR.php'] as $locale) {
    $translation = $read('game/include/resources/Translation/' . $locale);
    assertSupportTicket(preg_match("/\n    'please select' =>/", $translation) !== 1, $locale . ' keeps a dead top-level "please select" placeholder.');
}
assertSupportTicket(substr_count($read('game/include/resources/Translation/en-US.php'), "\n    'Choose' => 'Choose',") === 2, 'en-US must define Choose for Support and inGameSupport.');

echo "Support ticket contract passed.\n";
