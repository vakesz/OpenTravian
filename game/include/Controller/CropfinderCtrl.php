<?php

declare(strict_types=1);

namespace Controller;

use Core\Helper\PageNavigator;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Core\Locale;
use Model\CropFinderModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

class CropfinderCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $this->view = new GameView();
        // meta-page token (not village perspectiveResources).
        $this->view->vars['bodyCssClass'] = 'cropfinder';
        $this->view->vars['contentCssClass'] = 'cropfinder';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        if (!$this->session->hasGoldClub()) {
            // Without Gold club the reference renders the page shell with an empty content area.
            return;
        }
        $this->view->vars['titleInHeader'] = T('cropFinder', 'title');
		
        $settings = new PHPBatchView("cropfinder/settings");
        // Form radios + pager use `typ` (classic Travian param). Accept string values only.
        $typ = $_REQUEST['typ'] ?? null;
        if (is_string($typ) && in_array($typ, ['all', '9', '15'], true)) {
            $settings->vars['typ'] = $typ;
        } else {
            $settings->vars['typ'] = 'all';
        }
        if (isset($_REQUEST['bonus_getreide']) && in_array($_REQUEST['bonus_getreide'],
                ["all", 25, 50, 75, 100, 125, 150,])) {
            $settings->vars['bonus_getreide'] = $_REQUEST['bonus_getreide'];
        } else {
            $settings->vars['bonus_getreide'] = '25';
        }
        if (isset($_REQUEST['only_free'])) {
            $settings->vars['only_free'] = (int)$_REQUEST['only_free'] === 1;
        } else {
            $settings->vars['only_free'] = FALSE;
        }
        if (isset($_REQUEST['x']) && isset($_REQUEST['y'])) {
            $settings->vars['x'] = filter_var($_REQUEST['x'], FILTER_SANITIZE_NUMBER_INT);
            $settings->vars['y'] = filter_var($_REQUEST['y'], FILTER_SANITIZE_NUMBER_INT);
        } else {
            $xy = Formulas::kid2xy(Village::getInstance()->getKid());
            $settings->vars['x'] = $xy['x'];
            $settings->vars['y'] = $xy['y'];
        }
        $this->view->vars['content'] .= $settings->output();
        if (isset($_REQUEST['bonus_getreide']) && isset($_REQUEST['x']) && isset($_REQUEST['y']) && isset($_REQUEST['typ'])) {
            $m = new CropFinderModel();
            $page = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_SANITIZE_NUMBER_INT) : 1;
            $croppers = $m->getCroppers((int)$page,
                (int)$settings->vars['x'],
                (int)$settings->vars['y'],
                $settings->vars['typ'],
                $settings->vars['bonus_getreide'],
                $settings->vars['only_free']);
            $croplands = new PHPBatchView("cropfinder/table");
            $croplands->vars['tbody'] = '';
            $direction = strtolower(getDirection());
            while ($row = $croppers->fetch_assoc()) {
                // MySQLi returns SQRT() as a string; PHP 8+ round() requires int|float.
                $distance = round((float)$row['distance'], 1);
                $croplands->vars['tbody'] .= ' <tr>';
                $croplands->vars['tbody'] .= '<td class="dist">' . $distance . '</td>';
                $croplands->vars['tbody'] .= '<td class="coords"><a class="" href="/karte.php?x=' . $row['x'] . '&amp;y=' . $row['y'] . '"><span class="coordinates coordinatesWrapper coordinatesAligned coordinates' . $direction . '"><span class="coordinateX">(' . coordinateNumberHTML($row['x']) . '</span><span class="coordinatePipe">|</span><span class="coordinateY">' . coordinateNumberHTML($row['y']) . ')</span></span>‎</a></td>';
                $croplands->vars['tbody'] .= '<td class="typ">' . ($row['fieldtype'] == 1 ? '9' : '15') . ' ' . T("cropFinder", "cropper") . '</td>';
                $croplands->vars['tbody'] .= '<td class="oase"><div class="inlineIconList resourceWrapper"><div class="inlineIcon resources"><i class="r4"></i><span class="value ">&#8237;+&#8237;'.$row['crop_percent'].'&#8236;%&#8236;</span></div></div></td>';
                $croplands->vars['tbody'] .= '<td class="owned"><a href="/karte.php?d=' . $row['id'] . '">' . $m->getCroplandOccupiedBy($row) . '</a></td>';
                $croplands->vars['tbody'] .= '<td>' . $m->getAllianceTag($row) . '</td>';
            }
            if (!$croppers->num_rows) {
                $croplands->vars['tbody'] = '<tr><td colspan="6" class="noData">' . T("cropFinder", "noRows") . '</td></tr>';
            }
            $prefix = [];
            $prefix['x'] = $settings->vars['x'];
            $prefix['y'] = $settings->vars['y'];
            $prefix['bonus_getreide'] = $settings->vars['bonus_getreide'];
            $prefix['only_free'] = $settings->vars['only_free'] ? 1 : 0;
            $prefix['typ'] = $settings->vars['typ'];
            $totalSize = $m->getTotalSize($settings->vars['typ'], $settings->vars['bonus_getreide'], $settings->vars['only_free']);
            $pages = new PageNavigator((int)$page, (int)$totalSize, 50, $prefix, '/cropfinder.php');
            $croplands->vars['pages'] = $pages->get();
            $this->view->vars['content'] .= $croplands->output();
        }
    }
}
