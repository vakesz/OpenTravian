<?php

declare(strict_types=1);

// The profile editor's BB toolbar works like the reference's, and the options page orders its time zones through
// TimezoneHelper::timezoneOptions (tests/runtime/test-timezone-options-runtime.php pins the order).

$root = dirname(__DIR__, 2);

function expectProfileOptions(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$edit = (string) file_get_contents($root . '/game/include/resources/Templates/profile/EditProfile.php');
expectProfileOptions(str_contains($edit, "PHPBatchView::render('messages/bbToolbar', ['id' => 'profile', 'class' => 'noBackground'])") && str_contains($edit, "new Travian.Game.BBEditor('profile', false)"), 'The profile editor has the working BB toolbar.');
expectProfileOptions(str_contains($edit, "jQuery('#profile_previewButton').off('click')") && str_contains($edit, "jQuery('<div class=\"preview\"></div>')") && str_contains($edit, "editor.textArea.trigger('input')"), 'The preview shows both descriptions in place of their fields and tags count towards the length, like the reference.');
expectProfileOptions(str_contains($edit, 'editor.textArea = jQuery(this);'), 'The toolbar works on the description that was last used.');
expectProfileOptions(!str_contains($edit, 'bbWin{'), 'No placeholder window classes are left on the editor buttons.');
expectProfileOptions(str_contains((string) file_get_contents($root . '/game/include/Controller/Ajax/bb.php'), "case 'profile':"), 'The preview answers for the profile editor.');
expectProfileOptions(str_contains((string) file_get_contents($root . '/game/include/resources/Templates/profile/Profile.php'), 'alt="<?= T("HeroInventory", "Body") ?>"'), 'The hero body picture has its reference alt.');
expectProfileOptions(str_contains((string) file_get_contents($root . '/game/include/resources/Templates/options/Game.php'), 'TimezoneHelper::timezoneOptions('), 'The options page lists the zones through timezoneOptions().');

echo "Profile and options reference contract passed.\n";
