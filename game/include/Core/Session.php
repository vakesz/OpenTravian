<?php

namespace Core;

use Core\Caching\Caching;
use Core\Database\DB;
use Core\Database\DBI;
use Core\Database\GlobalDB;
use Core\Helper\IPTracker;
use Core\Helper\SessionVar;
use Core\Helper\WebService;
use Game\Admin\AdminAccounts;
use Game\Admin\AuditLog;
use Game\Admin\TwoFactorGate;
use Game\Hero\SessionHero;
use Game\Mail\EventMail;
use Model\AuctionModel;
use Model\LoginModel;
use Model\Quest;

use function array_key_exists;
use function getDisplay;
use function strtolower;

class Session
{
    public const SITTER_CAN_RAID = 1;
    public const SITTER_CAN_SEND_REINFORCEMENT = 2;
    public const SITTER_CAN_SEND_RESOURCES = 4;
    public const SITTER_CAN_BUY_OR_SPEND_GOLD = 8;
    public const SITTER_CAN_SEND_OR_ANSWER_MESSAGES = 16;
    public const SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS = 32;
    public const SITTER_CAN_CONTRIBUTE_ALLIANCE = 64;
    public const CAN_CHANGE_SETTINGS = 123;
    private const ACTION_REPLAY_WINDOW = 3.0;
    /** Seconds between two writes of a player's last-seen time. */
    private const LAST_SEEN_WRITE_INTERVAL = 60;
    /** @var \Core\Session|null */
    private static $_self;
    private static ?string $claimedFingerprint = null;
    /** @var mixed */
    public $data;
    /** @var bool */
    private $isLoggedIn = false;
    /** @var bool */
    private $isAdmin = false;
    /** @var bool */
    private $isAdminSet = false;

    /** @var DBI */
    private $db;

    /** @var Village */
    public $village;

    /** @var SessionHero */
    public $hero;

    private function __construct()
    {
        $this->db = DBI::getInstance();
        $this->init();
    }

    public function populateData(): void
    {
        // A control panel account has no village or hero to load.
        if ($this->isValid() && !AdminAccounts::isPanelOnly($this->getPlayerId())) {
            $this->village = Village::getInstance();
            $this->hero = SessionHero::getInstance();
        }
    }

    public function init(): void
    {
        if (!isset($_SESSION[$this->fixSessionPrefix('uid')]) || !isset($_SESSION[$this->fixSessionPrefix('pw')])) {
            $this->restoreFromRememberMe();
        }
        if (!isset($_SESSION[$this->fixSessionPrefix('uid')]) || !isset($_SESSION[$this->fixSessionPrefix('pw')])) {
            $this->isLoggedIn = false;
            return;
        }
        $uid = (int)$_SESSION[$this->fixSessionPrefix('uid')];
        $login = new LoginModel();
        $result = $login->checkUserOrSitterLogin($uid, (string)$_SESSION[$this->fixSessionPrefix('pw')]);
        if ($result <> 1) {
            $this->increaseClicks();
            $this->isLoggedIn = true;
            $this->setData($uid, $result >= 2, $result == 2 ? 1 : 2);
            if (isset($_REQUEST['adminLogin']) && is_numeric($_REQUEST['adminLogin'])) {
                $this->adminLogin((int)$_REQUEST['adminLogin']);
            }
        } else {
            $this->isLoggedIn = false;
            unset($_SESSION[$this->fixSessionPrefix('uid')]);
        }

        // $m = new AuctionModel();
        // echo $m->getNewPlaceId($this->getPlayerId());
        // die;
    }

    /**
     * @param string $key
     */
    public function fixSessionPrefix($key): string
    {
        return WebService::fixSessionPrefix($key);
    }

    /**
     * The PHP session was lost (expired file, new browser session, container
     * recreate) but a valid remember-me cookie is present: start a fresh session
     * for that account. The token itself is rotated by RememberMe::consume().
     */
    private function restoreFromRememberMe(): void
    {
        if (empty($_COOKIE[RememberMe::COOKIE]) || headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        $restored = RememberMe::consume();
        if ($restored === null) {
            return;
        }
        [$uid, $passwordHash] = $restored;
        if (TwoFactorGate::fromRequest()->applies((int)$uid)) {
            RememberMe::revoke();
            return;
        }
        $username = (string)$this->db->fetchScalar('SELECT name FROM users WHERE id=?', $uid);
        $this->establishSession($uid, $username, $passwordHash);
    }

    /** Idle limit for a player session; never shorter than the remember-me lifetime. */
    private static function playerIdleTimeout(): int
    {
        return max(600, (int)Config::getProperty("settings", "session_timeout"), RememberMe::LIFETIME);
    }

    /**
     * The one CSRF token of this session: stable until login or logout replaces it, so
     * second tabs and repeated clicks keep working. Double submits of the same action are
     * handled by claimAction(), not by rotating this value.
     */
    private static function sessionToken(): string
    {
        $key = WebService::fixSessionPrefix('csrf_token');
        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(16));
        }
        return $_SESSION[$key];
    }

    /** Token carried by logout links; the same value as the action checker. */
    public static function getLogoutToken(): string
    {
        return self::sessionToken();
    }

    /**
     * Logout href for templates. Authenticated players hit `/auth/logout` with the
     * per-session token (reference wire path). Guests get bare `/logout`, which is
     * only the guest 404 shell and never ends a session.
     */
    public static function logoutUrl(): string
    {
        return self::getInstance()->isValid()
            ? '/auth/logout?c=' . self::getLogoutToken()
            : '/logout';
    }

    /**
     * @param mixed $submitted
     */
    public static function matchesLogoutToken($submitted): bool
    {
        return is_string($submitted) && $submitted !== '' && hash_equals(self::getLogoutToken(), $submitted);
    }

    private function increaseClicks(): void
    {
        $page = addslashes(basename((string)($_SERVER['PHP_SELF'] ?? '')));
        if (in_array($page, ['map_block.php', 'map_mark.php', 'ajax.php', 'hero_v2_image.php', 'minimap.php'])) {
            return;
        }
        $sessionClicks = SessionVar::getVariable("sessionClicks", 0, 10);
        SessionVar::setVariable('sessionClicks', ++$sessionClicks, false);
    }

    /**
     * @param int $uid
     * @param bool $isSitter
     * @param int $sitterId
     */
    public function setData($uid, $isSitter, $sitterId = 0): void
    {
        $userRow = $this->db->fetchSingleRow('SELECT * FROM users WHERE id=?', $uid);
        if ($userRow === false) {
            return;
        }

        $data = $userRow;
        if ($uid > 0 && $data['kid'] == 0) {
            $sql = "SELECT kid FROM vdata WHERE owner=? ORDER BY pop DESC LIMIT 1";
            // An account with no village (Support, the extra administrators) stays at kid 0.
            $data['kid'] = (int)$this->db->fetchScalar($sql, (int)$userRow['id']);

            $this->db->run("UPDATE users SET kid=? WHERE id=?", [$data['kid'], $data['id']]);
        }
        $data['isSitter'] = $isSitter;
        $data['sitterPermissions'] = $isSitter ? $userRow['sit' . $sitterId . 'Permissions'] : 0;
        $data['sitterUid'] = $isSitter ? $userRow['sit' . $sitterId . 'Uid'] : 0;
        if (!isset($_SESSION[$this->fixSessionPrefix('admin_uid')])) {
            if (time() - $data['last_login_time'] > self::playerIdleTimeout()) {
                $this->logout();
                redirect("/dorf1.php");
                return;
            }
            if ($isSitter && time() - $data['last_owner_login_time'] > 21 * 86400) {
                $this->logout();
                redirect("/dorf1.php"); //TODO: it's causing problem
                return;
            }
            // Every request would otherwise write the row; readers (idle timeout, online lists) work in minutes.
            if (time() - (int)$data['last_login_time'] >= self::LAST_SEEN_WRITE_INTERVAL) {
                $time = $isSitter ? $data['last_owner_login_time'] : time();
                $this->db->run("UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?", [time(), $time, $uid]);
                $data['last_login_time'] = time();
                $data['last_owner_login_time'] = $time;
            }
        }
        $key = $this->fixSessionPrefix('admin_login_time');
        if (isset($_SESSION[$key]) && (time() - $_SESSION[$key]) > max(600, Config::getProperty("settings", "session_timeout"))) {
            $this->logout();
            redirect("/dorf1.php");
            return;
        } elseif (isset($_SESSION[$key])) {
            $_SESSION[$key] = time();
        }
        if (!isset($_SESSION[$this->fixSessionPrefix('admin_uid')]) && !$isSitter) {
            IPTracker::addCurrentIP($uid);
        }
        $favorTabsArr = explode(",", $data['favorTabs']);
        $data['favorTabs'] = [
            'buildingRallyPoint' => 0,
            'buildingMarket' => 0,
            'buildingTreasury' => 0,
            // /alliance defaults to Overview (tab 7), not Profile (1).
            'alliance' => 7,
            'buildingExpansion' => 0,
            'villageOverview' => 0,
            'reports' => 0,
            'allyPageProfile' => 0,
            'hero' => 1,
            'statistics' => 0,
            'statisticsTablePlayer' => 0,
            'statisticsTableAlly' => 0,
            'messages' => 0,
            'embassyBuildingSubTabs' => 0,
            'allyPageOverview' => 0,
            'villageStatisticsResources' => 0,
            'villageOverviewTroops' => 0,
            'auctions' => 0,
        ];
        $data['favorTabsRaw'] = [];
        $index = 0;
        foreach (array_keys($data['favorTabs']) as $key) {
            $rawToken = $favorTabsArr[$index] ?? '';
            $data['favorTabsRaw'][$key] = $rawToken;
            if ($rawToken !== '') {
                $data['favorTabs'][$key] = $rawToken;
            }
            $index++;
        }
        if (getDisplay("allowMultiVillageOnDifferentSessions")) {
            $kid = isset($_SESSION[$this->fixSessionPrefix("kid")]) ? (int)$_SESSION[$this->fixSessionPrefix("kid")] : $data['kid'];
        } else {
            $kid = $data['kid'];
        }
        if ($uid > 0 && $kid == 0) {
            if (getDisplay("allowMultiVillageOnDifferentSessions")) {
                $kid = $this->db->fetchScalar(
                    "SELECT kid FROM vdata WHERE owner=? ORDER BY pop DESC LIMIT 1",
                    (int)$data['id']
                );
                $_SESSION[$this->fixSessionPrefix("kid")] = $data['kid'] = $kid;
            } else {
                $kid = $this->db->fetchScalar(
                    "SELECT kid FROM vdata WHERE owner=? ORDER BY pop DESC LIMIT 1",
                    (int)$data['id']
                );
                $data['kid'] = $kid;
            }
            $this->db->run("UPDATE users SET kid=? WHERE id=?", [(int)$data['kid'], (int)$data['id']]);
        }
        if (isset($_GET['newdid']) && $_GET['newdid'] <> $kid) {
            $newdid = (int)$_GET['newdid'];
            $owner = (int)$this->db->fetchScalar("SELECT owner FROM vdata WHERE kid=?", (int)$newdid);
            if ($owner === (int)$data['id']) {
                if (getDisplay("allowMultiVillageOnDifferentSessions")) {
                    $kid = $_SESSION[$this->fixSessionPrefix("kid")] = $data['kid'] = $newdid;
                } else {
                    $kid = $data['kid'] = $newdid;
                    $this->db->run("UPDATE users SET kid=? WHERE id=?", [(int)$kid, (int)$data['id']]);
                }
            }
        }
        $owner = (int)$this->db->fetchScalar("SELECT owner FROM vdata WHERE kid=?", (int)$kid);
        if (!$owner || $owner <> (int)$data['id']) {
            if (getDisplay("allowMultiVillageOnDifferentSessions")) {
                $kid = $_SESSION[$this->fixSessionPrefix("kid")] = $data['kid'] = $userRow['kid'];
            } else {
                $kid = $data['kid'] = $userRow['kid'];
            }
        }
        $data['kid'] = $kid;
        $data['timezone'] = explode(",", $data['timezone']);

        $data['display'] = explode(",", $data['display']);
        // Records created before automatic completion refresh may not contain
        // the fifth display flag. Preserve an explicit player preference, but
        // let incomplete legacy vectors inherit the current default.
        $data['display'][4] = isset($data['display'][4]) ? (int)$data['display'][4] : 1;
        // Older player records predate the seasonal night image preference.
        // Keep their existing display settings and default the new preference on.
        $data['display'][6] = isset($data['display'][6]) ? (int)$data['display'][6] : 1;
        // Contextual help is an optional per-avatar preference appended to the
        // legacy display flags, so existing world databases need no migration.
        $data['display'][7] = isset($data['display'][7]) ? (int)$data['display'][7] : 0;
        // Bulk report deletion confirmation is also stored as an optional
        // per-avatar display flag for compatibility with existing worlds.
        $data['display'][8] = isset($data['display'][8]) ? (int)$data['display'][8] : 0;
        // Marketplace page size was not present in older display vectors.
        $data['display'][9] = isset($data['display'][9]) ? max(10, min(99, (int)$data['display'][9])) : 10;
        $data['allianceSettings'] = explode("|", $data['allianceSettings']);
        $data['autoComplete'] = explode(",", $data['autoComplete']);
        $this->data = $data;
        if (getCustom("serverIsFreeGold")) {
            $this->data['gift_gold'] = 9999999999999;
        }
        $this->endDisabledAdminSession();
        $this->checkAdmin();
    }

    /** A control panel account that was disabled while it was signed in loses the session on its next request. */
    private function endDisabledAdminSession(): void
    {
        if (AdminAccounts::isDisabled($this->getPlayerId())) {
            $this->logout();
            WebService::redirect('/');
        }
    }

    /**
     * @param mixed $uid
     * @param mixed $username
     * @param mixed $password
     * @param bool $isSitter
     */
    public function login($uid, $username, $password, $isSitter = false): void
    {
        // Every sign-in path (password form, lobby hand-off, social sign-in) ends here, so the
        // administrator second factor is enforced here rather than in each caller.
        if (AdminAccounts::isDisabled((int)$uid)) {
            AuditLog::record('sign-in-refused', 'disabled', 'A disabled administrator tried to sign in.', (int)$uid, (string)$username);
            return;
        }
        $twoFactor = TwoFactorGate::fromRequest();
        if (!$twoFactor->permitLogin((int)$uid, (bool)$isSitter)) {
            return;
        }
        $this->establishSession($uid, $username, $password, $isSitter);
        if ($isSitter) {
            $this->announceSitter((int)$uid, (string)$password);
        }
        if (TwoFactorGate::isAdminAccount((int)$uid)) {
            AuditLog::record('sign-in', '', $twoFactor->applies((int)$uid) ? 'with a second factor' : '', (int)$uid, (string)$username);
        }
        // A remember-me cookie would later restore an administrator without the second factor.
        if (!$isSitter && !$twoFactor->applies((int)$uid)) {
            RememberMe::issue((int)$uid);
        }
    }

    /** Tells the owner (if they opted in) which of their sitters signed in: the one whose password this is. */
    private function announceSitter(int $ownerUid, string $password): void
    {
        $slot = match ((new LoginModel())->checkUserOrSitterLogin($ownerUid, $password)) {
            2 => 1,
            3 => 2,
            default => 0,
        };
        $sitter = $slot === 0 ? 0 : (int)$this->db->fetchScalar('SELECT sit' . $slot . 'Uid FROM users WHERE id=?', [$ownerUid], 0);
        if ($sitter > 0) {
            EventMail::sitterSignedIn($ownerUid, $sitter);
        }
    }

    /**
     * @param mixed $uid
     * @param mixed $username
     * @param mixed $password
     * @param mixed $isSitter
     */
    private function establishSession($uid, $username, $password, $isSitter = false): void
    {
        session_regenerate_id(true);
        unset($_SESSION[WebService::fixSessionPrefix('csrf_token')]);
        if ($isSitter) {
            $this->db->run("UPDATE users SET last_login_time=? WHERE id=?", [time(), (int)$uid]);
        } else {
            IPTracker::addCurrentIP($uid);
            $this->db->run("UPDATE users SET last_login_time=?, last_owner_login_time=? WHERE id=?", [time(), time(), (int)$uid]);
        }
        unset($_SESSION[$this->fixSessionPrefix('admin_uid')]);
        unset($_SESSION[$this->fixSessionPrefix('admin_login_time')]);
        $_SESSION[$this->fixSessionPrefix('uid')] = $uid;
        $_SESSION[$this->fixSessionPrefix('user')] = $username;
        $_SESSION[$this->fixSessionPrefix('pw')] = $password;
        $_SESSION[$this->fixSessionPrefix('ip')] = WebService::ipAddress();
    }

    /**
     * @param int $type
     */
    public function logout($type = 1): bool
    {
        if ($this->isAdminInAnotherAccount() && $type == 1) {
            $adminUserId = (int)$_SESSION[$this->fixSessionPrefix('admin_uid')];
            $this->db->run("UPDATE users SET last_login_time=? WHERE id=?", [time(), $adminUserId]);

            $result = $this->db->fetchSingleRow("SELECT id, password FROM users WHERE id=?", $adminUserId);
            if ($result) {
                $_SESSION[$this->fixSessionPrefix('uid')] = $result['id'];
                $_SESSION[$this->fixSessionPrefix('pw')] = $result['password'];
                return false;
            }
        }
        $this->isLoggedIn = false;
        unset($_SESSION[$this->fixSessionPrefix('pw')]);
        unset($_SESSION[$this->fixSessionPrefix('uid')]);
        unset($_SESSION[WebService::fixSessionPrefix('allow_interruption')]);
        unset($_SESSION[WebService::fixSessionPrefix('csrf_token')]);
        RememberMe::revoke();
        return true;
    }

    public function isAdminInAnotherAccount(): bool
    {
        $id = $this->data['id'] ?? null;
        if ($id === null || AdminAccounts::isAdmin((int)$id)) {
            return false;
        }
        if (isset($_SESSION[$this->fixSessionPrefix('admin_uid')])) {
            return AdminAccounts::isAdmin((int)$_SESSION[$this->fixSessionPrefix('admin_uid')]) && $this->checkAdminIP();
        }
        return false;
    }


    public function checkForOverDueOnPayment(): void
    {
        // Self-hosted installations do not use legacy hosted-license enforcement.
    }


    /**
     * @param bool $onlyMain
     */
    public function checkAdminIP($onlyMain = false): bool
    {
        // Administrator access is controlled by authenticated administrator sessions.
        return true;
    }

    private function checkAdmin(): void
    {
        if ((AdminAccounts::isPanelOnly($this->getPlayerId()) || $this->getAdminUid() == 0) && !in_array(
            LOADED_PAGE,
            ['admin.php', 'login.php']
        )) {
            WebService::redirect("/admin.php");
        }
        // The admin dispatcher already redirects a non-staff request to the
        // public entry point.  Do not destroy a valid player session merely
        // because it reached /admin.php (for example through a stale link).
    }

    public function getPlayerId(): int
    {
        return (int)$this->get("id");
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        if (!$this->isLoggedIn) {
            return false;
        }

        return $this->data[$name];
    }

    /**
     * @return mixed
     */
    public function getAdminUid()
    {
        if ($this->isAdminInAnotherAccount()) {
            return $_SESSION[$this->fixSessionPrefix('admin_uid')];
        }
        return isset($_SESSION[$this->fixSessionPrefix('uid')]) ? $_SESSION[$this->fixSessionPrefix('uid')] : 1e9;
    }

    /**
     * @return mixed
     */
    public function isAdmin()
    {
        if ($this->isAdminSet) {
            return $this->isAdmin;
        }
        $admin_uid = $this->fixSessionPrefix('admin_uid');
        $isAdmin = (isset($this->data['id']) && AdminAccounts::isAdmin((int)$this->data['id']))
            || (isset($_SESSION[$admin_uid]) && AdminAccounts::isAdmin((int)$_SESSION[$admin_uid]));
        if ($isAdmin && !$this->checkAdminIP()) {
            //die("Access denied.");
        }
        $this->isAdminSet = true;
        $this->isAdmin = $isAdmin;
        return $this->isAdmin;
    }

    /**
     * @param int $uid
     */
    public function adminLogin($uid): void
    {
        if ($this->isAdmin()) {
            if ($uid != $this->getPlayerId()) {
                if (!$this->isReallyAdmin()) {
                    $this->logout();
                    WebService::redirect("?adminLogin=" . $uid);
                    return;
                }
                $password = $this->db->fetchScalar("SELECT password FROM users WHERE id=?", (int)$uid);
                if (!empty($password)) {
                    $_SESSION[$this->fixSessionPrefix('admin_uid')] = $_SESSION[$this->fixSessionPrefix('uid')];
                    $_SESSION[$this->fixSessionPrefix('uid')] = $uid;
                    $_SESSION[$this->fixSessionPrefix('pw')] = $password;
                    $_SESSION[$this->fixSessionPrefix('admin_login_time')] = time();
                    WebService::redirect("/dorf1.php");
                }
            }
        }
    }

    public function isReallyAdmin(): bool
    {
        return (AdminAccounts::isAdmin((int)$this->data['id']) || $this->data['access'] == 2);
    }

    public static function getCheckerInput(): string
    {
        return '<input type="hidden" name="' . self::getCheckerName() . '" value="' . self::getInstance()->getChecker() . '"/>';
    }

    public static function getCheckerName(): string
    {
        return 'mpvt_token';
    }

    public function getChecker(): string
    {
        return self::sessionToken();
    }

    /**
     * @return \Core\Session
     */
    public static function getInstance()
    {
        if (!(self::$_self instanceof self)) {
            self::$_self = new self();
            self::$_self->populateData();
        }
        return self::$_self;
    }

    /**
     * Constant-time comparison of a submitted token with the session token.
     *
     * @param mixed $submitted
     */
    public static function matchesChecker($submitted): bool
    {
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals(self::sessionToken(), trim($submitted));
    }

    public static function validateChecker(): bool
    {
        return self::matchesChecker($_REQUEST[self::getCheckerName()] ?? null);
    }

    /** claimAction() for the token carried in the request's form/query field. */
    public static function claimRequest(): bool
    {
        return self::claimAction($_REQUEST[self::getCheckerName()] ?? null);
    }

    /**
     * Validates the token of a state-changing game action and lets an identical request
     * through only once within ACTION_REPLAY_WINDOW seconds, so a double click or a resent
     * form does not repeat the action. Sessions are serialised per request, so the second
     * request always sees the first one's claim. Repeated calls within one request agree.
     * A page rendered in between (see pageRendered()) ends the guard: repeating the action
     * from a freshly opened page, such as the next level of the same building, is deliberate.
     *
     * @param mixed $submitted
     */
    public static function claimAction($submitted): bool
    {
        if (!self::matchesChecker($submitted)) {
            return false;
        }
        $fingerprint = sha1(($_SERVER['REQUEST_METHOD'] ?? '') . "\n" . ($_SERVER['REQUEST_URI'] ?? '') . "\n" . http_build_query($_POST));
        if (self::$claimedFingerprint === $fingerprint) {
            return true;
        }
        $key = WebService::fixSessionPrefix('last_action');
        $last = $_SESSION[$key] ?? null;
        if (is_array($last) && ($last[0] ?? null) === $fingerprint && microtime(true) - (float)($last[1] ?? 0) < self::ACTION_REPLAY_WINDOW) {
            return false;
        }
        $_SESSION[$key] = [$fingerprint, microtime(true)];
        self::$claimedFingerprint = $fingerprint;

        return true;
    }

    /**
     * A full page was rendered without an action of its own, so any later request comes from
     * a page the player has just seen and is not a double submit of the previous action.
     */
    public static function pageRendered(): void
    {
        if (self::$claimedFingerprint === null) {
            unset($_SESSION[WebService::fixSessionPrefix('last_action')]);
        }
    }

    public static function getCheckerForUrl(): string
    {
        return self::getCheckerName() . '=' . self::getInstance()->getChecker();
    }

    /**
     * @param int $permission
     * @return (int|string)|bool
     */
    public function checkSitterPermission($permission)
    {
        if ($this->isSitter() && $permission == self::CAN_CHANGE_SETTINGS) {
            return false;
        }
        if (!$this->isSitter()) {
            return true;
        }
        return $this->getSitterPermissions() & $permission;
    }

    public function isSitter(): bool
    {
        return $this->get("isSitter") == 1;
    }

    /**
     * @return mixed
     */
    public function getSitterPermissions()
    {
        return $this->get("sitterPermissions");
    }

    /**
     * @param mixed $permission
     * @return (int|string)|bool
     */
    public function hasAlliancePermission($permission)
    {
        if ($this->isSitter()) {
            return false;
        }

        return $this->data['alliance_role'] & $permission;
    }

    /**
     * @param mixed $ok
     */
    public function updatePublicMsg($ok): void
    {
        $this->db->run("UPDATE users SET ok=? WHERE id=?", [$ok, $this->data['id']]);
        $this->data['ok'] = $ok;
    }

    /**
     * @return mixed
     */
    public function getDisplay()
    {
        return $this->data['display'];
    }

    /**
     * @return mixed
     */
    public function getQuestBattle()
    {
        return $this->data['qst_battle'];
    }

    /**
     * @param mixed $value
     */
    public function setQuestBattle($value): void
    {
        $this->data['qst_battle'] = $value;
    }

    /**
     * @return mixed
     */
    public function getQuestWorld()
    {
        return $this->data['qst_world'];
    }

    /**
     * @param bool $zero
     */
    public function updatePackageCodeTry($zero = false): void
    {
        if ($zero) {
            $this->db->run("UPDATE users SET lastPkgCodeTry=?, pkgCodeTries=0 WHERE id=?", [
                time(),
                $this->getPlayerId()
            ]);
            return;
        }
        $this->db->run("UPDATE users SET lastPkgCodeTry=?, pkgCodeTries=pkgCodeTries+1 WHERE id=?", [
            time(),
            $this->getPlayerId()
        ]);
    }

    /**
     * The canonical locale code (a key of settings.availableLanguages) for this
     * request. Reading it never writes: a signed-in player's stored language
     * wins, then the validated language cookie, then the world default. The
     * only way to change the language is setLanguage().
     *
     * @return mixed
     */
    public function getLanguage()
    {
        if ($this->isValid() && $this->isInstalledLanguage($this->data['language'] ?? null)) {
            return $this->data['language'];
        }
        $cookie = $_COOKIE['travian-lang'] ?? null;
        if ($this->isInstalledLanguage($cookie)) {
            return $cookie;
        }

        return Config::getProperty("settings", "selectedLang");
    }

    /**
     * @param mixed $Lang
     */
    public function setLanguage($Lang): bool
    {
        if (!$this->isInstalledLanguage($Lang)) {
            return false;
        }
        $_COOKIE['travian-lang'] = $Lang;
        if (!headers_sent()) {
            $cookieParams = function_exists('travian_session_cookie_params') ? travian_session_cookie_params() : [];
            setcookie('travian-lang', $Lang, [
                'expires' => time() + 365 * 86400,
                'path' => '/',
                'secure' => !empty($cookieParams['secure']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        Config::getInstance()->settings->selectedLang = $Lang;
        if ($this->isValid()) {
            if (!$this->isAdminInAnotherAccount()) {
                $this->db->run("UPDATE users SET language=? WHERE id=?", [$Lang, $this->getPlayerId()]);
                Caching::getInstance()->delete("hero_sidebar_{$this->getPlayerId()}");
                Caching::getInstance()->delete(sprintf('%s:AllianceBox', $this->getPlayerId()));
                Caching::getInstance()->delete(sprintf('%s:InfoBox', $this->getPlayerId()));
            }
            $this->data['language'] = $Lang;
        }

        return true;
    }

    private function isInstalledLanguage(mixed $language): bool
    {
        return is_string($language)
            && $language !== ''
            && property_exists(Config::getProperty("settings", "availableLanguages"), $language);
    }

    public function isValid(): bool
    {
        return $this->isLoggedIn === true;
    }

    public function isEmailVerified(): bool
    {
        return $this->getPlayerId() <= 2 || AdminAccounts::isAdmin($this->getPlayerId()) || $this->get("email_verified") == 1;
    }

    /**
     * @param mixed $value
     */
    public function setQuestWorld($value): void
    {
        $this->data['qst_world'] = $value;
    }

    /**
     * @return mixed
     */
    public function getQuestEconomy()
    {
        return $this->data['qst_economy'];
    }

    /**
     * @param mixed $value
     */
    public function setQuestEconomy($value): void
    {
        $this->data['qst_economy'] = $value;
    }

    public function decreaseClicks(): void
    {
        $sessionClicks = SessionVar::getVariable("sessionClicks", 0, 10);
        SessionVar::setVariable('sessionClicks', --$sessionClicks, false);
    }

    public function isBannedClick(): bool
    {
        $blocked = SessionVar::getVariable("clickBlocked", 0, 0);
        if ($blocked >= time()) {
            return true;
        }
        $sessionClicks = SessionVar::getVariable("sessionClicks", 0, 10);
        $elapsedSeconds = SessionVar::getElapsedTimes("sessionClicks");
        $averageClicks = round($sessionClicks / (max($elapsedSeconds, 1)), 1);
        $result = $averageClicks >= Config::getAdvancedProperty("maxAverageClicksPerSecond");
        if ($result) {
            SessionVar::setVariable("clickBlocked", time() + 15);
        }
        return $result;
    }

    /**
     * @param mixed $raid
     * @param mixed $sendReinforcement
     * @param mixed $sendResources
     * @param mixed $buyOrSpendGold
     * @param mixed $sendORAnsserMessages
     * @param mixed $removeMessagesORReports
     * @param mixed $contributeAlliance
     */
    public function getSitterMask($raid, $sendReinforcement, $sendResources, $buyOrSpendGold, $sendORAnsserMessages, $removeMessagesORReports, $contributeAlliance): int
    {
        $mask = 0;
        if ($raid) {
            $mask |= self::SITTER_CAN_RAID;
        }
        if ($sendReinforcement) {
            $mask |= self::SITTER_CAN_SEND_REINFORCEMENT;
        }
        if ($sendResources) {
            $mask |= self::SITTER_CAN_SEND_RESOURCES;
        }
        if ($buyOrSpendGold) {
            $mask |= self::SITTER_CAN_BUY_OR_SPEND_GOLD;
        }
        if ($sendORAnsserMessages) {
            $mask |= self::SITTER_CAN_SEND_OR_ANSWER_MESSAGES;
        }
        if ($removeMessagesORReports) {
            $mask |= self::SITTER_CAN_REMOVE_ARCHIVE_MESSAGES_OR_REPORTS;
        }
        if ($contributeAlliance) {
            $mask |= self::SITTER_CAN_CONTRIBUTE_ALLIANCE;
        }
        return $mask;
    }

    /**
     * @param mixed $num
     * @return mixed
     */
    public function getSittersId($num)
    {
        return $this->data['sit' . $num . "Uid"];
    }

    /**
     * @param mixed $num
     * @param mixed $value
     */
    public function setSittersId($num, $value): void
    {
        $this->data['sit' . $num . "Uid"] = $value;
    }

    /**
     * @param mixed $num
     * @return mixed
     */
    public function getSittersPermissions($num)
    {
        return $this->data['sit' . $num . "Permissions"];
    }

    /**
     * @param mixed $num
     * @param mixed $value
     */
    public function setSittersPermissions($num, $value): void
    {
        $this->data['sit' . $num . "Permissions"] = $value;
    }


    /**
     * @return mixed
     */
    public function getSecureSessionKey()
    {
        if (!isset($this->data['session_key'])) {
            $this->data['session_key'] = sha1($this->data['id'] . $this->data['name'] . $this->data['password'] . $this->data['aid']);
        }

        return $this->data['session_key'];
    }

    public function getTransferGold(): float
    {
        return floor($this->get("bought_gold"));
    }

    /**
     * @return mixed
     */
    public function getBoughtGold()
    {
        return $this->get("bought_gold");
    }

    public function hasPublicMsg(): bool
    {
        return $this->data['ok'] == 1;
    }

    /**
     * @return mixed
     */
    public function getAjaxToken()
    {
        $token = $this->isValid() ? $this->data['ajax_token'] : (isset($_SESSION[$this->fixSessionPrefix('ajaxToken')]) ? $_SESSION[$this->fixSessionPrefix('ajaxToken')] : null);
        if (is_null($token) || empty($token)) {
            $token = $this->changeAjaxToken();
        }
        return $token;
    }

    /**
     * @return mixed
     */
    public function getPlayerUUID()
    {
        if ($this->isValid()) {
            return $this->get('uuid');
        }
        return null;
    }

    /**
     * @return mixed
     */
    public function changeAjaxToken()
    {
        $_SESSION[$this->fixSessionPrefix('ajaxToken')] = bin2hex(random_bytes(32));
        if ($this->isValid()) {
            $this->data['ajax_token'] = $_SESSION[$this->fixSessionPrefix('ajaxToken')];
            $this->db->run("UPDATE users SET ajax_token=? WHERE id=?", [
                $this->data['ajax_token'],
                $this->getPlayerId()
            ]);
        }
        return $_SESSION[$this->fixSessionPrefix('ajaxToken')];
    }

    public function getAllianceJoinTime(): int
    {
        return (int)$this->get("alliance_join_time");
    }

    public function getAllianceContribution(): int
    {
        return (int)$this->get("alliance_contributions");
    }

    /**
     * @param mixed $alliance_contributions
     */
    public function increaseAllianceContribution($alliance_contributions): void
    {
        $this->data['alliance_contributions'] += $alliance_contributions;
    }

    /**
     * @param mixed $aid
     */
    public function setAllianceId($aid): void
    {
        $this->data['aid'] = $aid;
        $quest = Quest::getInstance();
        $quest->setQuestBitwise('world', 8, 1);
    }

    public function hasGoldClub(): bool
    {
        return $this->get("goldclub") == 1;
    }

    /**
     * @param mixed $value
     */
    public function setGoldClub($value): void
    {
        $this->data['goldclub'] = $value;
    }

    /**
     * @return mixed
     */
    public function evasionStatus()
    {
        return $this->get("escape");
    }

    /**
     * @return mixed
     */
    public function getMapSettings()
    {
        return $this->get("mapMarkSettings");
    }

    /**
     * @param mixed $state
     */
    public function setEvasionStatus($state): void
    {
        if ($this->data['escape'] <> $state) {
            $this->data['escape'] = $state;
            $this->db->run("UPDATE users SET escape=? WHERE id=?", [$state, $this->getPlayerId()]);
        }
    }

    /**
     * @param mixed $resourceId
     */
    public function hasProductionBoost($resourceId): bool
    {
        return $this->get("b" . $resourceId) >= time();
    }

    /**
     * @param mixed $resourceId
     * @return mixed
     */
    public function productionBoostTill($resourceId)
    {
        return $this->get("b" . $resourceId) < time() ? 0 : $this->get("b" . $resourceId);
    }

    /**
     * @param mixed $resourceId
     * @param mixed $till
     */
    public function setProductionBoostTill($resourceId, $till): void
    {
        $this->data['b' . $resourceId] = $till;
    }

    /**
     * @return (array<mixed>|float|int)
     */
    public function getAvailableGold()
    { //means Gold - MasterBuilder Gold
        return $this->data['gift_gold'] + $this->data['bought_gold'];
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->get("name");
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->data['email'];
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email): void
    {
        $this->data['email'] = $email;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->data['name'] = $name;
    }

    /**
     * @return mixed
     */
    public function getTotalNameChanges()
    {
        return $this->data['total_name_changes'];
    }

    /**
     * @param mixed $value
     */
    public function setTotalNameChanges($value): void
    {
        $this->data['total_name_changes'] = $value;
    }

    public function getTotalPopulation(): int
    {
        return (int)$this->get("total_pop");
    }

    public function isAdminLoggedIn(): bool
    {
        return isset($_SESSION[$this->fixSessionPrefix('admin_uid')]) && AdminAccounts::isAdmin((int)$_SESSION[$this->fixSessionPrefix('admin_uid')]);
    }

    public function showImagesInReports(): bool
    {
        return $this->getDisplay()[0] == 0;
    }

    /**
     * @return mixed
     */
    public function getWeekAttackPoints()
    {
        return $this->get("week_attack_points");
    }

    /**
     * @return mixed
     */
    public function getWeekDefensePoints()
    {
        return $this->get("week_defense_points");
    }

    /**
     * @return mixed
     */
    public function getWeekRobbersPoints()
    {
        return $this->get("week_robber_points");
    }

    /**
     * @return mixed
     */
    public function getOldRank()
    {
        return $this->data['oldRank'];
    }

    /**
     * @return mixed
     */
    public function getSelectedVillageID()
    {
        return $this->data['kid'];
    }

    /**
     * @return (array<mixed>|float|int)
     */
    public function getGold()
    {
        return $this->data['gift_gold'] + $this->data['bought_gold'];
    }

    /**
     * @return mixed
     */
    public function getSilver()
    {
        return $this->data['silver'];
    }

    /**
     * @param mixed $value
     */
    public function setSilver($value): void
    {
        $m = new AuctionModel();
        $this->data['silver'] = $value + $m->getInBidSilver($this->data['id']);
    }

    /**
     * @return (float|int)
     */
    public function getAvailableSilver()
    {
        $m = new AuctionModel();
        $silver = $this->data['silver'] - $m->getInBidSilver($this->data['id']);

        return $silver;
    }

    /**
     * @return mixed
     */
    public function getNote()
    {
        return $this->data['note'];
    }

    /**
     * @return mixed
     */
    public function getUsedVacationDays()
    {
        return $this->data['vacationUsedDays'];
    }

    /**
     * @return mixed
     */
    public function getVacationTil()
    {
        return $this->data['vacationActiveTil'];
    }

    public function getCP(): float
    {
        return round($this->data['cp'] + ($this->data['cp_prod'] / 86400 * (time() - $this->data['lastupdate'])));
    }

    public function isInVacationMode(): bool
    {
        return $this->data['vacationActiveTil'] >= time();
    }

    /**
     * @param mixed $time
     */
    public function setVacationTill($time): void
    {
        $this->data['vacationActiveTil'] = $time;
    }

    /**
     * @param mixed $days
     */
    public function setVacationUsedDays($days): void
    {
        $this->data['vacationUsedDays'] += $days;
    }

    /**
     * @return mixed
     */
    public function getRace()
    {
        return $this->get("race");
    }

    /**
     * @return string|null
     */
    public function getRaceName()
    {
        switch ($this->get("race")) {
            case 1: return "roman";
            case 2: return "teuton";
            case 3: return "gaul";
            case 4: return "nature";
            case 5: return "natars";
            case 6: return "egyptian";
            case 7: return "hun";
            case 8: return "spartan";
            case 9: return "viking";
            default: return null;
        }
    }

    /**
     * @param string $name
     * @param int $value
     */
    public function set($name, $value): void
    {
        if (!$this->isLoggedIn) {
            return;
        }
        $this->data[$name] = $value;
    }

    /**
     * @return mixed
     */
    public function getSitterEmail()
    {
        return DB::getInstance()->fetchScalar("SELECT email FROM users WHERE id={$this->data['sitterUid']}");
    }

    /**
     * @return mixed
     */
    public function getSitterUID()
    {
        return $this->data['sitterUid'];
    }

    /**
     * @param mixed $timezone
     */
    public function setTimezone($timezone): void
    {
        $this->data['timezone'] = $timezone;
    }

    /**
     * @return mixed
     */
    public function getAllianceSettings()
    {
        return $this->data['allianceSettings'];
    }

    /**
     * @return mixed
     */
    public function getReportFilters()
    {
        if ($cache = Caching::getInstance()->get("reportFilters:" . self::reportFiltersCacheKey())) {
            $_SESSION['reportFiltersHex'] = $cache;
        } else {
            Caching::getInstance()->delete("reportFilters:" . self::reportFiltersCacheKey());
            Caching::getInstance()->set(
                "reportFilters:" . self::reportFiltersCacheKey(),
                $_SESSION['reportFiltersHex'] = explode(",", '0,0,0,7,31,31,255'),
                3 * 86400
            );
        }
        return $_SESSION['reportFiltersHex'];
    }

    private static function reportFiltersCacheKey(): string
    {
        return Session::getInstance()->getPlayerId() . ':' . self::reportFiltersKey();
    }

    /**
     * @return mixed
     */
    private static function reportFiltersKey()
    {
        $key = WebService::fixSessionPrefix("reportFilters");
        if (!isset($_COOKIE[$key])) {
            $_COOKIE[$key] = mt_rand(11111111, 99999999);
            setcookie($key, (string) $_COOKIE[$key], time() + 7 * 86400);
        }
        $int = (int)$_COOKIE[$key];
        if ($int > 99999999 || $int < 11111111) {
            unset($_COOKIE[$key]);
            return self::reportFiltersKey();
        }
        return $int;
    }

    /**
     * @param string $name
     * @param int $value
     * @param int $timeout
     */
    public static function setCookie($name, $value, $timeout = 0): void
    {
        $key = WebService::fixSessionPrefix($name);
        if ($timeout < 0) {
            unset($_COOKIE[$name]);
        } else {
            $_COOKIE[$key] = $value;
        }
        setcookie($key, (string) $value, $timeout > 0 ? time() + $timeout : $timeout);
    }

    /**
     * @param string $name
     * @param int|null $default
     * @return mixed
     */
    public static function getCookie($name, $default = null)
    {
        $name = WebService::fixSessionPrefix($name);
        if (array_key_exists($name, $_COOKIE)) {
            return $_COOKIE[$name];
        }
        return $default;
    }

    /**
     * @param mixed $reportFilters
     */
    public function setReportFilters($reportFilters): void
    {
        $_COOKIE['reportFiltersHex'] = $reportFilters;
        Caching::getInstance()->set(
            "reportFilters:" . self::reportFiltersCacheKey(),
            $_COOKIE['reportFiltersHex'],
            7 * 86400
        );
    }

    /**
     * @param mixed $timezone
     */
    public function setDisplay($timezone): void
    {
        $this->data['display'] = $timezone;
    }

    /**
     * @return mixed
     */
    public function getAutoComplete()
    {
        return $this->data['autoComplete'];
    }

    /**
     * @param mixed $timezone
     */
    public function setAutoComplete($timezone): void
    {
        $this->data['autoComplete'] = $timezone;
    }

    /**
     * @param mixed $settings
     */
    public function setAllianceSettings($settings): void
    {
        $this->data['allianceSettings'] = $settings;
    }

    public function isAllianceNotificationEnabled(): bool
    {
        return $this->data['allianceNotificationEnabled'] == 1;
    }

    /**
     * @param mixed $settings
     */
    public function setAllianceNotification($settings): void
    {
        $this->data['allianceNotificationEnabled'] = $settings;
    }

    /**
     * @return mixed
     */
    public function getSuccessAdventuresCount()
    {
        return $this->data['success_adventures_count'];
    }

    public function hasPlus(): bool
    {
        return $this->get("plus") >= time();
    }

    public function hasProtection(): bool
    {
        return $this->get("protection") >= time();
    }

    /**
     * @return mixed
     */
    public function protectionTill()
    {
        return $this->get("protection");
    }

    /**
     * @param mixed $protection
     */
    public function setProtection($protection): void
    {
        $this->data['protection'] = $protection;
    }

    /**
     * @param mixed $till
     */
    public function setPlusTill($till): void
    {
        $this->data['plus'] = $till;
    }

    /**
     * @return mixed
     */
    public function plusTill()
    {
        return $this->get("plus") < time() ? 0 : $this->get("plus");
    }

    /**
     * @param mixed $name
     * @param mixed $value
     */
    public function setFavoriteTab($name, $value): void
    {
        $this->data['favorTabs'][$name] = $value;
        $this->data['favorTabsRaw'][$name] = (string)$value;
        $this->updateFavoriteTabs();
    }

    public function updateFavoriteTabs(): void
    {
        $favorTabs = implode(",", $this->data['favorTabsRaw']);
        $this->db->run("UPDATE users SET favorTabs=? WHERE id=?", [$favorTabs, $this->getPlayerId()]);
    }

    /**
     * @return mixed
     */
    public function getRallyPointRecordsPerPage()
    {
        return $this->getDisplay()[2];
    }

    /**
     * @return mixed
     */
    public function getMarketplaceOffersPerPage()
    {
        return $this->getDisplay()[9];
    }

    /**
     * @return mixed
     */
    public function getReportsRecordsPerPage()
    {
        return $this->getDisplay()[1];
    }

    public function getDateFormat(): string
    {
        $timezone = $this->getTimezone();
        if ($timezone[1] == 0) {
            return 'd/m/y';
        } elseif ($timezone[1] == 1) {
            return 'm/d/y';
        } elseif ($timezone[1] == 2) {
            return 'd/m/y';
        } else {
            return 'y/m/d';
        }
    }

    /**
     * @return mixed
     */
    public function getTimezone()
    {
        return $this->data['timezone'];
    }

    /**
     * @return mixed
     */
    public function getKid()
    {
        if (getDisplay("allowMultiVillageOnDifferentSessions") && isset($_SESSION[$this->fixSessionPrefix("kid")])) {
            return $_SESSION[$this->fixSessionPrefix("kid")];
        }
        return $this->data['kid'];
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    public function getFavoriteTab($name)
    {
        if (!isset($this->data['favorTabs'][$name])) {
            return false;
        }
        if ($name == 'embassyBuildingSubTabs') {
            return \Controller\Build\EmbassyCtrl::TABS[$this->data['favorTabs'][$name]] ?? 'info';
        }
        return $this->data['favorTabs'][$name];
    }

    /**
     * Favourite tab the player explicitly picked, or null while nothing is
     * stored (a fresh account has no favourite, so no tab is marked).
     *
     * @param mixed $name
     */
    public function getStoredFavoriteTab($name): ?int
    {
        $token = $this->data['favorTabsRaw'][$name] ?? '';
        return $token === '' || !ctype_digit((string)$token) ? null : (int)$token;
    }

    public function getAllianceId(): int
    {
        return (int)$this->get("aid");
    }

    public function hasNewMessage(): bool
    {
        return $this->get("ok") == 1;
    }

    public function banned(): bool
    {
        return $this->get("access") == 0;
    }

    public function needValidation(): bool
    {
        return SessionVar::getVariable("reCaptchaValidation", true, 1200) == false;
    }

    /**
     * @param mixed $status
     */
    public function setValidationStatus($status): void
    {
        SessionVar::setVariable('reCaptchaValidation', $status);
    }
}
