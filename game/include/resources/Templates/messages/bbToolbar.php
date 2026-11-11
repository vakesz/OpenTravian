<?php
/**
 * BBCode toolbar shared by the message composer and the notepad.
 *
 * $vars: id ('message'|'notepad'|'profile') prefix of the element ids the BBEditor script binds to;
 *        class (optional) extra class of the toolbar, for example noBackground.
 */
$id = (string) $vars['id'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tagButtons = [
    ['bbBold', 'b', 'bold'],
    ['bbItalic', 'i', 'italic'],
    ['bbUnderscore', 'u', 'underline'],
    ['bbAlliance', 'alliance', 'alliance'],
    ['bbPlayer', 'player', 'player'],
    ['bbCoordinate', 'x|y', 'coordinates'],
    ['bbReport', 'report', 'report'],
];
$windowButtons = [
    ['bbResource', 'resources', 'resources'],
    ['bbSmilies', 'smilies', 'smilies'],
    ['bbTroops', 'troops', 'troops'],
];
$resourceTags = [1 => 'l', 2 => 'cl', 3 => 'ir', 4 => 'c'];
$smilies = [
    '*aha*' => 'aha', '*angry*' => 'angry', '*cool*' => 'cool', '*cry*' => 'cry', '*cute*' => 'cute',
    '*depressed*' => 'depressed', '*eek*' => 'eek', '*ehem*' => 'ehem', '*emotional*' => 'emotional',
    '*hit*' => 'hit', '*hmm*' => 'hmm', '*hmpf*' => 'hmpf', '*hrhr*' => 'hrhr', '*huh*' => 'huh',
    '*lazy*' => 'lazy', '*love*' => 'love', '*nocomment*' => 'nocomment', '*noemotion*' => 'noemotion',
    '*notamused*' => 'notamused', '*pout*' => 'pout', '*redface*' => 'redface', '*rolleyes*' => 'rolleyes',
    '*shy*' => 'shy', '*smile*' => 'smile', '*tongue*' => 'tongue', '*veryangry*' => 'veryangry',
    '*veryhappy*' => 'veryhappy', ':D' => 'grin', ':(' => 'sad', ':)' => 'happy', ';)' => 'wink',
];
$troopTag = static function (string $tag, string $unitClass, string $title) use ($escape): string {
    return '<a href="#" class="bbType{o} bbTag{' . $tag . '}"><img class="unit ' . $unitClass . '" src="/img/spacer.gif" title="'
        . $escape($title) . '" alt="' . $escape($title) . '"></a>';
};
?>
<div id="<?= $id; ?>_toolbar" class="bbToolbar<?= isset($vars['class']) ? ' ' . $escape($vars['class']) : ''; ?>">
<?php foreach ($tagButtons as [$class, $tag, $title]): ?>
<button type="button" class="icon bbButton <?= $class; ?> bbType{d} bbTag{<?= $tag; ?>}" title="<?= $escape(T('Messages', 'bb.' . $title)); ?>" useicon=""><img src="/img/spacer.gif" class="<?= $class; ?>"></button>
<?php endforeach; ?>
<?php foreach ($windowButtons as [$class, $window, $title]): ?>
<button type="button" id="<?= $id . '_' . $window; ?>Button" class="icon bbButton <?= $class; ?> bbWin{<?= $window; ?>}" title="<?= $escape(T('Messages', 'bb.' . $title)); ?>" useicon=""><img src="/img/spacer.gif" class="<?= $class; ?>"></button>
<?php endforeach; ?>
<button type="button" id="<?= $id; ?>_previewButton" class="icon bbButton bbPreview" title="<?= $escape(T('Messages', 'bb.preview')); ?>" useicon=""><img src="/img/spacer.gif" class="bbPreview"></button>
<div id="<?= $id; ?>_toolbarWindows" class="bbToolbarWindow">
<div id="<?= $id; ?>_resources"><?php foreach ($resourceTags as $resource => $tag): ?><a href="#" class="bbType{s} bbTag{[<?= $tag; ?>]}"><i class="r<?= $resource; ?>" title="<?= $escape(T('inGame', 'resources.r' . $resource)); ?>"></i></a><?php endforeach; ?></div>
<div id="<?= $id; ?>_smilies"><?php foreach ($smilies as $tag => $class): ?><a href="#" class="bbType{s} bbTag{<?= $escape($tag); ?>}"><img class="smiley <?= $class; ?>" src="/img/spacer.gif" title="<?= $escape($tag); ?>" alt="<?= $escape($tag); ?>"></a><?php endforeach; ?></div>
<div id="<?= $id; ?>_troops">
<?php
foreach ([...range(1, 30), ...range(51, 80)] as $unit) {
    echo $troopTag('tid' . $unit, 'u' . $unit, T('Troops', $unit . '.title'));
}
echo "<hr>\n";
foreach (range(31, 50) as $unit) {
    echo $troopTag('tid' . $unit, 'u' . $unit, T('Troops', $unit . '.title'));
}
echo "<hr>\n";
echo $troopTag('hero', 'uhero', T('Troops', '98.title'));
?>
<hr>
</div>
</div>
</div>
