<?php

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\TimezoneHelper;
use Game\Formulas;
use Game\PremiumDuration;
use Model\ArtefactsModel;
use Model\SummaryModel;
use Model\WonderOfTheWorldModel;

class ConfigurationDetailsCtrl
{
    /** Validation message for the last rejected Discord URL submission. */
    private ?string $discordError = null;
    /** Validation message for the last rejected support recipient submission. */
    private ?string $supportEmailError = null;
    /** Validation message for the last rejected SMTP submission. */
    private ?string $smtpError = null;
    /** Validation message for the last rejected social sign-in submission. */
    private ?string $socialError = null;
    private ?string $finishConstructionError = null;
    private ?string $premiumDurationError = null;

    /** Rows the Simple view keeps; every other row of the three tables is Advanced only. */
    private const SIMPLE_ROWS = [
        'World ID', 'Speed', 'Start time', 'Round age/length',
        'Register', 'Activation', 'Playable tribes', 'Discord link', 'Support recipient', 'SMTP delivery', 'Maintenance',
        'Active/Registered', 'Players online', 'Occupied/Free Villages', 'Engine status',
    ];

    public function __construct()
    {

        $config = Config::getInstance();
        $this->processActions();
        $dispatcher = Dispatcher::getInstance();
        $dispatcher->appendContent(\Game\AdminUi::page(
            'Info and settings',
            'World configuration, live counts and operator settings for this installation.',
            ''
        ));
        $dispatcher->appendContent('<div class="adminCol">');
        $params['content'] = null;
        $db = DB::getInstance();
        $params['title'] = 'Game Information';
        $this->addInfo($params['content'], 'World ID', getWorldId());
        $this->addInfo($params['content'], 'World Unique ID', getWorldUniqueId());
        $this->addInfo($params['content'], 'Speed', getGameSpeed());
        $this->addInfo($params['content'], 'Movement speed', getGame("movement_speed_increase"));
        $this->addInfo($params['content'], 'Map size', (2 * MAP_SIZE + 1) . ' x ' . (2 * MAP_SIZE + 1) . ' (coordinates -' . MAP_SIZE . ' to ' . MAP_SIZE . ')');
        $this->addInfo($params['content'], 'Min distance', round(Formulas::getMapMinDistanceFromCenter(), 1));
        $this->addInfo($params['content'], 'Storage multiplier', getGame("storage_multiplier"));
        $this->addInfo($params['content'], 'Cranny multiplier', getGame("cranny_multiplier"));
        $this->addInfo($params['content'], 'Trap multiplier', getGame("trap_multiplier"));
        $this->addInfo($params['content'], 'Cage multiplier', getGame("cage_multiplier"));
        $this->addInfo($params['content'], 'Hero regeneration multiplier', getGame("heroRegenerationMultiplier"));
        $this->addInfo($params['content'], 'Oasis animal cap', getGame("oasisAnimalCap"));
        $this->addInfo($params['content'], 'Oasis respawn interval', getGame("oasisRespawnInterval") . ' seconds');
        $this->addInfo($params['content'],
            'Start time',
            TimezoneHelper::autoDateString($config->game->start_time, true));
        $this->addInfo($params['content'],
            'Round age/length',
            round(getGameElapsedSeconds() / 86400,
                1) . '/' . round(($config->timers->wwPlansReleaseTime - $config->game->start_time) / 86400,
                1) . ' day(s)');
        $this->addInfo($params['content'], 'Round length (by natars)', round(getGame("round_length"), 2) . ' day(s)');
        $this->addInfo($params['content'],
            'Start/Daily gold',
            $config->gold->startGold . '/' . $config->gold->dailyGold);

        $this->addInfo($params['content'],
            'Last medals',
            TimezoneHelper::autoDateString($config->dynamic->lastMedalsGiven, true));
        $this->addInfo($params['content'],
            'Next medals',
            TimezoneHelper::autoDateString(getGame('medals_interval') + $config->dynamic->lastMedalsGiven, true));

        $this->addInfo($params['content'],
            'Last dailyQuest',
            TimezoneHelper::autoDateString($config->dynamic->lastDailyQuestReset, true));
        $this->addInfo($params['content'],
            'Next dailyQuest',
            TimezoneHelper::autoDateString($config->dynamic->lastDailyQuestReset + getGame('dailyQuestInterval'),
                true));

        $this->addInfo($params['content'], 'Daily quest interval', $config->game->dailyQuestInterval);

        $this->addInfo($params['content'], 'Protection hours', getGame("protection_time") / 3600);
        if (!ArtefactsModel::artifactsReleased()) {
            $this->addInfo($params['content'],
                'Artifacts Release',
                appendTimer($config->timers->ArtifactsReleaseTime - time(), 0, true));
        }
        if (!ArtefactsModel::wwPlansReleased()) {
            $this->addInfo($params['content'],
                'WWPlans Release',
                appendTimer($config->timers->wwPlansReleaseTime - time(), 0, true));
        }
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/ServerInfo.tpl')->getAsString());
        $dispatcher->appendContent('<br />');
        $params['content'] = null;
        $params['title'] = 'Settings';
        $gameServer = GlobalDB::getInstance()->query("SELECT * FROM gameServers WHERE id=" . getWorldUniqueId())->fetch_assoc();
        $this->addInfo($params['content'],
            'Register',
            $this->toggleForm('registerClosed', $gameServer['registerClosed'] == 0, 'Open', 'Closed'));
        $this->addInfo($params['content'],
            'Activation',
            $this->toggleForm('activation', $gameServer['activation'] == 1, 'Enabled', 'Disabled'));
        $this->addInfo($params['content'],
            'Need preregistration code',
            $this->toggleForm('needPreregistrationCode', $gameServer['preregistration_key_only'] == 1, 'Yes', 'No'));
        $this->addInfo($params['content'], 'Playable tribes', $this->playableTribesForm());
        $this->addInfo($params['content'], 'Discord link', $this->discordUrlForm());
        $this->addInfo($params['content'], 'Support recipient', $this->supportEmailForm());
        $this->addInfo($params['content'], 'Speed-world construction finish', $this->finishConstructionForm());
        $this->addInfo($params['content'], 'SMTP delivery', $this->smtpForm());
        $this->addInfo($params['content'], 'Social sign-in', $this->socialForm());
        $this->addInfo($params['content'], 'Premium feature durations', $this->premiumDurationForm());
        $this->addInfo($params['content'],
            'Maintenance',
            $this->toggleForm('maintenance', $config->dynamic->maintenance == 1, 'On', 'Off'));
        $this->addInfo($params['content'],
            'Fake accounts progress:',
            $this->toggleForm('fakeAccountProcess', $config->dynamic->fakeAccountProcess == 1, 'On', 'Off'));
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/ServerInfo.tpl')->getAsString());
        $dispatcher->appendContent('</div>');
        $dispatcher->appendContent('<div class="adminCol">');
        $params['content'] = null;
        $params['title'] = 'Player Information';
        $summary = new SummaryModel();
        $summary = $summary->getTotalSummary();
        {
            $percent = round($summary['active'] / ($summary['registered']) * 100, 1);
            $color = $percent <= 50 ? 'red' : ($percent <= 80 ? 'orange' : 'green');
            $percent = ' <span class="adminStat ' . $color . '">(' . $percent . '%)</span>';
            $this->addInfo($params['content'],
                'Active/Registered',
                $summary['active'] . '/' . $summary['registered'] . $percent);
        }
        {
            $percent = round($summary['fakeCount'] / ($summary['active']) * 100, 1);
            $color = $percent <= 20 ? 'green' : ($percent <= 40 ? 'orange' : 'red');
            $percent = ' <span class="adminStat ' . $color . '">(' . $percent . '%)</span>';
            $this->addInfo($params['content'],
                'Fake players',
                $summary['fakeCount'] . '/' . $summary['active'] . $percent);
        }
        $this->addInfo($params['content'],
            'Activating',
            $summary['activating']);
        {
            $percent = round($summary['verified'] / ($summary['real_players']) * 100, 1);
            $color = $percent <= 30 ? 'red' : ($percent <= 50 ? 'orange' : 'green');
            $percent = ' <span class="adminStat ' . $color . '">(' . $percent . '%)</span>';
            $this->addInfo($params['content'],
                'Verified players',
                $summary['verified'] . '/' . ($summary['active'] - $summary['fakeCount']) . $percent);
        }
        {
            $percent = round($summary['real_online'] / ($summary['real_players']) * 100, 1);
            $color = $percent <= 20 ? 'red' : ($percent <= 50 ? 'orange' : 'green');
            $percent = ' <span class="adminStat ' . $color . '">(' . $percent . '%)</span>';
            $this->addInfo($params['content'],
                'Players online',
                $summary['real_online'] . '/' . ($summary['real_players']) . $percent);
        }

        {
            $trainingQueueThreshold = $this->trainingQueueThreshold();
            $queueCount = $db->fetchScalar("SELECT 
        (
            (SELECT COUNT(id) FROM movement)+
            (SELECT COUNT(id) FROM building_upgrade)+
            (SELECT COUNT(id) FROM demolition)+
            (SELECT COUNT(uid) FROM deleting)+
            (SELECT COUNT(id) FROM auction WHERE finish=0 AND cancel=0)+
            (SELECT COUNT(id) FROM traderoutes WHERE enabled=1)+
            (SELECT COUNT(id) FROM buyGoldMessages)+
            (SELECT COUNT(id) FROM banQueue)+
            (SELECT COUNT(id) FROM alliance_notification)+
            (SELECT COUNT(id) FROM odelete)+
            (SELECT COUNT(id) FROM research)+
            (SELECT COUNT(id) FROM send)+
            (SELECT COUNT(id) FROM training WHERE commence >= " . $trainingQueueThreshold . ")+
            (SELECT COUNT(id) FROM notificationQueue)+
            (SELECT COUNT(id) FROM player_references WHERE rewardGiven=0)
        )");
            $color = $queueCount <= 800 ? 'green' : ($queueCount <= 2000 ? 'orange' : 'red');
            $queueCount = ' <span class="adminStat ' . $color . '">' . $queueCount . '</span>';
            $this->addInfo($params['content'], 'Queued Tasks', $queueCount);
        }

        $this->addInfo($params['content'],
            'Reports/Messages',
            $db->fetchScalar("SELECT CONCAT((SELECT COUNT(id) FROM ndata), \"/\", (SELECT COUNT(id) FROM mdata))"));
        {
            $villages = $db->fetchScalar("SELECT CONCAT((SELECT COUNT(kid) FROM available_villages WHERE occupied=1), \"/\", (SELECT COUNT(kid) FROM available_villages))");
            $p = explode("/", $villages);
            $percent = round($p[0] / $p[1] * 100, 1);
            $color = $percent <= 10 ? 'red' : ($percent <= 25 ? 'orange' : 'green');
            $percent = ' <span class="adminStat ' . $color . '">(' . $percent . '%)</span>';
            $this->addInfo($params['content'], 'Occupied/Free Villages', $villages . $percent);
        }
        {
            $oases = $db->fetchScalar("SELECT CONCAT((SELECT COUNT(kid) FROM odata WHERE owner>0), \"/\", (SELECT COUNT(kid) FROM odata))");
            $p = explode("/", $oases);
            $percent = round($p[0] / $p[1] * 100, 1);
            $color = $percent <= 10 ? 'red' : ($percent <= 25 ? 'orange' : 'green');
            $percent = ' <span class="adminStat ' . $color . '">(' . $percent . '%)</span>';
            $this->addInfo($params['content'], 'Occupied/Free Oases', $oases . $percent);
        }
        $this->addInfo($params['content'],
            'Gift/Bought gold',
            $db->fetchScalar('SELECT CONCAT((SELECT COALESCE(SUM(gift_gold), 0) FROM users WHERE access!=3 AND id>2), "/", (SELECT COALESCE(SUM(bought_gold), 0) FROM users WHERE access!=3 AND id>2))'));

        $status = \Core\Jobs\EngineHeartbeat::status()['text'];
        $this->addInfo($params['content'], 'Engine status', $status);
        $dispatcher->appendContent(Template::getInstance()->load($params, 'tpl/ServerInfo.tpl')->getAsString());
        $dispatcher->appendContent('</div>');
        // The troop totals are a heavy query that only the Advanced view shows, so Simple skips it.
        if ((Dispatcher::getInstance()->data['view'] ?? 'simple') === 'advanced') {
            $this->getTroopsSummary();
        }
    }

    /** One-second queued-training window in the timestamp precision of this world. */
    private function trainingQueueThreshold(): int
    {
        $game = Config::getInstance()->game;
        if (!empty($game->useNanoseconds)) {
            return nanoseconds() - 1_000_000_000;
        }
        if (!empty($game->useMilSeconds)) {
            return miliseconds() - 1_000;
        }

        return time() - 1;
    }

    private function processActions(): void
    {
        if (isServerFinished() || !\Game\Admin\AdminAccounts::isAdmin((int) \Core\Session::getInstance()->getAdminUid())) return;
        if (!\Core\Helper\WebService::isPost() || !\Core\Session::validateChecker()) return;
        $config = Config::getInstance();
        $db = DB::getInstance();
        $globalDB = GlobalDB::getInstance();
        if (isset($_POST['registerClosed'])) {
            AdminLog::getInstance()->addLog("Changed Registration status Settings.");
            $state = $_POST['registerClosed'] == 'on' ? 0 : 1;
            $gameServer['registerClosed'] = $state;
            $globalDB->query("UPDATE gameServers SET registerClosed=$state WHERE id=" . getWorldUniqueId());
        } else if (isset($_POST['activation'])) {
            AdminLog::getInstance()->addLog("Changed activation status Settings.");
            $state = $_POST['activation'] == 'on' ? 1 : 0;
            $gameServer['activation'] = $state;
            $globalDB->query("UPDATE gameServers SET activation=$state WHERE id=" . getWorldUniqueId());
        } else if (isset($_POST['savePlayableTribes'])) {
            $tribes = \Game\Tribes::parse($_POST['playableTribes'] ?? []);
            if ($tribes) {
                AdminLog::getInstance()->addLog("Changed playable tribes to " . \Game\Tribes::serialize($tribes) . ".");
                $value = \Game\Tribes::serialize($tribes);
                $db->query("UPDATE config SET playableTribes='" . $db->real_escape_string($value) . "'");
                $config->dynamic->playableTribes = $value;
                Caching::getInstance()->delete('WorldConfig');
            }
        } else if (isset($_POST['saveDiscordUrl'])) {
            $url = trim((string)($_POST['discordUrl'] ?? ''));
            if (!\Game\DiscordLink::isValid($url)) {
                $this->discordError = 'Enter an https:// URL (up to ' . \Game\DiscordLink::MAX_LENGTH . ' characters) or leave it blank to hide every Discord link.';
            } else {
                AdminLog::getInstance()->addLog($url === '' ? "Removed the Discord link." : "Changed the Discord link.");
                $db->run('UPDATE config SET discordUrl=?', [$url]);
                $config->dynamic->discordUrl = $url;
                Caching::getInstance()->delete('WorldConfig');
            }
        } else if (isset($_POST['saveSupportEmail'])) {
            $email = \Game\SupportContact::normalize((string)($_POST['supportEmail'] ?? ''));
            if (!\Game\SupportContact::isValid($email)) {
                $this->supportEmailError = 'Enter a valid support recipient email address.';
            } else {
                AdminLog::getInstance()->addLog('Changed the support recipient email address.');
                $db->run('UPDATE config SET supportEmail=?', [$email]);
                $config->dynamic->supportEmail = $email;
                Caching::getInstance()->delete('WorldConfig');
            }
        } else if (isset($_POST['saveSmtp'])) {
            $smtp = \Game\SmtpConfig::fromSubmission($_POST, \Game\SmtpConfig::current(), true);
            if ($smtp['config'] === null) {
                $this->smtpError = implode(' ', $smtp['errors']);
            } elseif (!\Game\SmtpConfig::save($smtp['config'])) {
                $this->smtpError = 'Unable to save the protected SMTP configuration.';
            } elseif ($db->run('UPDATE config SET needsRestart=1') === false) {
                $this->smtpError = 'SMTP settings were saved, but the delivery worker restart could not be requested.';
            } else {
                AdminLog::getInstance()->addLog('Changed SMTP delivery settings.');
                $config->dynamic->needsRestart = 1;
                Caching::getInstance()->delete('WorldConfig');
            }
        } else if (isset($_POST['saveSocial'])) {
            $social = \Game\SocialAuth\ProviderConfig::fromEnvironment();
            $parsed = $social->fromSubmission($_POST);
            if ($parsed['config'] === null) {
                $this->socialError = implode(' ', $parsed['errors']);
            } elseif (!$social->save($parsed['config'])) {
                $this->socialError = 'Unable to save the protected social sign-in configuration.';
            } else {
                AdminLog::getInstance()->addLog('Changed social sign-in settings.');
            }
        } else if (isset($_POST['savePremiumDurations'])) {
            $plusHours = is_string($_POST['plusDurationHours'] ?? null)
                ? PremiumDuration::hoursToSeconds($_POST['plusDurationHours'])
                : null;
            $boostHours = is_string($_POST['productionBoostDurationHours'] ?? null)
                ? PremiumDuration::hoursToSeconds($_POST['productionBoostDurationHours'])
                : null;
            if ($plusHours === null || $boostHours === null) {
                $this->premiumDurationError = 'Enter whole hours from 0 to 8760 for both durations. Use 0 for the speed-based default.';
            } else {
                // A bonus lasts at least one day: an override below 24 hours is stored as 24 hours.
                $plusHours = $plusHours > 0 ? PremiumDuration::atLeastOneDay($plusHours) : 0;
                $boostHours = $boostHours > 0 ? PremiumDuration::atLeastOneDay($boostHours) : 0;
                $updated = $db->run(
                    'UPDATE config SET plusAccountDurationSeconds=?, productionBoostDurationSeconds=?, needsRestart=1',
                    [$plusHours, $boostHours]
                );
                if ($updated === false) {
                    $this->premiumDurationError = 'Unable to save premium feature durations.';
                } else {
                    AdminLog::getInstance()->addLog(
                        'Changed premium feature durations: Plus ' . $plusHours . ' seconds; production boost ' . $boostHours . ' seconds.'
                    );
                    $config->dynamic->plusAccountDurationSeconds = $plusHours;
                    $config->dynamic->productionBoostDurationSeconds = $boostHours;
                    $config->dynamic->needsRestart = 1;
                    $config->gold->plusAccountDurationSeconds = PremiumDuration::configuredPlusDuration(
                        (int)$config->game->speed,
                        $plusHours
                    );
                    $config->gold->productionBoostDurationSeconds = PremiumDuration::configuredDuration(
                        (int)$config->game->speed,
                        $boostHours,
                        (int)$config->gold->productionBoostDurationLocalDefaultSeconds
                    );
                    Caching::getInstance()->delete('WorldConfig');
                }
            }
        } else if (isset($_POST['finishConstruction'])) {
            $requested = $_POST['finishConstruction'];
            if (!is_string($requested) || !in_array($requested, ['on', 'off'], true)) {
                $this->finishConstructionError = 'Choose whether to enable speed-world construction finish.';
            } else {
                $enabled = $requested === 'on';
                if ($enabled && !\Game\FinishConstruction::isSpeedWorld()) {
                    $this->finishConstructionError = 'Palace and Residence gold completion is available only on speed worlds above 1x.';
                } elseif ($db->run('UPDATE config SET finishConstruction=?', [$enabled ? 1 : 0]) === false) {
                    $this->finishConstructionError = 'Unable to save speed-world construction finish.';
                } else {
                    AdminLog::getInstance()->addLog(
                        ($enabled ? 'Enabled' : 'Disabled') . ' Palace and Residence gold completion.'
                    );
                    $config->dynamic->finishConstruction = $enabled ? 1 : 0;
                    Caching::getInstance()->delete('WorldConfig');
                }
            }
        } else if (isset($_POST['maintenance'])) {
            AdminLog::getInstance()->addLog("Changed maintenance Settings.");
            $state = $_POST['maintenance'] == 'on' ? 1 : 0;
            $config->dynamic->maintenance = $state;
            $db->query("UPDATE config SET maintenance=$state");
        } else if (isset($_POST['needPreregistrationCode'])) {
            AdminLog::getInstance()->addLog("Changed PreregistrationCode Settings.");
            $state = $_POST['needPreregistrationCode'] == 'on' ? 1 : 0;
            $globalDB->query("UPDATE gameServers SET preregistration_key_only=$state WHERE id=" . getWorldUniqueId());
        } else if (isset($_POST['fakeAccountProcess'])) {
            AdminLog::getInstance()->addLog("Changed fakeAccountProcess Settings.");
            $state = $_POST['fakeAccountProcess'] == 'on' ? 1 : 0;
            $config->dynamic->fakeAccountProcess = $state;
            $db->query("UPDATE config SET fakeAccountProcess=$state");
        }
    }

    /** One setting toggle: a POST form with the session checker and a Gpack button. */
    private function toggleForm(string $name, bool $isOn, string $onLabel, string $offLabel): string
    {
        return '<form method="post" action="admin.php?action=configurationDetails" class="adminInline">'
            . \Core\Session::getCheckerInput()
            . '<select name="' . $name . '" aria-label="' . $name . '">'
            . '<option value="on"' . ($isOn ? ' selected' : '') . '>' . $onLabel . '</option>'
            . '<option value="off"' . ($isOn ? '' : ' selected') . '>' . $offLabel . '</option></select> '
            . \Game\AdminUi::button('Apply', 'grey')
            . '</form>';
    }

    private function discordUrlForm(): string
    {
        $current = $_POST['discordUrl'] ?? (string)(Config::getInstance()->dynamic->discordUrl ?? '');
        $html = '<form method="post" action="admin.php?action=configurationDetails" class="adminInline">'
            . \Core\Session::getCheckerInput()
            . '<input type="url" name="discordUrl" maxlength="' . \Game\DiscordLink::MAX_LENGTH . '" size="40" aria-label="Discord link"'
            . ' placeholder="https://example.com/invite" autocomplete="off" value="' . \Game\AdminUi::esc((string)$current) . '"> '
            . \Game\AdminUi::button('Save', 'green', ['name' => 'saveDiscordUrl', 'value' => '1'])
            . '</form><small>Blank hides every Discord link (footer, menus, help).</small>';
        if ($this->discordError !== null) {
            $html .= \Game\AdminUi::notice($this->discordError, 'error');
        }
        return $html;
    }

    private function supportEmailForm(): string
    {
        $current = $_POST['supportEmail'] ?? (string)(Config::getInstance()->dynamic->supportEmail ?? '');
        $html = '<form method="post" action="admin.php?action=configurationDetails" class="adminInline">'
            . \Core\Session::getCheckerInput()
            . '<input type="email" name="supportEmail" maxlength="' . \Game\SupportContact::MAX_LENGTH . '" size="40" aria-label="Support recipient"'
            . ' autocomplete="email" value="' . \Game\AdminUi::esc((string)$current) . '" required> '
            . \Game\AdminUi::button('Save', 'green', ['name' => 'saveSupportEmail', 'value' => '1'])
            . '</form><small>Required recipient for local support tickets. SMTP credentials are held in protected deployment state.</small>';
        if ($this->supportEmailError !== null) {
            $html .= \Game\AdminUi::notice($this->supportEmailError, 'error');
        }
        return $html;
    }

    private function finishConstructionForm(): string
    {
        if (!\Game\FinishConstruction::isSpeedWorld()) {
            $html = '<small>Available only on speed worlds above 1x. The current world speed is '
                . (int)getGameSpeed() . 'x; World Wonder construction is never eligible.</small>';
        } else {
            $html = $this->toggleForm(
                'finishConstruction',
                \Game\FinishConstruction::isEnabled(),
                'Enabled',
                'Disabled'
            ) . '<small>Allows Finish now to complete queued Palace and Residence upgrades. '
                . 'World Wonder construction is never eligible.</small>';
        }
        if ($this->finishConstructionError !== null) {
            $html .= \Game\AdminUi::notice($this->finishConstructionError, 'error');
        }

        return $html;
    }

    private function smtpForm(): string
    {
        $values = \Game\SmtpConfig::formValues(\Game\SmtpConfig::current());
        if (isset($_POST['saveSmtp'])) {
            foreach (['host', 'port', 'encryption', 'from', 'username'] as $field) {
                $key = 'smtp_' . $field;
                if (is_string($_POST[$key] ?? null)) {
                    $values[$field] = trim($_POST[$key]);
                }
            }
            if (is_string($_POST['smtp_auth'] ?? null)) {
                $values['auth'] = $_POST['smtp_auth'];
            }
        }
        $passwordHint = $values['passwordConfigured']
            ? 'Leave blank to keep the configured password.'
            : 'Required when SMTP authentication is enabled.';
        $html = '<form method="post" action="admin.php?action=configurationDetails" class="adminInline smtpSettings">'
            . \Core\Session::getCheckerInput()
            . '<label>Host <input type="text" name="smtp_host" maxlength="' . \Game\SmtpConfig::MAX_HOST_LENGTH
            . '" value="' . \Game\AdminUi::esc($values['host']) . '" required></label> '
            . '<label>Port <input type="number" name="smtp_port" min="1" max="65535" value="'
            . \Game\AdminUi::esc($values['port']) . '" required></label> '
            . '<label>Encryption <select name="smtp_encryption" required>'
            . $this->smtpOption('starttls', 'STARTTLS', $values['encryption'])
            . $this->smtpOption('smtps', 'SMTPS', $values['encryption'])
            . '</select></label> '
            . '<label>Sender <input type="email" name="smtp_from" maxlength="' . \Game\SupportContact::MAX_LENGTH
            . '" value="' . \Game\AdminUi::esc($values['from']) . '" required></label> '
            . '<label>Authentication <select name="smtp_auth" required>'
            . $this->smtpOption('1', 'Required', $values['auth'])
            . $this->smtpOption('0', 'Not required', $values['auth'])
            . '</select></label> '
            . '<label>Username <input type="text" name="smtp_username" maxlength="' . \Game\SmtpConfig::MAX_USERNAME_LENGTH
            . '" value="' . \Game\AdminUi::esc($values['username']) . '" autocomplete="username"></label> '
            . '<label>Password <input type="password" name="smtp_password" maxlength="' . \Game\SmtpConfig::MAX_PASSWORD_LENGTH
            . '" value="" autocomplete="new-password"></label> '
            . \Game\AdminUi::button('Save', 'green', ['name' => 'saveSmtp', 'value' => '1'])
            . '</form><small>TLS is required. The configured operator sender remains From and envelope sender; player email is only Reply-To. '
            . \Game\AdminUi::esc($passwordHint) . '</small>';
        if ($this->smtpError !== null) {
            $html .= \Game\AdminUi::notice($this->smtpError, 'error');
        }

        return $html;
    }

    private function socialForm(): string
    {
        $social = \Game\SocialAuth\ProviderConfig::fromEnvironment();
        $values = $social->formValues();
        $base = \Game\SocialAuth\OAuthFlowFactory::baseUrl();
        $field = static fn (string $label, string $name, string $value, bool $secret): string => '<label>' . $label
            . ' <input type="' . ($secret ? 'password' : 'text') . '" name="' . $name . '" value="' . \Game\AdminUi::esc($value)
            . '" autocomplete="off"></label> ';
        $html = '<form method="post" action="admin.php?action=configurationDetails" class="adminInline socialSettings">'
            . \Core\Session::getCheckerInput();
        foreach (['google' => 'Google', 'facebook' => 'Facebook', 'apple' => 'Apple'] as $id => $label) {
            $provider = \Game\SocialAuth\Provider::from($id);
            $v = $values[$id]['values'];
            $html .= '<fieldset><legend>' . $label . ($values[$id]['secretsConfigured'] ? ' (configured)' : '') . '</legend>'
                . $field('Client ID', 'social_' . $id . '_client_id', $v['client_id'], false);
            if ($id === 'apple') {
                $html .= $field('Team ID', 'social_apple_team_id', $v['team_id'], false)
                    . $field('Key ID', 'social_apple_key_id', $v['key_id'], false)
                    . '<label>Private key (.p8) <textarea name="social_apple_private_key" rows="4" autocomplete="off"></textarea></label> ';
            } else {
                $html .= $field('Client secret', 'social_' . $id . '_client_secret', '', true);
            }
            $html .= '<label><input type="checkbox" name="social_' . $id . '_remove" value="1"> Remove</label>'
                . '<br><small>Redirect URI: <code>' . \Game\AdminUi::esc(\Game\SocialAuth\ProviderConfig::redirectUri($provider, $base))
                . '</code></small></fieldset>';
        }
        $html .= \Game\AdminUi::button('Save', 'green', ['name' => 'saveSocial', 'value' => '1'])
            . '</form><small>A provider appears on the registration form only when fully configured (Apple also needs an HTTPS base URL). '
            . 'Blank secrets keep the stored value. Credentials are kept in protected deployment state, never in SQL.</small>';
        if ($this->socialError !== null) {
            $html .= \Game\AdminUi::notice($this->socialError, 'error');
        }

        return $html;
    }

    private function smtpOption(string $value, string $label, string $selected): string
    {
        return '<option value="' . \Game\AdminUi::esc($value) . '"'
            . ($value === $selected ? ' selected' : '') . '>' . \Game\AdminUi::esc($label) . '</option>';
    }

    private function premiumDurationForm(): string
    {
        $config = Config::getInstance();
        $plusOverride = (int)($config->dynamic->plusAccountDurationSeconds ?? 0);
        $boostOverride = (int)($config->dynamic->productionBoostDurationSeconds ?? 0);
        $plusHours = is_string($_POST['plusDurationHours'] ?? null)
            ? $_POST['plusDurationHours']
            : (string)(int)ceil($plusOverride / 3600);
        $boostHours = is_string($_POST['productionBoostDurationHours'] ?? null)
            ? $_POST['productionBoostDurationHours']
            : (string)(int)ceil($boostOverride / 3600);
        $html = '<form method="post" action="admin.php?action=configurationDetails" class="adminInline">'
            . \Core\Session::getCheckerInput()
            . '<label>OpenTravian Plus (hours) <input type="number" name="plusDurationHours" min="0" max="8760" step="1" value="'
            . \Game\AdminUi::esc($plusHours) . '"></label> '
            . '<label>Resource bonus (hours) <input type="number" name="productionBoostDurationHours" min="0" max="8760" step="1" value="'
            . \Game\AdminUi::esc($boostHours) . '"></label> '
            . \Game\AdminUi::button('Save', 'green', ['name' => 'savePremiumDurations', 'value' => '1'])
            . '</form><small>0 uses the speed-based default: Plus lasts 7 days at x1, 3 days from x2 through x10 and 3 days x 10 / speed above x10; the resource bonus keeps 3 days at x2, x3, x5 and x10 and its local duration at other speeds. A bonus never lasts less than one day: an override of 1 to 23 hours is saved as 24 hours, and so are quest rewards. Current effective durations: Plus '
            . (int)ceil($config->gold->plusAccountDurationSeconds / 3600) . ' hours; resource bonus '
            . (int)ceil($config->gold->productionBoostDurationSeconds / 3600) . ' hours.</small>';
        if ($this->premiumDurationError !== null) {
            $html .= \Game\AdminUi::notice($this->premiumDurationError, 'error');
        }
        return $html;
    }

    private function playableTribesForm(): string
    {
        $names = [1 => 'Romans', 2 => 'Teutons', 3 => 'Gauls', 6 => 'Egyptians', 7 => 'Huns', 8 => 'Spartans', 9 => 'Vikings'];
        $playable = \Game\Tribes::playable();
        $html = '<form method="post" action="admin.php?action=configurationDetails">' . \Core\Session::getCheckerInput();
        foreach ($names as $id => $name) {
            $html .= '<label class="adminInline"><input type="checkbox" name="playableTribes[]" value="' . $id . '"'
                . (in_array($id, $playable, true) ? ' checked' : '') . '> ' . $name . '</label>';
        }
        return $html . \Game\AdminUi::button('Save', 'green', ['name' => 'savePlayableTribes', 'value' => '1']) . '</form>';
    }

    /**
     * @param mixed $content
     * @param string $name
     * @param mixed $value
     */
    private function addInfo(&$content, $name, $value): void
    {
        $content .= '<tr' . (in_array($name, self::SIMPLE_ROWS, true) ? '' : ' class="advancedOnly"') . '>
            <td>' . $name . '</td>
            <td>
                ' . $value . '
            </td>
        </tr>';
    }

    private function getTroopsSummary(): void
    {
        $maxRace = 9;
        //Note: because this uses lot's of memory and it will cause damage to servers removed!
        $cache = Caching::getInstance();
        if (($_cache = $cache->get("TroopsSummary"))) {
            $Troops = $_cache;
        } else {
            $db = DB::getInstance();
            $result = $db->query($this->getTroopsStatisticsQuery())->fetch_assoc();
            $Troops = [];
            for ($race = 1; $race <= $maxRace; ++$race) {
                for ($nr = 1; $nr <= 11; ++$nr) {
                    $Troops[$race][$nr] = $result["u{$race}_{$nr}"];
                }
            }
            $cache->set("TroopsSummary", $Troops, 6 * 3600);
        }
        $dispatcher = Dispatcher::getInstance();
        $dispatcher->appendContent('<br/><br/><div class="center"><div>');
        $dispatcher->appendContent('<table class="adminTable">');
        $dispatcher->appendContent('<thead><tr><th colspan="11">Total Troops in Game</th></tr></thead>');
        for ($race = 1; $race <= $maxRace; ++$race) {
            $dispatcher->appendContent(Template::getInstance()->load(['race' => $race, 'units' => $Troops[$race]],
                'tpl/troop_tbody.tpl')->getAsString());
        }
        $dispatcher->appendContent('</table>');
        $dispatcher->appendContent('</div></div>');
    }

    function getTroopsStatisticsQuery(): string
    {
        $query = 'SELECT ';
        $maxRace = 9;
        $maxNr = 11;
        for ($race = 1; $race <= $maxRace; ++$race) {
            for ($nr = 1; $nr <= $maxNr; ++$nr) {
                $query .= $this->getQueryForNr($nr, $race);
                if (!($race == $maxRace && $nr == $maxNr)) {
                    $query .= ',';
                }
            }
        }
        return $query;
    }

    /**
     * @param int $nr
     * @param int $race
     */
    function getQueryForNr($nr, $race): string
    {
        $units = "(SELECT COALESCE(SUM(u{$nr}), 0) FROM units WHERE race={$race})";
        $trapped = "(SELECT COALESCE(SUM(u{$nr}), 0) FROM trapped WHERE race={$race})";
        $enforcement = "(SELECT COALESCE(SUM(u{$nr}), 0) FROM enforcement WHERE race={$race})";
        $movement = "(SELECT COALESCE(SUM(u{$nr}), 0) FROM movement WHERE race={$race})";
        return "($units+$trapped+$enforcement+$movement) as u{$race}_{$nr}";
    }
}
