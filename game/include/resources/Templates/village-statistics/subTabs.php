<?php
/**
 * nested tab row (`div.contentNavi.tabNavi`) of the village statistics.
 *
 * $vars:
 *   tabs       list of ['slug' => string, 'label' => string, 'href' => string, 'active' => bool]
 *   favorName  nested favourite name stored in the player's favorTabs (optional)
 *   favorSlug  slug of the stored favourite, marked favorActive (optional)
 */
$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="contentNavi tabNavi ">
<?php foreach ($vars['tabs'] as $tab):
    $buttonId = get_button_id();
    ?>
	<div title="" class="container  <?= !empty($tab['active']) ? 'active' : 'normal'; ?>">
		<div class="background-start">&nbsp;</div>
		<div class="background-end">&nbsp;</div>
		<div class="content favor favorKey<?= $escape($tab['slug']); ?><?= ($vars['favorSlug'] ?? null) === $tab['slug'] ? ' favorActive' : ''; ?>">
			<a class="tabItem" id="<?= $escape($buttonId); ?>" href="<?= $escape($tab['href']); ?>"><?= $escape($tab['label']); ?></a>
		</div>
	</div>
<?php endforeach; ?>
<?php
$favorName = $vars['favorName'] ?? null;
if ($favorName !== null):
    $current = null;
    foreach ($vars['tabs'] as $tab) {
        if (!empty($tab['active'])) {
            $current = $tab;
        }
    }
    if ($current !== null):
        ?>
	<a type="submit" id="nestedTabFavorButton" class="icon nestedTabRowButton " onclick="Travian.api('favourite-tab', {data: {name: '<?= $escape($favorName); ?>',key: '<?= $escape($current['slug']); ?>'},success: function(data) {jQuery('.tabNavi .favor').removeClass('favorActive');jQuery('.favor.favorKey<?= $escape($current['slug']); ?>').addClass('favorActive');}});return false;" useicon=""><img src="/img/spacer.gif" class="&nbsp;" alt=""></a>
<?php endif; endif; ?>
	<div class="clear"></div>
</div>
