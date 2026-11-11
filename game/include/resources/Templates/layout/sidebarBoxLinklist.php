<div id="sidebarBoxLinklist" class="sidebarBox expanded">
	<div class="header">
		<div class="buttonsWrapper">
		   <?php
     $d = [
         "class" => "",
         "type" => $vars["plus"] ? "green" : "gold",
         "loadTitle" => false,
         "boxId" => "",
         "disabled" => false,
         "speechBubble" => "",
         "svg" => $vars["plus"] ? "edit_green" : "edit_gold",
         "redirectUrl" => $vars["plus"] ? "/linklist.php" : "#",
         "plusDialog" => $vars["plus"]
             ? []
             : [
                 "featureKey" => "linkList",
                 "cssClass" =>
                     "premiumFeaturePackage premiumFeaturePlus paymentShopV5",
                 "premiumFeatureDialogVersion" => 2,
                 "version" => 2,
             ],
     ];
     $noticeText = T("links", "OpenTravian Plus allows you to make a link list");
     $plusDialogCall = "new Travian.Game.PlusDialog({featureKey: 'linkList', cssClass: 'premiumFeaturePackage premiumFeaturePlus paymentShopV5', premiumFeatureDialogVersion: 2, version: 2}); return false;";
     if ($vars["plus"]) {
         unset($d["plusDialog"]);
     } else {
         // The reference opens the dialog from the button's own onclick; the data carries no plusDialog.
         unset($d["plusDialog"]);
         $d["title"] = T("links", "Link list") . " || " . $noticeText;
     }
     $linklistButton = getAButton(
         [
             "id" => get_button_id(),
             "type" => "button",
             "class" =>
                 "layoutButton buttonFramed withIcon round edit " .
                 ($vars["plus"] ? "green" : "gold"),
             "title" => htmlspecialchars(
                 $vars["plus"] ? T("links", "edit link list") : $d["title"],
                 ENT_QUOTES,
                 "UTF-8"
             ),
             "svg" => $vars["plus"] ? "edit_green" : "edit_gold",
             "href" => $vars["plus"] ? "/linklist.php" : "#",
         ],
         ["data" => $d]
     );
     if (!$vars["plus"]) {
         $linklistButton = str_replace(
             'onclick="event.preventDefault();"',
             'onclick="' . htmlspecialchars($plusDialogCall, ENT_QUOTES, "UTF-8") . '"',
             $linklistButton
         );
     }
     echo $linklistButton;
     ?>
		</div>
	</div>
	<div class="content">
		<div class="boxTitle"><?= T("links", "Link list") ?></div>
		<?php if ($vars["links"] !== ""): ?><ul><?= $vars[
    "links"
] ?></ul><?php endif; ?>
		<?php if (!$vars["plus"]): ?>
			<div class="linklistNotice"><?= T(
       "links",
       "OpenTravian Plus allows you to make a link list"
   ) ?></div>
		<?php elseif ($vars["noLinks"]): ?>
			<div class="linklistNotice"><?= T("links", "edit link list") ?></div>
		<?php endif; ?>
	</div>
</div>
