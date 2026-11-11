<?php

declare(strict_types=1);

function failNoExternalHelpLinks(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

// Help is local: the contextual manual and local support route must not expose
// legacy Answers, external help-centre, survey, or Discord destinations.

$root = dirname(__DIR__, 2) . '/game';

$needles = [
    'getAnswersUrl',
    'AnswersUrl',
    'answersUrl',
    'go2answer',
    'spielregeln',
    'discord.gg',
    'discord.com',
];
$articleCataloguePath = 'include/resources/HomepagePublicArticleCatalog.php';
// The public article snapshot is source content, not player chrome. These are
// the exact outbound links visibly present in the two captured articles; any
// new link must be deliberately sourced and added here with its route work.
$allowedArticleSourceLinks = [
    'https://www.timeanddate.com/time/change/',
];

$skipDirs = ['vendor', 'node_modules', '.git'];
$skipExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'mp3', 'ogg', 'swf', 'zip', 'gz', 'phar', 'mo'];

/** Drops licence / attribution comment blocks so upstream notices never trip the check. */
$stripLicence = static function (string $text): string {
    return (string) preg_replace_callback(
        '~/\*.*?\*/~s',
        static fn (array $m): string => preg_match('~licen[cs]e|copyright|\(c\)|attribution|@author~i', $m[0]) ? '' : $m[0],
        $text
    );
};

$violations = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static fn (SplFileInfo $f): bool => !($f->isDir() && in_array($f->getFilename(), $skipDirs, true))
    )
);
foreach ($iterator as $file) {
    if (!$file->isFile() || in_array(strtolower($file->getExtension()), $skipExt, true)) {
        continue;
    }
    $text = file_get_contents($file->getPathname());
    if ($text === false || str_contains(substr($text, 0, 8000), "\0")) {
        continue;
    }
    $text = $stripLicence($text);
    $relative = substr($file->getPathname(), strlen($root) + 1);
    // The article snapshot and the public label catalogue are source content (the latter's German
    // "Spielregeln" is the reference Game Rules label, not a link).
    if ($relative === $articleCataloguePath || $relative === 'include/resources/Translation/HomepagePublicCatalog.json') {
        continue;
    }
    // The operator's Discord webhook validates and names its own discord.com endpoint; it is an
    // admin-only outbound integration, never a player-facing help destination.
    if (in_array($relative, ['include/Game/Webhook/WebhookConfig.php', 'include/admin/include/Controllers/WebhookCtrl.php'], true)) {
        continue;
    }
    foreach ($needles as $needle) {
        if (stripos($text, $needle) !== false) {
            $violations[] = "{$relative}: {$needle}";
        }
    }
}

$articleCatalogue = require $root . '/' . $articleCataloguePath;
if (!is_array($articleCatalogue)) {
    failNoExternalHelpLinks('The public Homepage article catalogue must return a typed snapshot.');
}
$articleLinkHrefs = [];
$collectArticleLinks = static function (mixed $value) use (&$collectArticleLinks, &$articleLinkHrefs): void {
    if (!is_array($value)) {
        return;
    }
    if (array_key_exists('href', $value)) {
        if (($value['type'] ?? null) !== 'link' || !is_string($value['href'])) {
            failNoExternalHelpLinks('Public article links must be constrained typed link parts.');
        }
        $articleLinkHrefs[] = $value['href'];
    }
    foreach ($value as $child) {
        $collectArticleLinks($child);
    }
};
$collectArticleLinks($articleCatalogue);
$articleLinkHrefs = array_values(array_unique($articleLinkHrefs));
sort($articleLinkHrefs);
sort($allowedArticleSourceLinks);
if ($articleLinkHrefs !== $allowedArticleSourceLinks) {
    failNoExternalHelpLinks('Public article content contains an unsourced external link.');
}

if ($violations !== []) {
    fwrite(STDERR, "External or legacy help links remain:\n  " . implode("\n  ", $violations) . "\n");
    exit(1);
}

// The legacy numeric answer-id scheme must be gone from the view layer.
foreach (['functions.general.php', 'resources/View/GameView.php', 'resources/Templates/layout/Layout.php'] as $rel) {
    $text = (string) file_get_contents($root . '/include/' . $rel);
    if (preg_match('~answerId|getAnswersUrl~', $text)) {
        fwrite(STDERR, "{$rel} still references the answer-id scheme.\n");
        exit(1);
    }
}

echo "No external help links under game/.\n";
