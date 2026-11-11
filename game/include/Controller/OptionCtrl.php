<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Database\DBI;
use Core\Security\Password;
use Core\Helper\TimezoneHelper;
use Core\Helper\WebService;
use Core\Session;
use Game\Account\DataExport;
use Game\Admin\AuditLog;
use Game\Formulas;
use Game\Mail\EmailKind;
use Game\Mail\EmailPreferences;
use Game\Mail\MailService;
use Model\InfoBoxModel;
use Model\OptionModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

class OptionCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        if ($this->session->isSitter() && (!isset($_GET['s']) || (int)$_GET['s'] != 4) && $this->session->isInVacationMode()) {
            $this->redirect("/dorf1.php");
        }
        if (isset($_GET['cmd']) && trim($_GET['cmd']) == "extendBeginnersProtection" && !$this->session->isSitter()) {
            $baseProtection = Formulas::getProtectionBasicTime($this->session->get("signupTime"));
            $usedProtection = ($this->session->protectionTill() - $this->session->get("signupTime"));
            $protectionExtendingUsed = $usedProtection > $baseProtection;
            $protectionLeft = $this->session->protectionTill() - time();
            $isProtectionExtendTime = $protectionLeft <= Formulas::getProtectionShowExtendTime($this->session->get("signupTime"));
            if (!$protectionExtendingUsed && $isProtectionExtendTime) {
                $db = DB::getInstance();
                $db->query("UPDATE users SET protection=protection+" . Formulas::getProtectionExtendTime($this->session->get("signupTime")) . " WHERE id=" . $this->session->getPlayerId());
                $this->session->setProtection($this->session->protectionTill() + Formulas::getProtectionExtendTime($this->session->get("signupTime")));
                $db->query("UPDATE infobox SET showTo=" . ($this->session->protectionTill() + Formulas::getProtectionExtendTime($this->session->get("signupTime"))) . " WHERE uid={$this->session->getPlayerId()} AND type=6");
                InfoBoxModel::invalidateUserInfoBoxCache($this->session->getPlayerId());
            }
            $this->redirect("/dorf1.php");
        }
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = '';
        $this->view->vars['bodyCssClass'] = 'perspectiveResources options';
        $this->view->vars['contentCssClass'] = 'options';
        // Preferences is an overlay in the reference shell, so it needs the
        // shared title bar and perspective-aware close affordance.
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $this->view->vars['titleInHeader'] = T("Options", "Preferences");
        $selectedTab = isset($_REQUEST['s']) && $_REQUEST['s'] >= 1 && $_REQUEST['s'] <= 4 ? (int)$_REQUEST['s'] : 1;
        $this->view->vars['contentCssClass'] .= ' ' . match ($selectedTab) {
            2 => 'optionsAccount',
            3 => 'optionsSitter',
            4 => 'optionsVacation',
            default => 'optionsGame',
        };
        if (!$this->session->isSitter()) {
            $view = new PHPBatchView("options/menu");
            $view->vars['selectedTab'] = $selectedTab;
            $this->view->vars['content'] .= $view->output();
        }
        if ($selectedTab == 1) {
            $this->Game();
        } elseif ($selectedTab == 2) {
            $this->Account();
        } elseif ($selectedTab == 3) {
            $this->Sitter();
        } elseif ($selectedTab == 4 && Config::getProperty("game", "vacationDays") > 0) {
            $this->Vacation();
        }
    }

    private function Game(): void
    {
        $view = new PHPBatchView("options/Game");
        if (WebService::isPost()) {
            // The reference sends the selected full locale in the normal
            // preferences form. Keep that wire shape, but require the local
            // one-time checker before changing any account preference.
            if (!Session::validateChecker()) {
                $this->view->vars['content'] .= $view->output();
                return;
            }

            $currentLanguage = $this->session->getLanguage();
            $languageKey = $this->languageKeyForLocale($_POST['language'] ?? null);
            $rptFilters = Session::getInstance()->getReportFilters();
            $reportFilters = [
                isset($_POST['c4']) && (int)$_POST['c4'] == 1 ? 1 : 0,
                isset($_POST['c5']) && (int)$_POST['c5'] == 1 ? 1 : 0,
                isset($_POST['c6']) && (int)$_POST['c6'] == 1 ? 1 : 0,
                $rptFilters[3],
                $rptFilters[4],
                $rptFilters[5],
                $rptFilters[6],
            ];
            $autoComplete = [
                isset($_POST['c1']) && (int)$_POST['c1'] == 1 ? 1 : 0,
                isset($_POST['c2']) && (int)$_POST['c2'] == 1 ? 1 : 0,
                isset($_POST['c3']) && (int)$_POST['c3'] == 1 ? 1 : 0,
            ];
            $allianceSettings = [
                isset($_POST['c7']) && (int)$_POST['c7'] == 1 ? 1 : 0,
                isset($_POST['c8']) && (int)$_POST['c8'] == 1 ? 1 : 0,
                isset($_POST['c9']) && (int)$_POST['c9'] == 1 ? 1 : 0,
                isset($_POST['c10']) && (int)$_POST['c10'] == 1 ? 1 : 0,
                isset($_POST['c11']) && (int)$_POST['c11'] == 1 ? 1 : 0,
            ];
            $notificationEnabled = isset($_POST['receiveAllianceInvitations']) && (int)$_POST['receiveAllianceInvitations'] == 1 ? 1 : 0;
            $display = [
                isset($_POST['flag_hiden_report_image']) && (int)$_POST['flag_hiden_report_image'] == 1 ? 1 : 0,
                max(min((int)$_POST['epp'], 99), 10),
                max(min((int)$_POST['troopMovementsPerPage'], 99), 10),
                Session::getInstance()->getDisplay()[3],
                Session::getInstance()->getDisplay()[4],
                // Preserve the retired display-vector position so existing
                // player preferences keep their stable on-disk layout.
                (int)(Session::getInstance()->getDisplay()[5] ?? 0),
                isset($_POST['option_night_mode']) && (int)$_POST['option_night_mode'] == 1 ? 1 : 0,
                isset($_POST['hideContextualHelp']) && (int)$_POST['hideContextualHelp'] == 1 ? 1 : 0,
                isset($_POST['showDeleteReportsConfirmation']) && (int)$_POST['showDeleteReportsConfirmation'] == 1 ? 1 : 0,
                max(min((int)($_POST['marketplaceOffersPerPage'] ?? 10), 99), 10),
            ];
            $timezone = [
                is_string($_POST['timezone'] ?? null)
                && array_key_exists($_POST['timezone'], Config::getInstance()->timezones)
                    ? $_POST['timezone']
                    : 'default',
                in_array(
                    $_POST['tformat'],
                    [
                        0,
                        1,
                        2,
                        3,
                    ]
                ) ? $_POST['tformat'] : 0,
                isset($_POST['flag_local_time_instead_server']) && (int)$_POST['flag_local_time_instead_server'] == 1 ? 1 : 0,
            ];
            Session::getInstance()->setReportFilters($reportFilters);
            Session::getInstance()->setTimezone($timezone);
            Session::getInstance()->setDisplay($display);
            Session::getInstance()->setAllianceSettings($allianceSettings);
            Session::getInstance()->setAllianceNotification($notificationEnabled);
            Session::getInstance()->setAutoComplete($autoComplete);
            $m = new OptionModel();
            $m->updateGame(
                Session::getInstance()->getPlayerId(),
                implode(',', $reportFilters),
                implode('|', $allianceSettings),
                (int)$notificationEnabled,
                implode(',', $autoComplete),
                implode(',', $display),
                implode(',', $timezone)
            );
            if ($languageKey !== null && $languageKey !== $currentLanguage) {
                // The stored preference and the locale cookie change together.
                $this->session->setLanguage($languageKey);
                // Locale strings may already have been loaded for this request.
                // Render the selected language from a fresh request instead.
                $this->redirect('/options/game');
            }
            $view->vars['error'] = 0;
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function languageKeyForLocale(mixed $locale): ?string
    {
        if (!is_string($locale)) {
            return null;
        }

        foreach ((array) Config::getInstance()->settings->availableLanguages as $key => $language) {
            if (!is_string($key)
                || !is_object($language)
                || !isset($language->locale)
                || !is_string($language->locale)) {
                continue;
            }
            if ($language->locale === $locale) {
                return $key;
            }
        }

        return null;
    }

    private function Account(): void
    {
        // The reference gives sitters no Account tab; the owner's account is not theirs to delete or export.
        if ($this->session->isSitter()) {
            $this->redirect('/options?s=1');
        }
        $view = new PHPBatchView("options/Account");
        $m = new OptionModel();
        $config = Config::getInstance();
        $view->vars['colorBlind'] = Session::getInstance()->getDisplay()[3];
        $view->vars['canRespawn'] = Session::getInstance()->hasProtection() && !Session::getInstance()->isSitter();
        $view->vars['newsletter'] = EmailPreferences::enabled((int)Session::getInstance()->getPlayerId(), EmailKind::Newsletter);
        $view->vars['deliveryConfigured'] = MailService::deliveryConfigured();
        $view->vars['notifications'] = [];
        foreach (EmailKind::notifications() as $kind) {
            $view->vars['notifications'][$kind->value] = ['label' => $kind->label(), 'enabled' => EmailPreferences::enabled((int)Session::getInstance()->getPlayerId(), $kind)];
        }
        $view->vars['social'] = Session::getInstance()->isSitter() ? null : \Game\SocialAuth\OptionsSection::build(
            (int)Session::getInstance()->getPlayerId(),
            \Game\SocialAuth\ProviderConfig::fromEnvironment(),
            \Game\SocialAuth\OAuthFlowFactory::baseUrl(),
            \Game\SocialAuth\SocialFlash::fromGlobals()
        );
        if (WebService::isPost()) {
            if (!Session::matchesChecker($_POST['a'] ?? null)) {
                $this->view->vars['content'] .= $view->output();
                return;
            }

            if (isset($_POST['exportData'])) {
                $this->exportData($view);
            }
            if (isset($_POST['emailNotifications'])) {
                foreach (EmailKind::notifications() as $kind) {
                    $on = isset($_POST['emailNotification'][$kind->value]);
                    EmailPreferences::set((int)Session::getInstance()->getPlayerId(), $kind, $on);
                    $view->vars['notifications'][$kind->value]['enabled'] = $on;
                }
            }
            if (isset($_POST['newsletter'])) {
                EmailPreferences::set((int)Session::getInstance()->getPlayerId(), EmailKind::Newsletter, $_POST['newsletter'] === '1');
                $view->vars['newsletter'] = $_POST['newsletter'] === '1';
            }
            if (isset($_POST['colorblind']) && $_POST['colorblind'] == 1) {
                $display = Session::getInstance()->getDisplay();
                $display[3] = $view->vars['colorBlind'] = 1;
                $db = DB::getInstance();
                $db->query("UPDATE users SET display='" . implode(
                    ",",
                    $display
                ) . "' WHERE id=" . Session::getInstance()->getPlayerId());
                Session::getInstance()->setDisplay($display);
                $this->view->vars['colorBlind'] = true;
            } else {
                $display = Session::getInstance()->getDisplay();
                $display[3] = $view->vars['colorBlind'] = 0;
                $db = DB::getInstance();
                $db->query("UPDATE users SET display='" . implode(
                    ",",
                    $display
                ) . "' WHERE id=" . Session::getInstance()->getPlayerId());
                Session::getInstance()->setDisplay($display);
                $this->view->vars['colorBlind'] = false;
            }
            // Free avatar rename (no password confirm while free renames remain).
            if (isset($_POST['free_rename']) && isset($_POST['e']) && (string)$_POST['e'] === '2') {
                $total_pop = Session::getInstance()->get("total_pop");
                if ($total_pop < $config->gold->changeName->freeTillPopulation
                    && Session::getInstance()->getTotalNameChanges() < $config->gold->changeName->freeTimes) {
                    $newName = is_string($_POST['account_rename_new_name'] ?? null)
                        ? strip_tags($_POST['account_rename_new_name'])
                        : '';
                    if ($newName === '') {
                        $view->vars['error'] = T("Options", "Please enter a new account name and confirmation password");
                    } else {
                        $error = $m->doesNameMeetRequirements(Session::getInstance()->getName(), $newName);
                        if ($error === 0) {
                            $_SESSION[WebService::fixSessionPrefix('user')] = $newName;
                            $m->changeName(Session::getInstance()->getPlayerId(), $newName);
                            Session::getInstance()->setName($newName);
                            Session::getInstance()->setTotalNameChanges(Session::getInstance()->getTotalNameChanges() + 1);
                        } elseif ($error & OptionModel::NAME_BLACKLISTED) {
                            $view->vars['error'] = T("Options", "Name black listed");
                        } elseif ($error & OptionModel::NAME_EXISTS) {
                            $view->vars['error'] = T("Options", "Name exists");
                        } elseif ($error & OptionModel::NAME_SHORT) {
                            $view->vars['error'] = T("Options", "Name too short");
                        } elseif ($error & OptionModel::NAME_LONG) {
                            $view->vars['error'] = T("Options", "Name too long");
                        }
                    }
                }
            }
            // delete-avatar: type "delete" then submit (no password field).
            if (isset($_POST['del']) && (string)$_POST['del'] === '1') {
                $typedDelete = isset($_POST['confirmation_delete'])
                    ? strtolower(trim((string)$_POST['confirmation_delete']))
                    : '';
                if ($typedDelete !== 'delete') {
                    $view->vars['error'] = T("Options", "Please type: delete");
                } elseif (!$m->isDeletion(Session::getInstance()->getPlayerId())) {
                    if (Session::getInstance()->isInVacationMode()) {
                        $this->redirect('/options/vacation');
                    } elseif (!throttle_public_form('account-delete', 5, 3600)) {
                        $view->vars['error'] = T('Login', 'tooManyRequests');
                    } else {
                        $m->addDeleting(Session::getInstance()->getPlayerId());
                        AuditLog::record('account.delete-requested', 'uid=' . Session::getInstance()->getPlayerId(), '', (int)Session::getInstance()->getPlayerId(), (string)Session::getInstance()->getName());
                        InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());
                    }
                }
            }
        } elseif (isset($_GET['a']) && $_GET['a'] == 1) {
            if (Config::getInstance()->dynamic->serverFinished) {
                $this->innerRedirect("InGameWinnerPage");
            }
            if ($m->isDeletion(Session::getInstance()->getPlayerId())) {
                $m->cancelDeletion(Session::getInstance()->getPlayerId());
                AuditLog::record('account.delete-cancelled', 'uid=' . Session::getInstance()->getPlayerId(), '', (int)Session::getInstance()->getPlayerId(), (string)Session::getInstance()->getName());
            }
            InfoBoxModel::invalidateUserInfoBoxCache(Session::getInstance()->getPlayerId());
        }
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * POST exportData: sends the player's own data as a JSON download after the current password is confirmed. A
     * wrong password, a repeat within ten minutes and sitters (turned away before) get no file.
     */
    private function exportData(PHPBatchView $view): void
    {
        $session = Session::getInstance();
        $uid = (int)$session->getPlayerId();
        $stored = DBI::getInstance()->fetchScalar('SELECT password FROM users WHERE id=?', [$uid], '');
        $given = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!throttle_public_form('account-export', 3, 600)) {
            $view->vars['error'] = T('Login', 'tooManyRequests');
            return;
        }
        if (!is_string($stored) || $given === '' || !Password::verify($given, $stored)) {
            $view->vars['error'] = T('Options', 'password wrong');
            return;
        }
        $json = json_encode(DataExport::build($uid), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        AuditLog::record('account.export', 'uid=' . $uid, strlen($json) . ' bytes', $uid, (string)$session->getName());
        $file = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$session->getName()) . '-' . gmdate('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo $json;
        exit;
    }

    private function Sitter(): void
    {
        $view = new PHPBatchView("options/Sitters");
        $m = new OptionModel();
        $db = DB::getInstance();
        if (WebService::isPost() && Session::matchesChecker($_POST['a'] ?? null)) {
            $sitter1 = $this->mergeSitter(0, 1);
            $sitter2 = $this->mergeSitter(1, 2);
            if (!empty($sitter1['name'])) {
                $uid = $m->getUserByName($sitter1['name']);
                if ($uid && ($uid != $this->session->getPlayerId()) && ($uid != $this->session->getSittersId(2) || $this->session->getSittersId(2) == 0)) {
                    if ($this->getTotalSitterCount($uid, $this->session->getPlayerId()) < 2) {
                        $db->query("UPDATE users SET sit1Uid=$uid, sit1Permissions='{$sitter1['perm']}' WHERE id=" . $this->session->getPlayerId());
                        $this->session->setSittersId(1, $uid);
                        $this->session->setSittersPermissions(1, $sitter1['perm']);
                    } else {
                        $view->vars['error'] = T("Options", "This player is sitter for 2 players");
                    }
                }
            }
            if (!empty($sitter2['name'])) {
                $uid = $m->getUserByName($sitter2['name']);
                if ($uid && ($uid != $this->session->getPlayerId()) && ($uid != $this->session->getSittersId(1) || $this->session->getSittersId(1) == 0)) {
                    if ($this->getTotalSitterCount($uid, $this->session->getPlayerId()) < 2) {
                        $db->query("UPDATE users SET sit2Uid=$uid, sit2Permissions='{$sitter2['perm']}' WHERE id=" . $this->session->getPlayerId());
                        $this->session->setSittersId(2, $uid);
                        $this->session->setSittersPermissions(2, $sitter2['perm']);
                    } else {
                        $view->vars['error'] = T("Options", "This player is sitter for 2 players");
                    }
                }
            }
        } elseif (isset($_GET['id'], $_GET['type']) && Session::matchesChecker($_GET['a'] ?? null)) {
            $id = abs((int)$_GET['id']);
            if ($_GET['type'] == 1) {
                if ($this->session->getSittersId(1) == $id) {
                    $db->query("UPDATE users SET sit1Uid=0, sit1Permissions=87 WHERE id=" . $this->session->getPlayerId());
                    $this->session->setSittersId(1, 0);
                    $this->session->setSittersPermissions(1, 87);
                } elseif ($this->session->getSittersId(2) == $id) {
                    $db->query("UPDATE users SET sit2Uid=0, sit2Permissions=87 WHERE id=" . $this->session->getPlayerId());
                    $this->session->setSittersId(2, 0);
                    $this->session->setSittersPermissions(2, 87);
                }
            } elseif ($_GET['type'] == 2) {
                $uid = $this->session->getPlayerId();
                $db->query("UPDATE users SET
                sit1Uid=IF(sit1Uid=$uid, 0, sit1Uid),
                sit2Uid=IF(sit2Uid=$uid, 0, sit2Uid),
                sit1Permissions=IF(sit1Uid=$uid, 87, sit1Permissions),
                sit2Permissions=IF(sit2Uid=$uid, 87, sit2Permissions)
                WHERE id=$id");
            }
        }
        $this->view->vars['content'] .= $view->output();
    }

    /**
     * Read one sitter slot from POST: sitter[N][name] + sitter[N][flags][].
     *
     * @param int $formIndex 0 = Sitter I, 1 = Sitter II
     * @param int $sessionSlot 1 or 2 (users.sit1* / sit2*)
     * @return array{name:string,perm:int}
     */
    private function mergeSitter(int $formIndex, int $sessionSlot): array
    {
        $m = new OptionModel();
        $posted = $_POST['sitter'][$formIndex] ?? null;
        $name = '';
        $flagList = [];
        if (is_array($posted)) {
            $name = isset($posted['name']) ? trim((string)$posted['name']) : '';
            $flagList = isset($posted['flags']) && is_array($posted['flags']) ? $posted['flags'] : [];
        }
        if ($name === '') {
            $name = (string)$m->getPlayerName(Session::getInstance()->getSittersId($sessionSlot));
        }
        $perm = 0;
        $flags = [1, 2, 4, 8, 16, 32, 64];
        foreach ($flagList as $flag) {
            $flag = (int)$flag;
            if (in_array($flag, $flags, true)) {
                $perm |= $flag;
            }
        }
        return ['name' => $name, 'perm' => $perm];
    }

    /**
     * @param int $uid
     * @param mixed $myUID
     */
    private function getTotalSitterCount($uid, $myUID): int
    {
        $db = DB::getInstance();
        return (int) $db->fetchScalar("SELECT COUNT(id) FROM users WHERE (sit1Uid=$uid AND sit1Uid!=$myUID) OR (sit2Uid=$uid AND sit2Uid!=$myUID)");
    }

    private function Vacation(): void
    {
        if (Session::getInstance()->isInVacationMode()) {
            $this->showVacationActive();
            return;
        }
        $view = new PHPBatchView("options/Vacation");
        $m = new OptionModel();
        $view->vars['conditions'] = $conditions = $m->getVacationConditions(
            Session::getInstance()->getPlayerId(),
            Session::getInstance()->hasProtection()
        );
        $remainingVacationDays = Formulas::remainingVacationDays((int)Session::getInstance()->getUsedVacationDays());
        if (WebService::isPost() && !Session::getInstance()->isInVacationMode()) {
            if (!Session::validateChecker()) {
                $this->view->vars['content'] .= $view->output();
                return;
            }

            $days = min(max((int)($_POST['days'] ?? 0), 1), $remainingVacationDays);
            if ($remainingVacationDays > 0
                && array_sum($view->vars['conditions']) === count($view->vars['conditions'])
                && $remainingVacationDays >= $days) {
                $m->enterVacationMode(Session::getInstance()->getPlayerId(), $days);
                Session::getInstance()->setVacationTill($days * 86400 + time());
                Session::getInstance()->setVacationUsedDays(Session::getInstance()->getUsedVacationDays() + $days);
                $this->showVacationActive();

                return;
            }
        }
        $this->view->vars['content'] .= $view->output();
    }

    private function showVacationActive(): void
    {
        $view = new PHPBatchView("options/VacationActive");
        $view->vars['vacationTil'] = TimezoneHelper::autoDate(Session::getInstance()->getVacationTil(), true);
        $view->vars['vacationDays'] = max(1, ceil((Session::getInstance()->getVacationTil() - time()) / 86400));
        $this->view->vars['content'] .= $view->output();
    }
}
