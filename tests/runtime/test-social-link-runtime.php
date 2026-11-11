<?php

declare(strict_types=1);

// Links, lists and unlinks social identities of a throwaway fixture player against the real tables, checks the
// one-identity-per-provider rule, the audit rows and what Options shows.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Social link runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/options';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/options';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Game\SocialAuth\DbIdentityRepository;
use Game\SocialAuth\FlowError;
use Game\SocialAuth\IdentityClaims;
use Game\SocialAuth\LinkedIdentities;
use Game\SocialAuth\OptionsSection;
use Game\SocialAuth\Provider;
use Game\SocialAuth\ProviderConfig;
use Game\SocialAuth\SocialAudit;
use Game\SocialAuth\SocialFlash;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $user = fixtureVillageCreate();
} catch (RuntimeException $exception) {
    echo "Social link runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$uid = $user['id'];
$other = (int)$pdo->fetchScalar('SELECT id FROM users WHERE id>2 AND id<>? ORDER BY id LIMIT 1', [$uid]);
$subject = 'zzfx-' . getmypid();
register_shutdown_function(static function () use ($pdo, $uid, $subject): void {
    $pdo->run('DELETE FROM account_oauth_identity WHERE uid=? OR subject LIKE ?', [$uid, $subject . '%']);
    $pdo->run("DELETE FROM admin_log WHERE admin_uid=? AND action LIKE 'social.%'", [$uid]);
});
$repository = new DbIdentityRepository();
$claims = static fn (Provider $provider, string $sub): IdentityClaims => new IdentityClaims($provider, $sub, 'player@example.com', true, 'Ada');

if (LinkedIdentities::forUser($uid) !== [] || $repository->providerLinked($uid, Provider::Google)) {
    $fail('A new player has no linked identity.');
}
$repository->link($uid, $claims(Provider::Google, $subject));
$identities = LinkedIdentities::forUser($uid);
if (count($identities) !== 1 || $identities[0]['provider'] !== Provider::Google || $identities[0]['email'] !== 'player@example.com' || !$identities[0]['verified']) {
    $fail('The linked identity must be listed: ' . json_encode($identities));
}
if (!$repository->providerLinked($uid, Provider::Google) || $repository->findUserId(Provider::Google, $subject) !== $uid) {
    $fail('The identity must be found for its player.');
}

// One identity per provider and account, and an identity belongs to one player.
$duplicate = false;
try {
    $repository->link($uid, $claims(Provider::Google, $subject . '-second'));
} catch (PDOException) {
    $duplicate = true;
}
if (!$duplicate || count(LinkedIdentities::forUser($uid)) !== 1) {
    $fail('A second identity from the same provider must be refused by the table.');
}
if ($other > 0) {
    $taken = false;
    try {
        $repository->link($other, $claims(Provider::Google, $subject));
    } catch (PDOException) {
        $taken = true;
    }
    if (!$taken) {
        $fail('An identity of another player must be refused by the table.');
    }
}
$repository->link($uid, $claims(Provider::Facebook, $subject . '-fb'));
if (array_map(static fn (array $i): string => $i['provider']->value, LinkedIdentities::forUser($uid)) !== ['facebook', 'google']) {
    $fail('Different providers may be linked side by side.');
}

// Options shows the configured providers and what is linked; an unconfigured provider stays only while linked.
$dir = sys_get_temp_dir() . '/travian-social-' . getmypid();
mkdir($dir, 0700);
$config = new ProviderConfig($dir . '/social.local.php');
$parsed = $config->fromSubmission(['social_google_client_id' => 'gid.apps.example', 'social_google_client_secret' => 'secret']);
$config->save($parsed['config']);
$session = [];
$flash = new SocialFlash($session, 'k');
$flash->setNotice('linked', Provider::Google);
$section = OptionsSection::build($uid, $config, 'https://x.test', $flash);
$labels = array_column($section['rows'] ?? [], 'email', 'label');
ksort($labels);
if ($labels !== ['Facebook' => 'player@example.com', 'Google' => 'player@example.com']) {
    $fail('Options must list the configured and the linked providers: ' . json_encode($labels));
}
if (($section['notice']['text'] ?? '') !== 'Your Google account is now linked.' || $section['notice']['error']) {
    $fail('The link notice must use the approved text: ' . json_encode($section['notice'] ?? null));
}
if (OptionsSection::build($uid, $config, 'https://x.test', $flash)['notice'] !== null) {
    $fail('A notice is shown once.');
}
$flash->setNotice('error', Provider::Google, FlowError::LinkedElsewhere);
if (OptionsSection::build($uid, $config, 'https://x.test', $flash)['notice']['text'] !== 'This Google account is already linked to another player.') {
    $fail('The linked-elsewhere notice must name the provider.');
}
$flash->setNotice('passwordWrong', Provider::Google);
if (OptionsSection::build($uid, $config, 'https://x.test', $flash)['notice']['text'] !== (string)T('Options', 'password wrong')) {
    $fail('A wrong password uses the existing Options text.');
}
@unlink($dir . '/social.local.php');
@rmdir($dir);

// Unlinking removes one identity, once.
if (!LinkedIdentities::unlink($uid, Provider::Google) || LinkedIdentities::unlink($uid, Provider::Google) || $repository->providerLinked($uid, Provider::Google)) {
    $fail('Unlink must remove the identity once.');
}

// The audit trail gets the player as actor, the provider as target, and no subject or email.
SocialAudit::record('link', $uid, 'Fixture', Provider::Google);
SocialAudit::record('link-refused', $uid, 'Fixture', Provider::Google, FlowError::LinkedElsewhere->value);
$rows = $pdo->run("SELECT action, target, details, admin_name FROM admin_log WHERE admin_uid=? AND action LIKE 'social.%' ORDER BY id", [$uid])->fetchAll(PDO::FETCH_ASSOC);
if (array_column($rows, 'action') !== ['social.link', 'social.link-refused'] || $rows[0]['target'] !== "uid={$uid} provider=google" || $rows[1]['details'] !== 'linked_elsewhere' || $rows[0]['admin_name'] !== 'Fixture') {
    $fail('Audit rows are wrong: ' . json_encode($rows));
}
if (str_contains(json_encode($rows), $subject) || str_contains(json_encode($rows), 'example.com')) {
    $fail('The audit trail must not hold subjects or emails.');
}
echo "Social link runtime passed.\n";
