<?php

declare(strict_types=1);

/**
 * Contract: Preferences language selection uses the full-locale wire
 * value, but only persists a configured locale after a valid session checker.
 */
function failOptionsLanguagePersistenceContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$template = file_get_contents($root . '/game/include/resources/Templates/options/Game.php');
$controller = file_get_contents($root . '/game/include/Controller/OptionCtrl.php');

if ($template === false || $controller === false) {
    failOptionsLanguagePersistenceContract('Unable to read Options language persistence sources.');
}

foreach ([
    'name="language"',
    '\\Core\\Session::getCheckerInput()',
    '$selectedLocale = get_locale();',
    'htmlspecialchars($locale, ENT_QUOTES, \'UTF-8\')',
    '$locale === $selectedLocale',
] as $needle) {
    if (!str_contains($template, $needle)) {
        failOptionsLanguagePersistenceContract('Options Game template is missing language-persistence fragment: ' . $needle);
    }
}

if (str_contains($template, '$key == $L') || str_contains($template, '$key .')) {
    failOptionsLanguagePersistenceContract('Options language options must submit configured full locales, not short configuration keys.');
}

foreach ([
    'if (!Session::validateChecker())',
    "\$this->languageKeyForLocale(\$_POST['language'] ?? null)",
    'private function languageKeyForLocale(mixed $locale): ?string',
    'Config::getInstance()->settings->availableLanguages',
    'if ($language->locale === $locale)',
    '$currentLanguage = $this->session->getLanguage();',
    'if ($languageKey !== null && $languageKey !== $currentLanguage)',
    '$this->session->setLanguage($languageKey);',
    '$this->redirect(\'/options/game\');',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        failOptionsLanguagePersistenceContract('OptionCtrl is missing language-persistence safeguard: ' . $needle);
    }
}

$csrfCheck = strpos($controller, 'if (!Session::validateChecker())');
$firstMutation = strpos($controller, 'Session::getInstance()->setReportFilters(');
if ($csrfCheck === false || $firstMutation === false || $csrfCheck > $firstMutation) {
    failOptionsLanguagePersistenceContract('The Game-tab session checker must run before preference mutation.');
}

$resolver = strpos($controller, '$this->languageKeyForLocale($_POST[\'language\'] ?? null)');
$persist = strpos($controller, '$this->session->setLanguage($languageKey);');
if ($resolver === false || $persist === false || $resolver > $persist) {
    failOptionsLanguagePersistenceContract('Posted locale must be resolved to a configured key before Session::setLanguage persists it.');
}

$session = file_get_contents($root . '/game/include/Core/Session.php');
if ($session === false
    || !str_contains($session, 'public function setLanguage($Lang)')
    || !str_contains($session, 'private function isInstalledLanguage(mixed $language): bool')
    || str_contains($session, '$_REQUEST[\'lang\']')
    || str_contains($session, 'geoip_country_code_by_name')
    || str_contains($session, 'detectLang')) {
    failOptionsLanguagePersistenceContract('Session language must come from the stored preference or a validated cookie, never from a request parameter, and only setLanguage() may write it.');
}

$getStart = (int) strpos($session, 'public function getLanguage()');
$getLanguage = substr($session, $getStart, (int) strpos($session, 'public function setLanguage(') - $getStart);
if (str_contains($getLanguage, 'setLanguage(') || str_contains($getLanguage, 'setcookie(')) {
    failOptionsLanguagePersistenceContract('Reading the session language must not change state.');
}

echo "Options language persistence contract passed.\n";
