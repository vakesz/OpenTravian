<?php

declare(strict_types=1);

/*
 * An item's auction list narrows to one stack size like the reference (the 5x, 10x, 30x and 50x buttons after the
 * "all batch sizes" one): the state class owns the sizes, the page filters by them, the preference is accepted and
 * cleared, and the script asks for the refresh.
 */

function failBatchSize(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

$state = $read('game/include/Game/Hero/AuctionListState.php');
$controller = $read('game/include/Controller/HeroAuctionCtrl.php');
$template = $read('game/include/resources/Templates/hero/AuctionDetail.php');
$preferences = $read('game/include/Core/Helper/PreferencesHelper.php');
$script = $read('game/deployment/public/auction-lists.js');
$translation = $read('game/include/resources/Translation/en-US.php');

if (!str_contains($state, 'BATCH_SIZES = [5, 10, 30, 50]')
    || !str_contains($state, "'auctionsBuyItemTypeDetail_batchSize'")) {
    failBatchSize('The stack sizes and their preference must be the reference ones.');
}
if (!str_contains($controller, 'AuctionListState::batchSize()') || !str_contains($controller, "'batchSize' => (string)(")) {
    failBatchSize('The item-type page must filter by the chosen size and report it in its state.');
}
if (!str_contains($template, 'batchSize_all') || !str_contains($template, 'data-auction-batch')
    || !str_contains($template, "T('Auction', 'all batch sizes')")) {
    failBatchSize('The item-type list must render the stack-size buttons.');
}
if (!str_contains($preferences, 'AuctionListState::PREFERENCE_BATCH_SIZE')) {
    failBatchSize('The stack-size preference must be accepted and cleared.');
}
if (!str_contains($script, 'onBatch') || !str_contains($script, 'batchPref')) {
    failBatchSize('The script must refresh the list and store the stack size.');
}
if (!str_contains($translation, "['Auction']['all batch sizes']") || !str_contains($translation, "['Auction']['batch size title'] = '%s item batches'")) {
    failBatchSize('The reference titles of the stack-size buttons are missing.');
}

echo "Auction batch size contract passed.\n";
