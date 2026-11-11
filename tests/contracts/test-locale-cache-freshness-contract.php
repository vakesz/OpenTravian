<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__, 2) . '/game/include/Core/Locale.php');

if ($source === false
    || !str_contains($source, '$cachedLocale = $cache->get("Language:$locale");')
    || !str_contains($source, '$signature = implode(\',\', array_map(static fn(string $file): int => (int)filemtime($file), $files));')
    || !str_contains($source, "\$cachedLocale['signature'] !== \$signature")
    || !str_contains($source, "\$cache->set(\"Language:\$locale\", ['signature' => \$signature, 'catalog' => \$this->Locale], 2 * 3600);")) {
    fwrite(STDERR, "Locale cache entries must refresh when any translation layer changes.\n");
    exit(1);
}

if (preg_match('/if \(function_exists\(\'opcache_invalidate\'\) && ini_get\(\'opcache\.validate_timestamps\'\)\) \{\s*opcache_invalidate\(\$file, true\);\s*\}\s*\$catalog = require \$file;/', $source) !== 1) {
    fwrite(STDERR, "Locale::loadFile must invalidate opcache before requiring a catalogue when timestamps are validated (dev), or a stale compiled file is cached under a fresh mtime signature; production, without validation, must not force a recompile.\n");
    exit(1);
}

echo "Locale cache freshness contract passed.\n";
