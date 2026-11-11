<?php

declare(strict_types=1);

$controller = file_get_contents(dirname(__DIR__, 2) . '/game/include/Controller/Ajax/infobox.php');
$model = file_get_contents(dirname(__DIR__, 2) . '/game/include/Model/InfoBoxModel.php');

if ($controller === false
    || !str_contains($controller, 'setInfoBoxItemAsDeleted')
    || !str_contains($controller, 'Invalid infobox message')) {
    fwrite(STDERR, "The infobox delete endpoint must validate and delete only requested messages.\n");
    exit(1);
}

if ($model === false
    || !str_contains($model, 'self::invalidateUserInfoBoxCache($uid);')
    || preg_match('/return\\s+true;/i', $model) !== 1) {
    fwrite(STDERR, "Deleting infobox messages must invalidate the sidebar cache.\n");
    exit(1);
}

echo "Infobox delete API contract passed.\n";
