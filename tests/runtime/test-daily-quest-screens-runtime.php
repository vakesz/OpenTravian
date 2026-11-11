<?php

declare(strict_types=1);

// The daily-quest dialog markup for a throwaway fixture player: every chest of the overview is a plain div with its
// state (locked, achieved, completed) and the drawn check once collected; the reward screen of each milestone has the
// reference structure (back button, heading, sentence, a four-child rewardWrapper, the rotation table) and its three
// states (not reached, reachable, collected) differ in the heading, the sentence and the button.

if (!is_file('/travian/game/deployment/include/env.php')) {
    echo "Daily quest screens runtime skipped outside the php container.\n";
    exit(0);
}
$_SERVER['REQUEST_URI'] = '/dorf1.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/dorf1.php';
chdir('/travian/game/deployment/public');
require '/travian/game/deployment/include/env.php';
require '/travian/game/include/bootstrap.php';
require_once dirname(__DIR__) . '/support/village-fixture.php';

use Controller\Ajax\dailyQuest;
use Core\Database\DBI;
use Core\Helper\WebService;
use Model\DailyQuestModel;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};
$pdo = DBI::getInstance();

if (($argv[1] ?? '') !== 'child') {
    try {
        $user = fixtureVillageCreate();
    } catch (RuntimeException $exception) {
        echo "Daily quest screens runtime passed (no fixture village: {$exception->getMessage()}).\n";
        exit(0);
    }
    $pdo->run('UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?', [time(), time(), $user['id']]);
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' child ' . (int)$user['id'], $status);
    if ($status !== 0) {
        $fail('The daily quest screens phase failed.');
    }
    echo "Daily quest screens runtime passed.\n";
    exit(0);
}

$uid = (int)$argv[2];
$_SESSION[WebService::fixSessionPrefix('uid')] = $uid;
$_SESSION[WebService::fixSessionPrefix('pw')] = (string)$pdo->fetchScalar('SELECT password FROM users WHERE id=?', [$uid]);
\Core\Session::getInstance();
$model = new DailyQuestModel();

$setPoints = static function (int $target) use ($pdo, $model, $uid): void {
    $pdo->run('DELETE FROM daily_quest WHERE uid=?', [$uid]);
    $model->addDailyQuestRow($uid);
    $points = 0;
    for ($i = 1; $i <= 11 && $points < $target; ++$i) {
        for ($step = 1; $step <= $model->getStepCount($i) && $points < $target; ++$step) {
            $pdo->run("UPDATE daily_quest SET qst$i=? WHERE uid=?", [$step, $uid]);
            $points = $model->calcTotalPoints((array)$model->getQuest($uid));
        }
    }
};
$request = static function (array $body) use ($fail): string {
    $response = ['reload' => false, 'error' => false, 'success' => true, 'message' => '', 'reloadOnClose' => false];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $body;
    (new dailyQuest($response))->dispatch();
    if (!empty($response['error'])) {
        $fail('The dialog request must succeed: ' . json_encode($response));
    }

    return (string)$response['html'];
};
$dom = static function (string $html): DOMXPath {
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>');

    return new DOMXPath($document);
};
$classes = static fn (DOMNode $node): array => preg_split('/\s+/', trim((string)$node->attributes?->getNamedItem('class')?->nodeValue)) ?: [];
$text = static fn (DOMNode $node): string => trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

// Overview: all four chests are divs, each with the state of its milestone.
$setPoints(60);
$pdo->run('UPDATE daily_quest SET reward1Done=1 WHERE uid=?', [$uid]);
$xpath = $dom($request([]));
$chests = $xpath->query('//div[contains(@class,"dailyProgress")]//div[@class="reward"]');
if ($chests === false || $chests->length !== 4 || $xpath->query('//a[contains(@class,"reward")]')->length !== 0) {
    $fail('Every chest of the overview must be a plain div.reward (four of them, no links).');
}
$states = [];
foreach ($chests as $index => $chest) {
    $image = $xpath->query('.//div[contains(@class,"rewardImage")]', $chest)->item(0);
    $states[] = array_values(array_intersect($classes($image), ['locked', 'achieved', 'completed']))[0] ?? '?';
    $threshold = 25 * ($index + 1);
    if (!in_array('reward' . $threshold, $classes($image), true) || !in_array('animationsEnabled', $classes($image), true)) {
        $fail("Chest $threshold must carry its reward class and animationsEnabled.");
    }
    $check = $xpath->query('.//*[local-name()="svg" and contains(@class,"check")]', $chest)->length;
    if ($check !== ($index === 0 ? 1 : 0)) {
        $fail("Only the collected chest draws the check (chest $threshold has $check).");
    }
}
if ($states !== ['completed', 'achieved', 'locked', 'locked']) {
    $fail('Chests must show completed, achieved, locked, locked at 60 points with the first collected, got ' . json_encode($states));
}
$button = $xpath->query('//button[contains(@class,"collectRewards")]')->item(0);
if ($button === null || $button->attributes->getNamedItem('disabled') !== null) {
    $fail('With a reachable milestone the overview Collect rewards button must be enabled.');
}
$pdo->run('UPDATE daily_quest SET reward2Done=1 WHERE uid=?', [$uid]);
$button = $dom($request([]))->query('//button[contains(@class,"collectRewards")]')->item(0);
if ($button === null || $button->attributes->getNamedItem('disabled') === null) {
    $fail('With nothing left to collect the overview Collect rewards button must be disabled.');
}

// Reward screens: structure and the three states.
$expected = [
    'locked' => ['Keep collecting points!', "You currently have \u{202D}\u{202D}30\u{202C}/\u{202D}75\u{202C}\u{202C} points needed for today's reward."],
    'claimable' => ['Congratulations! You have collected enough points to get a reward!', 'Claim your reward for collecting 25 points today. This reward is chosen based on the current reward rotation.'],
    'collected' => ['Reward already claimed!', "You've already collected your reward for reaching 25 points today."],
];
$setPoints(30);
foreach (['locked' => [3, 0], 'claimable' => [1, 0], 'collected' => [1, 1]] as $state => [$tier, $done]) {
    $pdo->run('UPDATE daily_quest SET reward1Done=? WHERE uid=?', [$done, $uid]);
    $xpath = $dom($request(['questId' => sprintf('DailyQuestsReward_%02d', $tier)]));
    $screen = $xpath->query('//div[@id="dailyQuestsRewardScreen"]')->item(0);
    if ($screen === null) {
        $fail("The $state reward screen must be answered.");
    }
    $order = [];
    foreach ($screen->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $order[] = $child->tagName . ($child->tagName === 'button' ? '.back' : '');
        }
    }
    if ($order !== ['button.back', 'h1', 'div', 'div', 'table']) {
        $fail("The $state reward screen must hold back button, heading, sentence, wrapper and table: " . json_encode($order));
    }
    $wrapper = $xpath->query('.//div[@class="rewardWrapper"]', $screen)->item(0);
    $children = [];
    foreach ($wrapper->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $children[] = $child->tagName . '.' . implode('.', array_filter($classes($child), static fn (string $c): bool => in_array($c, ['reward', 'titleDecoration', 'iconValueBoxWrapper', 'collect'], true)));
        }
    }
    if ($children !== ['div.reward', 'div.titleDecoration', 'div.iconValueBoxWrapper', 'button.collect']) {
        $fail("The $state rewardWrapper must have exactly the four reference children: " . json_encode($children));
    }
    $heading = $text($xpath->query('.//h1', $screen)->item(0));
    $sentence = $text($xpath->query('./div[not(@class)]', $screen)->item(0));
    [$expectedHeading, $expectedSentence] = $expected[$state];
    if ($heading !== $expectedHeading || !str_starts_with($sentence, trim(preg_replace('/\s+/u', ' ', $expectedSentence) ?? ''))) {
        $fail("The $state reward screen heading or sentence is wrong: $heading / $sentence");
    }
    $collect = $xpath->query('.//button[contains(@class,"collect")]', $wrapper)->item(0);
    $collectClasses = $classes($collect);
    $disabled = $collect->attributes->getNamedItem('disabled') !== null;
    $wantsCollectable = $state === 'claimable';
    if (in_array('collectable', $collectClasses, true) !== $wantsCollectable || in_array('collected', $collectClasses, true) !== ($state === 'collected') || $disabled === $wantsCollectable) {
        $fail("The $state Collect button has the wrong classes or disabled state: " . json_encode([$collectClasses, $disabled]));
    }
    if ($collect->attributes->getNamedItem('data-text-collected')?->nodeValue !== 'Collected' || $text($collect) !== 'Collect' || $xpath->query('.//i[@class="collectedCheckmark"]', $collect)->length !== 1) {
        $fail("The $state Collect button must read Collect, carry data-text-collected and the checkmark.");
    }
    $heads = [];
    foreach ($xpath->query('.//table[@class="borderGap"]/thead//th', $screen) as $th) {
        $heads[] = $text($th);
    }
    if ($heads !== ['Reward rotation', 'Next claim']) {
        $fail('The rotation table needs its two headings: ' . json_encode($heads));
    }
    $rows = $xpath->query('.//table[@class="borderGap"]/tbody/tr', $screen);
    $rotationLength = [1 => 4, 2 => 5, 3 => 5, 4 => 4][$tier];
    if ($rows->length !== $rotationLength) {
        $fail("The tier $tier rotation must list $rotationLength rewards, got {$rows->length}.");
    }
    $cell = static fn (int $row) => $xpath->query('./td', $rows->item($row))->item(2);
    $first = $text($cell(0));
    if (!preg_match('/^(Today|Tomorrow) \(until \d{1,2}:\d{2}\)$/', $first) || !preg_match('/^\d{1,4}[.]\d{1,2}[.]\d{1,4}, \d{1,2}:\d{2}$/', $text($cell(1)))) {
        $fail('The first rotation row says when today ends, the others the day they come up: ' . $first);
    }
    // The reward in the box is the first one of the rotation.
    $boxIcon = $classes($xpath->query('.//div[contains(@class,"iconValueBox") and not(contains(@class,"Wrapper"))]/i', $wrapper)->item(0));
    $rowIcon = $classes($xpath->query('.//i', $rows->item(0))->item(0));
    if ($boxIcon !== $rowIcon) {
        $fail("The reward in the box must be today's one, the first of the rotation.");
    }
}

// Quest details screens: the reference structure for each of the 11 quests, today's progress in the track, the tip
// only for quests 2, 8 and 9 (11 has none: its contribution has no next threshold), and nothing for the video quests.
$setPoints(0);
$pdo->run('UPDATE daily_quest SET qst2=2, qst6=3 WHERE uid=?', [$uid]);
foreach (range(1, 11) as $id) {
    $xpath = $dom($request(['questId' => sprintf('DailyQuest_%02d', $id)]));
    $screen = $xpath->query(sprintf('//div[@id="dailyQuestsDetailsScreen" and @class="DailyQuest_%02d"]', $id))->item(0);
    if ($screen === null) {
        $fail("Quest $id must be answered as #dailyQuestsDetailsScreen.DailyQuest_" . sprintf('%02d', $id) . '.');
    }
    $order = [];
    foreach ($screen->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $order[] = $child->tagName . ($child->tagName === 'button' ? '.back' : '') . ($child->tagName === 'div' ? '.' . ($classes($child)[0] ?? '') : '');
        }
    }
    $expectedOrder = ['button.back', 'h1', 'div.description', ...(in_array($id, [2, 8, 9], true) ? ['div.additionalInformation'] : []), 'div.dailyQuest'];
    if ($order !== $expectedOrder) {
        $fail("The details screen of quest $id must hold " . json_encode($expectedOrder) . ', got ' . json_encode($order));
    }
    $pills = $xpath->query('.//div[contains(@class,"pill")]', $screen);
    $done = $xpath->query('.//div[contains(@class,"pill") and contains(@class,"completed")]', $screen)->length;
    $steps = $model->getStepCount($id);
    $progress = [2 => 2, 6 => 3][$id] ?? 0;
    if ($pills->length !== $steps || $done !== $progress) {
        $fail("Quest $id must show $steps pills with $progress done, got {$pills->length} and $done.");
    }
    $points = $text($xpath->query('.//div[@class="points"]', $screen)->item(0));
    $wanted = $model->getQuestReward($id, $progress) . '/' . $model->getTotalQuestReward($id);
    if (!str_contains(str_replace(["\u{202D}", "\u{202C}"], '', $points), $wanted)) {
        $fail("Quest $id must show its points as $wanted, got $points.");
    }
    // The overview row shows the same track.
    $row = $dom($request([]))->query(sprintf('//div[@id="dailyQuestDailyQuest_%02d"]//div[@class="questAchievedPoints" or contains(@class,"questAchievedPoints")]//div[@class="points"]', $id))->item(0);
    if ($row === null || $text($row) !== $points) {
        $fail("The overview row of quest $id must show the same points as its details screen.");
    }
}
foreach (['DailyQuest_00', 'DailyQuest_12', 'DailyQuest_13', 'DailyQuest_1', 'AchievementQuest_01'] as $unknown) {
    $response = ['reload' => false, 'error' => false, 'success' => true, 'message' => '', 'reloadOnClose' => false];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['questId' => $unknown];
    (new dailyQuest($response))->dispatch();
    if (empty($response['error'])) {
        $fail("The request for $unknown must be refused.");
    }
}

// The announced reward is the credited one: every id of every milestone names what the grant for that type does.
$expectedIds = [
    1 => ['allResources', 'xp', 'amount', 'randomResource'],
    2 => ['duration', 'resource_lumber', 'resource_clay', 'resource_iron', 'resource_crop'],
    3 => ['heroItem_ointment', 'heroItem_tabletOfLaw', 'heroItem_bandage25', 'heroItem_cage', 'getExtraAdventures'],
    4 => ['amount', 'randomResource', 'xp', 'allResources'],
];
foreach ($expectedIds as $tier => $ids) {
    foreach ($ids as $index => $id) {
        if (DailyQuestModel::rewardView($tier, $index + 1)['id'] !== $id) {
            $fail("Milestone $tier reward type " . ($index + 1) . " must be announced as $id.");
        }
    }
}
exit(0);
