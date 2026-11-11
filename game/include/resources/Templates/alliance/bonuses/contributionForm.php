<?php

use Core\Config;
use Core\Session;
use Game\Formulas;
use Model\AllianceBonusModel;

$config = Config::getInstance();
$session = Session::getInstance();
$limitPerDay = Formulas::getAllianceBonusDonationLimit((new AllianceBonusModel())->getMaxAllianceBonusLevel($session->getAllianceId()));
$reached = $session->getAllianceContribution() >= $limitPerDay;
?>
<?php if ($reached): ?>
    <span class="limitReachedMessage">
        <?php
        $nextReset = $config->dynamic->lastAllianceContributeReset + $config->allianceBonus->donate_reset_interval;
        $diff = $nextReset - time();
        $nextResetIn = appendTimer($diff);
        echo sprintf(
            T("AllianceBonus", "You have reached you daily contribution limit, Reset in %s"),
            $nextResetIn
        );
        ?>
    </span>
<?php elseif (!$session->checkSitterPermission(Session::SITTER_CAN_CONTRIBUTE_ALLIANCE)): ?>
    <span class="sitterErrorMessage">
        <?= T("AllianceBonus", "As sitter you can`t contribute to alliance bonuses"); ?>
    </span>
<?php else: ?>
    <?php require __DIR__ . "/donateForm.php"; ?>
<?php endif; ?>
