<?php

declare(strict_types=1);

function failKnowledgeBaseRemoval(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        failKnowledgeBaseRemoval("Cannot read {$path}.");
    }
    return $source;
};

foreach ([
    'assets/kb',
    'game/include/Controller/KnowledgeBaseCtrl.php',
    'game/include/Game/KnowledgeBase.php',
    'game/include/resources/Templates/knowledgebase/page.php',
    'game/include/Controller/HelpCtrl.php',
    'game/include/resources/Templates/help/dialog.php',
    'tools/fetch-knowledge-base.php',
    // These legacy FAQ illustrations had no remaining caller after the
    // generic Knowledge Base flow was removed.
    'game/deployment/public/img/un/h/faq_vp.jpg',
    'game/deployment/public/img/un/h/faq_botschaft.jpg',
] as $removedPath) {
    if (file_exists($root . '/' . $removedPath)) {
        failKnowledgeBaseRemoval("Retired Knowledge Base artifact remains: {$removedPath}.");
    }
}

foreach ([
    'game/include/mainInclude.php',
    'game/include/functions.general.php',
    'docker/nginx/default.conf.template',
    'docker/nginx/Dockerfile',
    'tools/README.md',
] as $path) {
    $source = $read($path);
    foreach (['/knowledgebase', 'assets/kb', 'KnowledgeBaseCtrl', 'knowledgeBaseUrl'] as $needle) {
        if (str_contains($source, $needle)) {
            failKnowledgeBaseRemoval("{$path} retains retired Knowledge Base wiring: {$needle}.");
        }
    }
}

$translationPaths = glob($root . '/game/include/resources/Translation/*.php') ?: [];
foreach ($translationPaths as $translationPath) {
    $translation = (string) file_get_contents($translationPath);
    if (str_contains($translation, '/knowledgebase/')) {
        failKnowledgeBaseRemoval(basename($translationPath) . ' still links to the retired Knowledge Base.');
    }
    if (str_contains($translation, "\$Definition['Help']")
        || str_contains($translation, "\$Definition['inGame']['Help']")) {
        failKnowledgeBaseRemoval(basename($translationPath) . ' retains the retired generic Help catalogue.');
    }
}

foreach ([
    'game/include/Controller/MessagesCtrl.php',
    'game/include/resources/Templates/messages/showMsg.php',
    'game/include/resources/Templates/verify/email.php',
] as $path) {
    $source = $read($path);
    if (str_contains($source, 'knowledgeBaseUrl(') || !str_contains($source, 'getSupportUrl()')) {
        failKnowledgeBaseRemoval("{$path} must target local Support instead of the retired Knowledge Base.");
    }
}

// imports.css remains byte-identical to the reference Gpack. Local
// KB styling belonged in the mutable local override and must be gone there.
$localCss = $read('assets/gpack/travian/mainPage/lang/overrides.css');
if (stripos($localCss, 'knowledgebase') !== false) {
    failKnowledgeBaseRemoval('The local Gpack override retains retired Knowledge Base CSS.');
}

// Contextual manual routes replace the generic Help shell. Nothing else in the
// runtime may surface the retired generic destination, including premium
// dialogs and daily-quest answer buttons.
$gameDirectory = new RecursiveDirectoryIterator($root . '/game', FilesystemIterator::SKIP_DOTS);
$gameSources = new RecursiveIteratorIterator($gameDirectory);
foreach ($gameSources as $sourceFile) {
    if (!$sourceFile->isFile() || $sourceFile->getExtension() !== 'php') {
        continue;
    }

    $path = substr($sourceFile->getPathname(), strlen($root) + 1);
    $source = (string)file_get_contents($sourceFile->getPathname());
    if (str_contains($source, 'help.php')) {
        failKnowledgeBaseRemoval("{$path} exposes the retired generic Help destination.");
    }
}

$dailyQuestModel = $read('game/include/Model/DailyQuestModel.php');
$dailyQuestTemplate = $read('game/include/resources/Templates/dailyQuest/questachivements.php');
if (str_contains($dailyQuestModel, 'answersLink')
    || str_contains($dailyQuestTemplate, 'answersLink')) {
    failKnowledgeBaseRemoval('Daily quests must keep their native payload shape without a generic Help action.');
}

$manualRoutes = $read('game/deployment/include/api_legacy_routes.php');
foreach ([
    "#^manual/0/0$#D",
    "#^manual/troop/(?P<id>[1-9][0-9]*)$#D",
    "#^manual/building/(?P<id>[1-9][0-9]*)$#D",
] as $route) {
    if (!str_contains($manualRoutes, $route)) {
        failKnowledgeBaseRemoval("Contextual manual route disappeared: {$route}.");
    }
}
foreach ([
    'game/include/Controller/Ajax/manual/overview.php',
    'game/include/Controller/Ajax/manual/building.php',
    'game/include/Controller/Ajax/manual/troop.php',
    'game/include/Game/Manual/ManualContent.php',
    'game/include/resources/Templates/manual/overview.php',
    'game/include/resources/Templates/manual/building.php',
    'game/include/resources/Templates/manual/unit.php',
] as $path) {
    if (!is_file($root . '/' . $path)) {
        failKnowledgeBaseRemoval("Contextual manual artifact disappeared: {$path}.");
    }
}

echo "Knowledge Base removal contract passed.\n";
