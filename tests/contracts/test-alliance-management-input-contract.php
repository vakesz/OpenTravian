<?php

declare(strict_types=1);

function failAllianceManagementInputContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/game/include/Controller/AllianceCtrl.php');
if ($controller === false) {
    failAllianceManagementInputContract('Could not read the Alliance controller.');
}

$templates = [
    'alliance descriptions' => $root . '/game/include/resources/Templates/alliance/optionChangeDesc.php',
    'alliance internal page' => $root . '/game/include/resources/Templates/alliance/changeInternalPage.php',
    'alliance name' => $root . '/game/include/resources/Templates/alliance/optionChangeName.php',
    'alliance forum link' => $root . '/game/include/resources/Templates/alliance/optionLinkToForum.php',
];

foreach ($templates as $name => $path) {
    $template = file_get_contents($path);
    if ($template === false) {
        failAllianceManagementInputContract("Could not read the $name template.");
    }
    $templates[$name] = $template;
}

if (str_contains($controller, 'FILTER_SANITIZE_STRING')
    || !str_contains($controller, 'private function getPostPlainText(string $key): string')
    || !str_contains($controller, 'real_escape_string($description1)')
    || !str_contains($controller, 'real_escape_string($info1)')
    || !str_contains($controller, 'real_escape_string($link)')
    || !str_contains($controller, "\$kickPlayerId = \$this->getPostInt('kickPlayer');")
    || !str_contains($controller, "\$this->getPostPlainText('newTag')")
    || !str_contains($controller, "\$this->getPostPlainText('newName')")
    || substr_count($templates['alliance descriptions'], "htmlspecialchars(\$vars['desc") !== 2
    || substr_count($templates['alliance internal page'], "htmlspecialchars(\$vars['info") !== 2
    || !str_contains($templates['alliance name'], "htmlspecialchars(\$vars['tag'], ENT_QUOTES, 'UTF-8')")
    || !str_contains($templates['alliance name'], 'name="newTag"')
    || !str_contains($templates['alliance name'], 'name="newName"')
    || !str_contains($templates['alliance forum link'], "htmlspecialchars(\$vars['f_link'], ENT_QUOTES, 'UTF-8')")) {
    failAllianceManagementInputContract('Alliance management must validate request types and encode reflected form values.');
}

echo "Alliance management input contract passed.\n";
