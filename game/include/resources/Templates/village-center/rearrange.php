<?php
/**
 * Main Building rearrange mode overlay, the `.rearrangeBuildings` block
 * (GET /dorf2.php?mode=rearrange). game-client.js moves it into #background and
 * Travian.Game.Village.RearrangeBuildings drives the select / drag-and-drop flow;
 * the gold confirm calls Travian.Game.PremiumFeature.RearrangeBuildings().
 *
 * @var array<string, mixed> $vars
 */
$rearrangeText = static fn(string $key): string => (string)T('Buildings', 'rearrangeMode.' . $key);
$rearrangeJs = static fn($value): string => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$rearrangeButton = static function (string $label, string $class, string $onclick, array $extra = []) use ($rearrangeJs): string {
    $id = get_button_id();
    $attributes = ' type="button" value="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" id="' . $id . '" class="' . $class . '"';
    $payload = ['type' => 'button', 'value' => $label, 'name' => '', 'id' => $id, 'class' => $class, 'title' => '', 'confirm' => '', 'onclick' => $onclick];
    if (isset($extra['title'])) {
        $attributes .= ' title="' . htmlspecialchars($extra['title'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if (isset($extra['coins'])) {
        $attributes .= ' coins="' . (int)$extra['coins'] . '"';
        $payload['coins'] = (int)$extra['coins'];
    }
    $attributes .= ' ' . ($extra['handler'] ?? 'onclick') . '="' . htmlspecialchars($onclick, ENT_QUOTES, 'UTF-8') . '" version="textButtonV1"';
    $payload['version'] = 'textButtonV1';
    $gold = isset($extra['coins']) ? '<i class="goldIcon"></i><span class="goldValue">' . (int)$extra['coins'] . '</span>' : '';

    return '<button' . $attributes . '>' . "\n\t" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . $gold . '</button>' . "\n"
        . '<script type="text/javascript" id="' . $id . '_script">' . "\n\tjQuery(function() {\n"
        . "        jQuery('button#" . $id . "').on('click', function () {\n"
        . "            jQuery(window).trigger('buttonClicked', [this, " . $rearrangeJs($payload) . "]);\n"
        . "        });\n\t});\n</script>\n";
};
$villageName = stored_name_html((string)\Core\Village::getInstance()->getName());
$cost = (int)\Core\Config::getInstance()->gold->rearrangeBuildingsGold;
?>
<div class="rearrangeBuildings hide">
            <div class="villageMask"></div>
            <div class="container">
                <h1><?= $rearrangeText('title') ?></h1>
                <div class="instructions">
                    <div class="step step1 active"><?= $rearrangeText('step1') ?></div>
                    <div class="step step2"><?= $rearrangeText('step2') ?></div>
                    <div class="step dragAndDrop"><?= $rearrangeText('dragAndDrop') ?></div>
                </div>
                <a class="close" title="<?= $rearrangeText('cancel') ?>" onclick="Travian.Game.Village.RearrangeBuildings.abort();">
                    <svg viewBox="0 0 20 20" class="close"><g class="outline">
  <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>
</g><g class="icon">
  <path d="M0 17.01L7.01 10 .14 3.13 3.13.14 10 7.01 17.01 0 20 2.99 12.99 10l6.87 6.87-2.99 2.99L10 12.99 2.99 20 0 17.01z"></path>
</g></svg>
                </a>
                <?= $rearrangeButton($rearrangeText('title'), 'textButtonV1 gold confirm disabled', 'Travian.Game.Village.RearrangeBuildings.proceed();', ['title' => '<span class="notice">' . $rearrangeText('toContinue') . '</span>']) ?>
            </div>
            <div class="proceedDialog">
                <h1><?= $rearrangeText('title') ?></h1>
                <p><?= sprintf($rearrangeText('proceedText'), '<strong>' . $villageName . '</strong>') ?></p>
                <div class="rearrangeBuildingsButtons">
                    <?= $rearrangeButton($rearrangeText('title'), 'textButtonV1 gold ', 'Travian.Game.PremiumFeature.RearrangeBuildings(); return false;', ['coins' => $cost, 'handler' => 'onClick']) ?>
                    <?= $rearrangeButton($rearrangeText('cancel'), 'textButtonV1 grey ', "Travian.WindowManager.closeByContext('rearrangeBuildingsConfirm')") ?>
                </div>
            </div>
            <div class="abortDialog">
                <h1><?= $rearrangeText('abortTitle') ?></h1>
                <p><?= $rearrangeText('abortText') ?></p>
                <div class="decisionButtons">
                    <?= $rearrangeButton($rearrangeText('abortConfirm'), 'textButtonV1 green ', "window.location.href = '/dorf2.php';") ?>
                    <?= $rearrangeButton($rearrangeText('cancel'), 'textButtonV1 grey ', "Travian.WindowManager.closeByContext('rearrangeBuildingsAbort')") ?>
                </div>
            </div>
        </div>
