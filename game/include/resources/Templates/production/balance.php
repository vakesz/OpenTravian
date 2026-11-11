<?php

use Core\Database\DB;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Model\ArtefactsModel;

$wwEffect = Village::getInstance()->isWW() && ArtefactsModel::wwPlansReleased() ? 1 / 2 : 1;
$helper = new Game\GoldHelper();
// Figures sit in left-to-right isolates; negatives carry a real minus sign inside a nested one.
$figure = static function ($value): string {
    $value = (float)$value;
    $text = number_format_x((int)round(abs($value)));

    return $value < 0
        ? "\u{202D}\u{2212}\u{202D}" . $text . "\u{202C}\u{202C}"
        : "\u{202D}" . $text . "\u{202C}";
};
?>
<div class="cropBalanceContainer">
    <?php
    ob_start();
    $totalProd = 0;
    $totalBonus = 0;
    $boostTotalBonus = 0;
    foreach ($vars['boost'][$vars['tabIndex']] as $boost) {
        $level = $vars['bonusBuilding'][$boost];
        if ($boost == 45) {
            $boostTotalBonus += ($level * 0.05) * $vars['oasesBonus']['bonus'][$vars['tabIndex'] - 1];
        } else {
            $boostTotalBonus += $level * 5;
        }
    }
    foreach ($vars['productionFields'] as $field) {
        $bonus = round5($field['value'] * ($boostTotalBonus + $vars['oasesBonus']['bonus'][$field['item_id'] - 1]) / 100);
        $totalProd += $field['value'];
        $totalBonus += $bonus;
    }
    $loadingCrop = Village::getInstance()->getCropLoading();
    // Interim balance = production (+hero, +25% when active) minus the crop
    // eaten by villagers AND construction orders (458 - 106 = 352).
    $heroCrop = $vars['heroProd'][3];
    $plusGain = round(($totalProd + $totalBonus + $heroCrop) / 4);
    $consumption = Village::getInstance()->getPop() + $loadingCrop;
    $interimBalance = $totalProd + $totalBonus - $consumption + $heroCrop + ($vars['bonusPlus'][3] ? $plusGain : 0);
    $interimBalanceWithBoost = $totalProd + $totalBonus - $consumption + $heroCrop + $plusGain;
    ?>
    <div class="balanceCropBalancePart">
        <table cellspacing="0" cellpadding="0" class="row_table_data">
            <tbody>
            <tr>
                <td><?= T("productionOverview", "Production of buildings and oases"); ?></td>
                <td class="numberCell"><?= $figure($totalProd + $totalBonus); ?></td>
            </tr>
            <tr>
                <td><?= T("productionOverview", "Population and construction orders"); ?></td>
                <td class="numberCell"><?= $figure(-$consumption); ?></td>
            </tr>
            <tr class="subtotal">
                <td class="bold"><?= T("Manual", "freeCrop"); ?></td>
                <td class="bold numberCell"><?= $figure($totalProd + $totalBonus - $consumption); ?></td>
            </tr>
            <tr>
                <td><?= T("productionOverview", "Incomplete construction orders"); ?></td>
                <td class="numberCell"><?= $figure($loadingCrop); ?></td>
            </tr>
            <tr>
                <td><?= T("productionOverview", "hero_production"); ?></td>
                <td class="numberCell"><?= $figure($heroCrop); ?></td>
            </tr>
            <tr class="<?= !$vars['bonusPlus'][3] ? 'inactive' : ''; ?>">
                <td>&#x202d;&#x202d;25&#x202c;%&#x202c; <?= lcfirst((string)T("productionOverview", "production")); ?><?= !$vars['bonusPlus'][3] ? ' (' . T("productionOverview", "inactive") . ')' : ''; ?></td>
                <td class="numberCell"><?= $figure($plusGain); ?></td>
            </tr>
            <tr class="bold">
                <td class="bold">&#x202d;&#x202d;0&#x202c;%&#x202c; <?= T("productionOverview", "compensation_bonus"); ?></td>
                <td class="numberCell"><?= $figure(0); ?></td>
            </tr>
            <tr class="subtotal">
                <td class="bold"><?= T("productionOverview", "interim_balance"); ?> =</td>
                <td class="bold numberCell"><?= $figure($interimBalance); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
    <?php
    /**
     * @param mixed $kid
     * @return string|null
     */
    function getOasesAsString($kid)
    {
        $kid = (int)$kid;
        $db = DB::getInstance();
        $find = $db->query("SELECT kid FROM odata WHERE did=$kid");
        if (!$find->num_rows) {
            return null;
        }
        $oases = [];
        while ($row = $find->fetch_assoc()) {
            $oases[] = $row['kid'];
        }
        $find->free();

        return implode(",", $oases);
    }


    $hdp = Village::getInstance()->getHorseDrinkingPoolLvl();
    $db = DB::getInstance();
    $totalOwnHDP = 0;
    $units = $db->query("SELECT * FROM units WHERE kid=" . Village::getInstance()->getKid())->fetch_assoc();
    $inVillage = 0;
    for ($i = 1; $i <= 11; ++$i) {
        $inVillage += $units['u' . $i] * Formulas::uUpkeep(nrToUnitId($i, Session::getInstance()->getRace()), 0);
        if (Formulas::checkHDPEffect(nrToUnitId($i, Session::getInstance()->getRace()), $hdp)) {
            $totalOwnHDP += $units['u' . $i] * Formulas::getHDPAndNonHDPDiffCrop(nrToUnitId($i,
                    Session::getInstance()->getRace()),
                    $hdp);
        }
    }
    $enf = $db->query("SELECT * FROM enforcement WHERE kid=" . Session::getInstance()->getKid());
    $enforcement = 0;
    while ($row = $enf->fetch_assoc()) {
        for ($i = 1; $i <= 11; ++$i) {
            $enforcement += $row['u' . $i] * Formulas::uUpkeep(nrToUnitId($i, Session::getInstance()->getRace()), 0);
            if (Formulas::checkHDPEffect(nrToUnitId($i, Session::getInstance()->getRace()), $hdp)) {
                $totalOwnHDP += $row['u' . $i] * Formulas::getHDPAndNonHDPDiffCrop(nrToUnitId($i,
                        Session::getInstance()->getRace()),
                        $hdp);
            }
        }
    }
    $imprisoned = 0;
    $trapped = $db->query("SELECT * FROM trapped WHERE kid=" . Session::getInstance()->getKid());
    while ($row = $trapped->fetch_assoc()) {
        for ($i = 1; $i <= 11; ++$i) {
            $imprisoned += $row['u' . $i] * Formulas::uUpkeep(nrToUnitId($i, Session::getInstance()->getRace()), 0);
            if (Formulas::checkHDPEffect(nrToUnitId($i, Session::getInstance()->getRace()), $hdp)) {
                $totalOwnHDP += $row['u' . $i] * Formulas::getHDPAndNonHDPDiffCrop(nrToUnitId($i,
                        Session::getInstance()->getRace()),
                        $hdp);
            }
        }
    }
    $kid = Session::getInstance()->getKid();
    $onTheWay = 0;
    $trapped = $db->query("SELECT * FROM movement WHERE ((kid=$kid AND mode=0) OR (to_kid=$kid AND mode=1))");
    while ($row = $trapped->fetch_assoc()) {
        for ($i = 1; $i <= 11; ++$i) {
            $onTheWay += $row['u' . $i] * Formulas::uUpkeep(nrToUnitId($i, Session::getInstance()->getRace()), 0);
            if (Formulas::checkHDPEffect(nrToUnitId($i, Session::getInstance()->getRace()), $hdp)) {
                $totalOwnHDP += $row['u' . $i] * Formulas::getHDPAndNonHDPDiffCrop(nrToUnitId($i,
                        Session::getInstance()->getRace()),
                        $hdp);
            }
        }
    }
    $total = $inVillage + $enforcement + $onTheWay + $imprisoned;
    $artEff = ArtefactsModel::getArtifactEffectByType(Session::getInstance()->getPlayerId(),
        Session::getInstance()->getKid(),
        \Model\ArtefactsModel::ARTIFACT_DIET);
    $sum = floor(($total - $totalOwnHDP) * $artEff * $wwEffect);
    $total_mine = $sum;
    ?>
    <div class="balanceCropBalancePart balanceTroops">
        <div class="switchDown" id="ownBalanceTroops">
            <div class="switchDownOpenStateContainer hide">
                <div class="switchOpened headline"><?= T("productionOverview", "Consumption of own troops"); ?></div>
                <div class="switchDownContent">
                    <table>
                        <tbody>
                        <tr>
                            <td class="troopLabel">- <?= T("productionOverview", "in village"); ?></td>
                            <td class="numberCell"><?= $figure(0 - $inVillage); ?></td>
                        </tr>
                        <tr>
                            <td class="troopLabel"> - <?= T("productionOverview", "reinforcements from your own villages"); ?></td>
                            <td class="numberCell"><?= $figure(0 - $enforcement); ?></td>
                        </tr>
                        <tr>
                            <td class="troopLabel">- <?= T("productionOverview", "in oases"); ?></td>
                            <td class="numberCell"><?= $figure(0); ?></td>
                        </tr>
                        <tr>
                            <td class="troopLabel">- <?= T("productionOverview", "on the way"); ?></td>
                            <td class="numberCell"><?= $figure(0 - $onTheWay); ?></td>
                        </tr>
                        <tr>
                            <td class="troopLabel">- <?= T("productionOverview", "troops being forwarded"); ?></td>
                            <td class="numberCell"><?= $figure(0); ?></td>
                        </tr>
                        <tr>
                            <td class="troopLabel">- <?= T("productionOverview", "imprisoned"); ?></td>
                            <td class="numberCell"><?= $figure(0 - $imprisoned); ?></td>
                        </tr>
                        <tr class="<?= $artEff <> 1 ? '' : 'inactive'; ?>">
                            <td class="troopLabel"><?= T("productionOverview", "Artefact bonus"); ?></td>
                            <td class="numberCell"><?= $figure($total - ($total * $artEff)); ?></td>
                        </tr>
                        <?php if ($wwEffect < 1): ?>
                            <tr>
                                <td class="troopLabel"><?= T("productionOverview", "WW effect"); ?></td>
                                <td class="numberCell"><?= $figure($total - ($total * $wwEffect)); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (Session::getInstance()->getRace() == 1): ?>
                            <tr class="<?= $totalOwnHDP >= 1 ? '' : 'inactive'; ?>">
                                <td class="troopLabel"><?= T("productionOverview", "HDP"); ?></td>
                                <td class="numberCell"><?= $figure($totalOwnHDP); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="subtotal">
                            <td class="bold"><?= T("productionOverview", "sum"); ?></td>
                            <td class="numberCell bold"><?= $figure(0 - $sum); ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="switchDownCloseStateContainer">
                <div class="switchClosed headline"><?= T("productionOverview", "Consumption of own troops"); ?></div>
                <div class="switchDownContent"><span class="bold"><?= $figure(0 - $sum); ?></span></div>
                <div class="clear"></div>
            </div>
        </div>
        <script type="text/javascript">
            jQuery(function () {
                new Travian.Game.SwitchDown('#ownBalanceTroops');
            });
        </script>
    </div>
    <?php
    /**
     * @param mixed $kid
     * @return mixed
     */
    function getHDP($kid)
    {
        $db = DB::getInstance();
        $buildings = $db->query("SELECT `f18t`, `f19`, `f19t`, `f20`, `f20t`, `f21`, `f21t`, `f22`, `f22t`, `f23`, `f23t`, `f24`, `f24t`, `f25`, `f25t`, `f26`, `f26t`, `f27`, `f27t`, `f28`, `f28t`, `f29`, `f29t`, `f30`, `f30t`, `f31`, `f31t`, `f32`, `f32t`, `f33`, `f33t`, `f34`, `f34t`, `f35`, `f35t`, `f36`, `f36t`, `f37`, `f37t`, `f38`, `f38t` FROM fdata WHERE kid={$kid}");
        if (!$buildings->num_rows) {
            return 0;
        }
        $buildings = $buildings->fetch_assoc();
        for ($i = 18; $i <= 38; ++$i) {
            if ($buildings['f' . $i . 't'] == 41) {
                return $buildings['f' . $i];
            }
        }

        return 0;
    }

    $totalHDP = 0;
    $enf = $db->query("SELECT * FROM enforcement WHERE to_kid=" . Session::getInstance()->getKid());
    $inVillage = 0;
    while ($row = $enf->fetch_assoc()) {
        $hdp = getHDP($row['kid']);//set hdp of kid
        for ($i = 1; $i <= 11; ++$i) {
            $inVillage += $row['u' . $i] * Formulas::uUpkeep(nrToUnitId($i, $row['race']), 0);
            if (Formulas::checkHDPEffect(nrToUnitId($i, Session::getInstance()->getRace()), $hdp)) {
                $totalHDP += $row['u' . $i] * Formulas::getHDPAndNonHDPDiffCrop(nrToUnitId($i,
                        Session::getInstance()->getRace()),
                        $hdp);
            }
        }
    }
    $oases = getOasesAsString(Village::getInstance()->getKid());
    $enforcement = 0;
    if (!empty($oases)) {
        $enf = $db->query("SELECT * FROM enforcement WHERE to_kid IN($oases)");
        while ($row = $enf->fetch_assoc()) {
            $hdp = getHDP($row['kid']);//set hdp of kid
            for ($i = 1; $i <= 11; ++$i) {
                $enforcement += $row['u' . $i] * Formulas::uUpkeep(nrToUnitId($i, $row['race']), 0);
                if (Formulas::checkHDPEffect(nrToUnitId($i, Session::getInstance()->getRace()), $hdp)) {
                    $totalHDP += $row['u' . $i] * Formulas::getHDPAndNonHDPDiffCrop(nrToUnitId($i,
                            Session::getInstance()->getRace()),
                            $hdp);
                }
            }
        }
    }
    $total = $inVillage + $enforcement;
    $sum = floor(($total - $totalHDP) * $artEff * $wwEffect);
    $total_mine += $sum;
    ?>
    <div class="balanceCropBalancePart balanceTroops">
        <div class="switchDown" id="foreignBalanceTroops">
            <div class="switchDownOpenStateContainer hide">
                <div class="switchOpened headline"><?= T("productionOverview", "Consumption of foreign troops"); ?></div>
                <div class="switchDownContent">
                    <table>
                        <tbody>
                        <tr>
                            <td class="troopLabel">- <?= T("productionOverview", "reinforcing your village"); ?></td>
                            <td class="numberCell"><?= $figure(0 - $inVillage); ?></td>
                        </tr>
                        <tr>
                            <td class="troopLabel">- <?= T("productionOverview", "in oases"); ?></td>
                            <td class="numberCell"><?= $figure(0 - $enforcement); ?></td>
                        </tr>
                        <tr>
                            <td class="troopLabel">- <?= T("productionOverview", "troops being forwarded"); ?></td>
                            <td class="numberCell"><?= $figure(0); ?></td>
                        </tr>
                        <tr class="<?= $artEff <> 1 ? '' : 'inactive'; ?>">
                            <td class="troopLabel"><?= T("productionOverview", "Artefact bonus"); ?></td>
                            <td class="numberCell"><?= $figure($total - ($total * $artEff)); ?></td>
                        </tr>
                        <?php if ($wwEffect < 1): ?>
                            <tr>
                                <td class="troopLabel"><?= T("productionOverview", "WW effect"); ?></td>
                                <td class="numberCell"><?= $figure($total - ($total * $wwEffect)); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (Session::getInstance()->getRace() == 1): ?>
                            <tr class="<?= $totalHDP >= 1 ? '' : 'inactive'; ?>">
                                <td class="troopLabel"><?= T("productionOverview", "HDP"); ?></td>
                                <td class="numberCell"><?= $figure($totalHDP); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="subtotal">
                            <td class="bold"><?= T("productionOverview", "sum"); ?></td>
                            <td class="numberCell bold"><?= $figure(0 - $sum); ?></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="switchDownCloseStateContainer">
                <div class="switchClosed headline"><?= T("productionOverview", "Consumption of foreign troops"); ?></div>
                <div class="switchDownContent"><span class="bold"><?= $figure(0 - $sum); ?></span></div>
                <div class="clear"></div>
            </div>
        </div>
        <script type="text/javascript">
            jQuery(function () {
                new Travian.Game.SwitchDown('#foreignBalanceTroops');
            });
        </script>
    </div>
    <div class="balanceCropBalancePart">
        <table cellspacing="0" cellpadding="0" class="row_table_data">
            <tbody>
            <tr class="total">
                <td class="bold"><?= T("productionOverview", "Crop balance"); ?> =</td>
                <td class="bold numberCell"><?= $figure($interimBalance - $total_mine); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
    <?php
    $content = ob_get_clean();
    ?>
    <div class="productionBoostSpeechBubble">
        <div class="productionBoostResourceSpeechBubble">
            <div class="fluidSpeechBubble-container">
                <div class="fluidSpeechBubble">
                    <div class="fluidSpeechBubble-tl"></div>
                    <div class="fluidSpeechBubble-tr"></div>
                    <div class="fluidSpeechBubble-tc"></div>
                    <div class="fluidSpeechBubble-ml"></div>
                    <div class="fluidSpeechBubble-mr"></div>
                    <div class="fluidSpeechBubble-mc"></div>
                    <div class="fluidSpeechBubble-bl"></div>
                    <div class="fluidSpeechBubble-br"></div>
                    <div class="fluidSpeechBubble-bc"></div>
                    <div class="speechArrowBack"></div>
                    <div class="fluidSpeechBubble-contents cf">
                        <form id="fluidSpeechBubble" method="post" action="">
                            <p><?= sprintf(T("productionOverview", "productionWithBoost"), '&#x202d;&#x202d;25&#x202c;%&#x202c;'); ?>: <span class="bold"><?= $figure($interimBalanceWithBoost - $total_mine); ?></span></p>
                            <?= $helper->getProductionBoostButton(4, false, false, true); ?>
                            <div class="productionBoostSpeechBubbleFurtherInfo">
                                <p><?= T("productionOverview", "productionBoostSpeechBubbleFurtherInfo"); ?></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?= $content; ?>
    <?php
    /*
    <div class="emptyStorageSpeechBubble">
        <div class="fluidSpeechBubble-container">
            <div class="fluidSpeechBubble">
                <div class="fluidSpeechBubble-tl"></div>
                <div class="fluidSpeechBubble-tr"></div>
                <div class="fluidSpeechBubble-tc"></div>
                <div class="fluidSpeechBubble-ml"></div>
                <div class="fluidSpeechBubble-mr"></div>
                <div class="fluidSpeechBubble-mc"></div>
                <div class="fluidSpeechBubble-bl"></div>
                <div class="fluidSpeechBubble-br"></div>
                <div class="fluidSpeechBubble-bc"></div>
                <div class="speechArrowBack"></div>
                <div class="fluidSpeechBubble-contents cf"><p>زمان باقی مانده برای خالی شدن انبار غذا و نیروهای به دلیل گرسنگی خواهند مرد: <span class="bold">1d 06:11:52</span></p></div>
            </div>
        </div>
    </div>
    */
    ?>
</div>