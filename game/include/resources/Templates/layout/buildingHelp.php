<?php
/**
 * Contextual help control of a building page (reference: Marketplace and Barracks). Unlike the
 * reference's page tour it opens the in-game manual entry of the building, like the top-right
 * manual button; it never links to an external document.
 *
 * $vars: buildingId (int); a value below 1 opens the manual overview (pages without a building entry)
 */
?>
<a id="contextualHelpButton" class="contentTitleButton buttonFramed green withIcon rectangle"
   onclick="<?= (int)$vars['buildingId'] > 0 ? "Travian.Game.Manual.open('building', " . (int)$vars['buildingId'] . ')' : 'Travian.Game.Manual.open()' ?>; return false;"
   title=" <?= T('inGame', 'Show contextual help on this page') ?>">&nbsp;</a>
