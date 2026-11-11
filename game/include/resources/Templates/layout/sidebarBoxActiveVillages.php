<?php

declare(strict_types=1);

$quickLinksJson = htmlspecialchars((string) ($vars['quickLinks'] ?? '[]'), ENT_QUOTES, 'UTF-8');
$villageId = (int) ($vars['villageId'] ?? 0);
$quickLinksLocked = (bool) ($vars['quickLinksLocked'] ?? false);
$quickLinksNoPlusTitle = htmlspecialchars(T('quickLinks', 'quickLinks') . '||' . T('inGame', 'needPlusDesc'), ENT_QUOTES, 'UTF-8');
$coordX = (int) ($vars['coordX'] ?? 0);
$coordY = (int) ($vars['coordY'] ?? 0);
$quickLinksVillageNameJson = json_encode(
    (string) ($vars['villageName'] ?? ''),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
) ?: '""';
?>
<div id="sidebarBoxActiveVillage" class="sidebarBox">
 <div class="header">
	<div class="buttonsWrapper quickLinks" data-quick-links="<?= $quickLinksJson; ?>">
		<?php if ($quickLinksLocked): ?>
			<div class="quickLinkSlot ">
				<div class="slot"></div>
				<svg viewBox="0 0 44 44" class="editSlotIndicator" aria-hidden="true">
					<path d="M22 44h-.5v-1h.5c1.2 0 2.4 0 3.5-.3l.2 1c-1.2.2-2.4.3-3.7.3Zm-3.6-.3c-1.4-.2-2.7-.6-4-1.1l.3-.9c1.2.5 2.5.8 3.8 1l-.2 1Zm10.3-.7-.3-1c1.3-.4 2.5-.9 3.6-1.6l.5.9c-1.2.7-2.5 1.2-3.8 1.6Zm-17.1-1.6c-1.2-.7-2.4-1.4-3.4-2.3l.6-.8c1 .8 2.1 1.6 3.3 2.2l-.5.9Zm23.5-1.7-.6-.8c1.1-.8 2-1.7 2.9-2.7l.7.7c-.9 1-2 2-3.1 2.8ZM5.9 36.9c-.9-1-1.8-2.1-2.5-3.3l.8-.5c.7 1.1 1.5 2.2 2.4 3.1l-.7.7Zm34.2-2.5-.8-.6c.7-1.1 1.4-2.3 1.9-3.5l.9.4c-.6 1.3-1.2 2.5-2 3.6ZM1.9 30.9C1.3 29.6.9 28.3.6 27l1-.2c.3 1.3.7 2.5 1.3 3.8L2 31Zm41.3-3-1-.3c.4-1.3.6-2.6.7-3.9h1c-.1 1.5-.4 2.8-.7 4.2ZM0 23.9V22c0-.8 0-1.5.1-2.3h1c0 .8-.1 1.5-.1 2.3v1.8H0Zm43-3.2c0-1.3-.3-2.6-.6-3.9l1-.2c.3 1.3.6 2.7.6 4.1h-1ZM1.6 16.9l-1-.2c.3-1.3.8-2.7 1.4-3.9l.9.4c-.6 1.2-1 2.5-1.3 3.7ZM41.4 14c-.5-1.2-1.1-2.4-1.8-3.5l.8-.5c.8 1.2 1.4 2.4 1.9 3.7l-.9.4Zm-37-3.4-.8-.5c.8-1.2 1.6-2.3 2.6-3.3l.7.7c-.9 1-1.7 2-2.5 3.1Zm33.4-2.5c-.9-1-1.8-1.9-2.9-2.7l.6-.8c1.1.8 2.1 1.8 3 2.8l-.8.7ZM9 5.5l-.6-.8c1.1-.9 2.3-1.6 3.5-2.2l.5.9c-1.2.6-2.3 1.3-3.3 2.1Zm23.4-1.7c-1.1-.7-2.4-1.2-3.6-1.6l.3-.9c1.3.4 2.6 1 3.8 1.7l-.5.9ZM15.1 2.2l-.3-.9c1.3-.5 2.7-.8 4-1v1c-1.2.2-2.5.5-3.7.9Zm10.8-.8C24.6 1.2 23.3 1 22 1V0c1.4 0 2.8.1 4.1.4l-.2 1Z"></path>
				</svg>
			</div>
			<?php foreach ($vars['lockedQuickLinks'] as $slotIndex => $lockedLink): ?>
			<div class="quickLinkSlot ">
				<a class="textButtonV2 buttonFramed layoutButton<?= $lockedLink['disabled'] ? ' disabled' : ''; ?> round withIcon gold" title="<?= $quickLinksNoPlusTitle; ?>" data-load-tooltip-data="<?= htmlspecialchars(json_encode(['type' => $lockedLink['key']]), ENT_QUOTES, 'UTF-8'); ?>" data-dragid="villageQuickLinks<?= $slotIndex + 1; ?>" href="#"><?= $lockedLink['svg']; ?></a>
				<svg viewBox="0 0 44 44" class="editSlotIndicator" aria-hidden="true">
					<path d="M22 44h-.5v-1h.5c1.2 0 2.4 0 3.5-.3l.2 1c-1.2.2-2.4.3-3.7.3Zm-3.6-.3c-1.4-.2-2.7-.6-4-1.1l.3-.9c1.2.5 2.5.8 3.8 1l-.2 1Zm10.3-.7-.3-1c1.3-.4 2.5-.9 3.6-1.6l.5.9c-1.2.7-2.5 1.2-3.8 1.6Zm-17.1-1.6c-1.2-.7-2.4-1.4-3.4-2.3l.6-.8c1 .8 2.1 1.6 3.3 2.2l-.5.9Zm23.5-1.7-.6-.8c1.1-.8 2-1.7 2.9-2.7l.7.7c-.9 1-2 2-3.1 2.8ZM5.9 36.9c-.9-1-1.8-2.1-2.5-3.3l.8-.5c.7 1.1 1.5 2.2 2.4 3.1l-.7.7Zm34.2-2.5-.8-.6c.7-1.1 1.4-2.3 1.9-3.5l.9.4c-.6 1.3-1.2 2.5-2 3.6ZM1.9 30.9C1.3 29.6.9 28.3.6 27l1-.2c.3 1.3.7 2.5 1.3 3.8L2 31Zm41.3-3-1-.3c.4-1.3.6-2.6.7-3.9h1c-.1 1.5-.4 2.8-.7 4.2ZM0 23.9V22c0-.8 0-1.5.1-2.3h1c0 .8-.1 1.5-.1 2.3v1.8H0Zm43-3.2c0-1.3-.3-2.6-.6-3.9l1-.2c.3 1.3.6 2.7.6 4.1h-1ZM1.6 16.9l-1-.2c.3-1.3.8-2.7 1.4-3.9l.9.4c-.6 1.2-1 2.5-1.3 3.7ZM41.4 14c-.5-1.2-1.1-2.4-1.8-3.5l.8-.5c.8 1.2 1.4 2.4 1.9 3.7l-.9.4Zm-37-3.4-.8-.5c.8-1.2 1.6-2.3 2.6-3.3l.7.7c-.9 1-1.7 2-2.5 3.1Zm33.4-2.5c-.9-1-1.8-1.9-2.9-2.7l.6-.8c1.1.8 2.1 1.8 3 2.8l-.8.7ZM9 5.5l-.6-.8c1.1-.9 2.3-1.6 3.5-2.2l.5.9c-1.2.6-2.3 1.3-3.3 2.1Zm23.4-1.7c-1.1-.7-2.4-1.2-3.6-1.6l.3-.9c1.3.4 2.6 1 3.8 1.7l-.5.9ZM15.1 2.2l-.3-.9c1.3-.5 2.7-.8 4-1v1c-1.2.2-2.5.5-3.7.9Zm10.8-.8C24.6 1.2 23.3 1 22 1V0c1.4 0 2.8.1 4.1.4l-.2 1Z"></path>
				</svg>
			</div>
			<?php endforeach; ?>
		<?php else: ?>
		<?php for ($quickLinkSlot = 0; $quickLinkSlot < 5; ++$quickLinkSlot): ?>
			<div class="quickLinkSlot" data-slot-index="<?= $quickLinkSlot; ?>">
				<div class="slot"></div>
				<svg viewBox="0 0 44 44" class="editSlotIndicator" aria-hidden="true">
					<path d="M22 44h-.5v-1h.5c1.2 0 2.4 0 3.5-.3l.2 1c-1.2.2-2.4.3-3.7.3Zm-3.6-.3c-1.4-.2-2.7-.6-4-1.1l.3-.9c1.2.5 2.5.8 3.8 1l-.2 1Zm10.3-.7-.3-1c1.3-.4 2.5-.9 3.6-1.6l.5.9c-1.2.7-2.5 1.2-3.8 1.6Zm-17.1-1.6c-1.2-.7-2.4-1.4-3.4-2.3l.6-.8c1 .8 2.1 1.6 3.3 2.2l-.5.9Zm23.5-1.7-.6-.8c1.1-.8 2-1.7 2.9-2.7l.7.7c-.9 1-2 2-3.1 2.8ZM5.9 36.9c-.9-1-1.8-2.1-2.5-3.3l.8-.5c.7 1.1 1.5 2.2 2.4 3.1l-.7.7Zm34.2-2.5-.8-.6c.7-1.1 1.4-2.3 1.9-3.5l.9.4c-.6 1.3-1.2 2.5-2 3.6ZM1.9 30.9C1.3 29.6.9 28.3.6 27l1-.2c.3 1.3.7 2.5 1.3 3.8L2 31Zm41.3-3-1-.3c.4-1.3.6-2.6.7-3.9h1c-.1 1.5-.4 2.8-.7 4.2ZM0 23.9V22c0-.8 0-1.5.1-2.3h1c0 .8-.1 1.5-.1 2.3v1.8H0Zm43-3.2c0-1.3-.3-2.6-.6-3.9l1-.2c.3 1.3.6 2.7.6 4.1h-1ZM1.6 16.9l-1-.2c.3-1.3.8-2.7 1.4-3.9l.9.4c-.6 1.2-1 2.5-1.3 3.7ZM41.4 14c-.5-1.2-1.1-2.4-1.8-3.5l.8-.5c.8 1.2 1.4 2.4 1.9 3.7l-.9.4Zm-37-3.4-.8-.5c.8-1.2 1.6-2.3 2.6-3.3l.7.7c-.9 1-1.7 2-2.5 3.1Zm33.4-2.5c-.9-1-1.8-1.9-2.9-2.7l.6-.8c1.1.8 2.1 1.8 3 2.8l-.8.7ZM9 5.5l-.6-.8c1.1-.9 2.3-1.6 3.5-2.2l.5.9c-1.2.6-2.3 1.3-3.3 2.1Zm23.4-1.7c-1.1-.7-2.4-1.2-3.6-1.6l.3-.9c1.3.4 2.6 1 3.8 1.7l-.5.9ZM15.1 2.2l-.3-.9c1.3-.5 2.7-.8 4-1v1c-1.2.2-2.5.5-3.7.9Zm10.8-.8C24.6 1.2 23.3 1 22 1V0c1.4 0 2.8.1 4.1.4l-.2 1Z"></path>
				</svg>
			</div>
		<?php endfor; ?>
		<?php endif; ?>
		<button type="button" class="textButtonV2 buttonFramed layoutButton settings round withIcon <?= $quickLinksLocked ? 'gold' : 'grey'; ?>"<?= $quickLinksLocked ? ' title="' . $quickLinksNoPlusTitle . '"' : ''; ?>>
			<svg viewBox="0 0 13 3" aria-hidden="true"><svg viewBox="0 0 13 3" class="outline"><path d="M3 1.5C3 2.33 2.33 3 1.5 3S0 2.33 0 1.5.67 0 1.5 0 3 .67 3 1.5ZM8 1.5C8 2.33 7.33 3 6.5 3S5 2.33 5 1.5 5.67 0 6.5 0 8 .67 8 1.5ZM13 1.5c0 .83-.67 1.5-1.5 1.5S10 2.33 10 1.5 10.67 0 11.5 0 13 .67 13 1.5Z"></path></svg><svg viewBox="0 0 13 3" class="icon"><path d="M3 1.5C3 2.33 2.33 3 1.5 3S0 2.33 0 1.5.67 0 1.5 0 3 .67 3 1.5ZM8 1.5C8 2.33 7.33 3 6.5 3S5 2.33 5 1.5 5.67 0 6.5 0 8 .67 8 1.5ZM13 1.5c0 .83-.67 1.5-1.5 1.5S10 2.33 10 1.5 10.67 0 11.5 0 13 .67 13 1.5Z"></path></svg></svg>
		</button>
	</div>
 </div>
 <div class="content">
	<div class="playerName"><?=$vars['playerName']; ?></div>
	<div id="villageName" class="boxTitle editable" data-x="<?= $coordX; ?>" data-y="<?= $coordY; ?>">
	   <form>
		  <input class="villageInput" type="text" maxlength="20" name="villageName" data-did="<?= $villageId; ?>" value="<?= stored_name_html((string)$vars['villageName']); ?>">
		  <svg viewBox="0 0 12.79 18.77" class="rename">
			 <path d="M4 15.61l1.68.9L.8 18.77 0 13.42l1.75.95zm8.25-13.39L8.36.12A1 1 0 0 0 7 .55L1.19 11.22a1 1 0 0 0 .42 1.41l3.89 2.1a1.05 1.05 0 0 0 1.41-.42l5.76-10.68a1 1 0 0 0-.42-1.41z"></path>
		  </svg>
	   </form>
	</div>
	<div class="population"><?= T("map", "population"); ?>: <span>‭<?=(int)$vars['population']; ?>‬</span></div>
	<div class="loyalty <?=$vars['loyalty'] > 100 ? "high" : (($vars['loyalty'] >= 50 || $vars['loyalty'] == 100) ? "medium" : "low"); ?>">
	   <?= T("ResidencePalace", "Loyalty"); ?>: <span>‭‭<?=round($vars['loyalty']); ?>‬%‬</span>
	</div>
	<script type="text/javascript" data-cmp-info="6">
	   function saveChanges(newVillageName) {
		Travian.api('village/change-names', {
			data: {
				data: [{villageId: <?= (int) $villageId; ?>, name: newVillageName}]
			},
			success: function() {
				jQuery('.listEntry.village .name[data-did="<?= (int) $villageId; ?>"]').text(newVillageName);
			},
				  error: function(body) {
					  var errorDialog = new Travian.Dialog.Dialog({
						  buttonOk: true,
						  buttonCloseOnClickOk: true,
						  preventFormSubmit: true
					  });
					  errorDialog.setContent(body && body.message ? body.message : 'Request failed.');
					  errorDialog.show();
				  }
		}, 'PUT');
	   }

	   jQuery(function($) {
		var villageNameForm = jQuery('#villageName form');
		var villageNameInput = jQuery('#villageName input');
		const activeVillageName = <?= $quickLinksVillageNameJson; ?>;

		villageNameInput.on('focusout', function () {
			saveChanges(villageNameInput.val())
		});

		villageNameForm.on('submit', function (event) {
			event.preventDefault();
				  villageNameInput.blur();
		});

		  const activeVillage = $('#sidebarBoxActiveVillage');
		  const quickLinks = activeVillage.find('.quickLinks');
		  if (<?= json_encode($quickLinksLocked); ?>) {
			  quickLinks.on('click', 'a.gold:not(.disabled), button.settings', function (event) {
				  event.preventDefault();
				  new Travian.Game.PlusDialog({featureKey: 'directLinks', cssClass: 'premiumFeaturePackage premiumFeaturePlus paymentShopV5', premiumFeatureDialogVersion: 2, version: 2});
			  });
			  return;
		  }
		  const quickLinkActions = <?= json_encode(array_values($vars['quickLinkActions'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
		  const quickLinkActionByKey = Object.fromEntries(quickLinkActions.map(function (action) { return [action.key, action]; }));
		  const maxSlots = 5;
		  const readQuickLinks = function () {
			  try {
				  const links = JSON.parse(String(quickLinks.attr('data-quick-links') || '[]'));
				  return Array.isArray(links) ? links.filter(function (key, index) {
					  return typeof key === 'string' && key !== '' && quickLinkActionByKey[key] && links.indexOf(key) === index;
				  }).slice(0, maxSlots) : [];
			  } catch (error) {
				  return [];
			  }
		  };
		  const writeQuickLinks = function (links) {
			  const value = JSON.stringify(links.slice(0, maxSlots));
			  quickLinks.attr('data-quick-links', value);
			  Travian.Game.Preferences.set('villageQuickLinks', value);
			  const villageListLinks = $('#sidebarBoxVillageList .quickLinks');
			  if (villageListLinks.length) {
				  villageListLinks.attr('data-quick-links', value);
				  villageListLinks.trigger('quickLinksUpdated');
			  }
		  };
		  const sourceButton = function (key, draggable, dialogButton, slotIndex) {
			  const action = quickLinkActionByKey[key];
			  if (!action) {
				  return $();
			  }
			  const button = $('<a>').append(action.svg || '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"></circle></svg>');
			  const attributes = {
				  href: action.disabled || dialogButton ? '#' : action.href,
				  'data-quick-link-key': key,
				  title: dialogButton ? action.title + ' — ' + action.tab : (action.disabled ? action.notAvailable : action.tooltipTitle + '||' + <?= json_encode(T('HeroGlobal', 'Tooltip loading')); ?>),
				  draggable: draggable ? 'true' : null
			  };
			  if (!dialogButton && action.tooltip) {
				  attributes['data-load-tooltip'] = 'quickLink';
				  attributes['data-load-tooltip-data'] = JSON.stringify({type: key});
			  }
			  if (!dialogButton) {
				  attributes['data-dragid'] = 'villageQuickLinks' + slotIndex;
			  }
			  button.attr(attributes);
			  button.attr('class', 'textButtonV2 buttonFramed layoutButton round withIcon ' + action.color + (action.disabled ? ' disabled' : ''));
			  return button;
		  };
		  const renderQuickLinkSlots = function () {
			  const links = readQuickLinks();
			  quickLinks.find('.quickLinkSlot').each(function (index) {
				  const slot = $(this);
				  Travian.Tip.clearAllInElement(slot[0]);
				  slot.find('.layoutButton').remove();
				  if (links[index]) {
					slot.append(sourceButton(links[index], true, false, index + 1));
				  }
			  });
			  Travian.Tip.updateAllInElement(quickLinks[0]);
		  };
		  const saveQuickLink = function (key, slotIndex) {
			  const links = readQuickLinks().filter(function (link) { return link !== key; });
			  links.splice(Math.max(0, Math.min(slotIndex, links.length)), 0, key);
			  writeQuickLinks(links);
			  renderQuickLinkSlots();
		  };
		  const removeQuickLink = function (key) {
			  writeQuickLinks(readQuickLinks().filter(function (link) { return link !== key; }));
			  renderQuickLinkSlots();
		  };
		  const quickLinksDialogHtml = function () {
			  const wrapper = $('<div>', {id: 'quickLinksDialog', class: 'contentV2 villageListQuickLinks'});
			  wrapper.append($('<h3>').text('Available quick links for village ' + activeVillageName));
			  wrapper.append($('<div>', {class: 'subtitle'}).text('To customize your quick links, drag & drop up to 5 buttons into the slots of your active village'));
			  const available = $('<div>', {class: 'quickLinksWrapper quickLinks'});
			  quickLinkActions.forEach(function (action, index) {
				  const button = sourceButton(action.key, true, true).attr('data-dragid', 'quickLinksDialog' + index);
				  available.append($('<div>').append(button).append($('<div>', {class: 'quickLinkTitle'}).text(action.title)).append($('<div>', {class: 'quickLinkTab'}).text(action.tab)));
			  });
			  wrapper.append(available);
			  return $('<div>').append(wrapper).html();
		  };
		  const bindQuickLinksDialog = function () {
			  const dialog = $('#quickLinksDialog');
			  dialog.on('click', '[data-quick-link-key]', function (event) { event.preventDefault(); });
			  dialog.on('dragstart', '[data-quick-link-key]', function (event) {
				  event.originalEvent.dataTransfer.effectAllowed = 'copy';
				  event.originalEvent.dataTransfer.setData('text/plain', String($(this).data('quick-link-key')));
			  });
			  dialog.on('dragover', function (event) { event.preventDefault(); });
			  dialog.on('drop', function (event) {
				  event.preventDefault();
				  const key = event.originalEvent.dataTransfer.getData('text/plain');
				  if (quickLinkActionByKey[key]) { removeQuickLink(key); }
			  });
		  };

		  quickLinks.on('dragstart', '[data-quick-link-key]', function (event) {
			  event.originalEvent.dataTransfer.effectAllowed = 'copyMove';
			  event.originalEvent.dataTransfer.setData('text/plain', String($(this).data('quick-link-key')));
			  $(this).closest('.quickLinkSlot').addClass('highlightDragSource');
		  });
		  quickLinks.on('dragend', '[data-quick-link-key]', function () {
			  quickLinks.find('.quickLinkSlot').removeClass('highlightDragSource highlightDropTarget');
		  });
		  quickLinks.on('dragover', '.quickLinkSlot', function (event) {
			  event.preventDefault();
			  event.originalEvent.dataTransfer.dropEffect = 'copy';
			  $(this).addClass('highlightDropTarget');
		  });
		  quickLinks.on('dragleave', '.quickLinkSlot', function () {
			  $(this).removeClass('highlightDropTarget');
		  });
		  quickLinks.on('drop', '.quickLinkSlot', function (event) {
			  event.preventDefault();
			  const key = event.originalEvent.dataTransfer.getData('text/plain');
			  if (quickLinkActionByKey[key]) {
				  saveQuickLink(key, quickLinks.find('.quickLinkSlot').index(this));
			  }
		  });
		  quickLinks.find('.settings').on('click', function () {
			  activeVillage.addClass('editQuickLinks');
			  quickLinks.find('.quickLinkSlot').addClass('editMode');
			  (new Travian.Dialog.Dialog({
				  version: 2,
				  cssClass: 'basic quickLinksDialog',
				  buttonOk: false,
				  buttonCancel: true,
				  preventFormSubmit: true,
				  fx: {duration: 0},
				  onOpen: function (dialog) {
					dialog.overlay.addClass('doNotCoverHeader stickToHeader');
				  },
				  onClose: function () {
					  activeVillage.removeClass('editQuickLinks');
					  quickLinks.find('.quickLinkSlot').removeClass('editMode');
				  }
			  })).setContent(quickLinksDialogHtml()).show();
			  bindQuickLinksDialog();
		  });
		  quickLinks.on('quickLinksUpdated', renderQuickLinkSlots);
		  renderQuickLinkSlots();
	   })
	</script>
 </div>
</div>
