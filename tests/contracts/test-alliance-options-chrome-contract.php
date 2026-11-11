<?php

declare(strict_types=1);

function failAllianceOptionsChromeContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
if ($controller === false) {
    failAllianceOptionsChromeContract('Could not read AllianceCtrl.php.');
}

$templates = [
    'invite' => $root . '/game/include/resources/Templates/alliance/optionInvite.php',
    'name' => $root . '/game/include/resources/Templates/alliance/optionChangeName.php',
    'kick' => $root . '/game/include/resources/Templates/alliance/optionKick.php',
];

foreach ($templates as $key => $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        failAllianceOptionsChromeContract("Could not read the {$key} options template.");
    }
    $templates[$key] = $contents;
}

if (file_exists($root . '/game/include/resources/Templates/alliance/optionKickConfirm.php')) {
    failAllianceOptionsChromeContract('Obsolete optionKickConfirm.php password path must be deleted.');
}

$showOptionsPos = strpos($controller, 'private function showAllianceOptions()');
$appendPos = strpos($controller, "\$this->view->vars['contentCssClass'] .= ' allianceOptions';", $showOptionsPos === false ? 0 : $showOptionsPos);
$menusPos = strpos($controller, 'private function showAllianceOptionMenus()', $showOptionsPos === false ? 0 : $showOptionsPos);
if ($showOptionsPos === false || $appendPos === false || $menusPos === false || $appendPos > $menusPos) {
    failAllianceOptionsChromeContract('showAllianceOptions() must append allianceOptions at the start of the options flow.');
}

if (!str_contains($controller, "\$this->getPostPlainText('newTag')")
    || !str_contains($controller, "\$this->getPostPlainText('newName')")
    || !str_contains($controller, "\$this->getPostInt('kickPlayer')")
    || !str_contains($controller, "\$this->getPostString('confirmation_kick')")) {
    failAllianceOptionsChromeContract('AllianceCtrl must read newTag/newName and kickPlayer/confirmation_kick.');
}

foreach (['invite' => '/alliance/options/invite', 'name' => '/alliance/options/name', 'kick' => '/alliance/options/kick'] as $key => $action) {
    if (!str_contains($templates[$key], 'textButtonV1')
        || !str_contains($templates[$key], $action)
        || str_contains($templates[$key], 'button-container addHoverClick')) {
        failAllianceOptionsChromeContract("{$key} template must use textButtonV1, {$action}, and no legacy button-container chrome.");
    }
}

if (str_contains($templates['invite'], 'roundV2')
    || !str_contains($templates['invite'], 'h4 class="round"')
    || str_contains($templates['invite'], 'name="a"')
    || str_contains($templates['invite'], 'name="s1"')) {
    failAllianceOptionsChromeContract('Invite template must use h4.round without hidden a/s1.');
}

if (str_contains($templates['name'], '<h4')
    || !str_contains($templates['name'], 'name="newTag"')
    || !str_contains($templates['name'], 'name="newName"')
    || !str_contains($templates['name'], 'id="SettingsFormular"')
    || str_contains($templates['name'], 'name="ally1"')
    || str_contains($templates['name'], 'name="ally2"')) {
    failAllianceOptionsChromeContract('Name template must use newTag/newName, SettingsFormular, and no h4.');
}

if (!str_contains($templates['kick'], 'h4 class="round"')
    || !str_contains($templates['kick'], 'name="kickPlayer"')
    || !str_contains($templates['kick'], 'name="confirmation_kick"')
    || !str_contains($templates['kick'], 'class="info option"')
    || str_contains($templates['kick'], 'name="a_user"')
    || str_contains($templates['kick'], 'name="pw"')) {
    failAllianceOptionsChromeContract('Kick template must use kickPlayer + confirmation_kick single-form chrome.');
}

echo "Alliance options chrome contract passed.\n";
