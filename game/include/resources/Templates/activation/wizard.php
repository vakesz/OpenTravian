<?php
/**
 * New-player activation and respawn wizard: tribe, starting position and
 * confirmation on one page, in the markup the `.activateWithGameLobbyAccount`
 * rules of the reference Gpack style. The steps are switched client-side by
 * Travian.Game.Activation; only the final button posts.
 *
 * @var array<string, mixed> $vars tribes, tribe, sector, recommendedSector, action, confirmHandler, captcha
 */

use Game\Captcha\ActivationCaptcha;

$tribes = $vars['tribes'];
$tribe = (int) $vars['tribe'];
$recommended = (string) $vars['recommendedSector'];
$sector = (string) $vars['sector'];
$action = (string) $vars['action'];
$confirmHandler = (string) $vars['confirmHandler'];
$captcha = $vars['captcha'] ?? null;
$sectors = [
    'nw' => T("ActivationNew", "North - West"),
    'no' => T("ActivationNew", "North - East"),
    'sw' => T("ActivationNew", "South - West"),
    'so' => T("ActivationNew", "South - East"),
];
$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
$button = static fn (string $id, string $class, string $label, string $type = 'button', string $extra = ''): string => '<button type="' . $type . '" value="' . $e($label) . '" id="' . $id . '" class="' . $class . '"' . $extra . ' version="textButtonV2">'
    . "\n<div>\n" . $e($label) . "\t</div>\n</button>";
?>
<?php if ($captcha !== null): ?>
<script src="<?= $e($captcha->scriptUrl()); ?>"></script>
<?php endif; ?>
<div class="dynamicTitles">
<div class="selectTribe active">
<h1 class="titleInHeader"><?= T("ActivationNew", "Select your tribe"); ?></h1>
</div>
<div class="selectSector">
<h1 class="titleInHeader selectSector"><?= T("activation", "selectASector"); ?></h1>
</div>
<div class="confirm">
<h1 class="titleInHeader confirm"><?= T("ActivationNew", "Confirm your selection"); ?></h1>
</div>
</div>
<div class="mobileCover"></div>
<div class="activationWrapper activateWithGameLobbyAccount selectTribe">
<form class="formV2" method="post" id="activate" action="<?= $e($action); ?>">
<div class="steps">
<div class="stepDescription selectTribe"><?= T("ActivationNew", "vidSelectDescription"); ?></div>
<div class="selection selectTribe">
<?php foreach ($tribes as $index => $id): ?>
<input type="radio" id="tribe_<?= $id; ?>"<?= $id === $tribe ? ' checked=""' : ''; ?> name="tribe" value="<?= $id; ?>" class="option<?= $index; ?>">
<label for="tribe_<?= $id; ?>" class="tribe<?= $id; ?>">
</label>
<?php endforeach; ?>
<div class="description tribeDescription">
<?php foreach ($tribes as $index => $id): ?>
<div class="optionDescription option<?= $index; ?> tribe<?= $id; ?>">
<?php if ($id === 3): ?>
<div class="recommended">
<span class="text">
<?= T("ActivationNew", "recommendedForNewPlayers"); ?>
</span>
</div>
<?php endif; ?>
<h2><?= T("Global", "races." . $id); ?></h2>
<ul>
<?php foreach (T("ActivationNew", "race" . $id . "_attributes") as $text): ?>
<li><?= $text; ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endforeach; ?>
</div>
<div id="selectionIndicator"></div>
</div>
<div class="buttonWrapper selectTribe">
<?= $button('selectTribe', 'textButtonV2 green  buttonFramed withText rectangle', T("ActivationNew", "Confirm")); ?>
<?= $button('updateTribe', 'textButtonV2 green update buttonFramed withText rectangle', T("ActivationNew", "Update")); ?>
</div>
<div class="stepDescription selectSector"><?= T("ActivationNew", "sectorSelectDescription"); ?></div>
<div class="selectSector mapWrapper">
<div id="activationMapContainer">
<div id="map" class="">
<?php foreach ($sectors as $code => $name): ?>
<input type="radio" name="sector" value="<?= $code; ?>" id="sector_<?= $code; ?>"<?= $code === $sector ? ' checked="checked"' : ''; ?>>
<label for="sector_<?= $code; ?>"<?= $code === $recommended ? ' data-text="' . T("ActivationNew", "RECOMMENDED") . '"' : ''; ?>><?= $name; ?></label>
<?php endforeach; ?>
</div>
</div>
</div>
<div class="buttonWrapper selectSector">
<?= $button('selectSector', 'textButtonV2 green  buttonFramed withText rectangle', T("ActivationNew", "Confirm")); ?>
<?= $button('updateSector', 'textButtonV2 green update buttonFramed withText rectangle', T("ActivationNew", "Update")); ?>
</div>
<div class="stepDescription confirm"><?= T("ActivationNew", "confirmDescription"); ?></div>
<div class="selection confirm">
<?php foreach ($tribes as $id): ?>
<div class="confirmTribe tribe<?= $id; ?>">
<h2><?= T("Global", "races." . $id); ?></h2>
<a class="change backToTribe"><?= T("ActivationNew", "Change"); ?></a>
</div>
<?php endforeach; ?>
<?php foreach ($sectors as $code => $name): ?>
<div class="confirmSector <?= $code; ?> ">
<h2><?= $name; ?></h2>
<a class="change backToSector"><?= T("ActivationNew", "Change"); ?></a>
</div>
<?php endforeach; ?>
</div>
<div class="buttonWrapper confirm">
<?= $button('confirm', 'textButtonV2 green withLoadingIndicator buttonFramed withText rectangle', T("ActivationNew", "Let's play"), 'submit', ' onclick="' . $confirmHandler . '(this); return false;"'); ?>
</div>
</div>
<?= \Core\Session::getCheckerInput(); ?>
<input type="hidden" name="timezone" value="">
<input type="hidden" name="captcha" value="">
<input type="checkbox" class="gameVersionOption" name="mobileOptimizations" value="1">
</form>
</div>
<script type="text/javascript">
    jQuery(function () { new Travian.FormV2('form#activate'); new Travian.Game.Activation(); });
    const <?= $confirmHandler; ?> = function (buttonElement) {
        Travian.Game.Activation.action(buttonElement, async function (onError) {
            const form = jQuery('form#activate');
            let captcha = '';
            <?php if ($captcha !== null): ?>
            try {
                captcha = await <?= $captcha->tokenExpression(ActivationCaptcha::ACTION); ?>;
            } catch (error) {
                onError({message: <?= json_encode(T('reCaptcha', 'Sorry you submitted wrong answer'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>});
                return;
            }
            <?php endif; ?>
            form.find('input[name="captcha"]').val(captcha);
            form.find('input[name="timezone"]').val(Intl.DateTimeFormat().resolvedOptions().timeZone || '');
            jQuery.ajax({type: 'POST', url: form.attr('action'), data: form.serialize(), dataType: 'json'})
                .done(function (response) {
                    if (response && response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        onError(response || {});
                    }
                })
                .fail(function () { onError({}); });
        });
    };
</script>
