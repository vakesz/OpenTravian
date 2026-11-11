<?php

namespace Controller\Ajax;

use Game\Account\DeletionLock;
use Core\Config;
use Core\Database\DB;
use Core\Session;
use Core\Village;
use resources\View\PHPBatchView;

class exchangeResourcesPopup extends AjaxBase
{
    /**
     * @return mixed
     */
    public function dispatch()
    {
        $session = Session::getInstance();
        $village = Village::getInstance();
        if (!$session->checkSitterPermission(Session::SITTER_CAN_BUY_OR_SPEND_GOLD)) {
            return $this->error(T('inGame', 'sitterNoPermForGold'), 403);
        }
        if (($session->banned() && $session->getPlayerId() > 2) || Config::getInstance()->dynamic->serverFinished) {
            return $this->error(T('inGame', 'bannedSmallPage'), 403);
        }
        $deletion = DeletionLock::refusal((int)$session->getPlayerId());
        if ($deletion !== null) {
            return $this->error($deletion, 403);
        }
        if ($village->isWW()) {
            return $this->error(T('npc', 'disabled_in_ww'));
        }
        if ($session->getTotalPopulation() < 40) {
            return $this->error(T('GoldHelper', 'exchangeResources.error_low_pop'));
        }
        if (!$village->hasMarketPlace()) {
            return $this->error(T('GoldHelper', 'exchangeResources.error_no_marketplace'));
        }

        $resources = array_map('floor', $village->getCurrentResources());
        $total = array_sum($resources);
        if ($total < 50) {
            return $this->error(T('GoldHelper', 'exchangeResources.error_low_resources'));
        }

        $defaultValues = $_POST['defaultValues'] ?? [];
        $seed = [];
        foreach (['r1', 'r2', 'r3', 'r4'] as $key) {
            $value = is_array($defaultValues) ? ($defaultValues[$key] ?? 0) : 0;
            $seed[] = is_scalar($value) && preg_match('/^\d{1,12}$/', (string)$value) ? (int)$value : 0;
        }
        // empty defaultValues → desired inputs start at 0 (not current stock).
        $desired = array_sum($seed) > 0
            ? \Game\NpcExchange::scale($seed, (int) $total)
            : [0, 0, 0, 0];

        $capacity = DB::getInstance()->run(
            'SELECT maxstore, maxcrop FROM vdata WHERE kid=?',
            [(int)$village->getKid()]
        );
        $result = $capacity ? $capacity->get_result() : false;
        $limits = $result ? $result->fetch_assoc() : false;
        if (!$limits) {
            return $this->error(T('npc', 'exchange_failed'));
        }

        $view = new PHPBatchView('gold/exchangeResourcesPopup');
        $view->vars['currentResources'] = $resources;
        $view->vars['desiredResources'] = $desired;
        $view->vars['resourceLimits'] = [(int)$limits['maxstore'], (int)$limits['maxstore'], (int)$limits['maxstore'], (int)$limits['maxcrop']];
        $view->vars['total'] = $total;
        $view->vars['goldCost'] = (int)Config::getInstance()->gold->exchangeResourcesGold;
        $view->vars['villageId'] = (int)$village->getKid();
        // dialog has no chrome title for this popup.
        $this->response['title'] = '';
        $this->response['html'] = $view->output();
    }
}
