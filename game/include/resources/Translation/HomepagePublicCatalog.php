<?php

declare(strict_types=1);

/*
 * Self-hosted public start-page locale snapshot: loads the readable
 * HomepagePublicCatalog.json beside it (the 48 public language options and the
 * homepage values of the 37 distinct source catalogues). Public language
 * selection stays on this world and does not change the game session locale.
 */
$catalogue = json_decode(
    (string) file_get_contents(__DIR__ . '/HomepagePublicCatalog.json'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
if (!is_array($catalogue)) {
    throw new \RuntimeException('The public homepage locale snapshot has an invalid shape.');
}

return $catalogue;
