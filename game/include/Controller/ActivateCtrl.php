<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Database\GlobalDB;
use Core\Helper\Mailer;
use Core\Helper\WebService;
use Core\Session;
use Game\Captcha\ActivationCaptcha;
use Game\Captcha\CaptchaProvider;
use Game\Formulas;
use Model\InfoBoxModel;
use Model\MessageModel;
use Model\RegisterModel;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;
use function getWorldUniqueId;

class ActivateCtrl extends OutOfGameCtrl
{
    /** @var array<mixed> */
    private $availableTribes  = [];
    private ?CaptchaProvider $captcha = null;
    /** @var string */
    private $token;
    /** @var mixed */
    private $activationRow    = [];

    public function __construct()
    {
        parent::__construct();
        $this->availableTribes = \Game\Tribes::playable();
        $this->captcha = ActivationCaptcha::provider();
        $config = Config::getInstance();
        if ($config->dynamic->maintenance == TRUE) {
            $this->redirect("/");
        }
        $this->token = $token = (string)(isset($_GET['token']) ? $_GET['token'] : ($_SESSION[WebService::fixSessionPrefix('token')] ?? ''));
        if (preg_match('/^[a-f0-9]{32,64}$/D', $token) !== 1) {
            $this->redirect("/");
        }
        $db = DB::getInstance();

        $statement = $db->run("SELECT * FROM activation WHERE token=? LIMIT 1", [$token]);
        $stmt = $statement ? $statement->get_result() : false;
        $this->activationRow = false;
        if($stmt && $stmt->num_rows){
            $this->activationRow = $stmt->fetch_assoc();
        }
        if ($this->activationRow === FALSE) {
            $this->redirect("/");
        }
        if ($config->game->start_time > time()) {
            $this->redirect("/login.php?registered=true");
        }
        $_SESSION[WebService::fixSessionPrefix('token')] = $token;
        $this->view = new OutOfGameView();
        $this->view->vars['bodyCssClass'] = 'activate';
        $this->view->vars['contentCssClass'] = 'activate';
        if (WebService::isPost()) {
            $this->respond($this->submit());
        }
        $this->wizard();
    }

    private function wizard(): void
    {
        $this->view->vars['content'] = PHPBatchView::render('activation/wizard', [
            'tribes' => $this->availableTribes,
            'tribe' => $this->availableTribes[0],
            'sector' => $this->recommendedSector(),
            'recommendedSector' => $this->recommendedSector(),
            'action' => '/activate.php',
            'confirmHandler' => 'activate',
            'captcha' => $this->captcha,
        ]);
    }

    private function recommendedSector(): string
    {
        static $sector = null;

        return $sector ??= (new RegisterModel())->getRecommendedSector();
    }

    /**
     * The wizard's final POST: a JSON redirect on success, a message otherwise.
     *
     * @return array<mixed>
     */
    private function submit(): array
    {
        if (!Session::validateChecker()) {
            return ['redirect' => '/activate.php'];
        }
        $tribe = (int)($_POST['tribe'] ?? 0);
        $sector = (string)($_POST['sector'] ?? '');
        if (!in_array($tribe, $this->availableTribes, true) || !in_array($sector, ['nw', 'sw', 'no', 'so', 'ne', 'se'], true)) {
            return ['redirect' => '/activate.php'];
        }
        if (!ActivationCaptcha::accepts($this->captcha, $_POST['captcha'] ?? null, (string)WebService::ipAddress())) {
            return ['message' => T('reCaptcha', 'Sorry you submitted wrong answer')];
        }

        return $this->completeRegistration($tribe, $sector);
    }

    /**
     * @param array<mixed> $payload
     */
    private function respond(array $payload): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }

    /** @return array{redirect?:string,message?:string} */
    private function completeRegistration(int $tribe, string $sector): array
    {
        $register = new RegisterModel();
        $kid = $register->generateBase($sector);
        if (!$kid) {
            return ['message' => T("ActivationNew", "unableToGenerateBase")];
        }
        $globalDB = GlobalDB::getInstance();
        $activationEnabled = false;
        $server = $globalDB->query("SELECT activation FROM gameServers WHERE id=" . getWorldUniqueId());
        if ($server->num_rows) {
            $server = $server->fetch_assoc();
            $activationEnabled = $server['activation'] == 1;
        }
        $db = DB::getInstance();
        $db->begin_transaction();
        $db->run("DELETE FROM activation WHERE id=? AND token=?", [
            (int)$this->activationRow['id'],
            $this->token,
        ]);
        $uid = $register->addUser($this->activationRow['name'],
            $this->activationRow['password'],
            $this->activationRow['email'],
            $tribe,
            $kid,
            1,
            true,
            false,
            !empty($this->activationRow['newsletter']));
        if (!$uid) {
            $db->rollback();
            $db->query("UPDATE available_villages SET occupied=0 WHERE kid=" . (int)$kid);
            return ['message' => T("ActivationNew", "unableToGenerateBase")];
        }
        $result = $register->createBaseVillage($uid, $this->activationRow['name'], $tribe, $kid);
        if (!$result) {
            $db->rollback();
            return ['message' => T("ActivationNew", "unableToGenerateBase")];
        }
        // A social sign-up's email was verified by the provider: treat it like
        // an activated account (no verification mail or reminder).
        $pendingSocial = \Game\SocialAuth\PendingSignup::fromGlobals();
        $socialVerified = $pendingSocial->isBoundTo($this->token) && ($pendingSocial->current()->emailVerified ?? false);
        if ($activationEnabled || $socialVerified) {
            $db->query("UPDATE users SET email_verified=1 WHERE id=$uid");
        } else {
            $this->sendVerificationEmail($this->activationRow['name'], $uid, $this->activationRow['email']);
            (new InfoBoxModel())->addInfo($uid, false, 16, '', 0, time() + Formulas::getTotalProtectionTime(time()));
        }
        $register->populateUserRank($uid);
        (new \Model\MessageModel())->sendWelcomeMessage($uid, (string) $this->activationRow['name']);
        if (!empty($this->activationRow['refUid'])) {
            $this->processReferences($this->activationRow['refUid'], $uid);
        }
        $level = Config::getProperty("game", "firstVillageCreationFieldsLevel");
        if ($level > 0) {
            $_SESSION[WebService::fixSessionPrefix('needQuestCheck')] = true;
        }
        $db->commit();
        // Link the provider identity only now that the user row is committed
        // (the repository uses its own connection). A failure leaves a normal
        // account that can recover through password reset; it is not fatal.
        if ($socialVerified && !$pendingSocial->finalize((int)$uid, $this->token, new \Game\SocialAuth\DbIdentityRepository())) {
            logError('Social identity could not be linked for user ' . (int)$uid);
        }
        // Use the canonical authenticated-session path: it rotates the PHP
        // session, clears stale account context, refreshes login timestamps,
        // records the IP, and issues the durable remember-me token.
        Session::getInstance()->login(
            $uid,
            $this->activationRow['name'],
            $this->activationRow['password']
        );
        $m = new MessageModel();
        $text = T("Global", "registration_startgame_message_message");
        if (!empty($text)) {
            $text = str_replace(['[name]'], [$this->activationRow['name']], $text);
            $m->sendMessage(0, $uid, T("Global", "registration_startgame_message_subject"), $text);
        }
        return ['redirect' => '/dorf1.php?finished=1'];
    }

    /**
     * @param mixed $playerName
     * @param int $uid
     * @param mixed $email
     */
    private function sendVerificationEmail($playerName, $uid, $email): void
    {
        $db = DB::getInstance();
        $code = get_random_string(mt_rand(18, 28));
        $db->run("INSERT INTO `activation_progress` (`uid`, `email`, `activationCode`, `time`) VALUES (?, ?, ?, ?)", [(int)$uid, (string)$email, $code, time()]);
        $id = $db->lastInsertId();
        $params = [
            'playerName'        => $playerName,
            'email'             => $email,
            'verification_url'  => WebService::get_base_url() . 'verify-url.php?code=' . sprintf("%s-%s",
                    $id,
                    $code),
            'verification_code' => $code,
        ];
        Mailer::sendEmail($email, T("EVerify", "Email verification"), PHPBatchView::render("verify/email", $params));
    }

    /**
     * @param mixed $refUid
     * @param int $uid
     */
    public function processReferences($refUid, $uid): void
    {
        if (empty($refUid)) return;
        $referrerPlayerId = (int)filter_var($refUid, FILTER_SANITIZE_NUMBER_INT);
        /** SERVER ID | REF_UID | UID */
        DB::getInstance()->query("INSERT INTO player_references (ref_uid, uid) VALUES ($referrerPlayerId, {$uid})");
    }
}
