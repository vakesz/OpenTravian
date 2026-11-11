<?php

namespace Controller;

use Core\Caching\Caching;
use Core\Helper\WebService;
use Core\Session;
use Core\Locale;
use Model\LinksModel;
use resources\View\GameView;
use resources\View\PHPBatchView;

class LinkListCtrl extends GameCtrl
{
    /** @var \resources\View\PHPBatchView|null */
    private $content = null;
    public const MAX_LINKS = 25;
    private function action(): void
    {
        $m = new LinksModel();
        //editing...
        $reCache = false;
        foreach ($_POST as $key => $pos) {
            if (is_string($key) && $key !== 'nrNew' && substr($key, 0, 2) == 'nr' && is_scalar($pos)) {
                $id = substr($key, 2);
                $name = $_POST['linkname' . $id] ?? null;
                $url = $_POST['linkziel' . $id] ?? null;
                if (!is_string($name) || !is_string($url)) {
                    continue;
                }
                if (empty($name) || empty($url)) {
                    if ($m->deleteLink(Session::getInstance()->getPlayerId(), (int)$id)) {
                        $reCache = true;
                    }
                } else {
                    if ($m->modifyLink(Session::getInstance()->getPlayerId(), (int)$id, $name, $url, (int)$pos)) {
                        $reCache = true;
                    }
                }
            }
        }
        //new links.
        $total = $m->getPlayerLinksCount(Session::getInstance()->getPlayerId());
        $newPositions = $_POST['nrNew'] ?? [];
        $newNames = $_POST['linknameNew'] ?? [];
        $newUrls = $_POST['linkzielNew'] ?? [];
        if (is_array($newPositions) && is_array($newNames) && is_array($newUrls)) {
            foreach ($newPositions as $key => $pos) {
                $name = $newNames[$key] ?? null;
                $url = $newUrls[$key] ?? null;
                if (!is_scalar($pos) || !is_string($name) || !is_string($url) || empty($name) || empty($url)) {
                    continue;
                }
                if ($total < self::MAX_LINKS && $m->addLink(Session::getInstance()->getPlayerId(), $name, $url, (int)$pos)) {
                    $reCache = true;
                    $total++;
                }
            }
        }
        if ($reCache) {
            $memcache = Caching::getInstance();
            $playerId = Session::getInstance()->getPlayerId();
            $memcache->delete(['links_' . $playerId, 'links_v2_' . $playerId, 'links_v3_' . $playerId]);
        }
    }

    public function __construct()
    {
        parent::__construct();
        if (!Session::getInstance()->hasPlus()) {
            $this->redirect('/dorf1.php');
        }
        $this->view = new GameView();
        $this->view->vars['titleInHeader'] = T('links', 'Link list');
        $this->view->vars['bodyCssClass'] = 'linklist';
        $this->view->vars['contentCssClass'] = 'linklist';
        $this->view->vars['showHeaderBar'] = true;
        $this->view->vars['showCloseButton'] = true;
        if (WebService::isPost()) {
            $this->action();
        }
        $m = new LinksModel();
        $links = $m->getPlayerLinks(Session::getInstance()->getPlayerId());
        $this->content = new PHPBatchView('linklist/main');
        $this->content->vars['lastNumber'] = $links->num_rows;
        $this->content->vars['links'] = [];
        $i = 0;
        while ($row = $links->fetch_assoc()) {
            $this->content->vars['links'][] = $row;
            ++$i;
        }
        $this->content->vars['recommend_html'] = '';
        foreach ((array) T('links', 'recommenced_links') as $key => $row) {
            $this->content->vars['recommend_html'] .=
                '<tr id="recommendedLinksRow' .
                $key .
                '">
                    <td class="nr">
                        <input class="text" type="text" name="nr" value="' .
                $i .
                '" size="1" maxlength="3" disabled="disabled">
                    </td>
                    <td class="nam">
                        <input class="text" type="text" name="linkname" value="' .
                $row['name'] .
                '" maxlength="30" disabled="disabled">
                    </td>
                    <td class="link">
                        <input class="text" type="text" name="linkziel" value="' .
                $row['url'] .
                '" maxlength="255" disabled="disabled">
                    </td>
                    <td class="add">
                        <button type="button" class="addElement " onclick="takeOverRecommendedLink(\'' .
                $key .
                '\')"></button>
                    </td>
                </tr>';
        }
        $this->view->vars['content'] .= $this->content->output();
    }
}
