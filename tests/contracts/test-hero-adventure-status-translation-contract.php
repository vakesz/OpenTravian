#!/usr/bin/env php
<?php

declare(strict_types=1);

function failHeroAdventureStatusTranslationContract(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/game/deployment/include/env.php';
require_once $root . '/game/include/bootstrap.php';

$sessionHero = file_get_contents($root . '/game/include/Game/Hero/SessionHero.php');
if ($sessionHero === false) {
    failHeroAdventureStatusTranslationContract('Unable to read SessionHero.');
}

if (!str_contains($sessionHero, '$adventureInventoryStatus = T("HeroGlobal", "inventoryStatus.adventure");')
    || !str_contains($sessionHero, 'vsprintf($adventureInventoryStatus, $adventureInventoryStatusValues)')
    || !str_contains($sessionHero, "if (substr_count(\$adventureInventoryStatus, '%s') === 4)")
    || !str_contains($sessionHero, 'Formulas::kid2xy($find[\'to_kid\'])')
    || !str_contains($sessionHero, 'href="/karte.php?x=')) {
    failHeroAdventureStatusTranslationContract('Adventure inventory status must support both the current and the established Persian placeholder shapes.');
}

$expectedLead = [
    // hero.json: status50_generic + heroArrival, which has no final period.
    'en-US' => 'Your hero is on its way to an adventure. Arrival in %s at %s',
    // hero.json: status50_generic + heroArrival, retrieved 2026-11-06.
    'ar-AE' => 'بطلك في طريقه إلى مغامرة. سيصل البطل خلال %s عند الساعة %s',
    'el-GR' => 'Ο ήρωάς σας βρίσκεται στον δρόμο προς μια περιπέτεια. Άφιξη σε %s στις %s',
    // does not publish a fa-IR hero.json; retain the established local Persian copy unchanged.
    'fa-IR' => 'قهرمان شما در حال ماجراجویی %s است. رسیدن تا %s در %s.',
];

$expectedPlaceholderCount = [
    'en-US' => 3,
    'ar-AE' => 3,
    'el-GR' => 3,
    'fa-IR' => 4,
];

foreach ($expectedLead as $locale => $lead) {
    $GLOBALS['Definition'] = [];
    $catalog = require $root . '/game/include/resources/Translation/' . $locale . '.php';
    $format = $catalog['HeroGlobal']['inventoryStatus']['adventure'] ?? null;
    if (!is_string($format)) {
        failHeroAdventureStatusTranslationContract("{$locale} is missing HeroGlobal.inventoryStatus.adventure.");
    }
    if (!str_starts_with($format, $lead)) {
        failHeroAdventureStatusTranslationContract("{$locale} adventure inventory status must retain its verified lead copy.");
    }
    $placeholderCount = $expectedPlaceholderCount[$locale];
    if (substr_count($format, '%s') !== $placeholderCount) {
        failHeroAdventureStatusTranslationContract("{$locale} adventure inventory status must retain its verified placeholder shape.");
    }
    if (!str_contains($format, 'href="/build.php?gid=16&amp;tt=1&amp;newdid=%s"')) {
        failHeroAdventureStatusTranslationContract("{$locale} adventure inventory status must retain the root-absolute rally-point route.");
    }

    try {
        vsprintf($format, $placeholderCount === 4
            ? ['(1|1)', '00:01:23', '12:34', '1']
            : ['00:01:23', '12:34', '1']);
    } catch (ArgumentCountError $error) {
        failHeroAdventureStatusTranslationContract("{$locale} adventure inventory status is not sprintf-compatible: {$error->getMessage()}");
    }
}

echo "Hero adventure status translation contract passed.\n";
