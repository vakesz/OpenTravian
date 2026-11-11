<h4 class="round"><?php use Core\Session;
    use Game\Formulas;

    echo T("Options", "Vacation"); ?></h4>

<div>
    <?= T("Options", "Use the vacation to protect your villages while being abroad"); ?>
    <br/>
    <?= T("Options", "While in vacation mode the following actions will be deactivated"); ?>:
    <ul>
        <li><?= T("Options", "send or receive troops"); ?></li>
        <li><?= T("Options", "going on adventures with your hero", "going on adventures with your hero"); ?></li>
        <li><?= T("Options", "starting new building orders or training troops", "starting new building orders or training troops"); ?></li>
        <li><?= T("Options", "using the marketplace (including trade routes)", "using the marketplace (including trade routes)"); ?></li>
        <li><?= T("Options", "holding celebrations or starting research or upgrades", "holding celebrations or starting research or upgrades"); ?></li>
        <li><?= T("Options", "creating or joining alliances", "creating or joining alliances"); ?></li>
        <li><?= T("Options", "contributing resources to alliance bonus", "contributing resources to alliance bonus"); ?></li>
        <li><?= T("Options", "participate in the auction", "participate in the auction"); ?></li>
        <li><?= T("Options", "changing the capital", "changing the capital"); ?></li>
        <li><?= T("Options", "deleting your account", "deleting your account"); ?></li>
    </ul>
</div>

<?php
// The reference paints the switch closed on every load and hides the list only when every condition is met.
$allConditionsMet = array_sum($vars['conditions']) === count($vars['conditions']);
$switchStatus = 'Closed';
?>
<div class="conditionsWrapper">
    <div class="switchWrap">
        <div class="openedClosedSwitch switch<?= $switchStatus; ?>"
             id="vacationModeToggle"><?= sprintf(T("Options", "x/y conditions met"),
                "\u{202D}\u{202D}" . array_sum($vars['conditions']) . "\u{202C}",
                "\u{202D}" . count($vars['conditions']) . "\u{202C}\u{202C}"); ?></div>
        <div class="clear"></div>
    </div>
    <div id="vacationModeConditions"<?= $allConditionsMet ? ' class="hide"' : ''; ?>>
        <ul>
            <?php foreach ([
                1 => "There are no outgoing troops",
                2 => "There are no incoming troops",
                3 => "There are no troops reinforing other players",
                4 => "No other player reinforces you",
                5 => "You have not annexed an oasis in the last 48 hours",
                6 => "You dont own a Wonder of the World Village",
                7 => "You dont own an artifact",
                8 => "You dont have beginners protection left",
                9 => "There are no troops in your traps",
                10 => "Your account is not in deletion",
            ] as $conditionId => $conditionLabel): ?>
                <li<?= !$vars['conditions'][$conditionId] ? ' class="alert"' : ''; ?>><?= T("Options", $conditionLabel); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php $remainingVacationDays = Formulas::remainingVacationDays((int)Session::getInstance()->getUsedVacationDays()); ?>
<?php $canGoOnVacation = $remainingVacationDays > 0 && array_sum($vars['conditions']) === count($vars['conditions']); ?>
<div class="vacationMode">
    <div><?= T("Options", "How many days should the vacation mode last"); ?>:</div>
    <div class="daySetter">
        <span class="daySetter sub" id="daySetterSub"><a href="#"></a></span>
        <input type="text" size="2" inputmode="numeric" name="days"
               id="dayInput" <?= $canGoOnVacation ? '' : 'disabled="disabled"'; ?>
               value="<?= $remainingVacationDays; ?>"/>
        <span class="daySetter add" id="daySetterAdd"><a href="#"></a></span>
    </div>
    <div id="vacationTimeWrapper"><?= T("Options", "Vacation til"); ?>
        &nbsp;<span id="vacationTime"></span>
    </div>
    <div><?= sprintf(T("Options", "Available Days x/y"),
            "\u{202D}\u{202D}" . $remainingVacationDays . "\u{202C}",
            "\u{202D}" . Formulas::maxVacationDays() . "\u{202C}\u{202C}"); ?></div>
    <div class="submitButtonContainer">
        <button type="button" id="vacationModeStart" version="textButtonV1"
                class="textButtonV1 green<?= !$canGoOnVacation ? ' disabled' : ''; ?>"
                title="<?= T("Options", "enter vacation mode now"); ?>"
                onclick="if(jQuery(this).hasClass('disabled')){event.stopPropagation(); return false;} return Travian.Game.Vacation.openConfirmation();"
                onfocus="jQuery('button', 'input[type!=hidden]', 'select').trigger('focus'); event.stopPropagation(); return false;"
                value="<?= T("Options", "enter vacation mode now"); ?>"><?= T("Options", "enter vacation mode now"); ?></button>
    </div>
</div>
<script type="text/javascript">
    jQuery(function () {
        var dayInput = jQuery('#dayInput'),
            days = dayInput.val(),
            tOffset = 16200;
        Travian.Game.Vacation.updateVacationTime(days, tOffset);
        if (days > 0) {
            jQuery('#vacationTimeWrapper').show();
        }

        var toggle = jQuery('#vacationModeToggle');
        if (toggle.length > 0) {
            toggle.on('click', function(e) {
                Travian.toggleSwitch(jQuery('#vacationModeConditions'), jQuery('#vacationModeToggle'));
            });
        }

        var setterSub = jQuery('#daySetterSub');
        if (setterSub.length > 0) {
            setterSub.on('click', function(e) {
                e.preventDefault();
                days = parseInt(dayInput.val());
                if(days > 1 ){
                    dayInput.val(--days);
                    Travian.Game.Vacation.updateVacationTime(days, tOffset);
                }
            });
        }

        var setterAdd = jQuery('#daySetterAdd');
        if (setterAdd.length > 0) {
            setterAdd.on('click', function(e) {
                e.preventDefault();
                days = parseInt(dayInput.val());
                if(days < <?=$remainingVacationDays;?>){
                    dayInput.val(++days);
                    Travian.Game.Vacation.updateVacationTime(days, tOffset);
                }
            });
        }

        if (dayInput.length > 0) {
            dayInput.on('change', function(e){
                if (isNaN(parseInt(dayInput.val()))) {
                    dayInput.val(<?=$remainingVacationDays;?>);
                } else if(dayInput.val() > <?=$remainingVacationDays;?>) {
                    dayInput.val(<?=$remainingVacationDays;?>);
                } else if(dayInput.val() < 1){
                    dayInput.val(1);
                }

                var days = dayInput.val();
                Travian.Game.Vacation.updateVacationTime(days, tOffset);

            });
            dayInput.on('keyup', function (e) {
                if (e.key === "Enter") {
                    this.blur();
                } else {
                    days = dayInput.val();
                    if (days.length > 0) {
                        days = parseInt(days);
                        if (!isNaN(days) || days.length === 0) {
                            if (days < 1) {
                                days = 1;
                            }
                            if (days > <?=$remainingVacationDays;?>) {
                                days = <?=$remainingVacationDays;?>;
                            }

                            dayInput.val(days);
                            Travian.Game.Vacation.updateVacationTime(days, tOffset);
                        } else {
                            dayInput.val(1);
                            Travian.Game.Vacation.updateVacationTime(1, tOffset);
                        }
                    }
                }
            })
        }
    });

</script>
