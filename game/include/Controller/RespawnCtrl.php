<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\WebService;
use Core\Session;
use Game\Captcha\ActivationCaptcha;
use Game\Captcha\CaptchaProvider;
use Game\Tribes;
use Model\AvatarRespawnModel;
use Model\RegisterModel;
use resources\View\OutOfGameView;
use resources\View\PHPBatchView;

/**
 * /respawn — beginner-protection tribe/sector wizard (activation wizard).
 */
class RespawnCtrl extends GameCtrl
{
    /** @var list<int> */
    private array $availableTribes = [];
    private ?CaptchaProvider $captcha = null;

    public function __construct()
    {
        parent::__construct();

        $session = Session::getInstance();
        $model = new AvatarRespawnModel();
        if ($session->isSitter() || !$model->canRespawn((int) $session->getPlayerId())) {
            $this->redirect('/options/account');
        }

        $this->availableTribes = Tribes::playable();
        $this->captcha = ActivationCaptcha::provider();
        $this->view = new OutOfGameView();
        $this->view->vars['bodyCssClass'] = 'activate';
        $this->view->vars['contentCssClass'] = 'activate';

        if (WebService::isPost()) {
            $this->respond($this->submit($model, $session));
        }

        $this->view->vars['content'] = PHPBatchView::render('activation/wizard', [
            'tribes' => $this->availableTribes,
            'tribe' => $this->availableTribes[0],
            'sector' => $this->recommendedSector(),
            'recommendedSector' => $this->recommendedSector(),
            'action' => '/respawn',
            'confirmHandler' => 'respawn',
            'captcha' => $this->captcha,
        ]);
    }

    /**
     * The wizard's final POST: a JSON redirect on success, a message otherwise.
     *
     * @return array<mixed>
     */
    private function submit(AvatarRespawnModel $model, Session $session): array
    {
        $tribe = (int) ($_POST['tribe'] ?? 0);
        $sector = ['ne' => 'no', 'se' => 'so'][(string) ($_POST['sector'] ?? '')] ?? (string) ($_POST['sector'] ?? '');
        if (!Session::validateChecker() || !in_array($tribe, $this->availableTribes, true) || !in_array($sector, ['nw', 'sw', 'no', 'so'], true)) {
            return ['redirect' => '/respawn'];
        }
        if (!ActivationCaptcha::accepts($this->captcha, $_POST['captcha'] ?? null, (string) WebService::ipAddress())) {
            return ['message' => T('reCaptcha', 'Sorry you submitted wrong answer')];
        }

        $uid = (int) $session->getPlayerId();
        $kid = $model->respawn($uid, $tribe, $sector);
        if ($kid === false) {
            return ['message' => T('ActivationNew', 'unableToGenerateBase')];
        }
        $model->syncSessionAfterRespawn($uid, $kid, $tribe);

        return ['redirect' => '/dorf1.php'];
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

    private function recommendedSector(): string
    {
        static $sector = null;

        return $sector ??= (new RegisterModel())->getRecommendedSector();
    }
}
