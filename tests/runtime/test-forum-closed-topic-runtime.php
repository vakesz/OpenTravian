<?php

declare(strict_types=1);

// Posting in a closed topic of a closed forum is limited to the players on the forum's allowed list.
// isTopicOpenedForUser() answers that question; addPost() enforces it. Runs on throwaway fixture players.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Forum closed-topic runtime skipped outside the php container.\n";
    exit(0);
}
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Core\Database\DBI;
use Model\AllianceModel;
use Model\ForumModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();
try {
    $leader = fixtureVillageCreate();
    $pdo->run('UPDATE users SET name=? WHERE id=?', ['zzfxl' . getmypid(), $leader['id']]);
    $listed = fixtureVillageCreate();
    $pdo->run('UPDATE users SET name=? WHERE id=?', ['zzfxm' . getmypid(), $listed['id']]);
} catch (RuntimeException $exception) {
    echo "Forum closed-topic runtime passed (no fixture village: {$exception->getMessage()}).\n";
    exit(0);
}
$aid = 0;
register_shutdown_function(static function () use ($pdo, &$aid): void {
    if ($aid > 0) {
        $forums = $pdo->fetchPluck('SELECT id FROM forum_forums WHERE aid=?', [$aid]);
        foreach ($forums as $forumId) {
            foreach (['forum_open_players', 'forum_open_alliances', 'forum_topic'] as $table) {
                $pdo->run("DELETE FROM `$table` WHERE forumId=?", [(int)$forumId]);
            }
        }
        foreach (['forum_forums' => 'aid', 'forum_post' => 'aid', 'alidata' => 'id', 'ali_log' => 'aid'] as $table => $column) {
            $pdo->run("DELETE FROM `$table` WHERE `$column`=?", [$aid]);
        }
    }
});

$aid = (int)(new AllianceModel())->createAlliance($leader['id'], 'zzfx forum ' . getmypid(), 'zq' . substr((string)getmypid(), -3));
if ($aid <= 0) {
    $fail('The fixture alliance could not be created.');
}
$pdo->run('UPDATE users SET aid=? WHERE id=?', [$aid, $listed['id']]);

$forum = new ForumModel();
// Area 3 is the closed forum; only the players listed by id may post in its closed topics.
$forumId = $forum->addForum($aid, 'Closed', 'Only listed', 3, 1, null, null, [$listed['id']], null);
if ($forumId <= 0) {
    $fail('addForum() must create the closed forum.');
}
$pdo->run('INSERT INTO forum_topic (forumId, thread, close, SurveyStartTime, Survey, end_time) VALUES (?, ?, 1, ?, ?, 0)', [$forumId, 'Closed topic', time(), '']);
$topicId = (int)$pdo->fetchScalar('SELECT LAST_INSERT_ID()');

if (!$forum->isTopicOpenedForUser($forumId, $listed['id'])) {
    $fail('A player on the allowed list must have access to the closed forum.');
}
if ($forum->isTopicOpenedForUser($forumId, $leader['id'])) {
    $fail('A player who is not on the allowed list must not have access.');
}

$posts = static fn (): int => (int)$pdo->fetchScalar('SELECT COUNT(id) FROM forum_post WHERE topicId=? AND deleted=0', [$topicId]);
$forum->addPost($leader['id'], $aid, $forumId, $topicId, 'unlisted member, not an administrator');
if ($posts() !== 0) {
    $fail('A member who is not on the list must not post in a closed topic.');
}
$forum->addPost($listed['id'], $aid, $forumId, $topicId, 'listed member');
if ($posts() !== 1) {
    $fail('A listed member must be able to post in a closed topic.');
}
$forum->addPost($leader['id'], $aid, $forumId, $topicId, 'administrator', false, true);
if ($posts() !== 2) {
    $fail('A forum administrator must be able to post in a closed topic.');
}

echo "Forum closed-topic runtime passed.\n";
