<?php
/**
 * Shared tab strip (`div.contentNavi.subNavi`).
 *
 * $vars:
 *   tabs          list of tab specs:
 *                   label (string, required), href (string, required),
 *                   active (bool), slug (string, favourite key suffix),
 *                   gold (bool, Gold Club gated: renders `.gold`, href `#`),
 *                   goldHref (string, href of a gated tab; defaults to `#`),
 *                   dialog (array|null, ['type' => 'goldclubDialog'|'plusDialog', 'config' => [...]]
 *                           opened on click for gated tabs),
 *                   title (string|false, anchor tooltip; omitted when false), class (extra anchor class)
 *   favor         bool   tabs sit in `div.content.favor.favorKey<slug>` wrappers
 *   favorSlug     string|null  slug of the player's stored favourite (favorActive)
 *   favorButton   array|null   name, number|key, slug, title for #tabFavorButton;
 *                              selector and successGuard optionally tune the callback
 *   wrapperClass  string extra class on the strip (e.g. `tabFavorWrapper`)
 *   reactState    bool   inactive tabs carry no `normal` class (the React-rendered
 *                        pages: tasks, production, auctions, hero)
 *   bareAnchors   bool   omit href and aria-current for React-rendered anchor shells;
 *                        callers provide the matching client-side navigation binding
 *   ids           bool   emit `button<id>` anchor ids (gated tabs open their premium dialog
 *                        from the anchor's own onclick; all other tabs are native links)
 */

$escape = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tabs = $vars['tabs'] ?? [];
$withFavor = !empty($vars['favor']);
$favorSlug = $vars['favorSlug'] ?? null;
$withIds = $vars['ids'] ?? true;
$bareAnchors = !empty($vars['bareAnchors']);
$wrapperClass = trim((string)($vars['wrapperClass'] ?? ''));
$favorButton = $vars['favorButton'] ?? null;
$favorField = is_array($favorButton) && array_key_exists('key', $favorButton) ? 'key' : 'number';
$favorValue = is_array($favorButton) ? ($favorButton[$favorField] ?? '') : '';
$favorSelector = is_array($favorButton) ? ($favorButton['selector'] ?? '.favor') : '.favor';
$favorSuccessGuard = !is_array($favorButton) || !array_key_exists('successGuard', $favorButton) || $favorButton['successGuard'];
?>
<?php if ($favorButton): ?>
<a type="button" title="<?= $escape($favorButton['title']); ?>" id="tabFavorButton" class="icon contentTitleButton  buttonFramed green withIcon rectangle"
onclick="Travian.api('favourite-tab', {data: {name: '<?= $escape($favorButton['name']); ?>',<?= $favorField; ?>: '<?= $escape($favorValue); ?>'},success: function(data) {<?= $favorSuccessGuard ? 'if (data.success) { ' : ''; ?>jQuery('<?= $escape($favorSelector); ?>').removeClass('favorActive');jQuery('.favor.favorKey<?= $escape($favorButton['slug']); ?>').addClass('favorActive');<?= $favorSuccessGuard ? '}' : ''; ?>}});return false;" useicon="">
<img src="/img/spacer.gif" class="&nbsp;" alt="" data-cmp-info="9"></a>
<?php endif; ?>

<div class="contentNavi subNavi<?= $wrapperClass !== '' ? ' ' . $escape($wrapperClass) : ''; ?>">
	<button type="button" class="scrollFrom" disabled="disabled"></button>
	<div class="scrollingContainer">
<?php foreach ($tabs as $tab):
    $gold = !empty($tab['gold']);
    $active = !empty($tab['active']);
    $slug = (string)($tab['slug'] ?? '');
    $href = $gold ? (string)($tab['goldHref'] ?? '#') : (string)$tab['href'];
    $state = $active ? 'active' : (!empty($vars['reactState']) ? '' : 'normal');
    $anchorClass = trim((string)preg_replace(
        '/\s+/',
        ' ',
        'tabItem ' . ($tab['class'] ?? '') . ($gold ? ' gold' : '') . ' ' . $state
    ));
    $buttonId = $withIds ? get_button_id() : null;
    $title = isset($tab['title']) && is_string($tab['title']) && $tab['title'] !== '' ? $tab['title'] : null;
    $dialogOnclick = null;
    if ($gold && !empty($tab['dialog'])) {
        // The reference writes the dialog options as a JS object literal with single-quoted strings.
        $dialogOptions = [];
        foreach ($tab['dialog']['config'] as $optionName => $optionValue) {
            $dialogOptions[] = $optionName . ': ' . (is_int($optionValue) ? $optionValue : "'" . addcslashes((string)$optionValue, "'\\") . "'");
        }
        $dialogOnclick = (($tab['dialog']['type'] ?? '') === 'plusDialog' ? 'new Travian.Game.PlusDialog(' : 'new Travian.Game.GoldclubDialog(')
            . '{' . implode(', ', $dialogOptions) . '}); return false;';
    }
    $wrapperClasses = 'content';
    $extraWrapper = trim((string)($tab['wrapperClass'] ?? ''));
    if ($extraWrapper !== '') {
        $wrapperClasses .= ' ' . $extraWrapper;
    }
    if ($withFavor) {
        $wrapperClasses .= ' favor' . ($slug !== '' ? ' favorKey' . $slug : '');
        if ($slug !== '' && $favorSlug !== null && $favorSlug === $slug) {
            $wrapperClasses .= ' favorActive';
        }
    }
    ?>
		<div class="<?= $escape($wrapperClasses); ?>">
			<a<?= $buttonId !== null ? ' id="' . $escape($buttonId) . '"' : ''; ?><?= $bareAnchors ? '' : ' href="' . $escape($href) . '"'; ?> class="<?= $escape($anchorClass); ?>"<?= $bareAnchors ? '' : ' title="' . $escape($title ?? '') . '"'; ?><?= $dialogOnclick !== null ? ' onclick="' . $escape($dialogOnclick) . '"' : ''; ?>><?= $escape($tab['label']); ?></a>
		</div>
<?php endforeach; ?>
	</div>
	<button type="button" class="scrollTo" disabled="disabled"></button>
	<?php // The init script lives inside the strip so `.contentNavi.subNavi + .navigationSpacer` (mobile spacer rule) stays adjacent. ?>
	<script type="text/javascript" data-cmp-info="6">
		jQuery(function() {
			Travian.Game.TabScrollNavigation();
		});
	</script>
</div>
