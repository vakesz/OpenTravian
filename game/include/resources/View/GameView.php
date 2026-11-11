<?php

namespace resources\View;

use Core\Caching\Caching;
use Core\Caching\ProfileCache;
use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\BBCode;
use Core\Helper\PreferencesHelper;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\GoldHelper;
use Game\Hero\HeroHud;
use Game\Hero\SessionHero;
use Game\QuickLink;
use Game\StockBar;
use Game\TruceDay;
use Model\AdventureModel;
use Model\AllianceModel;
use Model\AutoExtendModel;
use Model\InfoBoxModel;
use Model\LinksModel;
use Model\MovementsModel;
use Model\TasksModel;
use Model\VillageGroupModel;

use function array_key_exists;
use function array_keys;
use function logError;
use function getCustom;
use function getDisplay;

class GameView
{
    public $newVillagePrefix = [];
    public $vars = [];
    public $session;

    public function __construct()
    {
        $db = DB::getInstance();

        $this->session = Session::getInstance();
        if (!$this->session->isValid()) {
            return;
        }

        $this->vars['ajaxToken'] = $this->session->getAjaxToken();
        $this->vars['_player_uuid'] = $this->session->getPlayerUUID();
        $this->vars['race'] = $this->session->getRace();
        $this->vars['raceName'] = $this->session->getRaceName();
        $this->vars['titleInHeader'] = '';
        $this->vars['bodyCssClass'] = '';
        $this->vars['contentCssClass'] = '';
        $this->vars['containerCssClass'] = 'contentPage';
        $this->vars['colorBlind'] = $this->session->getDisplay()[3] == 1;
        $this->vars['autoReload'] = !getDisplay('allowAutoReloadSettingChange') || $this->session->getDisplay()[4];
        $this->vars['heroBox'] = '';
        $this->vars['sidebarBeforeContent'] = '';
        $this->vars['sidebarAfterContent'] = '';
        $this->vars['villageBoxes'] = '';
        $this->vars['showNavBar'] = true;
        $this->vars['showHeaderBar'] = false;
        $this->vars['showStockbar'] = true;
        $this->vars['showCloseButton'] = false;
        $this->vars['headerBar'] = false;
        $this->vars['content'] = '';
        $this->vars['showTime'] = true;
        // Harbor villages (settled on an abandoned shore) get the reference's shore scenery: div#background.isShore.
        $this->vars['isShore'] = (bool)$db->fetchScalar('SELECT shore FROM wdata WHERE id=?', [(int)$this->session->getKid()]);
        // Dorf2 fills harbour/ship layers; other pages leave the Gpack shell empty.
        $this->vars['behindContent'] = '';

        $memcache = Caching::getInstance();
        $uid = (int) $this->session->getPlayerId();
        if (!($newReports = $memcache->get('newReports' . $this->session->getPlayerId()))) {
            $newReports = $db->fetchScalar("SELECT COUNT(id) FROM ndata WHERE uid=$uid AND (viewed=0 AND deleted=0)");
            $memcache->add('newReports' . $this->session->getPlayerId(), $newReports, 30);
        }
        if (!($ignoreList = $memcache->get('ignoreList' . $this->session->getPlayerId()))) {
            $ignoreList = [];
            $find = $db->query("SELECT ignore_id FROM ignoreList WHERE uid=$uid");
            while ($row = $find->fetch_assoc()) {
                $ignoreList[] = $row['ignore_id'];
            }
            $memcache->add('ignoreList' . $this->session->getPlayerId(), $ignoreList, 86400);
        }
        $filter = sizeof($ignoreList) ? 'AND uid NOT IN(' . implode(',', $ignoreList) . ')' : '';
        if (!($newMessages = $memcache->get('newMessages' . $this->session->getPlayerId()))) {
            $newMessages = $db->fetchScalar(
                "SELECT COUNT(id) FROM mdata WHERE to_uid={$this->session->get(
                    'id'
                )} AND delete_receiver=0 AND (viewed=0) $filter"
            );
            $memcache->add('newMessages' . $this->session->getPlayerId(), $newMessages, 30);
        }
        $this->vars['newReportsCount'] = $newReports;
        $this->vars['newMessagesCount'] = $newMessages;
        $this->vars['dailyQuestsIndicator'] = (new \Model\DailyQuestModel())->hasClaimableReward($uid);
        $this->vars['shopNotificationCount'] = 0;
        $this->vars['isSitter'] = $this->session->isSitter();
    }

    public function output()
    {
        $this->vars['silverCount'] = $this->session->getAvailableSilver();
        $this->vars['goldCount'] = $this->session->getGold();
        if ($this->session->getPlayerId() > 0) {
            $this->renderStockBar();
            //before content
            $this->renderHeroBox();
            $this->renderAllianceBox();
            $this->renderInfoBox();
            $this->renderLinkList();
            //after content
            $this->renderActiveVillageBox();
            $this->renderVillagesList();
            $this->vars['sidebarAfterContent'] =
                '<div id="villageBoxes" class="contentV2">' . $this->vars['villageBoxes'] . '</div>' .
                $this->vars['sidebarAfterContent'];
            if ($this->session->getPlayerId() > 2) {
                $this->renderQuest();
            }
        }
        // The server-time HUD is an absolute clock; Layout selects the local
        // presentation only when the player explicitly enables it.
        $this->vars['dateTime'] = time();
        $this->vars['extra'] = TimezoneHelper::getTimeFormat() == 'h:i:s' ? TimezoneHelper::date('a') : '';
        $view = new PHPBatchView('layout/Layout');
        $view->vars = $this->vars;
        $view->display();
    }

    private function renderStockBar()
    {
        $this->vars['stockBar'] = [
            'maxstore' => $this->session->village->get('maxstore'),
            'maxcrop' => $this->session->village->get('maxcrop'),
            'production' => $this->session->village->getProduction(-1),
            'productionBoost' => [
                $this->session->hasProductionBoost(1),
                $this->session->hasProductionBoost(2),
                $this->session->hasProductionBoost(3),
                $this->session->hasProductionBoost(4),
            ],
            'storage' => array_map('round', $this->session->village->getCurrentResources(-1, false)),
            'titles' => StockBar::tooltips($this->session->village),
            'storageString' => [null, null, null, null],
            'storageClass' => [null, null, null, null],
            'percents' => [0, 0, 0, 0],
            'maxstoreString' => number_format_compact($this->session->village->get('maxstore')),
            'maxcropString' => number_format_compact($this->session->village->get('maxcrop')),
            'freeCropString' => number_format_compact($this->session->village->getProduction(3)),
        ];
        $this->vars['stockBar']['percents'][0] = round(
            ($this->vars['stockBar']['storage'][0] / $this->vars['stockBar']['maxstore']) * 100
        );
        $this->vars['stockBar']['percents'][1] = round(
            ($this->vars['stockBar']['storage'][1] / $this->vars['stockBar']['maxstore']) * 100
        );
        $this->vars['stockBar']['percents'][2] = round(
            ($this->vars['stockBar']['storage'][2] / $this->vars['stockBar']['maxstore']) * 100
        );
        $this->vars['stockBar']['percents'][3] = round(
            ($this->vars['stockBar']['storage'][3] / $this->vars['stockBar']['maxcrop']) * 100
        );

        foreach ($this->vars['stockBar']['storage'] as $k => $v) {
            $maxStore = $this->vars['stockBar']['maxstore'];
            if ($k == 3) {
                $maxStore = $this->vars['stockBar']['maxcrop'];
            }

            $this->vars['stockBar']['storageClass'][$k] = $v - $maxStore == 0 ? 'Full' : '';
            $this->vars['stockBar']['storageString'][$k] = number_format_compact($v);
        }
    }

    private function renderHeroBox()
    {
        $session = $this->session;
        $db = DB::getInstance();
        $view = new PHPBatchView('layout/topBarBoxHero');
        $m = new AdventureModel();
        $memcached = Caching::getInstance();

        $gender = $db->fetchScalar('SELECT gender FROM face WHERE uid=' . Session::getInstance()->getPlayerId());
        $beard = $db->fetchScalar('SELECT beard FROM face WHERE uid=' . Session::getInstance()->getPlayerId());
        $headProfile = $db->fetchScalar(
            'SELECT headProfile FROM face WHERE uid=' . Session::getInstance()->getPlayerId()
        );
        $hairColor = $db->fetchScalar('SELECT hairColor FROM face WHERE uid=' . Session::getInstance()->getPlayerId());
        $hairStyle = $db->fetchScalar('SELECT hairStyle FROM face WHERE uid=' . Session::getInstance()->getPlayerId());
        $ears = $db->fetchScalar('SELECT ears FROM face WHERE uid=' . Session::getInstance()->getPlayerId());
        $eyebrow = $db->fetchScalar('SELECT eyebrow FROM face WHERE uid=' . Session::getInstance()->getPlayerId());
        $eyes = $db->fetchScalar('SELECT eyes FROM face WHERE uid=' . Session::getInstance()->getPlayerId());
        $nose = $db->fetchScalar('SELECT nose FROM face WHERE uid=' . Session::getInstance()->getPlayerId());
        $mouth = $db->fetchScalar('SELECT mouth FROM face WHERE uid=' . Session::getInstance()->getPlayerId());

        $heroStatus = $this->session->hero->getHeroStatus();
        $view->vars = [
            'gender' => $gender,
            'beard' => $beard,
            'headProfile' => $headProfile,
            'hairColor' => $hairColor,
            'hairStyle' => $hairStyle,
            'ears' => $ears,
            'eyebrow' => $eyebrow,
            'eyes' => $eyes,
            'nose' => $nose,
            'mouth' => $mouth,
            'uid' => $this->session->getPlayerId(),
            'playerName' => $this->session->getName(),
            'hasNewPoints' => $this->session->hero->hasNewPoints(),
            'race' => $this->session->getRace(),
            'raceName' => $this->session->getRaceName(),
            'longStatus' => HeroHud::longStatus($this->session->hero, $heroStatus),
            'shortStatus' => $this->session->hero->getShortStatus(),
            'status' => $heroStatus,
            'heroStatusClass' => \Game\Hero\HeroHud::statusClass($heroStatus),
            // The status icon leads to the Rally point of the hero's village: the send-troops tab at home, the
            // overview of the moving troops while the hero is away (as on the reference).
            'heroRallyPointUrl' => '/build.php?newdid=' . (int)($this->session->hero->hero['kid'] ?? 0) . '&id=39&&tt='
                . ($heroStatus === SessionHero::STATUS_HOME ? 2 : 1),
            'health' => $this->session->hero->getHeroHealth(),
            'exp' => $this->session->hero->getHeroExp(),
            'expPercent' => $this->session->hero->getHeroExpPercent(),
            'dead' => $heroStatus === SessionHero::STATUS_DEAD,
            'regenerating' => $heroStatus === SessionHero::STATUS_REVIVING,
            'regeneratedHealth' => $this->session->hero->getRegeneratedHealth(),
            'lvlUp' =>
                $this->session->hero->getAvailablePoints() > 0 && $this->session->hero->getAvailablePoints() % 4 == 0,
            'toggle' => PreferencesHelper::getPreference('travian_toggle_hero'),
            'adventureWhiteButton' => ['adventureCount' => $m->getAdventureCount($this->session->getPlayerId())],
        ];
        $view->vars['adventureWhiteButton']['id'] = get_button_id();
        $view->vars['auctionWhiteButton']['id'] = get_button_id();

        $this->vars['heroBox'] = $view->output();
    }

    private function renderAllianceBox()
    {
        if ($this->session->getAllianceId() <= 0) {
            return;
        }

        $cache = Caching::getInstance();
        $key = sprintf('%s:AllianceBox', $this->session->getPlayerId());
        if ($cachedContent = $cache->get($key)) {
            $this->vars['sidebarBeforeContent'] .= $cachedContent;
            return;
        }
        $m = new AllianceModel();
        $embassy = $m->getMaxPlayerEmbassyLvl($this->session->getPlayerId());
        $view = new PHPBatchView('layout/sidebarBoxAlliance');
        $view->vars['aid'] = $this->session->getAllianceId();
        $view->vars['link'] = '#';
        $view->vars['noEmbassy'] = $embassy['embassy'] == 0;
        if (!$view->vars['noEmbassy']) {
            $view->vars['link'] = '/build.php?gid=18&newdid=' . (int) $embassy['kid'];
        }
        if ($view->vars['aid']) {
            $view->vars['allianceName'] = $m->getAllianceField($view->vars['aid'], 'name');
        }
        $newsSettings = $this->session->getAllianceSettings();
        $in = [];
        if ($newsSettings[0]) {
            $in[] = AllianceModel::LOG_NEW_VILLAGE;
        }
        if ($newsSettings[1]) {
            $in[] = AllianceModel::LOG_JOINED;
        }
        if ($newsSettings[2]) {
            $in[] = AllianceModel::LOG_INVITE;
        }
        if ($newsSettings[3]) {
            $in[] = AllianceModel::LOG_LEFT;
        }
        if ($newsSettings[4]) {
            $in[] = AllianceModel::LOG_KICK;
        }
        $db = DB::getInstance();
        $HTML = '';
        $view->vars['hasNews'] = false;
        if ($view->vars['aid'] && array_sum($newsSettings)) {
            $results = $db->query(
                "SELECT * FROM ali_log WHERE aid={$view->vars['aid']} AND type IN(" .
                    implode(',', $in) .
                    ') ORDER BY time DESC, id DESC LIMIT 3'
            );
            while ($row = $results->fetch_assoc()) {
                $HTML .=
                    '<ul><li>' .
                    TimezoneHelper::autoDateString($row['time'], true) .
                    ' ' .
                    BBCode::renderAliNewsEvent($row['data']) .
                    '</li></ul>';
            }
            $view->vars['hasNews'] = $results->num_rows >= 1;
        }
        $view->vars['toggle'] = PreferencesHelper::getPreference('travian_toggle_alliance');
        $view->vars['news'] = $HTML;
        $output = $view->output();
        //$cache->set($key, $output, 300);
        $this->vars['sidebarBeforeContent'] .= $output;
    }

    private function renderInfoBox()
    {
        $m = new InfoBoxModel();
        $publicInfobox = $m->getPublicInfoBox();
        $infoBox = $m->getMyInfoBox($this->session->getPlayerId());
        /*if (!sizeof($infoBox) && !sizeof($publicInfobox)) {
            return;
        }*/
        $infoBox_count = 0;
        $view = new PHPBatchView('layout/sidebarBoxInfoBox');
        $view->vars['total'] = sizeof($infoBox) + sizeof($publicInfobox);
        $view->vars['title'] = T('inGame', 'total_messages') . ': ' . $view->vars['total'];
        $unread = $m->getUnreadInfoBoxCount($this->session->getPlayerId());
        if ($unread) {
            $view->vars['title'] .= '<br />' . T('inGame', 'unReadMessages') . ': ' . $unread;
        }
        $view->vars['unreadCount'] = $unread;
        $view->vars['unread'] = $unread ? 'New' : 'Read';
        $view->vars['content'] = '';
        $view->vars['toggle'] = PreferencesHelper::getPreference('travian_toggle_infobox');
        $i = 0;
        foreach ($publicInfobox as $row) {
            if (
                ($row['showTo'] > 0 && $row['showTo'] < time()) ||
                ($row['showFrom'] > 0 && $row['showFrom'] > time())
            ) {
                continue;
            }
            ++$i;
            $class = [];
            if ($i == 1) {
                $class[] = 'firstElement';
                $class[] = 'active';
            }
            $view->vars['content'] .= '<li id="infoID_p' . $row['id'] . '" class="' . implode(' ', $class) . '">';
            if ($row['autoType'] == 1) {
                $cache = Caching::getInstance();
                if (!($offer = $cache->get('paymentOffer'))) {
                    $offer = GlobalDB::getInstance()
                        ->query('SELECT offer, offerFrom FROM paymentConfig LIMIT 1')
                        ->fetch_assoc();
                    $cache->add('paymentOffer', $offer, max($offer['offer'] - time(), 1));
                }
                $view->vars['content'] .= sprintf(
                    T('inGame', 'infoBox.AutoType_GoldPromotion'),
                    TimezoneHelper::autoDateString($offer['offerFrom'], true),
                    TimezoneHelper::autoDateString($offer['offer'], true)
                );
            } elseif ($row['autoType'] == 2) {
                //TODO: Public Peace day.
            } else {
                $view->vars['content'] .= $row['params'];
            }
            $view->vars['content'] .= '</li>';
            $infoBox_count++;
        }
        $conf = Config::getInstance();
        $infoBoxSize = sizeof($infoBox);
        $session = $this->session;

        foreach ($infoBox as $row) {
            if ($row['type'] == 8 || $row['type'] == 9) {
                $release = $row['type'] == 8 ? $conf->timers->ArtifactsReleaseTime : $conf->timers->wwPlansReleaseTime;
                $row['showFrom'] = $release - 5 * 86400;
                $row['showTo'] = $release;
            }
            if ($row['type'] == 6) {
                $row['showTo'] = $this->session->protectionTill();
            }
            if ($row['type'] == 15) {
                $row['showTo'] = $conf->game->catapultAvailableTime;
            }
            if ($row['type'] == 17) {
                if (!TruceDay::isActive()) {
                    continue;
                }
                $row['showFrom'] = $conf->dynamic->truceFrom;
                $row['showTo'] = $conf->dynamic->truceTo;
            }

            if (in_array($row['type'], [2, 3, 4, 5])) {
                if ($this->session->productionBoostTill($row['type'] - 1) - time() > 86400) {
                    --$infoBoxSize;
                    continue;
                }
            }
            if ($row['type'] == 1) {
                if ($this->session->plusTill() - time() > 86400) {
                    --$infoBoxSize;
                    continue;
                }
            }

            if (
                ($row['showTo'] > 0 && $row['showTo'] < time()) ||
                ($row['showFrom'] > 0 && $row['showFrom'] > time())
            ) {
                continue;
            }
            ++$i;
            $class = [];
            if ($i == 1) {
                $class[] = 'firstElement';
                $class[] = 'active';
            }
            if ($row['forAll'] == 1) {
                $row['readStatus'] = $m->haveIReadPublicOne($this->session->getPlayerId(), $row['id']);
                $row['del'] = $m->haveIDeletedPublicOne($this->session->getPlayerId(), $row['id']);
            }
            if (!$row['readStatus']) {
                $class[] = 'unreaded';
            }
            $view->vars['content'] .= '<li id="infoID_' . $row['id'] . '" class="' . implode(' ', $class) . '">';
            if ($row['type'] != 0) {
                $view->vars['content'] .= $this->renderInGameInfoBox($row);
            } else {
                if (!$row['forAll'] && !$row['del']) {
                    $view->vars['content'] .= '<a class="infoboxDeleteButton" href="#" data-id="' . $row['id'] . '">';
                    $view->vars['content'] .= '<img src="img/spacer.gif" class="del" alt="del">';
                    $view->vars['content'] .= '</a>';
                }
                $view->vars['content'] .= $row['params'];
            }
            $view->vars['content'] .= '</li>';
            if ($infoBoxSize == 1 && $unread) {
                $m->setInfoboxItemsRead($this->session->getPlayerId(), [$row['id']]);
            }
            $infoBox_count++;
        }

        if ($infoBox_count == 0) {
            $view->vars['total'] = 0;
            $view->vars['content'] .=
                '<li id="infoID_0" class="infoType_1 firstElement active unreaded"><span style="font-weight:bold;">No new messages!</span><br></li>';
            //return;
        }
        $output = $view->output();

        $this->vars['sidebarBeforeContent'] .= $output;
    }

    private function renderInGameInfoBox($row)
    {
        $return = '<p>';
        //$nonDeletableTypes = [6, 13, 11];
        $nonDeletableTypes = [6, 13, 11, 14, 16];
        if (!$row['del'] && !in_array($row['type'], $nonDeletableTypes)) {
            $return .= '<a class="infoboxDeleteButton" href="#" data-id="' . $row['id'] . '">';
            $return .= '<img src="img/spacer.gif" class="del" alt="del">';
            $return .= '</a>';
        }
        $config = Config::getInstance();
        $session = $this->session;
        $autoExtend = new AutoExtendModel();
        if ($row['type'] <= 5) {
            $gold = $row['type'] == 1 ? $config->gold->plusGold : $config->gold->productionBoostGold;
            if ($this->session->getAvailableGold() < $gold) {
                if ($autoExtend->hasAutoExtend($this->session->getPlayerId(), $row['type'])) {
                    $return .= T('inGame', 'infoBox.Not enough gold to extend this feature') . '<br />';
                }
            }
        }
        switch ($row['type']) {
            case 1:
                if ($this->session->plusTill() > time()) {
                    $return .= sprintf(
                        T('inGame', 'infoBox.PlusWillBeFinished'),
                        appendTimer($this->session->plusTill() - time())
                    );
                } else {
                    $return .= T('inGame', 'infoBox.plusAccountExpired');
                }
                break;
            case 2:
            case 3:
            case 4:
            case 5:
                if ($this->session->hasProductionBoost($row['type'] - 1)) {
                    $return .= sprintf(
                        T('inGame', 'infoBox.Boost' . ($row['type'] - 1) . 'WillBeFinished'),
                        appendTimer($this->session->productionBoostTill($row['type'] - 1) - time())
                    );
                } else {
                    $return .= T('inGame', 'infoBox.productionBoost' . ($row['type'] - 1) . 'Expired');
                }
                break;
            case 6:
                $baseProtection = Formulas::getProtectionBasicTime($this->session->get('signupTime'));
                $usedProtection = $this->session->protectionTill() - $this->session->get('signupTime');
                $protectionExtendingUsed = $usedProtection > $baseProtection;
                $protectionLeft = $this->session->protectionTill() - time();
                $isProtectionExtendTime =
                    $protectionLeft <= Formulas::getProtectionShowExtendTime($this->session->get('signupTime'));
                if (!$protectionExtendingUsed && $isProtectionExtendTime) {
                    $extendTime = Formulas::getProtectionExtendTime($this->session->get('signupTime'));
                    if ($extendTime >= 86400) {
                        $unit = round($extendTime / 86400, 1);
                        $unitName = T('inGame', 'Days');
                    } elseif ($extendTime >= 3600) {
                        $unit = round($extendTime / 3600, 1);
                        $unitName = T('inGame', 'Hours');
                    } elseif ($extendTime > 60) {
                        $unit = round($extendTime / 60, 1);
                        $unitName = T('inGame', 'Minutes');
                    } else {
                        $unit = $extendTime;
                        $unitName = T('inGame', 'Seconds');
                    }
                    $extendText = sprintf(
                        T('inGame', 'Click here to extend your beginners protection by 3 days'),
                        $unit,
                        $unitName
                    );
                    $confirmation = htmlspecialchars(
                        json_encode(T('inGame', 'Are you sure you want to extend your beginners protection?')),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $return .=
                        '<a href="/options?cmd=extendBeginnersProtection" onclick="return confirm(' .
                        $confirmation .
                        ');">' .
                        htmlspecialchars($extendText, ENT_QUOTES, 'UTF-8') .
                        '</a> ';
                }
                if ($this->session->protectionTill() < time()) {
                    $return .= 'Pending.';
                    break;
                }
                $return .= sprintf(
                    T('inGame', 'infoBox.protection'),
                    appendTimer($this->session->protectionTill() - time())
                );
                break;
            case 7:
                if ($config->timers->lastMedalsGiven < time()) {
                    $return .= 'Pending.';
                    break;
                }
                $return .= sprintf(
                    T('inGame', 'infoBox.MedalsWillBeGivenIn'),
                    appendTimer($config->dynamic->lastMedalsGiven + $config->game->medals_interval - time())
                );
                break;
            case 8:
                if ($config->timers->ArtifactsReleaseTime < time()) {
                    $return .= 'Pending.';
                    break;
                }
                $return .= sprintf(
                    T('inGame', 'infoBox.ArtifactsWillBeReleasedIn'),
                    appendTimer($config->timers->ArtifactsReleaseTime - time())
                );
                break;
            case 9:
                if ($config->timers->wwPlansReleaseTime < time()) {
                    $return .= 'Pending.';
                    break;
                }
                $return .= sprintf(
                    T(
                        'inGame',
                        getCustom('wwPlansEnabled') ? 'infoBox.wwPlansWillBeReleasedIn' : 'infoBox.youCanBuildWWIn'
                    ),
                    appendTimer($config->timers->wwPlansReleaseTime - time())
                );
                break;
            case 10:
                if ($config->timers->AutoFinishTime < time()) {
                    $return .= 'Pending.';
                    break;
                }
                $return .= sprintf(
                    T('inGame', 'infoBox.AutoFinishTime'),
                    appendTimer($config->timers->AutoFinishTime - time())
                );
                break;
            case 11:
                if ($row['showTo'] < time()) {
                    $return .= 'Pending.';
                    break;
                }
                $return .= sprintf(T('inGame', 'The account will be deleted in'), appendTimer($row['showTo'] - time()));
                break;
            case 12: //report overflow
                $day = false;
                $unit = 7;
                if ($unit % 24 == 0) {
                    $unit /= 24;
                    $day = true;
                }
                $return .= sprintf(
                    T('inGame', 'infoBox.overFlowMessage'),
                    $unit,
                    $day ? T('inGame', 'infoBox.day(s)') : T('inGame', 'infoBox.hour(s)')
                );
                break;
            case 13: //vacation
                $return .= sprintf(
                    T('inGame', 'infoBox.Your account is in vacation until [time]'),
                    TimezoneHelper::autoDate($row['showTo'], true)
                );
                $return .= '<br /><a href="/options/vacation" title="">' . T('inGame', 'More Information') . '</a>';
                break;
            case 14: //banned
                $uid = $this->session->getPlayerId();
                $db = DB::getInstance();
                $result = $db->query("SELECT * FROM banQueue WHERE uid=$uid ORDER BY id DESC LIMIT 1");
                if ($result->num_rows) {
                    $result = $result->fetch_assoc();
                    if (!empty($result['reason'])) {
                        $reason = T('inGame', "infoBox.Reasons.{$result['reason']}");
                        $return .=
                            '<span class="warning">' .
                            T('inGame', 'infoBox.Your account was banned!') .
                            '</span><br />';
                        $return .= T('inGame', 'infoBox.Reason') . ': ' . $reason;
                    }
                }
                break;
            case 15: //catapult
                $return .= sprintf(
                    T('inGame', 'infoBox.CatapultAvailableIn'),
                    appendTimer(Config::getProperty('game', 'catapultAvailableTime') - time())
                );
                break;
            case 16: //email not verified
                $return .= T('EVerify', 'YOUR_EMAIL_ADDRESS_IS_NOT_VERIFIED_TO_VERIFY_CLICK_HERE');
                break;
            case 17: //truce day
                $reason = TruceDay::getReasonId();
                $return .= sprintf(
                    T('Truce', "infobox_reasons.{$reason}"),
                    TimezoneHelper::autoDate(TruceDay::getFrom(), true),
                    TimezoneHelper::autoDate(TruceDay::getTo(), true)
                );
                break;
        }
        $return .= '</p>';
        if ($row['type'] == 1) {
            $helper = new GoldHelper();
            $return .= $helper->getPlusButton(true);
        } elseif ($row['type'] <= 5) {
            $helper = new GoldHelper();
            $return .= $helper->getProductionBoostButton($row['type'] - 1, true);
        }
        return $return;
    }

    private function renderLinkListItem(string $href, string $label, string $class = '', string $title = ''): string
    {
        $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $safeClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $titleAttr = $title !== '' ? ' title="' . $title . '"' : ' title="' . $safeLabel . '"';
        $targetAttr = $class === 'newTab' ? ' target="_blank" rel="noopener noreferrer"' : '';
        // Native V2 linklist grid: 15px svg.link + .name (imports.css).
        return '<li class="' .
            $safeClass .
            '"' .
            $titleAttr .
            '>' .
            '<a href="' .
            $safeHref .
            '"' .
            $targetAttr .
            '>' .
            '<svg viewBox="0 0 11 11" class="link" aria-hidden="true" focusable="false"><path d="M2.2 1.1v8.8L9.9 5.5z"></path></svg>' .
            '<span class="name">' .
            $safeLabel .
            '</span>' .
            '</a></li>';
    }

    private function renderLinkList()
    {
        $view = new PHPBatchView('layout/sidebarBoxLinklist');
        $pinnedLinks = '';
        $view->vars['plus'] = $this->session->hasPlus() || $this->session->isAdmin();
        $view->vars['editBlack'] = get_button_id();
        if (getDisplay('showPinnedLinkList')) {
            $label = (string) T('LinkList', 'Farmlist');
            $pinnedLinks .= $this->renderLinkListItem('/build.php?tt=99&id=39', $label);
            if (!$this->session->isAdmin()) {
                $supportTitle = htmlspecialchars(
                    T('LinkList', 'Contact Support') .
                        '<br /><span class="notice">' .
                        T('links', 'linkWillOpenInNewTab') .
                        '</span>',
                    ENT_QUOTES,
                    'UTF-8'
                );
                $pinnedLinks .= $this->renderLinkListItem(
                    '/messages/write?to=2',
                    (string) T('LinkList', 'Contact Support'),
                    'newTab',
                    $supportTitle
                );
            }
        }
        if ($this->session->isAdmin()) {
            $adminTitle = htmlspecialchars(
                T('LinkList', 'Go to admin panel') .
                    '<br /><span class="notice">' .
                    T('links', 'linkWillOpenInNewTab') .
                    '</span>',
                ENT_QUOTES,
                'UTF-8'
            );
            $pinnedLinks .= $this->renderLinkListItem(
                '/admin.php',
                (string) T('LinkList', 'Go to admin panel'),
                'newTab',
                $adminTitle
            );
        }
        $playerLinks = '';
        $hasPlayerLinks = false;
        if ($view->vars['plus']) {
            $memcache = Caching::getInstance();
            // v3: native svg.link + .name markup (invalidates legacy ► / <img> cache).
            $cacheKey = 'links_v3_' . $this->session->getPlayerId();
            $links = !$this->session->isAdminInAnotherAccount() ? $memcache->get($cacheKey) : false;
            if (is_array($links) && isset($links['hasPlayerLinks'], $links['links'])) {
                $hasPlayerLinks = (bool) $links['hasPlayerLinks'];
                $playerLinks = $links['links'];
            } else {
                $m = new LinksModel();
                $links = $m->getPlayerLinks($this->session->getPlayerId());
                $hasPlayerLinks = (bool) $links->num_rows;
                if ($hasPlayerLinks) {
                    while ($row = $links->fetch_assoc()) {
                        $class = '';
                        if (substr($row['url'], -1) == '*') {
                            $class = 'newTab';
                            $row['url'] = substr($row['url'], 0, strlen($row['url']) - 1);
                        }
                        $url = $row['url'];
                        if (strpos($url, 'http://') === false && strpos($url, 'https://') === false) {
                            $url = '/' . ltrim($url, '/');
                        }
                        $title = $url;
                        if ($class === 'newTab') {
                            $title = htmlspecialchars(
                                $url . '<br /><span class="notice">' . T('links', 'linkWillOpenInNewTab') . '</span>',
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        }
                        $playerLinks .= $this->renderLinkListItem($url, (string) $row['name'], $class, $title);
                    }
                }
                if (!$this->session->isAdmin()) {
                    $memcache->set(
                        $cacheKey,
                        [
                            'hasPlayerLinks' => $hasPlayerLinks,
                            'links' => $playerLinks,
                        ],
                        86400
                    );
                }
            }
        }
        $view->vars['links'] = $pinnedLinks . $playerLinks;
        // Empty-state notice only when the rendered list has no entries at all.
        $view->vars['noLinks'] = $view->vars['links'] === '';
        $this->vars['sidebarBeforeContent'] .= $view->output();
    }

    private function renderActiveVillageBox()
    {
        // ActiveVillage quick links: five `.quickLinkSlot` rings + settings (⋯),
        // sharing the villageQuickLinks preference with the village list.
        $village = Village::getInstance();
        $kid = (int) $village->getKid();
        $x = $y = 0;
        extract(Formulas::kid2xy($kid), EXTR_IF_EXISTS);
        $data = [
            'villageName' => $village->getName(),
            'villageId' => $kid,
            'coordX' => (int) $x,
            'coordY' => (int) $y,
            'loyalty' => $village->getLoyalty(),
            'population' => (int) $village->getPop(),
            'playerName' => htmlspecialchars((string) $this->session->getName(), ENT_QUOTES, 'UTF-8'),
            'quickLinks' => (string) PreferencesHelper::getPreference('villageQuickLinks'),
            'quickLinkActions' => $this->getQuickLinkActions(),
            'quickLinksLocked' => !$this->session->hasPlus(),
            'lockedQuickLinks' => $this->lockedQuickLinks(),
        ];
        $view = new PHPBatchView('layout/sidebarBoxActiveVillages');
        $this->vars['villageBoxes'] .= $view->output($data);
    }

    private function getTotalVillagesCount()
    {
        return $this->session->get('total_villages');
    }

    private function renderVillagesList()
    {
        $data = [];
        $data['buttonToggleId'] = get_button_id();
        $data['buttonDorf3Id'] = get_button_id();
        $data['villages'] = '';
        $data['villageGroups'] = '';
        // Keeps the current view when switching villages
        // (/build.php?newdid=N&id=39), rather than resetting a route with
        // query-driven state such as a production tab or map position.
        $requestParts = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $villageSwitchPath = is_array($requestParts) && isset($requestParts['path'])
            ? (string) $requestParts['path']
            : '';
        if (preg_match('#^/[A-Za-z0-9_./-]+$#', $villageSwitchPath) !== 1 || str_contains($villageSwitchPath, '..')) {
            $villageSwitchPath = '/dorf1.php';
        }
        $villageSwitchParams = [];
        if (is_array($requestParts) && isset($requestParts['query']) && is_string($requestParts['query'])) {
            parse_str($requestParts['query'], $villageSwitchParams);
        }
        // A village switch is navigation, never a replay of a mutation URL.
        // Canonical controller state below replaces matching view parameters.
        foreach (['newdid', 'a', 'c', 'd', 'action', 'buildmaster', 'checksum', 'new_building_level'] as $parameter) {
            unset($villageSwitchParams[$parameter]);
        }
        $villageSwitchQuery = http_build_query(array_merge($villageSwitchParams, $this->newVillagePrefix));
        $db = DB::getInstance();
        $ownedVillageCount = (int) $this->session->get('total_villages');
        $currentCP = $this->session->getCP();
        $memcache = Caching::getInstance();
        $maxVillageCount = Formulas::countCPVillages($currentCP);
        $newVillageCP = Formulas::newVillageCP($maxVillageCount + 1);
        $currentMaxVillageCP = Formulas::newVillageCP($maxVillageCount);
        $dif = $newVillageCP - $currentMaxVillageCP;
        $reached = ($currentCP - $currentMaxVillageCP) / $dif;
        $data['percent'] = 100 * $reached;
        $data['ownedVillageCount'] = $ownedVillageCount;
        $data['maxVillageCount'] = $maxVillageCount;
        $data['currentCP'] = $currentCP - $currentMaxVillageCP;
        $data['nextCP'] = $dif;
        $session = $this->session;
        $uid = $this->session->getPlayerId();
        $data['quickLinks'] = (string) PreferencesHelper::getPreference('villageQuickLinks');
        $data['quickLinkActions'] = $this->getQuickLinkActions();
        $data['quickLinksLocked'] = !$this->session->hasPlus();
        $villages = (new ProfileCache($this->session->get('profileCacheVersion')))->getProfileVillages($uid);
        $villageGroups = (new VillageGroupModel())->getGroups($uid);
        $data['villageGroupCount'] = count($villageGroups);
        $data['maxVillageGroups'] = VillageGroupModel::MAX_GROUPS;
        $villageRows = [];
        $attacksList = $memcache->get("attacksList:{$this->session->getPlayerId()}");
        if (!is_array($attacksList)) {
            $attacksList = [];
            if ($this->session->getPlayerId() > 2 && ($this->session->hasPlus() || $this->session->isAdmin())) {
                $attacks = $db->query(
                    "SELECT v.kid, (SELECT COUNT(m.id) FROM movement m WHERE m.mode=0 AND m.to_kid=v.kid AND m.attack_type IN(3,4)) as `attack_count` FROM vdata v WHERE v.owner={$this->session->getPlayerId()}"
                );
                if ($attacks !== false) {
                    while ($row = $attacks->fetch_assoc()) {
                        $attacksList[$row['kid']] = $row['attack_count'];
                    }
                }
                $attacks = $db->query(
                    "SELECT o.did, (SELECT COUNT(m.id) FROM movement m WHERE m.mode=0 AND m.to_kid=o.kid AND m.attack_type IN(3,4)) as `attack_count` FROM odata o WHERE o.owner={$this->session->getPlayerId()}"
                );
                if ($attacks !== false) {
                    while ($row = $attacks->fetch_assoc()) {
                        if (!isset($attacksList[$row['did']])) {
                            $attacksList[$row['did']] = 0;
                        }
                        $attacksList[$row['did']] += $row['attack_count'];
                    }
                }
                $memcache->add("attacksList:{$this->session->getPlayerId()}", $attacksList, 5);
            }
        }
        require SVG_COMMON_TEMPLATES;
        foreach ($villages as $index => $row) {
            $name = $row['name'];
            $attacks = array_key_exists($row['kid'], (array) $attacksList) ? $attacksList[$row['kid']] : 0;
            $x = $y = 0;
            extract(Formulas::kid2xy($row['kid']), EXTR_IF_EXISTS);
            $title = sprintf(
                '%s ‎<span class="coordinates coordinatesWrapper"><span class="coordinateX">(%s</span><span class="coordinatePipe">|</span><span class="coordinateY">%s)</span></span>',
                $name,
                coordinateNumberHTML($x),
                coordinateNumberHTML($y)
            );
            $value = sprintf(
                '‎<span class="coordinates coordinatesWrapper coordinatesAligned coordinates%s"><span class="coordinateX">(%s</span><span class="coordinatePipe">|</span><span class="coordinateY">%s)</span></span>',
                strtolower(getDirection()),
                coordinateNumberHTML($x),
                coordinateNumberHTML($y)
            );
            $active = $row['kid'] == $this->session->getKid() ? 'active' : '';

            $sortId = 'village' . (int) $row['kid'];
            $villageHtml = '<div class="dropContainer" data-sortid="' . $sortId . '" data-sortindex="' . $index . '">';
            $villageHtml .=
                '<div class="listEntry village ' .
                $active .
                ($attacks ? ' attack' : '') .
                '" data-did="' .
                (int) $row['kid'] .
                '" data-sortid="' .
                $sortId .
                '">';
            $villageHtml .= '<div class="dragAndDrop preventMobileSwipeNavigation" data-sortid="' . $sortId . '">';
            $villageHtml .= $svg['village_drag_n_drop'];
            $villageHtml .= '</div>';
            $linkHref = $active === 'active'
                ? '#'
                : $villageSwitchPath . '?newdid=' .
                (int) $row['kid'] .
                ($villageSwitchQuery !== '' ? '&' . $villageSwitchQuery : '');
            $villageHtml .= '<a href="' . htmlspecialchars($linkHref, ENT_QUOTES, 'UTF-8') . '" class="' . $active . '"';
            if ($active !== 'active') {
                $villageHtml .= ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"';
            }
            $villageHtml .= '>';
            $villageHtml .= '<div class="iconAndNameWrapper">';
            $incomingTooltipData = json_encode(['villageIds' => [(int) $row['kid']]], JSON_THROW_ON_ERROR);
            $villageHtml .= '<span class="incomingTroops" data-id="' . (int) $row['kid'] . '" data-load-tooltip="incomingTroops" data-load-tooltip-data="'
                . htmlspecialchars($incomingTooltipData, ENT_QUOTES, 'UTF-8') . '">';
            $villageHtml .= '<svg viewBox="0 0 20 19.06" class="attack">';
            $villageHtml .= $svg['village_incomming_attack'];
            $villageHtml .= '</svg>';
            $villageHtml .= '</span>';
            $villageHtml .= '<span class="name" data-did="' . (int) $row['kid'] . '">' . stored_name_html((string)$name) . '</span>';
            $villageHtml .= '</div>';
            $villageHtml .= '</a>';
            $villageHtml .= '<span class="coordinatesGrid">' . $value . '</span>';
            $villageHtml .= '</div>';
            $villageHtml .= '</div>';
            $villageRows[(int) $row['kid']] = $villageHtml;
        }
        foreach ($villageGroups as $group) {
            $groupId = (int) $group['id'];
            $groupRows = '';
            foreach ($group['villageIds'] as $kid) {
                if (isset($villageRows[$kid])) {
                    $groupRows .= $villageRows[$kid];
                    unset($villageRows[$kid]);
                }
            }
            $groupName = htmlspecialchars((string) $group['name'], ENT_QUOTES, 'UTF-8');
            $groupColor = htmlspecialchars((string) $group['color'], ENT_QUOTES, 'UTF-8');
            $expanded = PreferencesHelper::getPreference('villageListGroup' . $groupId . '_collapsed') ? 'collapsed' : 'expanded';
            $data['villageGroups'] .=
                '<div class="dropContainer villageGroupHeader" data-sortid="group' . $groupId . '" data-sortlevel="0">';
            $data['villageGroups'] .=
                '<div class="listEntry group ' .
                $groupColor .
                ' ' .
                $expanded .
                '" data-groupid="' .
                $groupId .
                '" data-color="' .
                $groupColor .
                '" data-sortid="group' .
                $groupId .
                '">';
            $data['villageGroups'] .=
                '<div class="dragAndDrop preventMobileSwipeNavigation" data-sortid="group' .
                $groupId .
                '">' .
                $svg['village_drag_n_drop'] .
                '</div>';
            $data['villageGroups'] .=
                '<div class="iconAndNameWrapper"><span class="name" data-group-name>' . $groupName . '</span></div>';
            $data['villageGroups'] .= '<div class="actions">';
            $data['villageGroups'] .=
                '<button type="button" class="secondaryIconButton villageGroupEdit" aria-label="Edit group"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 14.8V17h2.2L16 6.2 13.8 4 3 14.8zm14.7-9.3c.4-.4.4-1.1 0-1.5l-1.7-1.7a1.1 1.1 0 0 0-1.5 0l-1.3 1.3 2.2 2.2 1.3-1.3z"></path></svg></button>';
            $data['villageGroups'] .=
                '<button type="button" class="secondaryIconButton villageGroupToggle" aria-label="Toggle group"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7l5 5 5-5z"></path></svg></button>';
            $data['villageGroups'] .= '</div></div></div>';
            $data['villageGroups'] .=
                '<div class="groupWrapper ' .
                $expanded .
                '" data-group-wrapper="' .
                $groupId .
                '"><div class="groupContainer"><div class="dropContainer firstVillage" data-groupid="' .
                $groupId .
                '">' .
                $groupRows .
                '</div></div></div>';
        }
        $data['villages'] = implode('', $villageRows);
        $this->vars['villageBoxes'] .= PHPBatchView::render('layout/sidebarBoxVillageList', $data);
    }

    /**
     * Without Plus the reference shows the four default quick links locked (gold, no
     * customisation, no village-list slots); the buttons open the Plus dialog.
     *
     * @return list<array<string, mixed>>
     */
    private function lockedQuickLinks(): array
    {
        $actions = [];
        foreach ($this->getQuickLinkActions() as $action) {
            $actions[$action['key']] = $action;
        }
        $locked = [];
        foreach ((array) json_decode(PreferencesHelper::DEFAULT_VILLAGE_QUICK_LINKS, true) as $key) {
            if (isset($actions[$key])) {
                $locked[] = ['key' => $key, 'svg' => $actions[$key]['svg'], 'disabled' => $actions[$key]['disabled']];
            }
        }

        return $locked;
    }

    /**
     * Return the reference quick-link catalog for the currently selected village.
     * The selection is a player preference; availability and destinations are
     * deliberately resolved for the active village on every page render.
     */
    private function getQuickLinkActions(): array
    {
        global $svg;

        $village = Village::getInstance();
        $hasGold = $this->session->hasGoldClub();
        // Reference href suffixes per quick-link type (`/build.php?gid=<gid><suffix>`).
        $suffixes = [
            'ExpansionTrain' => '&s=1',
            'MarketplaceBuy' => '&t=1',
            'MarketplaceSendResources' => '&t=5',
            'MarketplaceTradeRoutes' => '&t=3',
            'RallyPointFarmList' => '&tt=99',
            'RallyPointOverview' => '&tt=1',
            'RallyPointSendTroops' => '&tt=2',
            'TownHallCelebration' => '&t=1',
        ];
        $goldTypes = ['MarketplaceTradeRoutes', 'RallyPointFarmList'];
        $gidIfBuilt = static fn (int $gid): int => (int) $village->findBuildingByGid($gid) > 0 ? $gid : 0;
        $firstBuiltGid = static function (array $gids) use ($gidIfBuilt): int {
            foreach ($gids as $gid) {
                if ($gidIfBuilt($gid) > 0) {
                    return $gid;
                }
            }

            return 0;
        };
        $buildingTitle = static fn (int $gid): string => T('Buildings', $gid . '.title');
        $managementTab = T('RallyPoint', 'Management');
        $action = static function (string $type, string $title, string $tab, int $gid) use ($svg, $suffixes, $goldTypes, $hasGold): array {
            $available = $gid > 0;
            $goldLocked = in_array($type, $goldTypes, true) && !$hasGold;

            return [
                'key' => $type,
                'title' => $title,
                'tab' => $tab,
                'icon' => $type,
                'svg' => $svg['quickLink_' . $type] ?? '',
                'color' => $goldLocked ? 'gold' : 'green',
                'disabled' => !$available,
                'href' => $available ? '/build.php?gid=' . $gid . ($suffixes[$type] ?? '') : '#',
                'tooltip' => $available && !$goldLocked,
                'tooltipTitle' => QuickLink::title($type, $gid),
                'notAvailable' => T('quickLinks', 'notAvailable'),
            ];
        };

        $expansionGid = $firstBuiltGid([25, 26, 44]);
        $hospitalGid = $firstBuiltGid([46, 48]);
        // Disabled dialog buttons still need a real building label. Spartans use
        // an Asclepeion; every other player tribe uses a Hospital.
        $hospitalTitleGid = $hospitalGid ?: ((int) $this->session->getRace() === 8 ? 48 : 46);
        $expansionTitleGid = $expansionGid ?: 26;

        return [
            $action('BlacksmithManagement', $buildingTitle(13), $managementTab, $gidIfBuilt(13)),
            $action('RallyPointOverview', $buildingTitle(16), T('RallyPoint', 'overview'), $gidIfBuilt(16)),
            $action('RallyPointSendTroops', $buildingTitle(16), T('RallyPoint', 'sendTroops'), $gidIfBuilt(16)),
            $action('RallyPointFarmList', $buildingTitle(16), T('RallyPoint', 'farmlist'), $gidIfBuilt(16)),
            $action('MarketplaceSendResources', $buildingTitle(17), T('MarketPlace', 'SendResources'), $gidIfBuilt(17)),
            $action('MarketplaceTradeRoutes', $buildingTitle(17), T('MarketPlace', 'Trade routes'), $gidIfBuilt(17)),
            $action('MarketplaceBuy', $buildingTitle(17), T('MarketPlace', 'Buy'), $gidIfBuilt(17)),
            $action('BarracksManagement', $buildingTitle(19), $managementTab, $gidIfBuilt(19)),
            $action('GreatBarracksManagement', $buildingTitle(29), $managementTab, $gidIfBuilt(29)),
            $action('StableManagement', $buildingTitle(20), $managementTab, $gidIfBuilt(20)),
            $action('GreatStableManagement', $buildingTitle(30), $managementTab, $gidIfBuilt(30)),
            $action('WorkshopManagement', $buildingTitle(21), $managementTab, $gidIfBuilt(21)),
            $action('TownHallCelebration', $buildingTitle(24), 'Celebration', $gidIfBuilt(24)),
            $action('TrapperManagement', $buildingTitle(36), $managementTab, $gidIfBuilt(36)),
            $action('HerosMansionManagement', $buildingTitle(37), $managementTab, $gidIfBuilt(37)),
            $action('HospitalManagement', $buildingTitle($hospitalTitleGid), $managementTab, $hospitalGid),
            $action('ExpansionTrain', $buildingTitle($expansionTitleGid), T('ResidencePalace', 'Train'), $expansionGid),
        ];
    }

    private function renderQuest()
    {
        $tasksModel = new TasksModel();
        $playerId = (int) $this->session->getPlayerId();
        $villageId = (int) $this->session->getKid();
        $hasTaskReward = $tasksModel->hasAvailableReward($playerId, $villageId);
        $view = new PHPBatchView('layout/sidebarBoxQuestmaster');
        $view->vars['hasNewReward'] = $hasTaskReward;
        $this->vars['sidebarAfterContent'] .= $view->output();
    }
}
