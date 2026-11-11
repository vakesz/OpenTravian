<?php

declare(strict_types=1);

function failModelInputContract(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__, 2);
$sources = [
    'profile model' => $root . '/game/include/Model/ProfileModel.php',
    'profile endpoint' => $root . '/game/include/Controller/Ajax/profile.php',
    'message model' => $root . '/game/include/Model/MessageModel.php',
    'links model' => $root . '/game/include/Model/LinksModel.php',
    'links controller' => $root . '/game/include/Controller/LinkListCtrl.php',
    'player note model' => $root . '/game/include/Model/PlayerNote.php',
    'player note endpoint' => $root . '/game/include/Controller/Ajax/alliance/playerNote.php',
];

foreach ($sources as $name => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        failModelInputContract("Could not read the $name source.");
    }
    $sources[$name] = $source;
}

foreach (['profile model', 'profile endpoint', 'message model', 'player note model', 'player note endpoint'] as $name) {
    if (str_contains($sources[$name], 'FILTER_SANITIZE_STRING')) {
        failModelInputContract("The $name must not use the deprecated string filter.");
    }
}

if (!str_contains($sources['profile endpoint'], 'is_string($description1)')
    || !str_contains($sources['profile endpoint'], 'is_string($description2)')
    || !str_contains($sources['profile endpoint'], 'is_string($location)')
    || !str_contains($sources['profile endpoint'], 'is_string($gender)')
    || !str_contains($sources['profile endpoint'], 'is_array($villageNames)')
    || !str_contains($sources['profile endpoint'], "is_string(\$village['name'])")
    || !str_contains($sources['profile endpoint'], 'updateProfileV2(')
    || !str_contains($sources['profile model'], 'public function updateProfileV2(')
    || !str_contains($sources['profile model'], 'StringChecker::isValidMessage($desc1)')
    || !str_contains($sources['profile model'], "htmlspecialchars(strip_tags(\$desc1), ENT_QUOTES, 'UTF-8')")
    || !str_contains($sources['profile model'], "htmlspecialchars(strip_tags(\$location), ENT_QUOTES, 'UTF-8')")
    || !str_contains($sources['profile model'], 'UPDATE users SET desc1=?, desc2=?, gender=?, location=?, showCountryFlag=?, additionalLanguages=? WHERE id=?')) {
    failModelInputContract('Profile and village-name updates must accept only scalar text fields.');
}

if (!str_contains($sources['message model'], "strip_tags(is_string(\$name) ? \$name : '')")) {
    failModelInputContract('Player-name lookups must accept only scalar names.');
}

if (!str_contains($sources['links model'], "filter_var(is_string(\$url) ? \$url : '', FILTER_SANITIZE_URL)")
    || substr_count($sources['links model'], 'real_escape_string($url)') !== 2
    || !str_contains($sources['links controller'], 'is_array($newPositions) && is_array($newNames) && is_array($newUrls)')) {
    failModelInputContract('Saved links must validate request shapes and escape names and URLs before storage.');
}

if (!str_contains($sources['player note model'], "strip_tags(is_string(\$text) ? \$text : '')")
    || !str_contains($sources['player note endpoint'], "is_scalar(\$_POST['affectedPlayerID'] ?? null)")
    || !str_contains($sources['player note endpoint'], 'is_string($noteText)')) {
    failModelInputContract('Player notes must accept only scalar IDs, actions, and text.');
}

echo "Model input contract passed.\n";
