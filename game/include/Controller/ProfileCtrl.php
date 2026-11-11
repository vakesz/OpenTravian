<?php

namespace Controller;

use Core\Caching\ProfileCache;
use Core\Config;
use Core\Database\DB;
use Core\Helper\BBCode;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\ProfileLanguages;
use Core\Locale;
use Model\AutomationModel;
use Model\ClubApi;
use Model\FarmListModel;
use Model\MapPageModel;
use Model\PlayerNote;
use Model\ProfileModel;
use Model\HeroModel;
use Model\StatisticsModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

use function getDisplay;

class ProfileCtrl extends GameCtrl
{
    /** @var mixed */
    private $selectedPlayerID;
    /** @var bool */
    private $isMe = false;
    /** @var array<mixed> */
    private $selectedPlayerData = [];

    public function __construct()
    {
        parent::__construct();
        $this->selectedPlayerID = isset($_REQUEST['uid'])
            ? (int) $_REQUEST['uid']
            : Session::getInstance()->getPlayerId();
        $this->view = new GameView();
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $this->view->newVillagePrefix['uid'] = $this->selectedPlayerID;
        $m = new ProfileModel();
        $this->selectedPlayerData = $m->getPlayer(
            $this->selectedPlayerID,
            'aid, access, email_verified, countryFlag, additionalLanguages, showMedals, profileCacheVersion, showCountryFlag, email, name, race, desc1, desc2, gender, birthday, location, protection'
        );
        if (!sizeof($this->selectedPlayerData)) {
            $this->view->vars['bodyCssClass'] = 'playerProfile';
            $this->view->vars['contentCssClass'] = 'playerProfile playerProfileOverview';
            return;
        }
        // Reference profile h1 is the player name (own and others), not "Player Profile".
        $this->view->vars['titleInHeader'] = $this->selectedPlayerData['name'];
        if ($this->selectedPlayerID == Session::getInstance()->getPlayerId()) {
            $this->isMe = true;
        }
        $selectedTab = isset($_REQUEST['s']) && in_array((int) $_REQUEST['s'], [1, 2], true) ? (int) $_REQUEST['s'] : 1;
        if ((!$this->isMe || Session::getInstance()->isSitter()) && $selectedTab === 2) {
            $selectedTab = 1;
        }
        // shell tokens: body.playerProfile + #content.playerProfile.playerProfileOverview|Edit.
        $this->view->vars['bodyCssClass'] = 'playerProfile';
        $this->view->vars['contentCssClass'] =
            'playerProfile ' . ($selectedTab === 2 ? 'playerProfileEdit' : 'playerProfileOverview');

        // Reference shell: div#playerProfile.contentV2 > div > (tab strip + spacer) > page body.
        $this->view->vars['content'] .= '<div id="playerProfile" class="contentV2"><div>';
        if ($this->isMe && !Session::getInstance()->isSitter()) {
            $this->view->vars['content'] .= '<div>';
            $view = new PHPBatchView('profile/menu');
            $view->vars['selectedTab'] = $selectedTab;
            $view->vars['buttonId1'] = get_button_id();
            $view->vars['buttonId2'] = get_button_id();
            $view->vars['banned'] = Session::getInstance()->banned();
            $this->view->vars['content'] .= $view->output();
            $this->view->vars['content'] .= '<div class="navigationSpacer"></div></div>';
        }
        if ($selectedTab === 1) {
            $this->Profile();
        }
        if ($selectedTab === 2) {
            $this->EditProfile();
        }
        $this->view->vars['content'] .= '</div></div>';
    }

    /**
     * @param float|int|string|null $oasisType
     */
    private function getOasisId($oasisType): int
    {
        $list = [
            2 => 1,
            3 => 2,
            4 => 3,

            6 => 4,
            7 => 5,
            8 => 6,

            10 => 7,
            11 => 8,
            12 => 9,

            14 => 10,
            15 => 11,
        ];
        return $list[$oasisType];
    }

    private function EditProfile(): void
    {
        if (Session::getInstance()->banned()) {
            return;
        }
        $cache = new ProfileCache($this->selectedPlayerData['profileCacheVersion']);
        $view = new PHPBatchView('profile/EditProfile');
        $view->vars['uid'] = $this->selectedPlayerID;
        $view->vars['kid'] = Village::getInstance()->getKid();
        $view->vars['gender'] = $this->selectedPlayerData['gender'];
        $view->vars['desc1'] = trim($this->selectedPlayerData['desc1']);
        $view->vars['desc2'] = trim($this->selectedPlayerData['desc2']);
        $view->vars['showCountryFlag'] = $this->selectedPlayerData['showCountryFlag'];
        $view->vars['showMedals'] = $this->selectedPlayerData['showMedals'];
        $view->vars['medals'] = '';
        $view->vars['medals2'] = '';
        $db = DB::getInstance();
        if ($_cache = $cache->getProfileMedals($this->selectedPlayerID)) {
            $view->vars['medals'] = $_cache;
            goto afterMedals;
        }
        $medals = $db->query("SELECT * FROM medal WHERE uid={$this->selectedPlayerID} ORDER BY week, category");
        $view->vars['medals'] .= '<tr>';
        $view->vars['medals'] .= '<td class="typ">' . T('Profile', 'DoveOfPeace') . '</td>';
        $view->vars['medals'] .= '<td class="ra"></td>';
        $view->vars['medals'] .= '<td class="we"></td>';
        $view->vars['medals'] .= '<td class="bb">[#0]</td>';
        $view->vars['medals'] .= '</tr>';
        while ($row = $medals->fetch_assoc()) {
            $view->vars['medals'] .= '<tr>';
            $view->vars['medals'] .= '<td class="typ">' . BBCode::getMedalCategory($row['category']) . '</td>';
            $view->vars['medals'] .= '<td class="ra">' . $row['rank'] . '</td>';
            $view->vars['medals'] .= '<td class="we">' . $row['week'] . '</td>';
            $view->vars['medals'] .= '<td class="bb">[#' . $row['id'] . ']</td>';
            $view->vars['medals'] .= '</tr>';
        }
        $cache->setProfileMedals($this->selectedPlayerID, $view->vars['medals']);
        afterMedals:
        $view->vars['medals2'] = null;
        if ($this->selectedPlayerData['email_verified']) {
            $medals = ClubApi::getPlayerEmailMedals(Session::getInstance()->getEmail(), false);
            while ($row = $medals->fetch_assoc()) {
                if ($row['type'] >= 16) {
                    continue;
                }
                $view->vars['medals2'] .= '<tr>';
                $view->vars['medals2'] .= '<td class="typ">' . BBCode::getMedal2Category($row) . '</td>';
                $view->vars['medals2'] .= '<td class="bb" style="text-align: center;">[c' . $row['id'] . ']</td>';
                $view->vars['medals2'] .= '</tr>';
            }
        }
        $view->vars['villages'] = '';
        $m = new ProfileModel();
        $direction = strtolower(getDirection());
        $race = (int) Session::getInstance()->getRace();
        $tabIndex = 6;
        $villages = $cache->getProfileEditVillages($this->selectedPlayerID);
        foreach ($villages as $row) {
            $xy = Formulas::kid2xy($row['kid']);
            $extra = [];
            if (!empty($row['isWW'])) {
                $extra[] = T('Profile', 'WoW');
            }
            if ($m->isThereAnArtifact($row['kid'])) {
                $extra[] = T('Profile', 'Artifact');
            }
            if (!empty($row['capital'])) {
                $extra[] = T('Profile', 'capital');
            }
            $extraLabel =
                $extra !== []
                    ? '<span class="additionalInfo" title="">(' .
                        htmlspecialchars(implode(', ', $extra), ENT_QUOTES, 'UTF-8') .
                        ')</span>'
                    : '';
            $oasesHtml = '';
            $oases = $m->getVillageOases($row['kid']);
            while ($o = $oases->fetch_assoc()) {
                $oasesHtml .= '<i class="r' . $this->getOasisId($o['type']) . '"></i>';
            }
            $safeName = htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8');
            $kid = (int) $row['kid'];
            $view->vars['villages'] .=
                '<tr>' .
                '<td class="tribe"><i class="tribe' .
                $race .
                '_medium"></i></td>' .
                '<td class="name"><label class="input fixed"><input tabindex="' .
                $tabIndex++ .
                '" maxlength="20" type="text" value="' .
                $safeName .
                '" name="village' .
                $kid .
                '" data-village-id="' .
                $kid .
                '"></label></td>' .
                '<td class="additionalInfoColumn">' .
                $extraLabel .
                '</td>' .
                '<td class="oases"><div>' .
                $oasesHtml .
                '</div></td>' .
                '<td class="inhabitants">' .
                (int) $row['pop'] .
                '</td>' .
                '<td class="coordinates"><a href="/karte.php?x=' .
                $xy['x'] .
                '&amp;y=' .
                $xy['y'] .
                '" data-load-tooltip="distance" data-load-tooltip-data="{&quot;x&quot;:' .
                $xy['x'] .
                ',&quot;y&quot;:' .
                $xy['y'] .
                '}">‭<span class="coordinates coordinatesWrapper coordinatesAligned coordinates' .
                $direction .
                '"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>‬</a></td>' .
                '</tr>';
        }
        $view->vars['location'] = (string) $this->selectedPlayerData['location'];
        $view->vars['countryFlag'] = strtolower((string) ($this->selectedPlayerData['countryFlag'] ?? ''));
        $language = $this->session->getLanguage();
        $availableLanguages = Config::getProperty('settings', 'availableLanguages');
        $profileLocale = isset($availableLanguages->{$language}->locale)
            ? (string)$availableLanguages->{$language}->locale
            : 'en-US';
        $view->vars['additionalLanguages'] = ProfileLanguages::fromStorage(
            (string)($this->selectedPlayerData['additionalLanguages'] ?? '[]'),
            $profileLocale
        );
        $view->vars['lifetimeMedals'] = $this->getLifetimeMedalsForEdit();
        $view->vars['desc1Len'] = mb_strlen($view->vars['desc1']);
        $view->vars['desc2Len'] = mb_strlen($view->vars['desc2']);
        $this->view->vars['content'] .= $view->output();
    }

    private function getLifetimeMedalsForEdit(): string
    {
        if (!$this->selectedPlayerData['email_verified']) {
            return '';
        }
        $html = '';
        $gpackBaseUrl = htmlspecialchars(get_gpack_cdn_base_url(), ENT_QUOTES, 'UTF-8');
        $img = [
            1 => 'ww-gold.png',
            2 => 'ww-silber.png',
            3 => 'ww-silber.png',
            4 => 'off1.png',
            5 => 'off2.png',
            6 => 'off3.png',
            7 => 'off4.png',
            8 => 'def1.png',
            9 => 'def2.png',
            10 => 'def3.png',
            11 => 'def4.png',
            12 => 'pop1.png',
            13 => 'pop2.png',
            14 => 'pop3.png',
            15 => 'pop4.png',
        ];
        $medals = ClubApi::getPlayerEmailMedals(Session::getInstance()->getEmail(), true);
        $byType = [];
        while ($row = $medals->fetch_assoc()) {
            $type = (int) $row['type'];
            if (!isset($img[$type])) {
                continue;
            }
            $byType[$type] = true;
        }
        ksort($byType);
        foreach (array_keys($byType) as $type) {
            $html .=
                '<div class="medal"><img class="medalGloria" width="53" alt="medal" src="' .
                $gpackBaseUrl .
                'gloria/' .
                $img[$type] .
                '"></div>';
        }
        return $html;
    }

    /**
     * @return string|null
     */
    private function getSecondMedals()
    {
        $HTML = null;
        if (!getDisplay('includeHiddenMedals') || !$this->selectedPlayerData['showMedals']) {
            return $HTML;
        }
        if (!$this->selectedPlayerData['email_verified']) {
            return $HTML;
        }
        $HTML .= '<style type="text/css">img.medalGloria{max-width: 58px;}</style>';
        $HTML .= '<h4 class="round">' . T('Profile', 'Player special medals') . '</h4>';
        $HTML .= '<div style="text-align: center">';
        $medals = ClubApi::getPlayerEmailMedals($this->selectedPlayerData['email'], true);
        if (!$medals->num_rows) {
            return null;
        }
        $medalsToShow = [];
        while ($row = $medals->fetch_assoc()) {
            $row['params'] = explode('|', $row['params']);
            $medalsToShow[$row['type']][] = [
                'params' => $row['params'],
                'worldId' => $row['worldId'],
                'nickname' => $row['nickname'],
                'tribe' => $row['tribe'],
                'points' => $row['params'][1],
            ];
        }
        ksort($medalsToShow);
        $img_travian = [
            1 => 'ww-gold.png',
            2 => 'ww-silber.png',
            3 => 'ww-silber.png',

            4 => 'off1.png',
            5 => 'off2.png',
            6 => 'off3.png',
            7 => 'off4.png',

            8 => 'def1.png',
            9 => 'def2.png',
            10 => 'def3.png',
            11 => 'def4.png',

            12 => 'pop1.png',
            13 => 'pop2.png',
            14 => 'pop3.png',
            15 => 'pop4.png',

            16 => '', //big off hammer
            17 => '', //big def hammer
            18 => '', //winner alliance
        ];

        $img = $img_travian;
        $gpackBaseUrl = htmlspecialchars(get_gpack_cdn_base_url(), ENT_QUOTES, 'UTF-8');
        $count = 0;
        foreach ($medalsToShow as $type => $values) {
            if (!isset($img[$type]) || empty($img[$type])) {
                continue;
            }
            $title = '';
            foreach ($values as $val) {
                ++$count;
                switch ($type) {
                    case 1:
                    case 2:
                    case 3:
                        $array = [
                            $val['worldId'],
                            $val['nickname'],
                            T('Global', "races.{$val['tribe']}"),
                            $val['points'],
                        ];
                        break;
                    case 4:
                    case 5:
                    case 6:
                    case 7:
                    case 8:
                    case 9:
                    case 10:
                    case 11:
                    case 12:
                    case 13:
                    case 14:
                    case 15:
                    case 16: //big off hammer
                    case 17: //big def hammer
                        $array = [
                            $val['worldId'],
                            $val['nickname'],
                            T('Global', "races.{$val['tribe']}"),
                            number_format_x($val['points']),
                        ];
                        break;
                    case 18: //winner alliance
                        $array = [
                            $val['worldId'],
                            $val['nickname'],
                            T('Global', "races.{$val['tribe']}"),
                            $val['params'][1],
                        ];
                        break;
                }
                if (!empty($title)) {
                    $title .= '<br />';
                }
                $title .= vsprintf(T('Profile', "mySpeicalM.{$type}"), $array);
            }
            $title = htmlspecialchars(
                '<span class="gloriatitle">' .
                    T('Profile', "mySpeicalMTitle.$type") .
                    '</span><div class="gloriacontent">' .
                    $title .
                    '</div>'
            );
            $HTML .=
                '<img class="medalGloria" src="' .
                $gpackBaseUrl .
                'gloria/' .
                $img[$type] .
                '" alt="' .
                $title .
                '" title="' .
                $title .
                '">&nbsp;&nbsp;';
            $title = null;
        }
        if ($count <= 0) {
            return null;
        }
        $HTML .= '</div>';
        return $HTML;
    }

    /**
     * Equipment slots for the profile hero panel, in the reference's slot order.
     *
     * @param array<string,int> $equipped slot => items.type (0 when empty)
     * @return list<array{slot:string,type:int,tier:int,title:string}>
     */
    private function getHeroEquipmentSlots(array $equipped): array
    {
        $heroItems = new \Game\Hero\HeroItems();
        $btypes = ['helmet' => 1, 'body' => 2, 'leftHand' => 3, 'rightHand' => 4, 'shoes' => 5, 'horse' => 6];
        $slots = [];
        foreach (['helmet', 'body', 'shoes', 'leftHand', 'rightHand', 'horse'] as $slot) {
            $type = (int) ($equipped[$slot] ?? 0);
            $tier = 0;
            $title = '';
            if ($type > 0) {
                // Item tiers run in groups of three consecutive types (horses: 103-105).
                $tier = $slot === 'horse' ? $type - 102 : (($type - 1) % 3) + 1;
                $tier = max(1, min(3, $tier));
                $title = (string) ($heroItems->getHeroItemProperties($btypes[$slot], $type)['name'] ?? '');
            }
            $slots[] = ['slot' => $slot, 'type' => $type, 'tier' => $tier, 'title' => $title];
        }
        return $slots;
    }

    private function Profile(): void
    {
        if ($this->selectedPlayerID == 0) {
            $view = new PHPBatchView('profile/Support');
            $this->view->vars['content'] .= $view->output();
            return;
        }
        $m = new ProfileModel();
        $h = new HeroModel();
        $view = new PHPBatchView('profile/Profile');
        $statistics = new StatisticsModel();
        $view->vars['race'] = $this->selectedPlayerData['race'];
        $view->vars['uid'] = $this->selectedPlayerID;
        $status = $m->getPlayerTotalVillagesAndPop($this->selectedPlayerID);
        $points = $m->getPlayer($this->selectedPlayerID, 'total_defense_points,total_attack_points');
        $view->vars['total_villages'] = $status['village_count'];
        $view->vars['rank_pop'] = $statistics->getPlayerRankById($this->selectedPlayerID);
        $view->vars['total_pop'] = $status['total_pop'];
        $view->vars['rank_att'] = $statistics->getPlayersPointsById($this->selectedPlayerID, false);
        $view->vars['rank_def'] = $statistics->getPlayersPointsById($this->selectedPlayerID, true);
        $view->vars['total_att'] = $points['total_attack_points'];
        $view->vars['total_def'] = $points['total_defense_points'];
        $heroData = $h->getHero($this->selectedPlayerID);
        $view->vars['hero_lvl'] = Formulas::heroLevel($heroData['exp']);
        $view->vars['hero_exp'] = number_format_x($heroData['exp']);
        $view->vars['location'] = $this->selectedPlayerData['location'];
        $view->vars['language'] =
            $this->selectedPlayerData['showCountryFlag'] == 1 ? $this->selectedPlayerData['countryFlag'] : null;
        $view->vars['gender'] = $this->selectedPlayerData['gender'];
        $view->vars['alliance'] = '-';
        $view->vars['specialMedals'] = $this->getSecondMedals();
        $view->vars['medals2'] = '';
        $view->vars['showNote'] = !$this->isMe;
        if ($view->vars['showNote']) {
            $view->vars['note'] = PlayerNote::getPlayerNote(
                Session::getInstance()->getPlayerId(),
                $this->selectedPlayerID
            );
        }
        $view->vars['isAdmin'] = Session::getInstance()->isAdmin();
        if ($this->selectedPlayerData['aid'] != 0) {
            $db = DB::getInstance();
            $tag = $db->fetchScalar('SELECT tag FROM alidata WHERE id=' . $this->selectedPlayerData['aid']);
            if ($tag) {
                $view->vars['alliance'] =
                    '<a class="lightColor" href="' .
                    allianceUrl((int) $this->selectedPlayerData['aid']) .
                    '">' .
                    $tag .
                    '</a>';
            }
        }
        if ($this->selectedPlayerData['birthday'] != 0) {
            $currentYear = TimezoneHelper::date('Y', null, true);
            $currentMonth = TimezoneHelper::date('m', null, true);
            $currentDay = TimezoneHelper::date('d', null, true);
            $age = (int) $currentYear - (int) substr($this->selectedPlayerData['birthday'], 0, 4);
            if ((int) $currentMonth - (int) substr($this->selectedPlayerData['birthday'], 5, 2) < 0) {
                $age--;
            } elseif ((int) $currentMonth - (int) substr($this->selectedPlayerData['birthday'], 5, 2) == 0) {
                if ($currentDay < (int) substr($this->selectedPlayerData['birthday'], 8, 2)) {
                    $age--;
                }
            }
            if ($age > 0) {
                $view->vars['age'] = $age;
            }
        }
        $view->vars['villages'] = '';
        $view->vars['isMe'] = $this->isMe;
        $view->vars['daysPlaying'] = $m->getDaysPlaying($this->selectedPlayerID);
        $view->vars['heroGender'] = $m->getHeroGender($this->selectedPlayerID);
        $view->vars['heroEquipment'] = $this->getHeroEquipmentSlots($m->getEquippedHeroItemTypes($this->selectedPlayerID));
        list($view->vars['desc1'], $view->vars['desc2']) = BBCode::BBCodeProfile(
            $this->selectedPlayerData['desc1'],
            $this->selectedPlayerData['desc2'],
            false,
            $this->selectedPlayerID
        );
        if ($this->selectedPlayerData['access'] == 0 && $this->selectedPlayerID > 2) {
            $view->vars['desc1'] = $view->vars['desc2'] = T('Profile', 'This player messages are banned');
        }
        $cache = new ProfileCache($this->selectedPlayerData['profileCacheVersion']);
        $farmList = new FarmListModel();
        $count = 0;
        $villages = $cache->getProfileVillages($this->selectedPlayerID);
        $direction = strtolower(getDirection());
        foreach ($villages as $row) {
            $isThereArtifact = $m->isThereAnArtifact($row['kid']);
            $count++;
            if (isset($_GET['artifactOnly']) && !$isThereArtifact) {
                continue;
            }
            $view->vars['villages'] .= '<tr>';
            $view->vars['villages'] .=
                '<td class="tribe"><i class="tribe' . (int) $this->selectedPlayerData['race'] . '_medium"></i></td>';
            $view->vars['villages'] .=
                '<td class="name"><a href="/karte.php?d=' . $row['kid'] . '">' . $row['name'] . '</a>';
            foreach (
                [
                    [$row['isWW'], T('Profile', 'WoW')],
                    [$isThereArtifact, T('Profile', 'Artifact')],
                    [$row['capital'], T('Profile', 'capital')],
                ]
                as [$flagged, $label]
            ) {
                if ($flagged) {
                    $view->vars['villages'] .= '<span class="additionalInfo">(' . $label . ')</span>';
                }
            }
            $xy = Formulas::kid2xy($row['kid']);
            $view->vars['villages'] .= '</td>';

            $view->vars['villages'] .= '<td class="oases"><div>';
            $oases = $m->getVillageOases($row['kid']);
            while ($o = $oases->fetch_assoc()) {
                $view->vars['villages'] .= '<i class="r' . $this->getOasisId($o['type']) . '"></i>';
            }
            $view->vars['villages'] .= '</div></td>';
            $view->vars['villages'] .= '<td class="inhabitants">' . $row['pop'] . '</td>';
            $view->vars['villages'] .=
                '<td class="coordinates"><a class="" href="/karte.php?x=' .
                $xy['x'] .
                '&y=' .
                $xy['y'] .
                '">‭<span class="coordinates coordinatesWrapper coordinatesAligned coordinates' .
                $direction .
                '"><span class="coordinateX">(' . coordinateNumberHTML($xy['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($xy['y']) . ')</span></span>‬</a></td>';
            $view->vars['villages'] .= '</tr>';
        }
        afterVillages:
        $this->view->vars['content'] .= $view->output();
    }
}
