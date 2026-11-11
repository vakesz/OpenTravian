<br/>
<?php if ($vars["canDemolish"]): ?>
<h4 class="round"><?= T("Buildings", "mainBuilding.Demolish building") ?></h4>
<p><?= T("Buildings", "mainBuilding.demolish_desc") ?></p>
<?php if ($vars["isDemolishing"] == false): ?>
    <form class="demolish_building" action="/build.php?gid=15" method="post">
        <input type="hidden" name="action" value="demolishBuilding"/>
        <input type="hidden" name="c" value="<?= $vars["checker"] ?>"/>

        <select id="demolish" name="abriss" class="dropdown">
            <?php foreach ($vars["options"] as $key => $option): ?>
                <option value="<?= $key ?>"><?= $key ?> <?= T(
                    "Buildings",
                    $option["itemId"] . ".title"
                ) ?> <?= $option["level"] ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" value="Demolish" id="btn_demolish" class="textButtonV1 green " version="textButtonV1">
            <?= T("Buildings", "mainBuilding.demolish") ?>
        </button>
        <div class="demolishNow">
            <?= $vars["demolishNow"] ?>
        </div>
        <script type="text/javascript">
            // InstantDemolition + legacy wayOfPayment both post a field id.
            // wayOfPayment wraps the callback result as additionalData.
            function getGid() {
                return {gid: jQuery('#demolish').val()};
            }
            function getDemolishData() {
                return {
                    villageId: <?= (int)\Core\Village::getInstance()->getKid() ?>,
                    slotId: parseInt(jQuery('#demolish').val(), 10),
                    action: jQuery('input[name="action"]').val()
                };
            }
        </script>
        <div class="clear"></div>
    </form>
<?php else: ?>
    <table cellpadding="1" cellspacing="1" id="demolish" class="transparent">
        <tr>
            <td class="abort">
                <button type="button" class="icon "
                        title="<?= T("Global", "General.cancel") ?>"
                        onclick="window.location.href = '/build.php?gid=15&amp;del=<?= (int)$vars[
                            "demolish"
                        ]["taskId"] ?>'; return false;">
                    <img src="img/spacer.gif" class="del" alt="del"/></button>
            </td>
            <td>
                <?= T("Buildings", $vars["demolish"]["itemId"] . ".title") ?>
                <span class="level"><?= T("Buildings", "level") ?>: <?= $vars[
    "demolish"
]["level"] ?></span>
            </td>
            <td class="times">
                <?= $vars["demolish"]["timer"] ?> <?= T(
     "Global",
     "General.hour"
 ) ?>.
            </td>
            <td class="times">
                <?= T("Global", "General.endat") ?> <?= $vars["demolish"][
     "endat"
 ] ?>.
            </td>
        </tr>
    </table>
    <br/>
    <?= $vars["finishNow"] ?>
    <div class="clear"></div>
<?php endif; ?>
<div class="clear"></div>
<?php endif; ?>
<?php if ($vars["canRearrange"]): ?>
<br/>
<h4 class="round"><?= T("Buildings", "mainBuilding.rearrange_title") ?></h4>
<p><?= T("Buildings", "mainBuilding.rearrange_desc") ?></p>
<?= getButton(
    ["type" => "button", "class" => "textButtonV1 gold ", "onclick" => "this.disabled = true; window.location.href = '/dorf2.php?newdid=" . (int)\Core\Village::getInstance()->getKid() . "&mode=rearrange'; return false;"],
    [],
    T("Buildings", "mainBuilding.rearrange")
) ?>
<?php endif; ?>
