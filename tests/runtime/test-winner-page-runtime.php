<?php

declare(strict_types=1);

// A finished world shows the winner page instead of the game. Controller\WinnerCtrl pours the Global.ServerFinishWinner
// (finish result 1) and Global.ServerFinishNoWinner (result 2) catalogue strings through vsprintf, so a bare "%" in either
// string (the credits line's "font-size:60%") aborts every game page with "ValueError: Unknown format specifier". This
// renders the page for both results against fixture rows in a transaction that is rolled back, and the live finish flag is
// only set in memory. It also fails any catalogue string that mixes a placeholder with a bare "%", the shape that breaks
// the same way for the other formatted strings.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Winner page runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';

use Controller\WinnerCtrl;
use Core\Config;
use Core\Database\DB;

$fail = static function (string $message): never {
    fwrite(STDERR, 'Winner page runtime failed: ' . $message . "\n");
    exit(1);
};

// Catalogue strings that take arguments must not hold a bare percent sign (write "%%").
$translationFiles = array_merge(glob('/travian/game/include/resources/Translation/*.php') ?: [], glob('/travian/game/include/resources/Translation/generated/*.php') ?: []);
foreach ($translationFiles as $file) {
    if (basename($file) === 'locales.php') {
        continue;
    }
    foreach (token_get_all((string)file_get_contents($file)) as $token) {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $text = eval('return ' . $token[1] . ';');
        if (!is_string($text) || !preg_match('/%(?:\d+\$)?[sdfu]/', str_replace('%%', '', $text))) {
            continue;
        }
        try {
            vsprintf($text, array_fill(0, 30, 'x'));
        } catch (Throwable $error) {
            $fail(basename($file) . ' line ' . $token[2] . ' mixes a placeholder with a bare "%": ' . $error->getMessage());
        }
    }
}

$db = DB::getInstance();
$dynamic = Config::getInstance()->dynamic;
$wasFinished = $dynamic->serverFinished;
$wonderKid = 4000000;   // no such village: the rows belong to nobody and are rolled back anyway
$playerId = 4000000;

$db->begin_transaction();
try {
    $db->run('UPDATE fdata SET f99 = 0 WHERE f99 = 100');
    $db->run("INSERT INTO users (id, name, password, race, kid, desc1, desc2, note, aid) VALUES (?, ?, '', 1, ?, '', '', '', 0)", [$playerId, 'zzfxWinner', $wonderKid]);
    $db->run('INSERT INTO vdata (kid, owner, fieldtype, name, capital, pop, cp, maxstore, maxcrop, created, expandedfrom) VALUES (?, ?, 1, ?, 0, 0, 0, 0, 0, 0, 0)', [$wonderKid, $playerId, 'zzfxWonder']);
    $db->run('INSERT INTO fdata (kid, f99) VALUES (?, 100)', [$wonderKid]);

    foreach ([1 => 'zzfxWonder', 2 => null] as $result => $villageName) {
        $dynamic->serverFinished = $result;
        $cssClass = '';
        $content = '';
        try {
            new WinnerCtrl($cssClass, $content);
        } catch (Throwable $error) {
            $fail("finish result {$result} threw " . get_class($error) . ': ' . $error->getMessage());
        }
        if (!str_contains($content, 'class="sysmsg"') || $cssClass !== 'sysmsg') {
            $fail("finish result {$result} did not render the winner page");
        }
        if (str_contains($content, '%s') || str_contains($content, '%%')) {
            $fail("finish result {$result} left an unformatted placeholder in the page");
        }
        if (!str_contains($content, 'font-size:60%;')) {
            $fail("finish result {$result} lost the credits line's literal percent sign");
        }
        if ($villageName !== null && !str_contains($content, $villageName)) {
            $fail("finish result {$result} does not name the Wonder village");
        }
    }
} finally {
    $dynamic->serverFinished = $wasFinished;
    $db->rollback();
}

echo "Winner page runtime passed.\n";
