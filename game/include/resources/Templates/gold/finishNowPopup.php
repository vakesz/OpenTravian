<?php

declare(strict_types=1);

/**
 * `#finishNowDialog`: intro copy, one "<h5>heading</h5>" list per kind of order (each building and
 * demolition as name + `span.lvl`), and the gold Use [coins] submit button.
 *
 * @var array{
 *     buildings: list<array{name:string,level:int}>,
 *     researches: list<array{label:string}>,
 *     demolitions: list<array{name:string,level:int}>,
 *     goldCost: int
 * } $vars
 */

$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
$levelOrders = static function (string $heading, array $orders) use ($escape): string {
    if ($orders === []) {
        return '';
    }
    $items = '';
    foreach ($orders as $order) {
        $items .= '<li><span>' . $escape($order['name']) . '</span> <span class="lvl">'
            . $escape(T('finishNowPopup', 'level') . ' ' . $order['level']) . '</span></li>';
    }

    return '<h5>' . $escape((string)T('finishNowPopup', $heading)) . '</h5><ul>' . $items . '</ul>';
};
?>
<div id="finishNowDialog">
    <p><?= $escape((string)T('finishNowPopup', 'desc')); ?></p>
    <?= $levelOrders('buildingOrders', $vars['buildings']); ?>
<?php if ($vars['researches'] !== []): ?>
    <h5><?= $escape((string)T('finishNowPopup', 'researchOrders')); ?></h5>
    <ul>
        <?php foreach ($vars['researches'] as $research): ?>
            <li><span><?= $escape($research['label']); ?></span></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
    <?= $levelOrders('demolishOrders', $vars['demolitions']); ?>
    <div class="buttonWrapper">
        <?= getButton(
            [
                'type' => 'submit',
                'value' => T('finishNowPopup', 'Use'),
                'class' => 'textButtonV1 gold ',
                'coins' => (int)$vars['goldCost'],
                'onclick' => 'Travian.Game.PremiumFeature.InstantCompletion.confirm(); return false;',
            ],
            ['data' => ['title' => T('finishNowPopup', 'useTitle')]],
            T('finishNowPopup', 'Use')
        ); ?>
    </div>
</div>
