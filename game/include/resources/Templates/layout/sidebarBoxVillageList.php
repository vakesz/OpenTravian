<div id="sidebarBoxVillageList" class="sidebarBox">
   <div class="header">
	  <div class="buttonsWrapper quickLinks" data-quick-links="<?= htmlspecialchars((string) $vars['quickLinks'], ENT_QUOTES, 'UTF-8'); ?>">
		<?php for ($quickLinkSlot = 0; $quickLinkSlot < 4; ++$quickLinkSlot): ?>
			<div class="quickLinkSlot" data-slot-index="<?= $quickLinkSlot; ?>">
				<div class="slot"></div>
				<svg viewBox="0 0 44 44" class="editSlotIndicator" aria-hidden="true">
					<path d="M22 44h-.5v-1h.5c1.2 0 2.4 0 3.5-.3l.2 1c-1.2.2-2.4.3-3.7.3Zm-3.6-.3c-1.4-.2-2.7-.6-4-1.1l.3-.9c1.2.5 2.5.8 3.8 1l-.2 1Zm10.3-.7-.3-1c1.3-.4 2.5-.9 3.6-1.6l.5.9c-1.2.7-2.5 1.2-3.8 1.6Zm-17.1-1.6c-1.2-.7-2.4-1.4-3.4-2.3l.6-.8c1 .8 2.1 1.6 3.3 2.2l-.5.9Zm23.5-1.7-.6-.8c1.1-.8 2-1.7 2.9-2.7l.7.7c-.9 1-2 2-3.1 2.8ZM5.9 36.9c-.9-1-1.8-2.1-2.5-3.3l.8-.5c.7 1.1 1.5 2.2 2.4 3.1l-.7.7Zm34.2-2.5-.8-.6c.7-1.1 1.4-2.3 1.9-3.5l.9.4c-.6 1.3-1.2 2.5-2 3.6ZM1.9 30.9C1.3 29.6.9 28.3.6 27l1-.2c.3 1.3.7 2.5 1.3 3.8L2 31Zm41.3-3-1-.3c.4-1.3.6-2.6.7-3.9h1c-.1 1.5-.4 2.8-.7 4.2ZM0 23.9V22c0-.8 0-1.5.1-2.3h1c0 .8-.1 1.5-.1 2.3v1.8H0Zm43-3.2c0-1.3-.3-2.6-.6-3.9l1-.2c.3 1.3.6 2.7.6 4.1h-1ZM1.6 16.9l-1-.2c.3-1.3.8-2.7 1.4-3.9l.9.4c-.6 1.2-1 2.5-1.3 3.7ZM41.4 14c-.5-1.2-1.1-2.4-1.8-3.5l.8-.5c.8 1.2 1.4 2.4 1.9 3.7l-.9.4Zm-37-3.4-.8-.5c.8-1.2 1.6-2.3 2.6-3.3l.7.7c-.9 1-1.7 2-2.5 3.1Zm33.4-2.5c-.9-1-1.8-1.9-2.9-2.7l.6-.8c1.1.8 2.1 1.8 3 2.8l-.8.7ZM9 5.5l-.6-.8c1.1-.9 2.3-1.6 3.5-2.2l.5.9c-1.2.6-2.3 1.3-3.3 2.1Zm23.4-1.7c-1.1-.7-2.4-1.2-3.6-1.6l.3-.9c1.3.4 2.6 1 3.8 1.7l-.5.9ZM15.1 2.2l-.3-.9c1.3-.5 2.7-.8 4-1v1c-1.2.2-2.5.5-3.7.9Zm10.8-.8C24.6 1.2 23.3 1 22 1V0c1.4 0 2.8.1 4.1.4l-.2 1Z"></path>
				</svg>
			</div>
		<?php endfor; ?>
		<a id="<?= $button_id = get_button_id(); ?>" class="textButtonV2 buttonFramed layoutButton villageStatistics round withIcon green" title="<?= T('inGame', 'Village statistics'); ?>" href="/village/statistics" accesskey="9">
		<svg class="overview"><svg viewBox="0 0 20 14.92" class="outline">
			  <path d="M10 1.41c4.61 0 8.34 6.05 8.34 6.05s-3.73 6.05-8.34 6.05-8.34-6-8.34-6 3.73-6 8.34-6M10 0C7.7 0 5.38 1.16 3.1 3.44A20.17 20.17 0 0 0 .46 6.72L0 7.46l.46.74a20.17 20.17 0 0 0 2.64 3.28c2.28 2.28 4.6 3.44 6.9 3.44s4.62-1.16 6.9-3.44a20.17 20.17 0 0 0 2.64-3.28l.46-.74-.46-.74a20.17 20.17 0 0 0-2.64-3.28C14.62 1.16 12.3 0 10 0zm.08 11.64a4.5 4.5 0 1 1 4.49-4.49 4.5 4.5 0 0 1-4.49 4.49zm0-7.88a3.39 3.39 0 1 0 3.38 3.39 3.39 3.39 0 0 0-3.38-3.39zm0 5.92a2.53 2.53 0 1 1 2.52-2.53 2.53 2.53 0 0 1-2.52 2.53z"></path>
		   </svg><svg viewBox="0 0 20 14.92" class="icon">
			  <path d="M10 1.41c4.61 0 8.34 6.05 8.34 6.05s-3.73 6.05-8.34 6.05-8.34-6-8.34-6 3.73-6 8.34-6M10 0C7.7 0 5.38 1.16 3.1 3.44A20.17 20.17 0 0 0 .46 6.72L0 7.46l.46.74a20.17 20.17 0 0 0 2.64 3.28c2.28 2.28 4.6 3.44 6.9 3.44s4.62-1.16 6.9-3.44a20.17 20.17 0 0 0 2.64-3.28l.46-.74-.46-.74a20.17 20.17 0 0 0-2.64-3.28C14.62 1.16 12.3 0 10 0zm.08 11.64a4.5 4.5 0 1 1 4.49-4.49 4.5 4.5 0 0 1-4.49 4.49zm0-7.88a3.39 3.39 0 1 0 3.38 3.39 3.39 3.39 0 0 0-3.38-3.39zm0 5.92a2.53 2.53 0 1 1 2.52-2.53 2.53 2.53 0 0 1-2.52 2.53z"></path>
		   </svg></svg>
		</a>
		<script type="text/javascript" data-cmp-info="6">jQuery('#<?=$button_id;?>').click(function (event){
			jQuery(window).trigger('buttonClicked', [event.delegateTarget,{"type":"green","loadTooltip":null,"boxId":"","disabled":false,"attention":false,"colorBlind":false,"class":"","id":"<?=$button_id;?>","redirectUrl":"\/village\/statistics","redirectUrlExternal":"","svg":"sideBar\/overview.svg","content":""}]);});</script>
		<button type="button" class="textButtonV2 buttonFramed layoutButton settings round withIcon grey"<?= !empty($vars['quickLinksLocked']) ? ' title="' . htmlspecialchars(T('quickLinks', 'quickLinks') . '||' . T('inGame', 'needPlusDesc'), ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
			<svg viewBox="0 0 13 3" aria-hidden="true"><svg viewBox="0 0 13 3" class="outline"><path d="M3 1.5C3 2.33 2.33 3 1.5 3S0 2.33 0 1.5.67 0 1.5 0 3 .67 3 1.5ZM8 1.5C8 2.33 7.33 3 6.5 3S5 2.33 5 1.5 5.67 0 6.5 0 8 .67 8 1.5ZM13 1.5c0 .83-.67 1.5-1.5 1.5S10 2.33 10 1.5 10.67 0 11.5 0 13 .67 13 1.5Z"></path></svg><svg viewBox="0 0 13 3" class="icon"><path d="M3 1.5C3 2.33 2.33 3 1.5 3S0 2.33 0 1.5.67 0 1.5 0 3 .67 3 1.5ZM8 1.5C8 2.33 7.33 3 6.5 3S5 2.33 5 1.5 5.67 0 6.5 0 8 .67 8 1.5ZM13 1.5c0 .83-.67 1.5-1.5 1.5S10 2.33 10 1.5 10.67 0 11.5 0 13 .67 13 1.5Z"></path></svg></svg>
		</button>
		
	  </div>
   </div>
   <div class="content">
      <div class="expansionSlotInfo">
         <div class="boxTitle"><?=T("inGame", "villages"); ?> <span class="slots"><?=$vars['ownedVillageCount']; ?>‬/‭<?=$vars['maxVillageCount']; ?></span></div>
         <div class="barWrapper">
            <div class="bar" style="width:<?=$vars['percent']; ?>%">&nbsp;</div>
         </div>
      </div>
      <div class="villageList">
		 <div class="groupsHeader">
			<span><?= T('inGame', 'villageGroups'); ?></span>
			<span>(<?=$vars['villageGroupCount']; ?>/<?=$vars['maxVillageGroups']; ?>)</span>
			<button type="button" class="secondaryIconButton addGroup">
				<svg viewBox="0 0 20 16" aria-hidden="true"><path d="M12 12h2v-2h2V8h-2V6h-2v2h-2v2h2v2ZM2 16c-.55 0-1.02-.2-1.41-.59C.2 15.02 0 14.55 0 14V2C0 1.45.2.98.59.59.98.2 1.45 0 2 0h6l2 2h8c.55 0 1.02.2 1.41.59.39.39.59.86.59 1.41v10c0 .55-.2 1.02-.59 1.41-.39.39-.86.59-1.41.59H2Zm0-2h16V4H9.18l-2-2H2v12Z"></path></svg>
			</button>
		 </div>
		 <?=$vars['villageGroups']; ?>
			<?=$vars['villages']; ?>    
      </div>
      <script type="text/javascript" data-cmp-info="6"> 
	  jQuery(function ($) {
		  const villageList = $('#sidebarBoxVillageList');
		  const quickLinks = villageList.find('.quickLinks');
		  const quickLinkActions = <?= json_encode(array_values($vars['quickLinkActions']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
		  const quickLinkActionByKey = Object.fromEntries(quickLinkActions.map(function (action) { return [action.key, action]; }));
		  const quickLinksLocked = <?= json_encode((bool) ($vars['quickLinksLocked'] ?? false)); ?>;
		  const readQuickLinks = function () {
			  if (quickLinksLocked) { return []; }
			  try {
				  const links = JSON.parse(String(quickLinks.attr('data-quick-links') || '[]'));
				  return Array.isArray(links) ? links.filter(function (key, index) {
					  return typeof key === 'string' && quickLinkActionByKey[key] && links.indexOf(key) === index;
				  }).slice(0, 4) : [];
			  } catch (error) {
				  return [];
			  }
		  };
		  const writeQuickLinks = function (links) {
			  const value = JSON.stringify(links.slice(0, 4));
			  quickLinks.attr('data-quick-links', value);
			  Travian.Game.Preferences.set('villageQuickLinks', value);
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
			  wrapper.append($('<h3>').text('Available quick links for the village list'));
			  wrapper.append($('<div>', {class: 'subtitle'}).text('To customize your quick links, drag & drop up to 4 buttons into the slots of your village list. The set of quick links is the same for every village, but the state and tooltip reflect the active village'));
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
			  villageList.addClass('editQuickLinks');
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
					  villageList.removeClass('editQuickLinks');
					  quickLinks.find('.quickLinkSlot').removeClass('editMode');
				  }
			  })).setContent(quickLinksDialogHtml()).show();
			  bindQuickLinksDialog();
		  });
		  quickLinks.on('quickLinksUpdated', renderQuickLinkSlots);
		  renderQuickLinkSlots();
		  const reportError = function (response) {
			  (new Travian.Dialog.Dialog({preventFormSubmit: true}))
				  .setContent(response && response.message ? response.message : 'Request failed.')
				  .show();
		  };
		  const request = function (method, path, data, onSuccess) {
			  Travian.api(path, {data: data, success: onSuccess, error: reportError}, method);
		  };
		  const reload = function () { window.location.reload(); };
		  // The reference sends the whole list after a drag: groups with their villages, then loose villages.
		  const listLayout = function () {
			  const entries = [];
			  villageList.find('.groupWrapper').each(function () {
				  const wrapper = $(this);
				  entries.push({
					  id: wrapper.data('group-wrapper'),
					  villages: wrapper.find('.listEntry.village').map(function () { return $(this).data('did'); }).get()
				  });
			  });
			  villageList.children('.dropContainer').find('.listEntry.village').each(function () {
				  entries.push({id: $(this).data('did'), villages: null});
			  });
			  return entries;
		  };

		  villageList.on('click', '.addGroup', function () {
			  request('POST', 'village-group', {name: <?= json_encode(T('inGame', 'newGroup')); ?>, color: 'grey'}, reload);
		  });
		  villageList.on('click', '.villageGroupToggle', function () {
			  const group = $(this).closest('.listEntry.group');
			  const collapse = group.hasClass('expanded');
			  group.add(villageList.find('.groupWrapper[data-group-wrapper="' + group.data('groupid') + '"]'))
				  .toggleClass('expanded', !collapse).toggleClass('collapsed', collapse);
			  Travian.Game.Preferences.set('villageListGroup' + group.data('groupid') + '_collapsed', collapse);
		  });
		  villageList.on('click', '.villageGroupEdit', function () {
			  const group = $(this).closest('.listEntry.group');
			  const name = group.find('[data-group-name]');
			  const colors = ['grey', 'yellow', 'green', 'turquoise', 'blue', 'violet', 'magenta', 'red', 'orange'];
			  if (!group.hasClass('editMode')) {
				  group.addClass('editMode');
				  name.replaceWith($('<input>', {class: 'name', 'data-group-name': '', maxlength: 20, name: 'groupName', value: name.text()}));
				  const selectedColor = String(group.data('color') || 'grey');
				  const picker = $('<div>', {class: 'villageGroupColors', role: 'radiogroup', 'aria-label': 'Group colour'});
				  colors.forEach(function (color) {
					  picker.append($('<button>', {
						  type: 'button',
						  class: 'villageGroupColor ' + color + (color === selectedColor ? ' selected' : ''),
						  'data-color': color,
						  role: 'radio',
						  'aria-checked': color === selectedColor ? 'true' : 'false',
						  'aria-label': color,
						  title: color
					  }));
				  });
				  group.find('.iconAndNameWrapper').append(picker);
				  $(this).attr('aria-label', 'Save group').attr('title', 'Save group');
				  group.find('.actions').prepend($('<button>', {type: 'button', class: 'secondaryIconButton villageGroupDelete', 'aria-label': 'Delete group', title: 'Delete group'}).html('<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 6h10l-1 11H6L5 6zm2-3h6l1 2H6l1-2z"></path></svg>'));
				  group.find('input[name=groupName]').trigger('focus').select();
				  return;
			  }
			  const color = String(group.find('.villageGroupColor.selected').data('color') || group.data('color') || 'grey');
			  request('PUT', 'village-group/' + group.data('groupid'), {name: group.find('input[name=groupName]').val(), color: color}, reload);
		  });
		  villageList.on('click', '.villageGroupColor', function (event) {
			  event.preventDefault();
			  event.stopPropagation();
			  const swatch = $(this);
			  const group = swatch.closest('.listEntry.group');
			  const color = String(swatch.data('color') || 'grey');
			  group.removeClass('grey yellow green turquoise blue violet magenta red orange').addClass(color).attr('data-color', color).data('color', color);
			  group.find('.villageGroupColor').removeClass('selected').attr('aria-checked', 'false');
			  swatch.addClass('selected').attr('aria-checked', 'true');
		  });
		  villageList.on('click', '.villageGroupDelete', function () {
			  const groupId = $(this).closest('.listEntry.group').data('groupid');
			  (new Travian.Dialog.Dialog({
				  preventFormSubmit: true,
				  onOkay: function () { request('DELETE', 'village-group/' + groupId, undefined, reload); }
			  })).setContent(<?= json_encode(T('inGame', 'deleteVillageGroupConfirmation')); ?>).show();
		  });
		  villageList.on('dragstart', '.listEntry.village', function (event) {
			  const nativeEvent = event.originalEvent;
			  nativeEvent.dataTransfer.effectAllowed = 'move';
			  nativeEvent.dataTransfer.setData('text/plain', String($(this).data('did')));
			  $(this).addClass('beingDragged');
		  });
		  villageList.on('dragend', '.listEntry.village', function () { villageList.find('.villageGroupDropTarget').removeClass('villageGroupDropTarget'); $(this).removeClass('beingDragged'); });
		  villageList.on('dragover', '.groupWrapper .groupContainer, .groupsHeader', function (event) { event.preventDefault(); event.originalEvent.dataTransfer.dropEffect = 'move'; $(this).addClass('villageGroupDropTarget'); });
		  villageList.on('dragleave', '.groupWrapper .groupContainer, .groupsHeader', function () { $(this).removeClass('villageGroupDropTarget'); });
		  villageList.on('drop', '.groupWrapper .groupContainer, .groupsHeader', function (event) {
			  event.preventDefault();
			  const kid = parseInt(event.originalEvent.dataTransfer.getData('text/plain'), 10);
			  const groupId = $(this).closest('.groupWrapper').data('group-wrapper');
			  const entries = listLayout();
			  entries.forEach(function (entry, index) {
				  if (entry.villages === null && entry.id === kid) { entries.splice(index, 1); }
				  if (entry.villages !== null) { entry.villages = entry.villages.filter(function (did) { return did !== kid; }); }
			  });
			  if (groupId) {
				  entries.filter(function (entry) { return entry.villages !== null && entry.id === groupId; })[0].villages.push(kid);
			  } else {
				  entries.push({id: kid, villages: null});
			  }
			  request('POST', 'village-list', {data: entries}, reload);
		  });
		  Travian.Game.VillageList.enableDragAndDropSorting();
	  });</script>
   </div>
</div>
