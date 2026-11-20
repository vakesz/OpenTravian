<?php

declare(strict_types=1);

/*
 * Entry points that run without the autoloader (the setup wizard before installation, operator tools) require game
 * classes one by one. When such a class starts using a named-constant class (Core\Time, Game\Gid, ...), the entry
 * point must load that class too, or it fails with "Class not found" only when the code path runs. This contract
 * reads each entry point's direct requires, loads them in a clean process without the autoloader, and checks that
 * every constant class the loaded files reference is loaded.
 */

$root = dirname(__DIR__, 2);

/** Classes that hold only constants and need no autoloader: the ones standalone entry points may have to preload. */
const CONSTANT_CLASSES = [
    'Core\\Time', 'Game\\Gid', 'Game\\Unit', 'Game\\Tribes', 'Game\\OasisType', 'Game\\UserId', 'Game\\AccessLevel',
    'Game\\Slot', 'Game\\ResearchMode', 'Game\\Hero\\ItemBtype', 'Game\\Hero\\ItemType',
    'Game\\Conquest\\CaptureResult', 'Game\\Conquest\\OasisCaptureResult',
];

$entries = ['game/deployment/public/setup.php', 'tools/set-world-speed.php', 'tools/preflight.php'];

/** @return list<string> game/include files the entry requires directly, in order */
function directRequires(string $root, string $entry): array
{
    $source = (string) file_get_contents("$root/$entry");
    $files = [];
    // Literal paths: '/game/include/X.php' (tools) or '/include/X.php' (deployment, relative to game/).
    // A foreach over class names joined with $gameInclude (the setup wizard) expands to include/Game/<name>.php.
    preg_match_all("~'(/game)?/include/([A-Za-z/]+\.php)'|foreach \(\[([^\]]+)\] as \\\$gameClass\)~", $source, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        if (!empty($match[3])) {
            preg_match_all("~'([A-Za-z/]+)'~", $match[3], $names);
            foreach ($names[1] as $name) {
                $files[] = "game/include/Game/$name.php";
            }
            continue;
        }
        $relative = $match[2];
        if ($relative === 'bootstrap.php' || !str_contains($relative, '/')) {
            continue; // bootstrap.php brings the autoloader; flat files (env.php) are not classes
        }
        $files[] = "game/include/$relative";
    }

    return array_values(array_unique($files));
}

/** @return list<string> constant classes the file references, resolved through its namespace and use imports */
function referencedConstantClasses(string $path): array
{
    $source = (string) file_get_contents($path);
    $namespace = preg_match('~^namespace\s+([^;]+);~m', $source, $m) ? $m[1] : '';
    $imports = [];
    preg_match_all('~^use\s+([A-Za-z\\\\]+)(?:\s+as\s+(\w+))?;~m', $source, $uses, PREG_SET_ORDER);
    foreach ($uses as $use) {
        $short = $use[2] ?? '';
        $imports[$short !== '' ? $short : substr((string) strrchr('\\' . $use[1], '\\'), 1)] = $use[1];
    }
    preg_match_all('~(?<![\w\\\\$])(\\\\?[A-Z][A-Za-z\\\\]*)::~', $source, $refs);
    $found = [];
    foreach (array_unique($refs[1]) as $ref) {
        if (str_starts_with($ref, '\\')) {
            $class = substr($ref, 1);
        } else {
            $first = explode('\\', $ref)[0];
            $class = isset($imports[$first])
                ? $imports[$first] . substr($ref, strlen($first))
                : ($namespace !== '' ? "$namespace\\$ref" : $ref);
        }
        if (in_array($class, CONSTANT_CLASSES, true)) {
            $found[] = $class;
        }
    }

    return array_values(array_unique($found));
}

$failures = [];
foreach ($entries as $entry) {
    $files = directRequires($root, $entry);
    if ($files === []) {
        $failures[] = "$entry: no direct requires found (has the loading changed? update this contract)";
        continue;
    }
    $needed = [];
    foreach ($files as $file) {
        if (!is_file("$root/$file")) {
            $failures[] = "$entry requires missing $file";
            continue;
        }
        foreach (referencedConstantClasses("$root/$file") as $class) {
            $needed[$class][] = $file;
        }
    }
    // Load the same files in a clean process (no autoloader) and report which needed classes are not declared.
    $script = 'foreach (' . var_export(array_map(static fn(string $f): string => "$root/$f", $files), true) . ' as $f) { require_once $f; }'
        . ' foreach (' . var_export(array_keys($needed), true) . ' as $c) { if (!class_exists($c, false)) { echo $c, "\n"; } }';
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -r ' . escapeshellarg($script) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $failures[] = "$entry: loading its direct requires failed: " . implode(' ', $output);
        continue;
    }
    foreach (array_filter($output) as $missing) {
        $failures[] = "$entry does not load $missing, which " . implode(', ', array_unique($needed[$missing])) . ' uses';
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Standalone entry dependencies contract passed.\n";
