<?php
/**
 * Setup wizard page. Included by public/setup.php, which provides $values,
 * $errors, $finished, $setupToken, $setupTokenTooShort, $appDatabaseUser,
 * $clearConfirmation* and $setupTribeNames. Renders with the self-hosted
 * Gpack V2 components (formV2, textButtonV2, contentNavi) and lang/admin.css.
 */

use Game\AdminUi;

$gpackBase = '/gpack/travian/mainPage/';
$adminCssPath = $repositoryRoot . '/assets/gpack/travian/mainPage/lang/admin.css';
$adminCssVersion = is_file($adminCssPath) ? (int)filemtime($adminCssPath) : 0;
$mode = $values['setup_mode'];
$tokenBlocked = $setupToken === '' || $setupTokenTooShort;
$farmOptions = [];
foreach (\Game\Installer\InstallInput::FARM_VILLAGE_PRESETS as $count => $label) {
    $farmOptions[$count] = $label . ($count > 0 ? ' (' . $count . ')' : '');
}
$flagOptions = ['' => 'Built-in default', '1' => 'On', '0' => 'Off'];
$steps = ['Access', 'Database', 'World', 'Accounts', 'Services', 'Review'];
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>OpenTravian setup</title>
    <link rel="stylesheet" href="<?= setup_escape($gpackBase) ?>css_ltr/imports.css">
    <link rel="stylesheet" href="<?= setup_escape($gpackBase) ?>lang/admin.css?v=<?= $adminCssVersion ?>">
</head>
<body class="ltr setupPage">
<div class="adminPage setupWizard">
    <header class="adminHeader">
        <span class="adminBrand">
            <img src="<?= setup_escape($gpackBase) ?>logo.png" alt="" width="40">
            <span>
                <strong>OpenTravian setup</strong>
                <small>Create your local world</small>
            </span>
        </span>
    </header>
    <main class="adminMain contentV2 setupMain">
        <?php if ($finished): ?>
            <h1>Setup is complete</h1>
            <?= AdminUi::notice('This world is installed. The setup wizard is locked.', 'success') ?>
            <section class="adminBox">
                <h2 class="adminBoxTitle">What next</h2>
                <ol class="adminBoxBody">
                    <li>Sign in at <a href="/">the game</a> as <strong>Multihunter</strong> with the password you chose.</li>
                    <li>Open the <a href="/admin.php">control panel</a>. Its home page lists what is still worth setting up.</li>
                    <li>Turn on <a href="/admin.php?action=twoFactor">two-factor sign-in</a> before you invite players.</li>
                    <li>Review the <a href="/admin.php?action=worldRules&amp;view=advanced">world rules</a>, and add email delivery if you skipped it.</li>
                </ol>
            </section>
            <div class="adminActions"><?= AdminUi::button('Open the game', 'green', ['href' => '/']) ?></div>
        <?php else: ?>
            <?php if ($setupToken === ''): ?>
                <?= AdminUi::notice('The wizard is disabled until the server administrator sets TRAVIAN_SETUP_TOKEN.', 'error') ?>
            <?php elseif ($setupTokenTooShort): ?>
                <?= AdminUi::notice('The wizard is disabled because TRAVIAN_SETUP_TOKEN is shorter than ' . SETUP_TOKEN_MIN_LENGTH . ' characters. Generate a longer one with: openssl rand -hex 32', 'error') ?>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <?= AdminUi::notice($error, 'error') ?>
            <?php endforeach; ?>
            <?php if ($clearConfirmationDatabases): ?>
                <h1>Clear existing databases?</h1>
                <section class="adminBox" aria-labelledby="clear-databases-title">
                    <h2 class="adminBoxTitle" id="clear-databases-title">Existing data found</h2>
                    <div class="adminBoxBody">
                        <p>The following database<?= count($clearConfirmationDatabases) === 1 ? '' : 's' ?> contain<?= count($clearConfirmationDatabases) === 1 ? 's' : '' ?> tables:</p>
                        <ul>
                            <?php foreach ($clearConfirmationDatabases as $databaseName): ?>
                                <li><code><?= setup_escape($databaseName) ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                        <p><strong>This permanently deletes every table and the dedicated <code><?= setup_escape($appDatabaseUser) ?></code> application database account before installing a fresh world.</strong></p>
                        <form method="post" action="/setup/" class="adminActions">
                            <input type="hidden" name="setup_action" value="confirm_clear">
                            <input type="hidden" name="clear_nonce" value="<?= setup_escape($clearConfirmationNonce) ?>">
                            <button type="button" class="textButtonV2 buttonFramed rectangle withText grey" onclick="window.location.href='/setup/'"><div>No, keep existing data</div></button>
                            <button type="submit" class="textButtonV2 buttonFramed rectangle withText green"><div>Yes, clear and install</div></button>
                        </form>
                    </div>
                </section>
            <?php else: ?>
                <h1>Install a world</h1>
                <p class="adminLead">Quick install creates a ready-to-play world with sensible defaults. Advanced install exposes every world setting the game supports. The choice is remembered in this browser.</p>
                <?= AdminUi::tabs([
                    ['label' => 'Quick install', 'href' => '/setup/?mode=simple', 'active' => $mode === 'simple', 'attributes' => ['data-mode-tab' => 'simple']],
                    ['label' => 'Advanced install', 'href' => '/setup/?mode=advanced', 'active' => $mode === 'advanced', 'attributes' => ['data-mode-tab' => 'advanced']],
                ], 'Setup mode') ?>
                <?php if ($errors): ?>
                    <p class="adminHint">Secret fields are never sent back to the browser, so enter the token and passwords again.</p>
                <?php endif; ?>
                <ol class="setupSteps no-js-hidden" aria-label="Setup steps">
                    <?php foreach ($steps as $label): ?><li><?= setup_escape($label) ?></li><?php endforeach; ?>
                </ol>
                <form method="post" action="/setup/" id="setup" class="formV2" data-mode="<?= $mode ?>">
                    <input type="hidden" name="setup_mode" value="<?= $mode ?>">

                    <fieldset data-step="Access">
                        <h2>Access</h2>
                        <?= AdminUi::secret('setup_token', 'One-time setup token', ['autocomplete' => 'off', 'spellcheck' => 'false', 'required' => true], 'The <code>TRAVIAN_SETUP_TOKEN</code> value from the server\'s <code>.env</code> file.') ?>
                    </fieldset>

                    <fieldset data-step="Database">
                        <h2>Database</h2>
                        <div class="adminGrid">
                            <?= AdminUi::input('database_host', 'Database host', $values['database_host'], ['required' => true]) ?>
                            <?= AdminUi::input('database_user', 'Database admin user', $values['database_user'], ['required' => true]) ?>
                            <div class="wide"><?= AdminUi::secret('database_password', 'Database admin password', [], 'For the bundled Docker database (host <code>db</code>, user <code>root</code>) use <code>MARIADB_ROOT_PASSWORD</code> from <code>.env</code>. The installer uses it once to create a dedicated database user and never stores it.') ?></div>
                            <div class="advancedOnly"><?= AdminUi::input('account_database', 'Account database', $values['account_database'], ['required' => true]) ?></div>
                            <div class="advancedOnly"><?= AdminUi::input('world_database', 'World database', $values['world_database'], ['required' => true]) ?></div>
                        </div>
                    </fieldset>

                    <fieldset data-step="World">
                        <h2>World</h2>
                        <div class="adminGrid">
                            <?= AdminUi::input('title', 'World name', $values['title'], ['required' => true, 'maxlength' => 100]) ?>
                            <div class="simpleOnly"><?= AdminUi::select('speed_preset', 'Game speed', array_combine(\Game\Installer\InstallInput::SPEED_PRESETS, array_map(static fn($speed) => $speed . 'x', \Game\Installer\InstallInput::SPEED_PRESETS)), $values['speed'], [], 'Construction, production and training scale with the speed. Troop and merchant movement follow the local speed profile.') ?></div>
                            <div class="advancedOnly"><?= AdminUi::input('speed', 'Game speed', $values['speed'], ['type' => 'number', 'min' => 1, 'max' => 1000000000, 'required' => true], '1 to 1,000,000,000. Movement, storage, cranny and trap rates derive from it unless overridden below.') ?></div>
                            <div class="simpleOnly"><?= AdminUi::select('farm_preset', 'Natar farm villages', $farmOptions, $values['farm_villages'], [], 'Villages owned by the Natars, spread over the map, that players can raid for resources or conquer. More can be added later in the control panel.') ?></div>
                            <div class="advancedOnly"><?= AdminUi::input('farm_villages', 'Natar farm villages', $values['farm_villages'], ['type' => 'number', 'min' => 0, 'max' => \Game\Installer\InstallInput::MAX_FARM_VILLAGES, 'required' => true], '0 to ' . \Game\Installer\InstallInput::MAX_FARM_VILLAGES . '. Spread over the map; each has a small Natar garrison and can be raided or conquered.') ?></div>
                            <div class="advancedOnly"><?= AdminUi::input('world_id', 'World ID', $values['world_id'], ['maxlength' => 5, 'required' => true], 'Up to 5 letters, numbers, underscores or hyphens.') ?></div>
                            <div class="advancedOnly"><?= AdminUi::input('starting_gold', 'Starting gold', $values['starting_gold'], ['type' => 'number', 'min' => 0, 'max' => 999999999, 'required' => true]) ?></div>
                            <div class="advancedOnly"><?= AdminUi::select('map_size', 'Map size', ['100' => '201 x 201 (-100 to 100)', '200' => '401 x 401 (-200 to 200)', '400' => '801 x 801 (-400 to 400)'], (string)$values['map_size']) ?></div>
                            <div class="advancedOnly wide"><?= AdminUi::input('index_url', 'Account/index URL', $values['index_url'], ['type' => 'url', 'required' => true]) ?></div>
                            <div class="advancedOnly wide"><?= AdminUi::input('world_url', 'Game URL', $values['world_url'], ['type' => 'url', 'required' => true]) ?></div>
                        </div>

                        <div class="advancedOnly">
                            <h3>Playable tribes</h3>
                            <div class="adminGrid tribeGrid">
                                <?php foreach ($setupTribeNames as $tribeId => $tribeName): ?>
                                    <label class="checkbox">
                                        <input type="checkbox" name="playable_tribes[]" value="<?= $tribeId ?>"<?= in_array($tribeId, $values['playable_tribes'], true) ? ' checked' : '' ?>>
                                        <svg viewBox="0 0 18 18" class="checkbox" aria-hidden="true"><path class="box" d="M16 0H2a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V2a2 2 0 00-2-2z"></path><rect class="indeterminate" width="10" height="2" x="4" y="8"></rect><path class="checkmark" d="M15.28 4.28l-8.27 8.27-4.3-4.26h0"></path></svg>
                                        <div class="label"><?= $tribeName ?></div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="adminHint">Players can pick any ticked tribe at activation and registration. Romans, Teutons, Gauls, Egyptians, Huns and Spartans start ticked; Vikings start off. This can be changed later in the admin panel.</p>

                            <h3>Speed profile overrides</h3>
                            <p class="adminHint">Leave a field empty to use the value derived from the speed and the local world-speed profile (see the README).</p>
                            <div class="adminGrid">
                                <?php foreach (\Game\Installer\InstallInput::PROFILE_FIELDS as $key => [$label, $type, , $min, $max, $unit]): ?>
                                    <?php $derived = \Game\Installer\InstallInput::effectiveProfile(['speed' => (int)$values['speed']])[$key]; ?>
                                    <?= AdminUi::input('profile[' . $key . ']', $label . ' (' . $unit . ')', $values['profile_input'][$key] ?? '', [
                                        'type' => 'text', 'inputmode' => 'decimal', 'id' => 'f_profile_' . $key,
                                        'placeholder' => 'derived: ' . (\Game\Installer\InstallInput::PROFILE_FIELDS[$key][2] > 1 ? round($derived / \Game\Installer\InstallInput::PROFILE_FIELDS[$key][2], 2) : $derived),
                                        'autocomplete' => 'off',
                                    ], $min . ' to ' . $max) ?>
                                <?php endforeach; ?>
                            </div>

                            <h3>Game rules</h3>
                            <div class="adminGrid">
                                <?php foreach (\Game\WorldOverrides::DEFINITIONS as $key => [, $type, $min, $max, $label]): ?>
                                    <?php if ($type === 'bool'): ?>
                                        <?= AdminUi::select('override[' . $key . ']', $label, $flagOptions, isset($values['overrides'][$key]) ? ($values['overrides'][$key] ? '1' : '0') : '', ['id' => 'f_override_' . $key]) ?>
                                    <?php elseif ($type === 'string'): ?>
                                        <?= AdminUi::input('override[' . $key . ']', $label, $values['overrides'][$key] ?? '', [
                                            'type' => 'text', 'maxlength' => max($max, 32), 'id' => 'f_override_' . $key, 'placeholder' => $key === 'discordUrl' ? 'https://example.com/invite' : 'built-in default', 'autocomplete' => 'off',
                                        ], $key === 'discordUrl' ? 'Optional. Every Discord link stays hidden until an https URL is set; editable later in the admin panel' : 'Video bonuses stay disabled until the provider is custom and a URL is set') ?>
                                    <?php else: ?>
                                        <?= AdminUi::input('override[' . $key . ']', $label, $values['override_input'][$key] ?? '', [
                                            'type' => 'number', 'min' => $min, 'max' => $max, 'id' => 'f_override_' . $key, 'placeholder' => 'built-in default',
                                        ], $min . ' to ' . $max) ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset data-step="Accounts">
                        <h2>Accounts</h2>
                        <?= AdminUi::secret('multihunter_password', 'Multihunter account password', ['minlength' => 12, 'required' => true], 'The staff account. Sign in with it through the normal login, then open <code>/admin.php</code>. At least 12 characters.') ?>
                        <label class="checkbox">
                            <input type="checkbox" name="create_starter_player" value="1"<?= $values['create_starter_player'] === '1' ? ' checked' : '' ?>>
                            <svg viewBox="0 0 18 18" class="checkbox" aria-hidden="true"><path class="box" d="M16 0H2a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V2a2 2 0 00-2-2z"></path><rect class="indeterminate" width="10" height="2" x="4" y="8"></rect><path class="checkmark" d="M15.28 4.28l-8.27 8.27-4.3-4.26h0"></path></svg>
                            <div class="label">Create a starter player with a first village</div>
                        </label>
                        <div class="adminGrid starter-player-fields">
                            <?= AdminUi::input('player_name', 'Starter player name', $values['player_name'], ['maxlength' => 15, 'data-starter-required' => 'true']) ?>
                            <label class="select fixed">
                                <select name="player_race" data-starter-required="true">
                                    <?php foreach ($setupTribeNames as $tribeId => $tribeName): ?>
                                        <option value="<?= $tribeId ?>"<?= (string)$values['player_race'] === (string)$tribeId ? ' selected' : '' ?><?= !in_array($tribeId, \Game\Tribes::DEFAULT_PLAYABLE, true) ? ' class="advancedOnly"' : '' ?>><?= $tribeName ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="label">Starter player tribe</div>
                            </label>
                            <div class="wide"><?= AdminUi::secret('player_password', 'Starter player password', ['minlength' => 12, 'data-starter-required' => 'true']) ?></div>
                        </div>
                    </fieldset>

                    <fieldset data-step="Services">
                        <h2>Services</h2>
                        <p class="adminHint">Optional. Skip this step to run without email; everything here can be set later in the control panel.</p>
                        <?= AdminUi::input('support_email', 'Support recipient email', $values['support_email'], ['type' => 'email', 'maxlength' => \Game\SupportContact::MAX_LENGTH, 'autocomplete' => 'email'], 'Optional without email delivery, required with it. Player support requests are delivered to this address; change it later in the admin panel.') ?>
                        <h3>Email delivery</h3>
                        <p class="adminHint">Optional. Leave the SMTP host empty to run without email: support requests cannot be sent and other mail stays queued until SMTP is configured in the admin panel. The SMTP password is stored only in the protected state volume and is never displayed again. Player email addresses are used only as Reply-To addresses.</p>
                        <div class="adminGrid">
                            <?= AdminUi::input('smtp_host', 'SMTP host', $values['smtp_host'], ['maxlength' => \Game\SmtpConfig::MAX_HOST_LENGTH, 'autocomplete' => 'off']) ?>
                            <?= AdminUi::input('smtp_port', 'SMTP port', $values['smtp_port'], ['type' => 'number', 'min' => 1, 'max' => 65535, 'inputmode' => 'numeric']) ?>
                            <?= AdminUi::select('smtp_encryption', 'SMTP encryption', ['starttls' => 'STARTTLS', 'smtps' => 'SMTPS'], $values['smtp_encryption']) ?>
                            <?= AdminUi::input('smtp_from', 'SMTP sender email', $values['smtp_from'], ['type' => 'email', 'maxlength' => \Game\SupportContact::MAX_LENGTH, 'autocomplete' => 'email'], 'The configured operator address used for From and envelope sender.') ?>
                            <?= AdminUi::select('smtp_auth', 'SMTP authentication', ['1' => 'Required', '0' => 'Not required'], $values['smtp_auth']) ?>
                            <div class="smtp-auth-fields">
                                <?= AdminUi::input('smtp_username', 'SMTP username', $values['smtp_username'], ['maxlength' => \Game\SmtpConfig::MAX_USERNAME_LENGTH, 'autocomplete' => 'username', 'data-smtp-auth-required' => 'true']) ?>
                                <?= AdminUi::secret('smtp_password', 'SMTP password', ['maxlength' => \Game\SmtpConfig::MAX_PASSWORD_LENGTH, 'data-smtp-auth-required' => 'true'], 'Required when SMTP authentication is enabled.') ?>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset data-step="Review" class="reviewStep no-js-hidden">
                        <h2>Review</h2>
                        <p>Check the settings below, then install. Passwords are not shown.</p>
                        <table class="adminTable" id="setupReview"><tbody></tbody></table>
                    </fieldset>

                    <div class="adminActions setupActions">
                        <button type="button" class="back no-js-hidden textButtonV2 buttonFramed rectangle withText grey"><div>Back</div></button>
                        <button type="button" class="next no-js-hidden textButtonV2 buttonFramed rectangle withText green"><div>Next</div></button>
                        <button type="submit" class="textButtonV2 buttonFramed rectangle withText green"<?= $tokenBlocked ? ' disabled' : '' ?>><div>Install game</div></button>
                    </div>
                </form>
                <script>
                    (function () {
                        var form = document.getElementById('setup');
                        var pages = form.querySelectorAll('fieldset');
                        var markers = document.querySelectorAll('.setupSteps li');
                        var back = form.querySelector('.back');
                        var next = form.querySelector('.next');
                        var submit = form.querySelector('[type=submit]');
                        var modeField = form.querySelector('[name=setup_mode]');
                        var review = document.querySelector('#setupReview tbody');
                        var current = 0;

                        function applyMode(mode) {
                            form.setAttribute('data-mode', mode);
                            modeField.value = mode;
                            document.querySelectorAll('[data-mode-tab]').forEach(function (tab) {
                                var active = tab.getAttribute('data-mode-tab') === mode;
                                tab.className = 'tabItem ' + (active ? 'active' : 'normal');
                                if (active) { tab.setAttribute('aria-current', 'page'); } else { tab.removeAttribute('aria-current'); }
                            });
                            form.querySelectorAll('.advancedOnly').forEach(function (block) {
                                var off = mode !== 'advanced';
                                if (block.tagName === 'OPTION') { block.disabled = off; block.hidden = off; return; }
                                block.hidden = off;
                                block.querySelectorAll('input,select,textarea').forEach(function (field) { field.disabled = off; });
                            });
                            form.querySelectorAll('.simpleOnly').forEach(function (block) {
                                var off = mode === 'advanced';
                                block.hidden = off;
                                block.querySelectorAll('input,select').forEach(function (field) { field.disabled = off; });
                            });
                            var race = form.querySelector('[name=player_race]');
                            if (race && race.options[race.selectedIndex] && race.options[race.selectedIndex].disabled) {
                                race.value = race.querySelector('option:not(:disabled)').value;
                            }
                        }

                        var starterToggle = form.querySelector('[name=create_starter_player]');
                        var starterFields = form.querySelector('.starter-player-fields');
                        function updateStarterPlayerFields() {
                            var enabled = starterToggle.checked;
                            starterFields.hidden = !enabled;
                            starterFields.querySelectorAll('input,select').forEach(function (field) {
                                field.disabled = !enabled;
                                field.required = enabled && field.hasAttribute('data-starter-required');
                            });
                        }
                        starterToggle.addEventListener('change', updateStarterPlayerFields);

                        var smtpHost = form.querySelector('[name=smtp_host]');
                        var smtpAuth = form.querySelector('[name=smtp_auth]');
                        var smtpAuthFields = form.querySelector('.smtp-auth-fields');
                        var smtpRequired = form.querySelectorAll('[name=smtp_from],[name=support_email]');
                        // Email delivery is optional: nothing is required until an SMTP host is entered.
                        function updateSmtpFields() {
                            if (!smtpHost || !smtpAuth || !smtpAuthFields) { return; }
                            var configured = smtpHost.value.trim() !== '';
                            smtpRequired.forEach(function (field) { field.required = configured; });
                            var authEnabled = configured && smtpAuth.value === '1';
                            smtpAuthFields.hidden = !authEnabled;
                            smtpAuthFields.querySelectorAll('input').forEach(function (field) {
                                field.disabled = !authEnabled;
                                field.required = authEnabled && field.hasAttribute('data-smtp-auth-required');
                            });
                        }
                        if (smtpHost) { smtpHost.addEventListener('input', updateSmtpFields); }
                        if (smtpAuth) { smtpAuth.addEventListener('change', updateSmtpFields); }

                        form.querySelectorAll('.adminSecretToggle').forEach(function (button) {
                            button.addEventListener('click', function () {
                                var input = document.getElementById(button.getAttribute('data-target'));
                                var reveal = input.type === 'password';
                                input.type = reveal ? 'text' : 'password';
                                button.setAttribute('aria-pressed', String(reveal));
                                button.setAttribute('aria-label', reveal ? 'Hide value' : 'Show value');
                            });
                        });

                        function labelOf(field) {
                            var label = field.closest('label');
                            var text = label ? label.querySelector('.label') : null;
                            return text ? text.textContent.replace(/:$/, '') : field.name;
                        }

                        function reviewValue(field) {
                            if (field.name === 'speed') {
                                var speed = Number(field.value);
                                if (Number.isInteger(speed) && speed > 0) {
                                    return speed.toLocaleString('en-US') + '×';
                                }
                            }
                            return field.value;
                        }

                        function buildReview() {
                            review.textContent = '';
                            function row(name, value) {
                                var tr = document.createElement('tr');
                                var th = document.createElement('td');
                                th.className = 'head';
                                th.textContent = name;
                                var td = document.createElement('td');
                                td.textContent = value;
                                tr.appendChild(th);
                                tr.appendChild(td);
                                review.appendChild(tr);
                            }
                            row('Mode', modeField.value === 'advanced' ? 'Advanced install' : 'Quick install');
                            var tribes = [];
                            form.querySelectorAll('input,select').forEach(function (field) {
                                if (field.disabled || field.type === 'hidden' || field.type === 'button' || field.type === 'submit') { return; }
                                if (field.name === 'playable_tribes[]') {
                                    if (field.checked) { tribes.push(labelOf(field)); }
                                    return;
                                }
                                if (field.type === 'checkbox') { row(labelOf(field), field.checked ? 'Yes' : 'No'); return; }
                                if (field.type === 'password') { row(labelOf(field), field.value ? 'Set' : 'Not set'); return; }
                                if (field.tagName === 'SELECT') { row(labelOf(field), field.options[field.selectedIndex].text); return; }
                                if (field.value !== '') { row(labelOf(field), reviewValue(field)); }
                            });
                            if (tribes.length) { row('Playable tribes', tribes.join(', ')); }
                        }

                        function pageIsValid(page) {
                            var fields = page.querySelectorAll('input,select');
                            for (var i = 0; i < fields.length; i++) {
                                if (!fields[i].reportValidity()) { return false; }
                            }
                            return true;
                        }

                        function show(index) {
                            current = index;
                            pages.forEach(function (page, i) { page.classList.toggle('active', i === index); });
                            markers.forEach(function (marker, i) {
                                marker.classList.toggle('done', i < index);
                                marker.classList.toggle('current', i === index);
                                if (i === index) { marker.setAttribute('aria-current', 'step'); } else { marker.removeAttribute('aria-current'); }
                            });
                            if (pages[index].classList.contains('reviewStep')) { buildReview(); }
                            back.hidden = index === 0;
                            next.hidden = index === pages.length - 1;
                            submit.hidden = index !== pages.length - 1;
                            var first = pages[index].querySelector('input:not([disabled]):not([type=hidden]),select:not([disabled])');
                            if (first) { first.focus(); }
                        }

                        document.querySelectorAll('[data-mode-tab]').forEach(function (tab) {
                            tab.addEventListener('click', function (event) {
                                event.preventDefault();
                                var chosen = tab.getAttribute('data-mode-tab');
                                applyMode(chosen);
                                // The server stores the choice in a cookie; the response is not needed.
                                fetch('/setup/?mode=' + chosen, {credentials: 'same-origin'}).catch(function () {});
                            });
                        });
                        back.addEventListener('click', function () { show(current - 1); });
                        next.addEventListener('click', function () { if (pageIsValid(pages[current])) { show(current + 1); } });
                        form.addEventListener('keydown', function (event) {
                            if (event.key === 'Enter' && event.target.tagName === 'INPUT' && current < pages.length - 1) {
                                event.preventDefault();
                                next.click();
                            }
                        });
                        form.addEventListener('invalid', function (event) {
                            for (var i = 0; i < pages.length; i++) {
                                if (pages[i].contains(event.target) && i !== current) { show(i); break; }
                            }
                        }, true);

                        document.documentElement.classList.add('js');
                        applyMode(form.getAttribute('data-mode'));
                        updateStarterPlayerFields();
                        updateSmtpFields();
                        show(0);
                    })();
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
