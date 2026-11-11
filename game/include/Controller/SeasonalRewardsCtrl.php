<?php

declare(strict_types=1);

namespace Controller;

use Core\Session;
use Model\SeasonPassModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

use function T;

/** Player-facing Season Pass / Seasonal Rewards screen (local-world scope). */
class SeasonalRewardsCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();

        $uid = (int)$this->session->getPlayerId();
        $model = new SeasonPassModel();
        $model->onPlayerActivity($uid);

        $tab = (string)($_GET['t'] ?? 'seasonPass');
        if (!in_array($tab, ['seasonPass', 'shards', 'governors'], true)) {
            $tab = 'seasonPass';
        }
        $governorKey = (string)($_GET['g'] ?? '');
        if ($governorKey !== '' && !in_array($governorKey, SeasonPassModel::GOVERNOR_KEYS, true)) {
            $governorKey = '';
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $checker = (string)($_POST['c'] ?? '');
            $action = (string)($_POST['action'] ?? '');
            $validChecker = Session::claimAction($checker);
            $message = 'unavailable';

            if ($validChecker) {
                if ($action === 'claimReward') {
                    $rewardId = filter_var($_POST['rewardId'] ?? null, FILTER_VALIDATE_INT);
                    $message = $rewardId !== false && $model->claimReward($uid, (int)$rewardId)
                        ? 'claimed'
                        : 'unavailable';
                } elseif ($action === 'rerollMission') {
                    $missionId = filter_var($_POST['missionId'] ?? null, FILTER_VALIDATE_INT);
                    $message = $missionId !== false && $model->rerollRandomMission($uid, (int)$missionId)
                        ? 'rerolled'
                        : 'rerollFailed';
                } elseif ($action === 'grantPremium' && $this->session->isAdmin()) {
                    $message = $model->grantPremium($uid) ? 'premium' : 'unavailable';
                }
            }

            $returnTab = (string)($_POST['t'] ?? $tab);
            if (!in_array($returnTab, ['seasonPass', 'shards', 'governors'], true)) {
                $returnTab = 'seasonPass';
            }
            $query = [
                't' => $returnTab,
                'message' => $message,
            ];
            $returnGovernor = (string)($_POST['g'] ?? '');
            if ($returnTab === 'governors' && in_array($returnGovernor, SeasonPassModel::GOVERNOR_KEYS, true)) {
                $query['g'] = $returnGovernor;
            }
            $this->redirect('/seasonalRewards?' . http_build_query($query));
        }

        $board = $model->getPlayerBoard($uid);
        // Has no active in-world Season Pass at present and sends the
        // direct route back to the village. Do the same until an operator has
        // activated a local season; the optional local feature must not expose
        // an invented empty-state screen in the normal player experience.
        if ($board === null) {
            $this->redirect('/dorf1.php');
        }
        $theme = is_array($board) ? (string)($board['season']['theme'] ?? 'green') : 'green';

        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = T('SeasonPass', 'title', 'Season Pass');
        $this->view->vars['bodyCssClass'] = 'perspectiveResources seasonalRewards seasonalRewardsEnabled';
        $this->view->vars['containerCssClass'] = 'contentPage';
        $this->view->vars['contentCssClass'] = 'seasonalRewards';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;

        $view = new PHPBatchView('seasonalRewards/main');
        $view->vars = [
            'tab' => $tab,
            'theme' => $theme,
            'board' => $board,
            'model' => $model,
            'checker' => (string)$this->session->getChecker(),
            'isAdmin' => $this->session->isAdmin(),
            'message' => (string)($_GET['message'] ?? ''),
            'governorKey' => $governorKey,
        ];
        $this->view->vars['content'] = $view->output();
    }
}
