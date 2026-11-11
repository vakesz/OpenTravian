<?php

namespace Core\Helper;

use Controller\ReportsCtrl;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Game\Formulas;
use Core\Locale;
use function get_gpack_cdn_base_url;
use function is_numeric;
use Model\AllianceModel;
use Model\ClubApi;
use function preg_match;
use function preg_match_all;
use function preg_replace;

class MedalsBBCode
{
    /** @var array<mixed> */
    public  $data      = [];
    /** @var array<mixed> */
    private $processed = [];

    /**
     * @param mixed $matches
     * @return mixed
     */
    public function BBCodeMedals($matches)
    {
        $db = DB::getInstance();
        $key = BBCode::plain($matches[1]);
        if (isset($this->processed[$key])) {
            return $matches[0];
        }
        $this->processed[$key] = TRUE;
        if (strtolower($key) == 'natars') {
            if (!$this->data['isAlliance'] && $this->data['uid'] == 1) {
                $HTML = T("Global", "NatarsName");
                $HTML .= '<br>';
                $HTML .= '=======================';
                $HTML .= '<br>';
                $HTML .= '<img src="/img/world-wonder-100.png" style="width:60%" border="0" alt="' . T("Buildings",
                        "40.title") . '">';
                $HTML .= '<br>';
                $HTML .= '=======================';
                return $HTML;
            }
            return $matches[0];
        } else if (!$this->data['isAlliance'] && $key == '0') {
            $row = $db->query("SELECT signupTime, protection FROM users WHERE id={$this->data['uid']}")->fetch_assoc();
            $this->data['signupTime'] = $row['signupTime'];
            $this->data['protection'] = $row['protection'];
            $hasProtection = $this->data['protection'] > time();
            $date = TimezoneHelper::autoDateString($hasProtection ? $this->data['protection'] : $this->data['signupTime'],
                TRUE);
            return '<img class="tn' . ($hasProtection ? '' : 'd') . '" title="' . sprintf(T("BBCode",
                    $hasProtection ? 'this player is under protection to x' : 'this player is registered at x'),
                    $date) . '" alt="" src="img/spacer.gif">';
        }
        $key = (int)$key;
        if ($this->data['isAlliance']) {
            $find = $db->query("SELECT * FROM allimedal WHERE aid={$this->data['aid']} AND id={$key}");
        } else {
            $find = $db->query("SELECT * FROM medal WHERE uid={$this->data['uid']} AND id={$key}");
        }
        if (!$find->num_rows) {
            return $matches[0];
        }
        $find = $find->fetch_assoc();
        if ($find['category'] <= 4) {
            $title = T("Medals", "Category") . ': ' . T("Medals", 'names.' . $find['category']);
            $title .= '<br />' . T("Medals", "Week") . ': ' . $find['week'];
            $title .= '<br />' . T("Medals", "Rank") . ': ' . $find['rank'];
            $title .= '<br />' . ($find['category'] == 4 ? T("Medals", "Resources") : T("Medals",
                    "Points")) . ': ' . number_format_x($find['points']);
        } else {
            $title = sprintf(T("Medals", "category" . $find['category']), $find['points']);
            $title .= '<br /> <br />' . T("Medals", "Received in week:") . $find['week'];
        }
        return '<img class="medal ' . $find['img'] . '" src="img/spacer.gif" alt="' . $title . '" title="' . $title . '">';
    }

    /**
     * @param mixed $matches
     * @return mixed
     */
    public function BBCodeMedals2($matches)
    {
        if ($this->data['isAlliance']) {
            return $matches[0];
        }
        $key = (int)$matches[1];
        if (isset($this->processed[$key])) {
            return $matches[0];
        }
        $this->processed[$key] = TRUE;
        $email = DB::getInstance()->fetchScalar("SELECT email FROM users WHERE id={$this->data['uid']} LIMIT 1");
        $find = ClubApi::getPlayerEmailMedalById($email, (int)$key);
        if (!$find->num_rows) {
            return $matches[0];
        }
        $find = $find->fetch_assoc();
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

            16 => '', //big off hammer
            17 => '', //big def hammer
            18 => '', //winner alliance
        ];
        if (!isset($img[$find['type']]) || empty($img[$find['type']])) {
            return null;
        }
        $find['params'] = explode("|", $find['params']);
        $array = [];
        switch ($find['type']) {
            case 1:
            case 2:
            case 3:
                $array = [
                    $find['params'][0],
                    $find['worldId'],
                    $find['nickname'],
                    T("Global", "races.{$find['tribe']}"),
                    $find['params'][1],
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
                    $find['params'][0],
                    $find['worldId'],
                    $find['nickname'],
                    T("Global", "races.{$find['tribe']}"),
                    number_format_x($find['params'][1]),
                ];
                break;
            case 18: //winner alliance
                $array = [
                    $find['params'][0],
                    $find['worldId'],
                    $find['nickname'],
                    T("Global", "races.{$find['tribe']}"),
                    $find['params'][1],
                ];
        }
        $title = vsprintf(T("Medals", "SpecialMedals.{$find['type']}"), $array);
        $title = htmlspecialchars($title);
        return '<img class="medalGloria" src="' . get_gpack_cdn_base_url() . '/gloria/' . $img[$find['type']] . '" alt="' . $title . '" title="' . $title . '">';
    }
}

class AllianceBBCode
{
    /** types:
     *  1 => conf with
     *  2 => nap with
     *  3 => war with
     * @var int
     */
    public $aid = 0;

    /**
     * @param mixed $matches
     */
    public function Diplomacy($matches): string
    {
        $HTML = $this->ally();
        $HTML .= $this->nap();
        $HTML .= $this->war();
        return $HTML;
    }

    public function ally(): string
    {
        $HTML = '<p>' . T("BBCode", "confederacies with") . '</p>';
        $db = DB::getInstance();
        $find = $db->query("SELECT * FROM diplomacy WHERE accepted=1 AND type=1 AND (aid1={$this->aid} OR aid2={$this->aid})");
        while ($row = $find->fetch_assoc()) {
            $aid = $row['aid1'] == $this->aid ? $row['aid2'] : $row['aid1'];
            $tag = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$aid}");
            if (!$tag) {
                continue;
            }
            $HTML .= '<a href="' . allianceUrl((int) $aid) . '">' . $tag . '</a><br />';
        }
        return $HTML . '<br />';
    }

    public function nap(): string
    {
        $HTML = '<p>' . T("BBCode", "non-aggression pact(s) with") . '</p>';
        $db = DB::getInstance();
        $find = $db->query("SELECT * FROM diplomacy WHERE accepted=1 AND type=2 AND (aid1={$this->aid} OR aid2={$this->aid})");
        while ($row = $find->fetch_assoc()) {
            $aid = $row['aid1'] == $this->aid ? $row['aid2'] : $row['aid1'];
            $tag = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$aid}");
            if (!$tag) {
                continue;
            }
            $HTML .= '<a href="' . allianceUrl((int) $aid) . '">' . $tag . '</a><br />';
        }
        return $HTML . '<br />';
    }

    public function war(): string
    {
        $HTML = '<p>' . T("BBCode", "at war(s) with") . '</p>';
        $db = DB::getInstance();
        $find = $db->query("SELECT * FROM diplomacy WHERE accepted=1 AND type=3 AND (aid1={$this->aid} OR aid2={$this->aid})");
        while ($row = $find->fetch_assoc()) {
            $aid = $row['aid1'] == $this->aid ? $row['aid2'] : $row['aid1'];
            $tag = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$aid}");
            if (!$tag) {
                continue;
            }
            $HTML .= '<a href="' . allianceUrl((int) $aid) . '">' . $tag . '</a><br />';
        }
        return $HTML . '<br />';
    }

    /**
     * @param mixed $number
     */
    public function forum($number): string
    {
        $number = min(max(1, (int)$number[1]), 99);
        $db = DB::getInstance();
        $HTML = '<div id="allyInfoForum">';
        $HTML .= '<ul><h4 class="chartHeadline">' . T("BBCode", "latest postings on forum") . '</h4>';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $total = $db->fetchScalar("SELECT COUNT(id) FROM forum_post WHERE aid={$this->aid} AND deleted=0");
        $results = $db->query("SELECT * FROM forum_post WHERE aid={$this->aid} AND deleted=0 ORDER BY time DESC, id DESC LIMIT " . (($page - 1) * $number) . "," . $number);
        while ($row = $results->fetch_assoc()) {
            $HTML .= '<li><a href="' . htmlspecialchars(allianceUrl((int) $row['aid'], 'forum', ['tid' => $row['topicId']]), ENT_QUOTES, 'UTF-8') . '">sdsds</a></li>';
        }
        $HTML .= '</ul>';
        $HTML .= '<div id="allyInfoForumPaginator" class="paginator">';
        $prefix = [];
        $nav = new PageNavigator($page, $total, $number, $prefix, "/alliance/overview");
        $HTML .= $nav->get(TRUE);
        $HTML .= '</div>';
        $HTML .= '</div><br />';
        return $HTML;
    }

    /**
     * @param mixed $number
     */
    public function news($number): string
    {
        $number = min(max(1, (int)$number[1]), 99);
        $db = DB::getInstance();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $total = $db->fetchScalar("SELECT COUNT(id) FROM ali_log WHERE aid={$this->aid}");
        // Wraps Alliance events in #allyInfoNews (headline + table + paginator + clear).
        $HTML = '<div id="allyInfoNews">';
        $HTML .= '<h4 class="chartHeadline">' . T("BBCode", "Alliance Events") . '</h4>';
        $HTML .= '<table cellpadding="1" cellspacing="1" class="events">';
        $HTML .= '<tbody>';
        $results = $db->query("SELECT * FROM ali_log WHERE aid={$this->aid} ORDER BY time DESC LIMIT " . (($page - 1) * $number) . "," . $number);
        while ($row = $results->fetch_assoc()) {
            $HTML .= '<tr>';
            $HTML .= '<td class="event">' . BBCode::renderAliNewsEvent($row['data']) . '</td>';
            $HTML .= '<td class="dat">' . TimezoneHelper::reportDateString((int) $row['time']) . '</td>';
            $HTML .= '</tr>';
        }
        $HTML .= '</tbody>';
        $HTML .= '</table>';
        $HTML .= '<div id="allyInfoNewsPaginator" class="paginator">';
        $prefix = [];
        $nav = new PageNavigator($page, $total, $number, $prefix, "/alliance/overview", false, 'bold');
        $HTML .= $nav->get(TRUE);
        $HTML .= '</div>';
        $HTML .= '<div class="clear"></div>';
        $HTML .= '</div>';
        return $HTML;
    }

    public function StrengthOfOwnAlliance(): string
    {
        //The strength of the own alliance will show you the sum of the attack points as well as sum of the defense points of your alliance over the last 7 days (if you go with the mouse over the different bars, you will see the concrete numbers). Basically, this graph shows you the military strength of your alliance.
        $db = DB::getInstance();
        $stats = $db->query("SELECT * FROM alistats WHERE aid={$this->aid} ORDER BY time DESC LIMIT 7");
        $HTML = '<h4 class="chartHeadline">' . T("BBCode", "Strength of own alliance") . '</h4>';
        $dates = $bars = '';
        $maxAttackPoint = $db->fetchScalar("SELECT MAX(total_off_point) FROM alistats WHERE aid={$this->aid} ORDER BY time DESC LIMIT 7");
        $maxDefensePoint = $db->fetchScalar("SELECT MAX(total_def_point) FROM alistats WHERE aid={$this->aid} ORDER BY time DESC LIMIT 7");
        $results = [];
        while ($row = $stats->fetch_assoc()) {
            $results[] = $row;
        }
        for ($i = 6; $i >= 0; --$i) {
            if (isset($results[$i])) {
                $percent1 = $results[$i]['total_off_point'] / ($maxAttackPoint > 0 ? $maxAttackPoint : 1) * 100;
                $percent2 = $results[$i]['total_def_point'] / ($maxDefensePoint > 0 ? $maxDefensePoint : 1) * 100;
                $dates .= '<td>' . TimezoneHelper::date("d.m", $results[$i]['time']) . '. </td>';
                $bars .= '<td class="bar positive"><div class="wrapper"><div class="bar left red" title="' . $results[$i]['total_off_point'] . '" style="height: ' . $percent1 . '%;"></div><div class="bar right blue" title="' . $results[$i]['total_def_point'] . '" style="height: ' . $percent2 . '%;"></div><div class="clear"></div></div></td>';
            } else {
                $dates .= '<td>' . TimezoneHelper::date("d.m",
                        (isset($results[0]['time']) ? $results[0]['time'] : time()) - $i * 86400) . '. </td>';
                $bars .= '<td class="bar positive"><div class="wrapper"><div class="bar left red" title="0" style="height: 0%;"></div><div class="bar right blue" title="0" style="height: 0%;"></div><div class="clear"></div></div></td>';
            }
        }
        $HTML .= '<table class="barChart oneDir" cellpadding="1" cellspacing="1">';
        $HTML .= '<tbody>';
        $HTML .= '<tr>' . $dates . '</tr>';
        $HTML .= '<tr>' . $bars . '</tr>';
        $HTML .= '<tr><td colspan="7" class="legend"><div class="legend red"></div>&nbsp;' . T("BBCode",
                "attack") . '<br/><div class="legend blue"></div>&nbsp;' . T("BBCode",
                "defense") . '<br/><div class="clear"></div></td></tr>';
        $HTML .= '</tbody></table>';
        return $HTML;
    }

    public function FightingPoints(): string
    {
        //The fighting points will show you the sum of the attack points as well as sum of the defense points made by your alliance over the last 7 days (if you go with the mouse over the different bars you will see the concrete number). So this graph shows you the military activity of your alliance.
        $db = DB::getInstance();
        $stats = $db->query("SELECT * FROM alistats WHERE aid={$this->aid} ORDER BY time DESC LIMIT 7");
        $HTML = '<h4 class="chartHeadline">' . T("BBCode", "Fighting points (difference to yesterday)") . '</h4>';
        $dates = $bars = $bars2 = '';
        $maxAttackPoint = $db->fetchScalar("SELECT MAX(total_off_point) FROM alistats WHERE aid={$this->aid} ORDER BY time DESC LIMIT 7");
        $maxDefensePoint = $db->fetchScalar("SELECT MAX(total_def_point) FROM alistats WHERE aid={$this->aid} ORDER BY time DESC LIMIT 7");
        $results = [];
        while ($row = $stats->fetch_assoc()) {
            $results[] = $row;
        }
        for ($i = 6; $i >= 0; --$i) {
            if (!isset($results[$i])) {
                $dates .= '<td>' . TimezoneHelper::date("d.m",
                        (isset($results[0]['time']) ? $results[0]['time'] : time()) - $i * 86400) . '. </td>';
                $bars .= '<td class="bar positive"><div class="wrapper"><div class="bar left red" title="0" style="height: 0%;"></div><div class="bar right blue" title="0" style="height: 0%;"></div><div class="clear"></div></div></td>';
                $bars2 .= '<td class="bar positive"><div class="wrapper"><div class="bar left red" title="0" style="height: 0%;"></div><div class="bar right blue" title="0" style="height: 0%;"></div><div class="clear"></div></div></td>';
                continue;
            }
            $percent1 = $maxAttackPoint == 0 ? 0 : $results[$i]['total_off_point'] / $maxAttackPoint * 100;
            $percent2 = $maxDefensePoint == 0 ? 0 : $results[$i]['total_def_point'] / $maxDefensePoint * 100;
            $dates .= '<td>' . TimezoneHelper::date("d.m", $results[$i]['time']) . '. </td>';
            $bars .= '<td class="bar positive"><div class="wrapper"><div class="bar left red" title="' . $results[$i]['total_off_point'] . '" style="height: ' . $percent1 . '%;"></div><div class="bar right blue" title="' . $results[$i]['total_def_point'] . '" style="height: ' . $percent2 . '%;"></div><div class="clear"></div></div></td>';
            if (!isset($results[$i + 1])) {
                $bars2 .= '<td class="bar positive"><div class="wrapper"><div class="bar left red" title="0" style="height: 0%;"></div><div class="bar right blue" title="0" style="height: 0%;"></div><div class="clear"></div></div></td>';
                continue;
            }
            $percent1 = $results[$i + 1]['total_off_point'] / ($maxAttackPoint > 0 ? $maxAttackPoint : 1) * 100;
            $percent2 = $results[$i + 1]['total_def_point'] / ($maxDefensePoint > 0 ? $maxDefensePoint : 1) * 100;
            $bars2 .= '<td class="bar positive"><div class="wrapper"><div class="bar left red" title="' . $results[$i]['total_off_point'] . '" style="height: ' . $percent1 . '%;"></div><div class="bar right blue" title="' . $results[$i]['total_def_point'] . '" style="height: ' . $percent2 . '%;"></div><div class="clear"></div></div></td>';
        }
        $HTML .= '<table class="barChart twoDirs" cellpadding="1" cellspacing="1">';
        $HTML .= '<tbody>';
        $HTML .= '<tr>' . $dates . '</tr>';
        $HTML .= '<tr>' . $bars . '</tr>';
        $HTML .= '<tr>' . $bars2 . '</tr>';
        $HTML .= '<tr><td colspan="7" class="legend"><div class="legend red"></div>&nbsp;' . T("BBCode",
                "attack") . '<br/><div class="legend blue"></div>&nbsp;' . T("BBCode",
                "defense") . '<br/><div class="clear"></div></td></tr>';
        $HTML .= '</tbody></table>';
        return $HTML;
    }

    /**
     * @param mixed $match
     * @return mixed
     */
    public function losses($match)
    {
        $db = DB::getInstance();
        $tag = $db->real_escape_string(BBCode::plain($match[1]));
        $find = $db->query("SELECT id FROM alidata WHERE tag='$tag'");
        if (!$find->num_rows) {
            return T("BBCode", "This alliance cannot be found");
        }
        $aid = $find->fetch_assoc()['id'];
        $name = htmlspecialchars((string)$match[1], ENT_QUOTES);
        $stats = $db->query("SELECT * FROM alistats WHERE aid=$aid ORDER BY time DESC LIMIT 7");
        $HTML = '<h4 class="chartHeadline">' . sprintf(T("BBCode", "Losses compared to alliance"), $name) . '</h4>';
        $dates = $bars = '';
        $results = [];
        $size = $stats->num_rows;
        $max_killed_by = 0;
        $max_stolen_by = 0;
        $max_killed_of = 0;
        $max_stolen_of = 0;
        while ($row = $stats->fetch_assoc()) {
            if ($row['stolen_of'] > $max_stolen_of) {
                $max_stolen_of = $row['stolen_of'];
            }
            if ($row['stolen_by'] > $max_stolen_by) {
                $max_stolen_by = $row['stolen_by'];
            }
            if ($row['killed_of'] > $max_killed_of) {
                $max_killed_of = $row['killed_of'];
            }
            if ($row['killed_by'] > $max_killed_by) {
                $max_killed_by = $row['killed_by'];
            }
            $results[] = $row;
        }
        for ($i = 6; $i >= 0; --$i) {
            if (!isset($results[$i])) {
                $dates .= '<td>' . TimezoneHelper::date("d.m", $results[0]['time'] - $i * 86400) . '. </td>';
                $bars .= '<td class="bar positive">
							<div class="wrapper">
								<div class="stackedWrapper left" style="height: 0%;">
									<div class="bar top tomato" title="0" style="height: 0%;">
									</div>
									<div class="bar red" title="0" style="height: 0%;">
									</div>
								</div>
								<div class="stackedWrapper right" style="height: 0%;">
									<div class="bar top cornflowerblue" title="0" style="height: 0%;">
									</div>
									<div class="bar blue" title="0" style="height: 0%;">
									</div>
								</div>
								<div class="clear"></div>
							</div>
						</td>';
            } else {
                $dates .= '<td>' . TimezoneHelper::date("d.m", $results[$i]['time']) . '. </td>';
                $compare_alliance_gain_troops_percent = $max_killed_by == 0 ? 0 : ($results[$i]['killed_by'] / $max_killed_by * 100);
                $compare_alliance_gain_resource_percent = $max_stolen_by == 0 ? 0 : ($results[$i]['stolen_by'] / $max_stolen_by * 100);
                $total_percent = min($compare_alliance_gain_troops_percent + $compare_alliance_gain_resource_percent,
                    100);
                $compare_alliance_killed_of = $max_killed_of == 0 ? 0 : ($results[$i]['killed_of'] / $max_killed_of * 100);
                $compare_alliance_stolen_of = $max_stolen_of == 0 ? 0 : ($results[$i]['stolen_ofs'] / $max_stolen_of * 100);
                $total_percent2 = min($compare_alliance_killed_of + $compare_alliance_stolen_of, 100);
                $bars .= '<td class="bar positive">
							<div class="wrapper">
								<div class="stackedWrapper left" style="height: ' . $total_percent . '%;">
									<div class="bar top tomato" title="' . $results[$i]['killed_by'] . '" style="height: ' . $compare_alliance_gain_troops_percent . '%;">
									</div>
									<div class="bar red" title="' . $results[$i]['stolen_by'] . '" style="height: ' . $compare_alliance_gain_resource_percent . '%;">
									</div>
								</div>
								<div class="stackedWrapper right" style="height: ' . $total_percent2 . '%;">
									<div class="bar top cornflowerblue" title="' . $results[$i]['killed_of'] . '" style="height: ' . $compare_alliance_killed_of . '%;">
									</div>
									<div class="bar blue" title="' . $results[$i]['stolen_of'] . '" style="height: ' . $compare_alliance_stolen_of . '%;">
									</div>
								</div>
								<div class="clear"></div>
							</div>
						</td>';
            }
        }
        $HTML .= '<table class="barChart oneDir" cellpadding="1" cellspacing="1">';
        $HTML .= '<tbody>';
        $HTML .= '<tr>' . $dates . '</tr>';
        $HTML .= '<tr>' . $bars . '</tr>';
        $HTML .= '<tr><td colspan="7" class="legend"><div class="legend tomato"></div>&nbsp;' . sprintf(T("BBCode",
                "troops destroyed by alliance Ally"),
                $name) . '<br><div class="legend red"></div>&nbsp;' . sprintf(T("BBCode",
                "resources stolen by alliance Ally"),
                $name) . '<br><div class="legend cornflowerblue"></div>&nbsp;' . sprintf(T("BBCode",
                "troops destroyed of alliance Ally"),
                $name) . '<br><div class="legend blue"></div>&nbsp;' . sprintf(T("BBCode",
                "resources stolen of alliance Ally"),
                $name) . '<br><div class="clear"></div></td></tr>';
        $HTML .= '</body></table><br />';
        return $HTML;
    }
}

class BBCode
{
    /** @var mixed */
    private static $from_uid = null;
    /** @var mixed */
    private static $to_uid   = null;

    /**
     * @param mixed $from_uid
     */
    public static function setFromPlayer($from_uid): void
    {
        self::$from_uid = $from_uid;
    }

    /**
     * @param mixed $to_uid
     */
    public static function setToPlayer($to_uid): void
    {
        self::$to_uid = $to_uid;
    }

    /**
     * @param mixed $data
     */
    public static function renderAliNewsEvent($data): string
    {
        $db = DB::getInstance();
        $params = explode(":", $data);
        $event = '';
        switch ($params[0]) {
            case AllianceModel::LOG_DIPLOMACY_CONF:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $tag2 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[2]}");
                if (!$tag2) {
                    $tag2 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has offered a confederacy to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>',
                    '<a href="' . allianceUrl((int) $params[2]) . '">' . $tag2 . '</a>');
                break;
            case AllianceModel::LOG_DIPLOMACY_CONF_ACCEPTED:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has accepted a confederacy to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>');
                break;
            case AllianceModel::LOG_DIPLOMACY_CONF_REFUSE:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $tag2 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[2]}");
                if (!$tag2) {
                    $tag2 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has refused a confederacy to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>',
                    '<a href="' . allianceUrl((int) $params[2]) . '">' . $tag2 . '</a>');
                break;
            case AllianceModel::LOG_DIPLOMACY_NAP_ACCEPTED:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1->num_rows) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has accepted nap to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>');
                break;
            case AllianceModel::LOG_DIPLOMACY_NAP_REFUSE:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has refused nap to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>');
                break;
            case AllianceModel::LOG_DIPLOMACY_WAR_ACCEPTED:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has accepted war to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>');
                break;
            case AllianceModel::LOG_DIPLOMACY_WAR_REFUSE:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $tag2 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[2]}");
                if (!$tag2) {
                    $tag2 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has refused war to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>',
                    '<a href="' . allianceUrl((int) $params[2]) . '">' . $tag2 . '</a>');
                break;
            case AllianceModel::LOG_DIPLOMACY_WAR:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $tag2 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[2]}");
                if (!$tag2) {
                    $tag2 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has offered war to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>',
                    '<a href="' . allianceUrl((int) $params[2]) . '">' . $tag2 . '</a>');
                break;
            case AllianceModel::LOG_DIPLOMACY_NAP:
                $tag1 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[1]}");
                if (!$tag1) {
                    $tag1 = '<span class="none2">[?]</span>';
                }
                $tag2 = $db->fetchScalar("SELECT tag FROM alidata WHERE id={$params[2]}");
                if (!$tag2) {
                    $tag2 = '<span class="none2">[?]</span>';
                }
                $event = sprintf(T("BBCode", "x has offered nap to y"),
                    '<a href="' . allianceUrl((int) $params[1]) . '">' . $tag1 . '</a>',
                    '<a href="' . allianceUrl((int) $params[2]) . '">' . $tag2 . '</a>');
                break;
            case AllianceModel::LOG_JOINED:
                $event = sprintf(T("BBCode", "X joined Alliance"),
                    '<a href="' . profileUrl((int) $params[1]) . '">' . $params[2] . '</a>');
                break;
            case AllianceModel::LOG_LEFT:
                $name = $db->fetchScalar("SELECT name FROM users WHERE id={$params[1]}");
                if(empty($name)){
                    $name = '[?]';
                }
                $event = sprintf(T("BBCode", "X left Alliance"), '<a href="' . profileUrl((int) $params[1]) . '">' . $name . '</a>');
                break;
            case AllianceModel::LOG_KICK:
                $event = sprintf(T("BBCode", "X kicked Y from Alliance"),
                    '<a href="' . profileUrl((int) $params[1]) . '">' . $params[2] . '</a>',
                    '<a href="' . profileUrl((int) $params[3]) . '">' . $params[4] . '</a>');
                break;
            case AllianceModel::LOG_NEW_VILLAGE:
                $event = sprintf(T("BBCode", "X created new village"),
                    '<a href="' . profileUrl((int) $params[1]) . '">' . $params[2] . '</a>');
                break;
            case AllianceModel::LOG_INVITE:
                $event = sprintf(T("BBCode", "X invited Y"),
                    '<a href="' . profileUrl((int) $params[1]) . '">' . $params[2] . '</a>',
                    '<a href="' . profileUrl((int) $params[3]) . '">' . $params[4] . '</a>');
                break;
        }
        return $event;
    }

    /**
     * @param mixed $desc1
     * @param mixed $desc2
     * @param bool $isAlliance
     * @param mixed $id
     * @return list<string>
     */
    public static function BBCodeProfile($desc1, $desc2, $isAlliance, $id)
    {
        $MedalsBBCode = new MedalsBBCode();
        $data = [];
        $data['isAlliance'] = $isAlliance;
        if ($isAlliance) {
            $data['aid'] = $id;
        } else {
            $data['uid'] = $id;
        }
        $MedalsBBCode->data = $data;
        $desc1 = preg_replace_callback("/\[\#(.*?)\]/", [$MedalsBBCode, "BBCodeMedals",], $desc1);
        $desc2 = preg_replace_callback("/\[\#(.*?)\]/", [$MedalsBBCode, "BBCodeMedals",], $desc2);
        $desc1 = preg_replace_callback("/\[c(.*?)\]/", [$MedalsBBCode, "BBCodeMedals2",], $desc1);
        $desc2 = preg_replace_callback("/\[c(.*?)\]/", [$MedalsBBCode, "BBCodeMedals2",], $desc2);
        if ($data['isAlliance']) {
            $allianceBBCode = new AllianceBBCode();
            $allianceBBCode->aid = $data['aid'];
            $desc1 = preg_replace_callback("/\[diplomatie\]/", [$allianceBBCode, "Diplomacy",], $desc1);
            $desc2 = preg_replace_callback("/\[diplomatie\]/", [$allianceBBCode, "Diplomacy",], $desc2);
            $desc1 = preg_replace_callback("/\[nap\]/", [$allianceBBCode, "nap",], $desc1);
            $desc2 = preg_replace_callback("/\[nap\]/", [$allianceBBCode, "nap",], $desc2);
            $desc1 = preg_replace_callback("/\[war\]/", [$allianceBBCode, "war",], $desc1);
            $desc2 = preg_replace_callback("/\[war\]/", [$allianceBBCode, "war",], $desc2);
        }
        return [nl2br(trim($desc1)), nl2br(trim($desc2))];
    }

    /**
     * @param string $input
     * @param int $aid
     * @return string|null
     */
    public static function BBCodeInternalPage($input, $aid)
    {
        $input = nl2br(self::translateMessagesBBCode($input));
        $allianceBBCode = new AllianceBBCode();
        $allianceBBCode->aid = $aid;
        $input = preg_replace_callback("#\[forum\](.*?)\[/forum\]#is", [$allianceBBCode, "forum",], $input);
        $input = preg_replace_callback("#\[news\](.*?)\[/news\]#is", [$allianceBBCode, "news",], $input);
        $input = preg_replace_callback("#\[stats\]strength\[/stats\]#is",
            [$allianceBBCode, "StrengthOfOwnAlliance",],
            $input);
        $input = preg_replace_callback("#\[stats\]fighting points\[/stats\]#is",
            [$allianceBBCode, "FightingPoints",],
            $input);
        $input = preg_replace_callback("#\[losses\](.*?)\[/losses\]#is", [$allianceBBCode, "losses",], $input);
        return $input;
    }

    /**
     * @param mixed $input
     * @return array<string>|string|null
     */
    public static function translateMessagesBBCode($input)
    {
        $input = preg_replace('/ ?(?:.*)spieler\.php\?uid=([0-9]+)/', '[player]$1[/player]', $input);
        $input = preg_replace('/ ?(?:.*)allianz\.php\?aid=([0-9]+)/', '[alliance]$1[/alliance]', $input);
        $input = preg_replace('/ ?(?:.*)karte\.php\?x=([\-0-9]+)&y=([\-0-9]+)/', '[x|y]$1|$2[/x|y]', $input);
        $input = preg_replace('/ ?(?:.*)position_details\.php\?x=([\-0-9]+)&y=([\-0-9]+)/', '[x|y]$1|$2[/x|y]', $input);
        $input = preg_replace_callback('/ ?(?:.*)karte\.php\?kid=([\-0-9]+)/', static function (array $match): string {
            $xy = Formulas::kid2xy((int)$match[1]);

            return '[x|y]' . $xy['x'] . '|' . $xy['y'] . '[/x|y]';
        }, $input);
        if ((!is_null(self::$from_uid) && self::$from_uid <= 2) || (!is_null(self::$to_uid) && self::$to_uid <= 2)) {
            $input = preg_replace_callback('#(?<!href\=[\'"])(https?|ftp|file)://[-A-Za-z0-9+&@\#/%()?=~_|$!:,.;]*[-A-Za-z0-9+&@\#/%()=~_|$]#',
                [self::class, 'regexp_url_search'],
                $input);
        }
        // Like the reference, markup typed by the player is shown as text; "&" is left alone.
        $input = str_replace(['<', '>', '"', "'"], ['&lt;', '&gt;', '&quot;', '&#039;'], $input);
        $pattern = [
            '/\[b\](.*?)\[\/b\]/is' => '<span style="font-weight:bold;">$1</span>',
            '/\[i\](.*?)\[\/i\]/is' => '<span style="font-style:italic;">$1</span>',
            '/\[u\](.*?)\[\/u\]/is' => '<span style="text-decoration:underline;">$1</span>',
            '/\[hero\]/' => '<img class="unit uhero" src="/img/spacer.gif" title="' . T("Troops", "hero.title") . '" alt="' . T("Troops", "hero.title") . '" />',
        ];
        foreach (['l' => 1, 'cl' => 2, 'ir' => 3, 'c' => 4] as $tag => $resource) {
            $title = T("inGame", "resources.r" . $resource);
            $pattern['/\[' . $tag . '\]/'] = '<i class="r' . $resource . '" title="' . $title . '"></i>';
        }
        foreach (self::SMILEYS as $class => $code) {
            $guard = $code === ';)' ? '(?<!&#039)(?<!&quot)(?<!&lt)(?<!&gt)' : '';
            $pattern['/' . $guard . preg_quote($code, '/') . '/'] = '<img class="smiley ' . $class . '" src="/img/spacer.gif" alt="' . $code . '" title="' . $code . '" />';
        }
        $input = preg_replace(array_keys($pattern), array_values($pattern), $input);
        $input = preg_replace_callback("/\[tid([0-9]+)\]/", [self::class, 'BBCodeUnits'], $input);
        $input = preg_replace_callback("#\[x\|y\](.*?)\[/x\|y\]#is", [self::class, 'BBCodeCoordinates'], $input);
        $input = preg_replace_callback("#\[player\](.*?)\[/player\]#is", [self::class, 'BBCodePlayer'], $input);
        $input = preg_replace_callback("#\[alliance\](.*?)\[/alliance\]#is", [self::class, 'BBCodeAlliance'], $input);
        $input = preg_replace_callback("#\[report\](.*?)\[/report\]#is", [self::class, 'BBCodeReport'], $input);
        $input = preg_replace_callback("#\[url=(.*?)\](.*?)\[/url\]#is", [self::class, 'BBCodeUrl'], $input);
        $input = preg_replace_callback("#\[url\](.*?)\[/url\]#is", [self::class, 'BBCodeUrl'], $input);
        $input = preg_replace_callback("#\[color=(.*?)\](.*?)\[/color\]#is", [self::class, 'BBCodeColor'], $input);
        $input = preg_replace_callback("#\[quote(?:=(.*?))?\](.*?)\[/quote\]#is", [self::class, 'BBCodeQuote'], $input);
        return $input;
    }

    /** Smiley classes and the text each one stands for. */
    private const SMILEYS = [
        'aha' => '*aha*', 'angry' => '*angry*', 'cool' => '*cool*', 'cry' => '*cry*', 'cute' => '*cute*',
        'depressed' => '*depressed*', 'eek' => '*eek*', 'ehem' => '*ehem*', 'emotional' => '*emotional*',
        'grin' => ':D', 'happy' => ':)', 'hit' => '*hit*', 'hmm' => '*hmm*', 'hmpf' => '*hmpf*',
        'hrhr' => '*hrhr*', 'huh' => '*huh*', 'lazy' => '*lazy*', 'love' => '*love*',
        'nocomment' => '*nocomment*', 'noemotion' => '*noemotion*', 'notamused' => '*notamused*',
        'pout' => '*pout*', 'redface' => '*redface*', 'rolleyes' => '*rolleyes*', 'sad' => ':(',
        'shy' => '*shy*', 'smile' => '*smile*', 'tongue' => '*tongue*', 'veryangry' => '*veryangry*',
        'veryhappy' => '*veryhappy*', 'wink' => ';)',
    ];

    /**
     * @param mixed $type
     * @return mixed
     */
    public static function getMedalCategory($type)
    {
        return T("Medals", "names." . $type);
    }

    /**
     * @param array<string, float|int|string|null> $row
     */
    public static function getMedal2Category($row): string
    {
        return sprintf(T("Profile", "SpecialMedalsTitle." . $row['type']), $row['worldId']);
    }

    /**
     * @param mixed $matches
     */
    private static function regexp_url_search($matches): string
    {
        return '[url]' . $matches[0] . '[/url]';
    }

    /**
     * @param mixed $matches
     * @return mixed
     */
    private static function BBCodeUnits($matches)
    {
        $unit = (int)$matches[1];
        if ($unit < 1 || $unit > 99) {
            return $matches[0];
        }
        $title = T("Troops", $unit . ".title");

        return '<img class="unit u' . $unit . '" src="/img/spacer.gif"  title="' . $title . '" alt="' . $title . '" />';
    }

    /**
     * @param mixed $matches
     */
    private static function BBCodeCoordinates($matches): string
    {
        $coordinate = explode("|", $matches[1]);
        if (sizeof($coordinate) < 2 || !is_numeric($coordinate[0]) || !is_numeric($coordinate[1])) {
            return $matches[0];
        }
        $kid = Formulas::xy2kid($coordinate[0], $coordinate[1]);
        $db = DB::getInstance();
        $html = str_replace(["\u{202D}", "\u{202C}", "\u{2212}"], ['&#x202d;', '&#x202c;', '&minus;'], getCoordinatesHTML($coordinate[0], $coordinate[1]));
        $coordinate = ' ' . '&#x202d;' . $html . '&#x202c;';
        $find = $db->query("SELECT fieldtype, oasistype, landscape, occupied FROM wdata WHERE id=$kid")->fetch_assoc();
        $link = '<a class="bbCoordsLink" href="/karte.php?d=' . $kid . '">';
        if ($find['landscape']) {
            return $link . T("Global", "Wilderness") . $coordinate . '</a>';
        } else if ($find['oasistype']) {
            return $link . T("Global", $find['occupied'] ? 'OccupiedOasis' : 'unOccupiedOasis') . $coordinate . '</a>';
        } else {
            if (!$find['occupied']) {
                return $link . T("Global", 'Abandoned valley') . $coordinate . '</a>';
            }
            $villageName = $db->fetchScalar("SELECT name FROM vdata WHERE kid=$kid");
            return $link . $villageName . $coordinate . '</a>';
        }
    }

    /**
     * @param mixed $matches
     */
    private static function BBCodePlayer($matches): string
    {
        $db = DB::getInstance();
        $name = self::plain($matches[1]);
        if(is_numeric($name)){
            $name = (int) $name;
            $find = $db->query("SELECT id, name FROM users WHERE id=$name");
        } else {
            $name = $db->real_escape_string($name);
            $find = $db->query("SELECT id, name FROM users WHERE name='$name'");
        }
        if (!$find->num_rows) {
            return '<span style="font-style:italic;">' . T("BBCode", "player not found") . '</span>';
        }
        $find = $find->fetch_assoc();
        return '<a href="' . profileUrl((int) $find['id']) . '">' . $find['name'] . '</a>';
    }

    /**
     * @param mixed $matches
     */
    private static function BBCodeAlliance($matches): string
    {
        $name = self::plain($matches[1]);
        $db = DB::getInstance();
        if(is_numeric($name)){
            $name = (int) $name;
            $find = $db->query("SELECT id, name FROM alidata WHERE id=$name");
        } else {
            $name = $db->real_escape_string($name);
            $find = $db->query("SELECT id, name FROM alidata WHERE name='$name'");
        }
        if (!$find->num_rows) {
            return '<span style="font-style:italic;">' . T("BBCode", "alliance not found") . '</span>';
        }
        $find = $find->fetch_assoc();
        return '<a href="' . allianceUrl((int) $find['id']) . '">' . $find['name'] . '</a>';
    }

    /**
     * @param mixed $matches
     * @return mixed
     */
    private static function BBCodeReport($matches)
    {
        $url = parse_url(trim(html_entity_decode(strip_tags($matches[1]))));
        if (!isset($url['query'])) {
            return '<a>' . T("BBCode", "invalid report ID") . '</a>';
        }
        parse_str($url['query'], $reportMatch);
        $reportId = $reportMatch['id'] ?? '';
        $data = explode("|", is_string($reportId) ? $reportId : '');
        $id = (int)$data[0];
        if (!$id) {
            return '<a>' . T("BBCode", "invalid report ID") . '</a>';
        }
        $private_key = preg_replace('/[^A-Za-z0-9]/', '', $data[1] ?? '');
        $report = DB::getInstance()->query("SELECT * FROM ndata WHERE id=" . $id);
        $report = $report->num_rows ? $report->fetch_assoc() : null;
        if ($report === null || $private_key != $report['private_key']) {
            return '<a>' . T("BBCode", "report not found") . '</a>';
        }
        $m = new ReportsCtrl();
        return '<a href="/report?id=' . $id . '|' . $private_key . '">' . $m->getNoticeSubject($report,
                TRUE) . '</a>';
    }

    /**
     * [url=address]text[/url] links to a web address for every sender; the bare [url]address[/url] form
     * only exists for the links the game adds to system messages. Other schemes are left as text.
     *
     * @param mixed $matches
     * @return mixed
     */
    private static function BBCodeUrl($matches)
    {
        $bare = sizeof($matches) < 3;
        $address = trim($matches[1]);
        $text = $bare ? $address : $matches[2];
        if ($bare && !((!is_null(self::$from_uid) && self::$from_uid <= 2) || (!is_null(self::$to_uid) && self::$to_uid <= 2))) {
            return $matches[0];
        }
        $decoded = html_entity_decode($address);
        if (!in_array(strtolower((string) parse_url($decoded, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $matches[0];
        }

        return '<a href="' . $address . '" target="_blank">' . $text . '</a>';
    }

    /** The sixteen CSS colour names the reference's [color] accepts. */
    private const COLOR_NAMES = ['aqua', 'black', 'blue', 'fuchsia', 'gray', 'green', 'lime', 'maroon', 'navy', 'olive', 'purple', 'red', 'silver', 'teal', 'white', 'yellow'];

    /**
     * @param mixed $matches
     * @return mixed
     */
    private static function BBCodeColor($matches)
    {
        if (!in_array($matches[1], self::COLOR_NAMES, true)) {
            return $matches[0];
        }

        return '<span style="color:' . $matches[1] . ';">' . $matches[2] . '</span>';
    }

    /**
     * @param mixed $matches
     */
    private static function BBCodeQuote($matches): string
    {
        return '<div class="quote-header b">' . T("BBCode", "Quote:") . ' ' . ($matches[1] ?? '')
            . '</div><div class="quote-body">"' . $matches[2] . '"</div>';
    }

    /**
     * Tag-free, quote-encoded text for names, tags and titles.
     *
     * @param mixed $value
     */
    public static function plain($value): string
    {
        return htmlspecialchars(strip_tags(html_entity_decode((string)$value, ENT_QUOTES)), ENT_QUOTES, 'UTF-8');
    }
}
