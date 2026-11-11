<?php

declare(strict_types=1);

// Registration claims a village atomically, names are UNIQUE, and a lost race is handled.

$root = dirname(__DIR__, 2);
$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$read = static fn (string $path): string => (string)file_get_contents($root . '/' . $path);

$register = $read('game/include/Model/RegisterModel.php');
if (!str_contains($register, 'WHERE kid=$kid AND occupied=0') || !str_contains($register, '$db->affectedRows() !== 1')) {
    $fail('generateBase() must claim a village with UPDATE ... AND occupied=0 and check the affected rows.');
}
if (preg_match('/\bsubstr\(\$name|\bsubstr\(trim\(\$name/', $register)) {
    $fail('Player names must be cut with mb_substr().');
}
if (!str_contains($register, 'if (!$inserted)')) {
    $fail('addUser() must return false when the UNIQUE users.name insert fails.');
}
if (!str_contains($read('game/include/Model/AllianceModel.php'), 'getCode() === 1062')) {
    $fail('createAlliance() must handle a duplicate-key error.');
}
foreach (['Controller/AllianceCtrl.php', 'Controller/Build/EmbassyCtrl.php'] as $file) {
    if (preg_match('/\bstrlen\(\$view->vars\[\'(name|tag)\'\]\)/', $read('game/include/' . $file))) {
        $fail("{$file} must measure names with mb_strlen().");
    }
}
$schema = $read('database/world.sql');
$alidata = substr($schema, (int)strpos($schema, 'CREATE TABLE `alidata`'), 4000);
$users = substr($schema, (int)strpos($schema, 'CREATE TABLE IF NOT EXISTS `users`'), 12000);
$alidataDefinition = substr($alidata, 0, (int)strpos($alidata, 'ENGINE'));
if (!str_contains($alidataDefinition, 'UNIQUE KEY `tag` (`tag`)')) {
    $fail('alidata must declare UNIQUE KEY tag.');
}
if (str_contains($alidataDefinition, 'UNIQUE KEY `name`')) {
    $fail('Alliance names may repeat: only the tag is unique.');
}
if (!str_contains(substr($users, 0, (int)strpos($users, 'ENGINE')), 'UNIQUE KEY `name` (`name`)')) {
    $fail('users must declare UNIQUE KEY name.');
}

echo "Registration uniqueness contract passed.\n";
