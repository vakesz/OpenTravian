<?php

namespace Controller;

use Core\Database\DB;
use Core\Session;
use Game\Hero\HeroFace;
use Game\Hero\SessionHero;
use Model\HeroFaceModel;
use Core\Locale;
use resources\View\GameView;
use resources\View\PHPBatchView;

class HeroFaceCtrl extends GameCtrl
{
    public function __construct()
    {
        parent::__construct();
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = '';
        $this->view->vars['bodyCssClass'] = 'hero hero_editor';
        $this->view->vars['containerCssClass'] = 'contentPage heroPageShell';
        // Reference appearance shell: body hero_editor, content heroV2Appearance.
        $this->view->vars['contentCssClass'] = 'heroV2 heroV2Appearance';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        $this->view->vars['titleInHeader'] = Session::getInstance()->getName() . ' - ' . T('HeroInventory', 'level') . ' ';
        $this->view->vars['titleInHeader'] .= $this->session->hero->getLevel();
        $navigation = new PHPBatchView('hero/navigation');
        $navigation->vars['selectedTab'] = 3;
        $view = new PHPBatchView("hero/heroFace");
        $f = new HeroFace();
        $db = DB::getInstance();
        $uid = (int)Session::getInstance()->getPlayerId();
        $statement = $db->run('SELECT * FROM face WHERE uid=?', [$uid]);
        $heroFace = $statement ? $statement->get_result()->fetch_assoc() : null;
        if (!is_array($heroFace)) {
            throw new \RuntimeException('The hero appearance could not be loaded.');
        }
        $appearanceModel = new HeroFaceModel();
        $encodedFace = $f->normalizeEmptySlots($f->encodeAttribute($heroFace));
        $view->vars['heroFace'] = $heroFace;
        $view->vars['heroFaceDataHtml'] = $f->getAllHeroFaceAttributesHtml(
            $heroFace['gender'],
            $encodedFace,
            $appearanceModel->getUnlockedOptions($uid)
        );
        $view->vars['HeroFaceJson'] = [
            'element' => 'heroEditor',
            'command' => 'heroEditor',
            'attributes' => $encodedFace,
        ];
        $view->vars['heroRace'] = (int)$this->session->getRace();
        $this->view->vars['content'] .= '<div id="heroV2" class="contentV2">'
            . $navigation->output()
            . $view->output()
            . '</div>';
    }
}
