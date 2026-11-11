<?php

declare(strict_types=1);

function failNightMoodSystemThemeContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * Small source-coupled DOM/media mock for the inline system-theme script.
 * The caller obtains both theme literals and listener wiring from the rendered
 * Layout source before driving this harness, so a change to the client
 * branch, either target attribute, or its live listener fails the contract.
 */
final class SystemThemeScriptMock
{
    /** @var array<string, string> */
    private array $attributes = [];

    private bool $changeListenerBound = false;

    public function __construct(
        private readonly string $darkTheme,
        private readonly string $lightTheme,
    ) {
    }

    public function boot(bool $prefersDark): void
    {
        $this->applyTheme($prefersDark);
    }

    public function bindChangeListener(): void
    {
        $this->changeListenerBound = true;
    }

    public function emitPreferenceChange(bool $prefersDark): void
    {
        if (!$this->changeListenerBound) {
            throw new \LogicException('The system-theme listener has not been bound.');
        }

        $this->applyTheme($prefersDark);
    }

    /** @return array{html: string, body: string} */
    public function attributes(): array
    {
        return [
            'html' => $this->attributes['html'],
            'body' => $this->attributes['body'],
        ];
    }

    private function applyTheme(bool $prefersDark): void
    {
        $theme = $prefersDark ? $this->darkTheme : $this->lightTheme;
        $this->attributes['html'] = $theme;
        $this->attributes['body'] = $theme;
    }
}

$root = dirname(__DIR__, 2);

$nightMoodPath = $root . '/game/include/Core/Helper/NightMood.php';
$layoutPath = $root . '/game/include/resources/Templates/layout/Layout.php';
$headPath = $root . '/game/include/resources/Templates/layout/head.php';
$optionsPath = $root . '/game/include/resources/Templates/options/Game.php';
$translationPath = $root . '/game/include/resources/Translation/en-US.php';

foreach (
    [
        'NightMood helper' => $nightMoodPath,
        'layout' => $layoutPath,
        'head' => $headPath,
        'options' => $optionsPath,
        'translation' => $translationPath,
    ] as $label => $path
) {
    if (!is_readable($path)) {
        failNightMoodSystemThemeContract("Could not read the $label source at $path.");
    }
}

$nightMood = file_get_contents($nightMoodPath);
$layout = file_get_contents($layoutPath);
$head = file_get_contents($headPath);
$options = file_get_contents($optionsPath);
$translation = file_get_contents($translationPath);

if ($nightMood === false || $layout === false || $head === false || $options === false || $translation === false) {
    failNightMoodSystemThemeContract('Failed to load one or more night-mood sources.');
}

if (!str_contains($nightMood, '?bool $prefersDark = null')) {
    failNightMoodSystemThemeContract('NightMood::theme must accept an optional prefersDark flag.');
}

require_once $nightMoodPath;

if (!method_exists(\Core\Helper\NightMood::class, 'themeForPage')) {
    failNightMoodSystemThemeContract('NightMood must resolve the guest-page default separately from an authenticated player opt-in.');
}

if (\Core\Helper\NightMood::themeForPage(false, false, false, 0) !== 'default') {
    failNightMoodSystemThemeContract('An explicit system-light preference must override guest night hours.');
}

if (\Core\Helper\NightMood::themeForPage(false, false, true, 12) !== 'night') {
    failNightMoodSystemThemeContract('An explicit system-dark preference must select night mood.');
}

if (\Core\Helper\NightMood::themeForPage(false, false, null, 0) !== 'night') {
    failNightMoodSystemThemeContract('Server rendering without a system preference must retain the guest night-hours fallback.');
}

if (\Core\Helper\NightMood::themeForPage(true, false, false, 0) !== 'default') {
    failNightMoodSystemThemeContract('An authenticated player without the night-hours preference must keep the default theme.');
}

if (!str_contains($layout, 'NightMood::themeForPage(')) {
    failNightMoodSystemThemeContract('The shared layout must use NightMood::themeForPage for guest pages.');
}

if (!str_contains($head, 'get_gpack_link_and_hash("night_mood.css", false, $documentDirection)')) {
    failNightMoodSystemThemeContract('night_mood.css must always be linked from the layout head with the selected document direction.');
}

if (preg_match('/if\s*\(\s*\(int\)\s*\(\s*\$headDisplay\[6\]/', $head) === 1) {
    failNightMoodSystemThemeContract('night_mood.css must not be gated on display[6] alone.');
}

if (!str_contains($head, 'name="color-scheme"')) {
    failNightMoodSystemThemeContract('The layout head must advertise light/dark color-scheme support.');
}

if (!str_contains($layout, "prefers-color-scheme: dark")) {
    failNightMoodSystemThemeContract('The layout must apply night mood from prefers-color-scheme: dark.');
}

if (!str_contains($layout, "? 'night' : 'default'")) {
    failNightMoodSystemThemeContract('The layout must let an explicit system-light preference override night hours.');
}

if (!str_contains($layout, 'data-night-mood-time-based')) {
    failNightMoodSystemThemeContract('The layout must expose whether the current page uses the server-time night mood.');
}

if (str_contains($layout, 'data-night-mood-opt-in')) {
    failNightMoodSystemThemeContract('The obsolete opt-in-only client attribute must not leave guest pages locked to the daytime theme.');
}

$themeScriptStart = strpos($layout, 'function resolveTheme()');
$themeScriptEnd = $themeScriptStart === false
    ? false
    : strpos($layout, '</script>', $themeScriptStart);
if ($themeScriptStart === false || $themeScriptEnd === false) {
    failNightMoodSystemThemeContract('The layout must retain an inspectable inline system-theme script.');
}
$themeScript = substr($layout, $themeScriptStart, $themeScriptEnd - $themeScriptStart);

if (!preg_match(
    "/return\\s+window\\.matchMedia\\('\\(prefers-color-scheme: dark\\)'\\)\\.matches\\s*\\?\\s*'(?<dark>[a-z]+)'\\s*:\\s*'(?<light>[a-z]+)'\\s*;/",
    $themeScript,
    $themeMatch,
)) {
    failNightMoodSystemThemeContract('The client system-theme branch must directly map dark and light media states.');
}
if (!preg_match(
    "/function\\s+applyTheme\\s*\\(\\)\\s*\\{\\s*var\\s+theme\\s*=\\s*resolveTheme\\(\\);\\s*document\\.documentElement\\.setAttribute\\('data-theme',\\s*theme\\);\\s*body\\.setAttribute\\('data-theme',\\s*theme\\);/s",
    $themeScript,
)) {
    failNightMoodSystemThemeContract('The client theme application must write the resolved theme to both html and body.');
}

$initialApplyOffset = strpos($themeScript, 'applyTheme();');
$modernListenerOffset = strpos($themeScript, "media.addEventListener('change', applyTheme);");
$legacyListenerOffset = strpos($themeScript, "media.addListener(applyTheme);");
if ($initialApplyOffset === false
    || $modernListenerOffset === false
    || $legacyListenerOffset === false
    || $initialApplyOffset > $modernListenerOffset
    || $initialApplyOffset > $legacyListenerOffset) {
    failNightMoodSystemThemeContract('The client theme script must apply once, then listen for both modern and legacy OS changes.');
}

// Drive the source-derived dark/light literals through a mock DOM and media
// change event. This protects initial system preference and live OS switches
// without relying on a JavaScript runtime inside the PHP contract container.
$themeMock = new SystemThemeScriptMock($themeMatch['dark'], $themeMatch['light']);
$themeMock->boot(false);
if ($themeMock->attributes() !== ['html' => 'default', 'body' => 'default']) {
    failNightMoodSystemThemeContract('A system-light initial render must update html and body to the default theme.');
}
$themeMock->bindChangeListener();
$themeMock->emitPreferenceChange(true);
if ($themeMock->attributes() !== ['html' => 'night', 'body' => 'night']) {
    failNightMoodSystemThemeContract('A live system-dark change must update html and body to night theme.');
}
$themeMock->emitPreferenceChange(false);
if ($themeMock->attributes() !== ['html' => 'default', 'body' => 'default']) {
    failNightMoodSystemThemeContract('A live system-light change must restore html and body to the default theme.');
}

echo "Night mood system theme contract passed.\n";
