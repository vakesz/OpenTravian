<?php

use Core\Helper\TimezoneHelper;

require_once __DIR__ . '/icons.php';

/**
 * One reward screen of the daily-quest dialog, as the reference draws it: the chest, the reward the rotation holds
 * today, the Collect button and the rotation with the day each reward is next up. The three states (not reached,
 * reachable, collected) differ in the heading, the sentence and the button.
 *
 * @var array{tier: int, points: int, achieved: int, state: string, rotation: list<array{id: string, value: int|float, resetAt: int}>} $vars
 */
$tier = (int)$vars['tier'];
$points = (int)$vars['points'];
$achieved = (int)$vars['achieved'];
$state = (string)$vars['state'];
$rotation = $vars['rotation'];
$rewardLines = (array)T('DailyQuest', 'rewardLines');

// The reference isolates every number with LRO ... PDF, and writes n/total as LRO LRO n PDF / LRO total PDF PDF.
$number = static fn (int|float $value): string => "\u{202D}" . number_format($value, floor($value) === (float)$value ? 0 : 2) . "\u{202C}";
$fraction = static fn (int $nominator, int $denominator): string => "\u{202D}\u{202D}" . $nominator . "\u{202C}/\u{202D}" . $denominator . "\u{202C}\u{202C}";
$line = static fn (array $reward): string => sprintf((string)($rewardLines[$reward['id']] ?? ''), $number($reward['value']), '+25%');
$heading = ['claimable' => 'Congratulations! You have collected enough points to get a reward!', 'collected' => 'Reward already claimed!', 'locked' => 'Keep collecting points!'][$state];
$description = [
    'claimable' => sprintf(T('DailyQuest', 'Claim your reward for collecting %s points today. This reward is chosen based on the current reward rotation.'), $points),
    'collected' => sprintf(T('DailyQuest', 'collectedRewardDescription'), $points),
    'locked' => sprintf(T('DailyQuest', 'lockedRewardDescription'), $fraction($achieved, $points)),
][$state];
// A reachable milestone turns into "already claimed" two seconds after it is collected, without a new request:
// the client reads the words from these attributes (as it does for the button).
$collectedHeading = $state === 'claimable' ? ' data-text-collected="' . htmlspecialchars(T('DailyQuest', 'Reward already claimed!'), ENT_QUOTES) . '"' : '';
$collectedDescription = $state === 'claimable' ? ' data-text-collected="' . htmlspecialchars(sprintf(T('DailyQuest', 'collectedRewardDescription'), $points), ENT_QUOTES) . '"' : '';
$today = $rotation[0];
$clock = static fn (int $time): string => TimezoneHelper::date(TimezoneHelper::getTimeFormat(false), $time);
$dateTime = static fn (int $time): string => TimezoneHelper::date(
    match ((int)\Core\Session::getInstance()->getTimezone()[1]) {
        0, 2 => 'j.n.Y',
        1 => 'n.j.Y',
        default => 'Y.n.j',
    },
    $time
) . ', ' . $clock($time);
?>
<div id="dailyQuestsRewardScreen">
    <button type="button" class="textButtonV2 buttonSecondary backButton withTextAndIcon rectangle withText green">
        <div>
            <svg viewBox="0 0 10.2 16.5"><path d="m.1 8.1-.1.2.1.2L7 15.9c.7.7 1.9.8 2.6.1.7-.7.8-1.8.1-2.5L5 8.3l4.7-5.2c.7-.7.7-1.9-.1-2.6-.7-.7-1.9-.7-2.6.1l-.1.1L.1 8.1z"></path></svg>
            <span><?= T('DailyQuest', 'Back'); ?></span>
        </div>
    </button>
    <h1<?= $collectedHeading; ?>><?= T('DailyQuest', $heading); ?></h1>
    <div<?= $collectedDescription; ?>><?= $description; ?></div>
    <div class="rewardWrapper">
        <div class="reward">
            <?= daily_quest_svg('below'); ?>
            <div class="rewardImage reward<?= $points; ?> achieved"></div>
            <?= daily_quest_svg('above'); ?>
            <div class="points"><?= $points; ?></div>
        </div>
        <div class="titleDecoration"></div>
        <div class="iconValueBoxWrapper">
            <div class="iconValueBox  <?= $today['id']; ?>  <?= $state === 'collected' ? 'disabled' : ''; ?>"><i class="<?= $today['id']; ?>"></i><span><span><?= $number($today['value']); ?><?= in_array($today['id'], ['resource_lumber', 'resource_clay', 'resource_iron', 'resource_crop', 'duration'], true) ? ' ' . T('DailyQuest', 'day') : ''; ?></span></span></div>
        </div>
        <button type="button"
                class="textButtonV2 buttonFramed collect <?= $state === 'claimable' ? 'collectable' : ''; ?> <?= $state === 'collected' ? 'collected' : ''; ?> rectangle withText green"
                <?= $state === 'claimable' ? '' : 'disabled="disabled"'; ?>
                data-text-collected="<?= T('DailyQuest', 'Collected'); ?>">
            <div><?= T('DailyQuest', 'Collect'); ?><i class="collectedCheckmark"></i></div>
        </button>
    </div>
    <table class="borderGap">
        <thead>
        <tr>
            <th colspan="2"><?= T('DailyQuest', 'Reward rotation'); ?></th>
            <th><?= T('DailyQuest', 'Next claim'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rotation as $index => $reward): ?>
            <tr>
                <td><i class="<?= $reward['id']; ?>"></i></td>
                <td><?= $line($reward); ?></td>
                <td><?= $index === 0
                    ? sprintf(T('DailyQuest', TimezoneHelper::date('Y-m-d', $reward['resetAt']) === TimezoneHelper::date('Y-m-d') ? 'Today (until %s)' : 'Tomorrow (until %s)'), $clock($reward['resetAt']))
                    : $dateTime($reward['resetAt']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
