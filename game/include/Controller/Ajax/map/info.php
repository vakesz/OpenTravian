<?php
namespace Controller\Ajax\map;

use Controller\Ajax\AjaxBase;
use Controller\MapCtrl;
use Model\MapModel;

class info extends AjaxBase
{
    public function dispatch(): void
    {
        if (!$this->session->isValid()) {
            return;
        }

        if (!isset($_POST['data']) || !is_array($_POST['data'])) {
            $this->error('Map block data is required.');
            return;
        }

        $mapModel = new MapModel();
        $this->response['elements'] = [];
        $this->response['blocks'] = [];
        $zoomLevel = isset($_POST['zoomLevel']) ? (int)$_POST['zoomLevel'] : 1;
        $zoomLevel = max(1, min(3, $zoomLevel));
        MapCtrl::getAttackReinforceOtherElements($this->response);
        foreach ($_POST['data'] as $block) {
            if (!is_array($block) || !isset($block['position']) || !is_array($block['position'])) {
                continue;
            }
            $position = $block['position'];
            $tx0 = filter_var($position['x0'] ?? null, FILTER_VALIDATE_INT);
            $tx1 = filter_var($position['x1'] ?? null, FILTER_VALIDATE_INT);
            $ty0 = filter_var($position['y0'] ?? null, FILTER_VALIDATE_INT);
            $ty1 = filter_var($position['y1'] ?? null, FILTER_VALIDATE_INT);
            if ($tx0 === false || $tx1 === false || $ty0 === false || $ty1 === false) {
                continue;
            }
            $block = $mapModel->getMapBlock($tx0, $ty0, $tx1, $ty1, $zoomLevel, FALSE);
            if ($block === FALSE) {
                $version = 0;
            } else {
                $version = $block[1];
            }
            $this->response['blocks'][$tx0][$ty0][$tx1][$ty1]['version'] = (int)$version;
        }
    }
}
