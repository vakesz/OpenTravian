<?php
namespace Controller\Build;
use Controller\AnyCtrl;
use Core\Database\DB;
use Core\Helper\TimezoneHelper;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\GoldHelper;
use function isServerFinished;
use resources\View\PHPBatchView;
class BreweryCtrl extends AnyCtrl
{
    /**
     * @param mixed $index
     */
    public function __construct($index)
    {
        parent::__construct();

        $this->view = new PHPBatchView("build/building/Brewery");
        $this->view->vars['buildingIndex'] = $index;
        $this->view->vars['festivalDuration'] = Formulas::getFestivalDuration();
        $this->view->vars['festivalResources'] = Formulas::getFestivalResources();
        if(!$this->isFestivalRunning() && Session::claimAction($_GET['z'] ?? null)) {
            if(Session::getInstance()->banned()) {
                $this->innerRedirect("InGameBannedPage");
            } else if(isServerFinished()) {
                $this->innerRedirect("InGameWinnerPage");
            } else if(Session::getInstance()->isInVacationMode()) {
                $this->redirect('/options/vacation');
            } else {
                $cost = $this->view->vars['festivalResources'];
                if(Village::getInstance()->isResourcesAvailable($cost)) {
                    $db = DB::getInstance();
                    $festivalVillage = Village::getInstance();
                    $festival = time() + $this->view->vars['festivalDuration'];
                    $festivalKid = $festivalVillage->getKid();
                    $started = $festivalVillage->transaction(static function () use ($festivalVillage, $cost, $festival, $festivalKid): bool {
                        $running = (int)DB::getInstance()->fetchScalar('SELECT festival FROM vdata WHERE kid=?', [$festivalKid]);
                        if ($running > time() || !$festivalVillage->spend($cost)) {
                            return false;
                        }
                        $update = DB::getInstance()->run('UPDATE vdata SET festival=? WHERE kid=?', [$festival, $festivalKid]);

                        return $update !== false;
                    });
                    if ($started) {
                        Village::getInstance()->setFestival($festival);
                    }
                }
            }
        }
        $this->view->vars['isFestival'] = $this->isFestivalRunning();
        $this->view->vars['npcButton'] = (new GoldHelper())->getExchangeResourcesButtonByCost($this->view->vars['festivalResources']);
        $this->view->vars['contractLinkButton'] = $this->getButton($index);
        if($this->isFestivalRunning()) {
            $this->view->vars['timeLeft'] = appendTimer(Village::getInstance()->getFestival() - time());
            $this->view->vars['endat'] = TimezoneHelper::date("H:i", Village::getInstance()->getFestival());
        }
    }

    /**
     * @param mixed $id
     * @return mixed
     */
    private function getButton($id)
    {
        if($this->isFestivalRunning()) {
            return '<span class="errorMessage">' . T("inGame", "one celebration is running") . '</span>';
        }
        $cost = Formulas::getFestivalResources();
        if(Village::getInstance()->isResourcesAvailable($cost)) {
            return getButton(["type" => "button", "class" => "textButtonV1 green ", "version" => "textButtonV1", "onclick" => "window.location.href = '/build.php?id=$id&z=" . Session::getInstance()->getChecker() . "'; return false;", 'value' => T("inGame", "run celebration"),], ["data" => ["type" => "button", "version" => "textButtonV1"]], T("inGame", "run celebration"));
        }
        $contract = Village::getInstance()->contractResourcesLink($cost);
        return $contract['text'];
    }

    private function isFestivalRunning(): bool
    {
        return Village::getInstance()->getFestival() > time();
    }
}
