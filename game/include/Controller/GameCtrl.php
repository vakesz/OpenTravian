<?php

namespace Controller;

use Core\Config;
use Core\Session;
use Model\SeasonPassModel;
use function is_my_ip;

class GameCtrl extends AbstractCtrl
{
    /** @var bool */
    public $isAdminReal = FALSE;
    /** @var mixed */
    public $isAdmin = FALSE;
    /** @var bool */
    public $isAdminInAccount = FALSE;
    /** @var null */
    protected $newVillagePrefix;

    public function __construct()
    {
        parent::__construct();
        if (!Session::getInstance()->isValid()) {
            $this->innerRedirect("LoginCtrl");
        }
        if (!($this instanceof InGameBannedClickPage) && Session::getInstance()->isBannedClick()) {
            $this->innerRedirect('InGameBannedClickPage');
        }
        $this->isAdmin = Session::getInstance()->isAdmin();
        $config = Config::getInstance();
        if (!$this->isAdmin && time() < $config->game->start_time) {
            $this->innerRedirect("LoginCtrl");
        }
        if ($config->dynamic->maintenance == TRUE && !$this->isAdmin && Session::getInstance()->getPlayerId() > 2) {
            $this->innerRedirect("InGameMaintenanceCtrl");
        }
        if (!$this->isAdmin && Session::getInstance()->hasPublicMsg() && !($this instanceof ResourceFieldsCtrl) && !($this instanceof PublicMsgCtrl)) {
            $this->innerRedirect("PublicMsgCtrl");
        }
        if (!$this->isAdmin && Session::getInstance()->needValidation() && !($this instanceof RecaptchaCtrl)) {
            $this->innerRedirect("RecaptchaCtrl");
        }

        // Season Pass daily-login progress is scoped to the local world DB.
        // Skip the Seasonal Rewards controller itself to avoid a double ensure.
        if (!($this instanceof SeasonalRewardsCtrl) && !Session::getInstance()->isAdminInAnotherAccount()) {
            (new SeasonPassModel())->onPlayerActivity((int)Session::getInstance()->getPlayerId());
        }
    }

    /**
     * @return mixed
     */
    public function render()
    {
        $output = '';
        $this->beforeRender();
        if ($this->view) {
            $output = $this->view->output();
            Session::pageRendered();
        }
        $this->afterRender();
        return $output;
    }

    protected function beforeRender()
    {
    }

    protected function afterRender()
    {
    }
}
