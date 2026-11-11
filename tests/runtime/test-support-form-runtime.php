<?php

declare(strict_types=1);

// The support form's Username and Email come from the signed-in account on the server (never from the request), and a
// signed-out visitor gets empty fields.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Support form runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/support/ticket';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/support/ticket';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\SupportTicketCtrl;
use Core\Database\DBI;
use Core\Helper\WebService;
use Core\Session;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$field = static function (string $html, string $id): string {
    return preg_match('/<input id="' . $id . '"[^>]*value="([^"]*)"/', $html, $match) === 1 ? html_entity_decode($match[1]) : '#missing';
};
$render = static function (): string {
    $controller = new SupportTicketCtrl();

    return (string)$controller->view->vars['content'];
};

// Signed out: empty fields, even when the request tries to name an account or an address.
$_GET = ['username' => 'someone', 'email' => 'someone@example.com'];
$_POST = [];
$html = $render();
if ($field($html, 'support_username') !== '' || $field($html, 'support_email') !== '') {
    $fail('A signed-out visitor must get empty Username and Email fields.');
}

try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Support form runtime passed for a visitor (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$pdo = DBI::getInstance();
$pdo->run('UPDATE users SET email=? WHERE id=?', ['zzfx-support@example.com', $user['id']]);
$name = (string)$pdo->fetchScalar('SELECT name FROM users WHERE id=?', [$user['id']]);
$_SESSION[WebService::fixSessionPrefix('uid')] = $user['id'];
$_SESSION[WebService::fixSessionPrefix('pw')] = $user['password'];
(new ReflectionProperty(Session::class, '_self'))->setValue(null, null);
Session::getInstance();

// Signed in: both fields come from the account, whatever the request says.
$_GET = ['username' => 'someone', 'email' => 'someone@example.com'];
$html = $render();
if ($field($html, 'support_username') !== $name || $field($html, 'support_email') !== 'zzfx-support@example.com') {
    $fail('A signed-in player must get their own Username and Email: ' . json_encode([$field($html, 'support_username'), $field($html, 'support_email')]));
}
if (!str_contains($html, '>Choose</option>') || str_contains($html, 'please select</option>')) {
    $fail('The selects must say Choose.');
}

echo "Support form runtime passed.\n";
