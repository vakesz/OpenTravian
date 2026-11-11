<form id="settings" action="/options/game" method="post">
	<input type="hidden" name="s" value="1"/>
	<input type="hidden" name="e" value="1"/>
	<?= \Core\Session::getCheckerInput(); ?>

	<?php
 use Core\Config;
 use Core\Session;
 ?>
	<?php
 $checked = 'checked="checked"';
 $session = Session::getInstance();
 ?>
	<h4 class="round"><?= T("Options", "Contextual help", "Contextual help") ?></h4>
	<table class="transparent set" cellpadding="1" cellspacing="1" id="contextual_help">
		<tbody>
		<tr>
			<td class="sel">
				<input class="check" <?= $session->getDisplay()[7] == 1 ? $checked : "" ?>
				       type="checkbox" name="hideContextualHelp" id="hideContextualHelp" value="1"/>
			</td>
			<td><label for="hideContextualHelp"><?= str_replace(
       "features. Changing",
       "features.<br />Changing",
       (string) T(
           "Options",
           "Don't show the contextual help for current features",
           "Don't show the contextual help for current features. Changing this preference will set it as your account's default and will be pre-selected for all new avatars."
       )
   ) ?></label></td>
		</tr>
		<tr>
			<td></td>
			<td><?= T(
       "Options",
       "Note: newly introduced features will have contextual help enabled when released",
       "Note: newly introduced features will have contextual help enabled when released. Once released, you can disable this help feature here."
   ) ?></td>
		</tr>
		</tbody>
	</table>
	<h4 class="round spacer">
		<span><?= T("Options", "Seasonal mood images") ?></span>
		<span class="hidden pleaseSaveYourChanges"><?= T(
      "Options",
      "Please save your changes",
      "Please save your changes."
  ) ?></span>
	</h4>
	<table class="transparent set" cellpadding="1" cellspacing="1" id="night_mode_settings">
		<tbody>
		<tr class="tr_seasonal_mode">
			<td class="sel">
				<input class="check" <?= $session->getDisplay()[6] == 1 ? $checked : "" ?>
				       type="checkbox" name="option_night_mode" id="option_night_mode" value="1" onchange="jQuery('body').find('span.pleaseSaveYourChanges').removeClass('hidden')"/>
			</td>
			<td><label for="option_night_mode"><?= T(
       "Options",
       "Enable night mood images"
   ) ?></label></td>
		</tr>
		</tbody>
	</table>
	<h4 class="round spacer"><?= T("Options", "Report", "Report") ?></h4>
	<table class="transparent set" cellpadding="1" cellspacing="1"
	       id="report_filter">
		<tbody>
		<tr>
			<td class="sel">
				<input class="check" <?= $session->getDisplay()[8] == 1 ? $checked : "" ?>
				       type="checkbox" name="showDeleteReportsConfirmation"
				       id="showDeleteReportsConfirmation" value="1"/>
			</td>
			<td><label for="showDeleteReportsConfirmation"><?= T(
       "Options",
       "Show confirmation when deleting multiple reports",
       "Show confirmation when deleting multiple reports"
   ) ?></label></td>
		</tr>
		<tr>
			<td class="sel">
				<input
					class="check" <?= $session->getReportFilters()[0] == 1 ? $checked : "" ?>
					type="checkbox"
					name="c4" value="1" id="report_filter_option_4"/>
			</td>
			<td>
				<label
					for="report_filter_option_4"><?= T(
         "Options",
         "No reports about transfers between your own villages"
     ) ?></label>
			</td>
		</tr>
		<tr>
			<td class="sel">
				<input
					class="check" <?= $session->getReportFilters()[1] == 1 ? $checked : "" ?>
					type="checkbox"
					name="c5" value="1" id="report_filter_option_5"/>
			</td>
			<td>
				<label
					for="report_filter_option_5"><?= T(
         "Options",
         "No reports about transfers to foreign villages"
     ) ?></label>
			</td>
		</tr>
		<tr>
			<td class="sel">
				<input
					class="check" <?= $session->getReportFilters()[2] == 1 ? $checked : "" ?>
					type="checkbox"
					name="c6" value="1" id="report_filter_option_6"/>
			</td>
			<td>
				<label
					for="report_filter_option_6"><?= T(
         "Options",
         "No reports about transfers from foreign villages"
     ) ?></label>
			</td>
		</tr>
		</tbody>
	</table>
	<h4 class="round spacer"><?= T("Options", "Alliance settings") ?></h4>
	<table class="transparent set" cellpadding="1" cellspacing="1"
	       id="alliance">
		<tbody>
		<tr>
			<td class="sel">
				<input
					class="check" <?= $session->isAllianceNotificationEnabled() ? $checked : "" ?>
					type="checkbox" name="receiveAllianceInvitations" value="1" id="receiveAllianceInvitations"/>
			</td>
			<td>
				<label for="receiveAllianceInvitations"><?= T(
        "Options",
        "Receive notifications about invitations to an alliance"
    ) ?></label>
			</td>
		</tr>
		</tbody>
	</table>
	<?php if (Session::getInstance()->getAllianceId() > 0): ?>
		<table class="transparent set" cellpadding="1" cellspacing="1"
		       id="alliance">
			<tbody>
			<tr>
				<td colspan="2"><?= T("Options", "Show alliance news") ?>:</td>
			</tr>
			<tr>
				<td class="sel">
					<input
						class="check" <?= $session->getAllianceSettings()[0] == 1 ? $checked : "" ?>
						type="checkbox" name="c7" value="1" id="v7"/>
				</td>
				<td><label for="v7"><?= T("Options", "Alliance members founded new village") ?></label></td>
			</tr>
			<tr>
				<td class="sel">
					<input
						class="check" <?= $session->getAllianceSettings()[1] == 1 ? $checked : "" ?>
						type="checkbox" name="c8" value="1" id="v8"/>
				</td>
				<td><label for="v8"><?= T("Options", "New alliance member joined") ?></label></td>
			</tr>
			<tr>
				<td class="sel">
					<input
						class="check" <?= $session->getAllianceSettings()[2] == 1 ? $checked : "" ?>
						type="checkbox" name="c9" value="1" id="v9"/>
				</td>
				<td><label for="v9"><?= T("Options", "Player has been invited") ?></label></td>
			</tr>
			<tr>
				<td class="sel">
					<input
						class="check" <?= $session->getAllianceSettings()[3] == 1 ? $checked : "" ?>
						type="checkbox" name="c10" value="1" id="v10"/>
				</td>
				<td><label for="v10"><?= T("Options", "Player has left alliance") ?></label></td>
			</tr>
			<tr>
				<td class="sel">
					<input
						class="check" <?= $session->getAllianceSettings()[4] == 1 ? $checked : "" ?>
						type="checkbox" name="c11" value="1" id="v11"/>
				</td>
				<td><label for="v11"><?= T("Options", "Player was kicked from alliance") ?></label></td>
			</tr>
			</tbody>
		</table>
	<?php endif; ?>
	<h4 class="round spacer"><?= T("Options", "Auto-complete") ?></h4>
	<table class="transparent set" cellpadding="1" cellspacing="1"
	       id="completion">
		<tbody>
		<tr>
			<td colspan="2"><?= rtrim(
       (string) T("Options", "Complete for rally point and marketplace"),
       " :؛"
   ) ?>:</td>
		</tr>
		<tr>
			<td class="sel">
				<input
					class="check" <?= $session->getAutoComplete()[0] == 1 ? $checked : "" ?>
					type="checkbox"
					name="c1" value="1" id="v1"/>
			</td>
			<td>
				<label for="v1"><?= T(
        "Options",
        "Own villages"
    ) ?></label>
			</td>
		</tr>
		<tr>
			<td class="sel">
				<input
					class="check" <?= $session->getAutoComplete()[1] == 1 ? $checked : "" ?>
					type="checkbox"
					name="c2" value="1" id="v2"/>
			</td>
			<td>
				<label for="v2"><?= T(
        "Options",
        "Nearby villages"
    ) ?></label>
			</td>
		</tr>
		<tr>
			<td class="sel">
				<input
					class="check" <?= $session->getAutoComplete()[2] == 1 ? $checked : "" ?>
					type="checkbox"
					name="c3" value="1" id="v3"/>
			</td>
			<td>
				<label for="v3"><?= T(
        "Options",
        "Alliance members' villages"
    ) ?></label>
			</td>
		</tr>
		</tbody>
	</table>
	<h4 class="round spacer"><?= T("Options", "Display") ?></h4>

	<table class="transparent set" cellpadding="1" cellspacing="1" id="other">
		<tbody>
		<tr>
				<td class="sel">
					<input
						class="check" <?= $session->getDisplay()[0] == 1 ? $checked : "" ?>
						type="checkbox"
						id="flag_hiden_report_image"
						name="flag_hiden_report_image" value="1"/>
				</td>
				<td>
					<label
						for="flag_hiden_report_image"><?= T(
          "Options",
          "Don't display images in reports"
      ) ?></label>
				</td>
		</tr>
		</tbody>
	</table>

	<table class="transparent set" cellpadding="1" cellspacing="1"
	       id="entriesPerPage">
		<tbody>
		<tr>
			<td>
				<label for="epp"><?= T("Options", "Messages and reports per page") ?></label>
			</td>
			<td>
				<input type="text" inputmode="numeric" maxlength="2"
				       value="<?= $session->getReportsRecordsPerPage() ?>"
				       id="epp"
				       name="epp" class="text messageReport"/>
			</td>
		</tr>
		<tr>
			<td>
				<label for="troopMovementsPerPage"><?= T(
        "Options",
        "Troop movements per page in rally point"
    ) ?></label>
			</td>
			<td>
				<input type="text" inputmode="numeric" maxlength="2"
				       value="<?= $session->getRallyPointRecordsPerPage() ?>"
				       id="troopMovementsPerPage" name="troopMovementsPerPage"
				       class="text troopMovementsPerPage"/>
			</td>
		</tr>
		<tr>
			<td>
				<label for="marketplaceOffersPerPage"><?= T(
        "Options",
        "Offers per page in the marketplace",
        "Offers per page in the marketplace:"
    ) ?></label>
			</td>
			<td>
				<input type="text" inputmode="numeric" maxlength="2" value="<?= $session->getMarketplaceOffersPerPage() ?>"
				       id="marketplaceOffersPerPage" name="marketplaceOffersPerPage" class="marketplaceOffersPerPage text"/>
			</td>
		</tr>
		</tbody>
	</table>
	<h4 class="round spacer"><?= T("Options", "Time zone preferences") ?></h4>
    <table class="transparent set timeSettings" cellpadding="1" cellspacing="1">
        <tbody>
        <tr>
            <td colspan="2"><?= T(
                "Options",
                "You can change your time zone here"
            ) ?></td>
        </tr>
        <tr>
            <th><?= T("Options", "Time zone") ?></th>
            <td>
                <select name="timezone">
                    <?php $myTimeZone = (string) (Session::getInstance()->getTimezone()[0] ?? 'default'); ?>
                    <option value="default"<?= $myTimeZone === 'default' ? ' selected="selected"' : '' ?>><?= htmlspecialchars(T("Options", "UTC server time option", "(UTC) Server time"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></option>
                    <?php foreach (\Core\Helper\TimezoneHelper::timezoneOptions((array) Config::getInstance()->timezones) as $zoneId => $zoneLabel): ?>
                        <option value="<?= htmlspecialchars((string) $zoneId, ENT_QUOTES, "UTF-8") ?>"<?= $myTimeZone === (string) $zoneId ? ' selected="selected"' : '' ?>><?= htmlspecialchars((string) $zoneLabel, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        </tbody>
    </table>
    <table class="transparent set timeSettings" cellpadding="1" cellspacing="1">
        <tbody>
        <tr>
            <td class="sel">
                <input class="check" type="checkbox" id="flag_local_time_instead_server" name="flag_local_time_instead_server" value="1"
                       <?= !empty(Session::getInstance()->getTimezone()[2]) ? $checked : "" ?>>
            </td>
            <td>
                <label for="flag_local_time_instead_server"><?= T(
                    "Options",
                    "Show local time instead of server time on top",
                    "Show local time instead of server time on top"
                ) ?></label>
            </td>
        </tr>
        </tbody>
    </table>
    <table class="transparent set timeSettings" cellpadding="1" cellspacing="1">
        <tbody>
        <tr>
            <th class="timeFormat">
                <?= T("Options", "Date format") ?></th>
            <td>
                <label>
                    <input
                            class="radio" <?= $session->getTimezone()[1] == 0
                                ? $checked
                                : "" ?>
                            type="radio"
                            name="tformat" value="0"/> EU (dd.mm.yy, 24h) </label>
                <label>
                    <input
                            class="radio" <?= $session->getTimezone()[1] == 1
                                ? $checked
                                : "" ?>
                            type="radio"
                            name="tformat" value="1"/> US (mm/dd/yy, 12h) </label>
                <label>
                    <input
                            class="radio" <?= $session->getTimezone()[1] == 2
                                ? $checked
                                : "" ?>
                            type="radio"
                            name="tformat" value="2"/> UK (dd/mm/yy, 12h) </label>
                <label>
                    <input
                            class="radio" <?= $session->getTimezone()[1] == 3
                                ? $checked
                                : "" ?>
                            type="radio"
                            name="tformat" value="3"/> ISO (yy/mm/dd, 24h) </label>
            </td>
        </tr>
        </tbody>
    </table>
	<h4 class="round spacer"><?= T(
     "Options",
     "language settings"
 ) ?></h4>
	<table class="transparent set" cellpadding="1" cellspacing="1" id="languageSettings">
		<tbody>
		<tr>
			<th><?= T("Options", "Language") ?></th>
			<td>
				<select name="language">
					<?php
     $Languages = (array) Config::getInstance()->settings->availableLanguages;
     $selectedLocale = get_locale();
     foreach ($Languages as $value) {
         $locale = (string) $value->locale;
         echo '<option value="' .
             htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') .
             '"' .
             ($locale === $selectedLocale ? ' selected="selected"' : "") .
             ">" .
             htmlspecialchars((string) $value->name, ENT_QUOTES, 'UTF-8') .
             "</option>";
     }
     ?>
				</select>
			</td>
		</tr>
		</tbody>
	</table>


	<div class="submitButtonContainer">
		<button type="submit" value="<?= T("Global", "General.save") ?>" name="s1" id="btn_ok" class="textButtonV1 green " version="textButtonV1"><?= T("Global", "General.save") ?></button>
		<script type="text/javascript">
			jQuery(function() {
				if (jQuery('#btn_ok').length > 0) {
					jQuery('#btn_ok').click(function (event) {
						jQuery(window).trigger('buttonClicked', [this, {
							"type": "submit",
							"value": "<?= T("Global", "General.save") ?>",
							"name": "s1",
							"id": "btn_ok",
							"class": "textButtonV1 green ",
							"title": "",
							"confirm": "",
							"onclick": "",
							"version": "textButtonV1"
						}]);
					});
				}
			});
		</script>
	</div>
</form>
