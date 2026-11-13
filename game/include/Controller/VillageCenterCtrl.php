<?php

namespace Controller;

use Core\Config;
use Core\Dispatcher;
use Core\Helper\VillagePerspective;
use Core\Helper\WebService;
use Core\Session;
use Game\Buildings\BuildingHelper;
use Game\Buildings\BuildingRearranger;
use Game\BuildRequest;
use Game\Formulas;
use Model\WonderOfTheWorldModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

use function htmlspecialchars;

class VillageCenterCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $this->view = new GameView();
        $this->view->vars['bodyCssClass'] = 'perspectiveBuildings village2';
        $this->view->vars['contentCssClass'] = 'village2';
        $this->view->vars['containerCssClass'] = null;
        if ($this->session->getPlayerId() <= 0) {
            return;
        }
        VillagePerspective::rememberBuildingsView();
        if (!$this->proc()) {
            return;
        }
        $village = $this->session->village;
        $maps = [];
        for ($i = 19; $i <= 40; ++$i) {
            if ($village->isWW() && in_array($i, [21, /*26,*/ 30, 31, 32])) {
                continue;
            }
            $index = $i;
            if ($village->isWW() && $i == 26) {
                $index = 99;
            }
            $title = $village->getFieldTitleAsString($index);
            $item_id = $village->getField($index)['item_id'];
            $level = $village->getField($index)['level'];
            $title = htmlspecialchars($title);
            $maps[] = [
                'index' => $i,
                "title" => $title,
                'name' => $item_id > 0 ? htmlspecialchars((string)T('Buildings', $item_id . '.title')) : '',
                'movable' => in_array($i, BuildingRearranger::movableSlots([$i => $item_id]), true),
                'item_id' => $item_id,
                'level' => $level,
                'levelColor' => $this->getBuildingConstruction($index),
                'class' => $this->getBuildingImgClass($index),
                // Ship overlays are painted under #background .behindContent .harbour (Gpack).
                'ships' => [],
            ];
        }
        $this->view->vars['behindContent'] = $this->renderBehindContent($village);
        $isShore = (new BuildingHelper())->isShoreVillage((int)$village->getKid());
        $this->view->vars['content'] = PHPBatchView::render('village-center/main', [
            'isWW' => $village->isWW(),
            'isShore' => $isShore,
            "race" => $this->session->getRace(),
            "raceName" => $this->session->getRaceName(),
            'onLoadBuildings' => Dispatcher::getInstance()->dispatch("onLoadBuildingsDorfCtrl", false),
            "maps" => $maps,
            'rearrange' => $this->rearrangeRequested($village),
            'checker' => $this->session->getChecker(),
        ]);
    }

    /**
     * Gpack harbour (and optional ship layers) sit in #background .behindContent.
     * The villageContent slot keeps the click SVG + level badge only.
     *
     * @param mixed $village
     */
    private function renderBehindContent($village): string
    {
        $html = '';
        $harborLevel = 0;
        for ($i = 19; $i <= 40; ++$i) {
            $field = $village->getField($i);
            if ((int)$field['item_id'] === \Game\Ships::HARBOR_GID && (int)$field['level'] > 0) {
                $harborLevel = (int)$field['level'];
                break;
            }
        }
        if ($harborLevel > 0) {
            $raceName = htmlspecialchars((string)$this->session->getRaceName(), ENT_QUOTES, 'UTF-8');
            $ships = '';
            foreach ($this->harborShipClasses() as $shipClass) {
                $ships .= '<div class="' . htmlspecialchars($shipClass, ENT_QUOTES, 'UTF-8') . '"></div>';
            }
            $html .= '<div class="harbour ' . $raceName . '">' . $ships . '</div>';
        }

        return $html;
    }

    /**
     * Rearrange mode needs a built Main Building and a village with the standard layout.
     *
     * @param mixed $village
     */
    private function rearrangeRequested($village): bool
    {
        if (($_GET['mode'] ?? null) !== 'rearrange' || $village->isWW()) {
            return false;
        }
        for ($i = BuildingRearranger::FIRST_MOVABLE_SLOT; $i <= BuildingRearranger::LAST_MOVABLE_SLOT; ++$i) {
            if ((int)$village->getField($i)['item_id'] === 15 && (int)$village->getField($i)['level'] >= 1) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] Gpack class of every ship type that has a ship at home */
    private function harborShipClasses(): array
    {
        $classes = [];
        foreach (\Game\Ships::homeShips((int)$this->session->village->getKid()) as $type => $count) {
            if ($count > 0) {
                $classes[] = \Game\Ships::key($type);
            }
        }

        return $classes;
    }

    private function proc(): bool
    {
        $village = $this->session->village;
        if (BuildRequest::handle($village)) {
            return $this->finishBuildRequest();
        }
        return true;
    }

    private function finishBuildRequest(): bool
    {
        if ($this->session->banned()) {
            new BannedCtrl($this->view->vars['contentCssClass'], $this->view->vars['content']);
            return false;
        }
        if (Config::getInstance()->dynamic->serverFinished) {
            new WinnerCtrl($this->view->vars['contentCssClass'], $this->view->vars['content']);
            return false;
        }

        // Post/redirect/get: a reload or back navigation must not repeat the build action.
        WebService::redirect('/dorf2.php');

        return true;
    }

    /**
     * @param int $k
     */
    public function getBuildingImgClass($k): string
    {
        $village = $this->session->village;
        if ($village->isWW() && $k == 99) {
            return 'ww g40 g40_' . WonderOfTheWorldModel::stage((int)$village->getField($k)['level']);
        }
        if ($village->getField($k)['item_id'] == 0) {
            if ($k == 39) {
                return 'g16e';
            }
            return "iso";
        }
        $res = 'g' . $village->getField($k)['item_id'] . ($village->isWW() && $k == 39 ? "e_ww" : '');
        if ($village->getField($k)['level'] == 0) {
            //$res .= 'b';
        }
        return $res;
    }

    /**
     * @param int $i
     */
    public function getBuildingConstruction($i): string
    {
        $village = $this->session->village;
        $max = Formulas::buildingMaxLvl($village->getField($i)['item_id'], $village->isCapital());
        if ($village->getField($i)['level'] >= $max || ($village->getField($i)['level'] + $village->getField($i)['upgrade_state']) == $max) {
            $color = 'maxLevel';
        } else {
            $item_id = $village->getField($i)['item_id'];
            $lvl = $village->getField($i)['level'] + $village->getField($i)['upgrade_state'] + 1;
            $cost = Formulas::buildingUpgradeCosts($item_id, $lvl);
            if (!$village->isWorkersBusy(false, $item_id == 40)['isBusy'] && $village->isResourcesAvailable($cost) && !$village->checkArtifactDependencies($item_id) && $village->checkDependencies($item_id, $lvl) == 0) {
                $color = 'good';
            } else {
                $color = 'notNow';
            }
        }
        $aid = $i == 99 ? 'ww' : 'aid' . $i;
        if ($village->getField($i)['upgrade_state']) {
            return 'colorLayer ' . "$color $aid underConstruction";
        }
        return 'colorLayer ' . "$color $aid";
    }
}
