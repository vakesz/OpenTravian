<?php

declare(strict_types=1);

/**
 * Contract: without the premium feature the reference does not hide its pages behind a redirect to the
 * map: the crop finder keeps its page shell with an empty content area (no Gold club), and the link list
 * sends accounts without OpenTravian Plus back to the resource view.
 */
function failReferenceGatedPagesContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$cropfinder = file_get_contents($root . '/game/include/Controller/CropfinderCtrl.php');
$linklist = file_get_contents($root . '/game/include/Controller/LinkListCtrl.php');
if ($cropfinder === false || $linklist === false) {
    failReferenceGatedPagesContract('Cannot read the gated page controllers.');
}

$gate = strpos($cropfinder, '!$this->session->hasGoldClub()');
$title = strpos($cropfinder, "titleInHeader'] = T('cropFinder'");
if ($gate === false || $title === false || $gate > $title
    || !preg_match('/hasGoldClub\(\)\) \{\s*(\/\/[^\n]*\n\s*)*return;/', $cropfinder)
    || str_contains($cropfinder, "redirect('/karte.php')")) {
    failReferenceGatedPagesContract('Without Gold club the crop finder must return its empty shell before building any content.');
}

if (!preg_match("/hasPlus\(\)\) \{\s*\\\$this->redirect\('\/dorf1\.php'\);/", $linklist)) {
    failReferenceGatedPagesContract('The link list must redirect accounts without Plus to /dorf1.php.');
}

echo "Reference gated pages contract passed.\n";
