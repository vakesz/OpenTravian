<?php

declare(strict_types=1);

function failGpackStaticAssetContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * CSS-relative URLs are checked separately. This covers literal GPack paths
 * emitted by PHP templates and client-side code, where a miss would otherwise
 * only show up as a broken image in a specific UI state.
 */
$repositoryRoot = dirname(__DIR__, 2);
$mainPage = $repositoryRoot . '/assets/gpack/travian/mainPage';
$sourceRoots = [
    $repositoryRoot . '/game',
    $repositoryRoot . '/services',
    $mainPage,
];
$extensions = ['css', 'js', 'php'];
$assetPattern = '~(?:img_ltr|img_rtl)/[A-Za-z0-9_./-]+\.(?:png|jpe?g|gif|svg|webp)~i';
$assets = [];

foreach ($sourceRoots as $sourceRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            failGpackStaticAssetContract('GPack source must be readable: ' . $file->getPathname());
        }
        if (preg_match_all($assetPattern, $source, $matches) !== false) {
            foreach ($matches[0] as $assetPath) {
                $assets[$assetPath] = true;
            }
        }
    }
}

$missing = [];
foreach (array_keys($assets) as $assetPath) {
    if (!is_file($mainPage . '/' . $assetPath)) {
        $missing[] = $assetPath;
    }
}
sort($missing);

if ($missing !== []) {
    failGpackStaticAssetContract(
        "Every literal GPack asset path must exist.\n" . implode("\n", $missing)
    );
}

/**
 * imports.css is the CDN gpack/692 file. The repository stores it
 * formatted (dev/asset-format/format.mjs, prettier) and the web image re-minifies
 * it, so the contract pins both the SHA-256 of the original reference bytes (kept
 * for provenance) and of the stored formatted file. Equivalence of the two is
 * proven by dev/asset-format/verify-equivalence.mjs. Local-only rules belong in
 * lang/overrides.css. Provenance: ASSET_PROVENANCE.md.
 */
$referenceImports = [
    'css_ltr' => [
        'reference' => '6a45f2d91413f7a96af897cb1f9e74889b4fba5b43bd7368ad599fd271fb2e25',
        'stored' => 'a296bdecb449d452d58b01f3aaf871cafef0008080d8f2e9bf5e42d50e892778',
    ],
    'css_rtl' => [
        'reference' => '13562ae7e140b1283cc547ee700b36fb59f21bd60d0000b3c8400983900c8224',
        'stored' => '3ccde40f7aa296b80e08ba115bc6e02795a7a94a33de8d1fcea875d78c00f7dd',
    ],
];
foreach ($referenceImports as $directory => $hashes) {
    $importsPath = $mainPage . '/' . $directory . '/imports.css';
    $actualHash = is_file($importsPath) ? hash_file('sha256', $importsPath) : false;
    if ($actualHash !== $hashes['stored']) {
        failGpackStaticAssetContract(
            sprintf(
                "%s/imports.css must equal the recorded formatted gpack/692 reference (expected %s, got %s). "
                . "Re-run dev/asset-format/format.mjs on a re-synced file and update the pinned hashes (original reference %s).",
                $directory,
                $hashes['stored'],
                $actualHash === false ? 'missing' : $actualHash,
                $hashes['reference']
            )
        );
    }
}

echo sprintf("GPack static asset contract passed (%d paths).\n", count($assets));
