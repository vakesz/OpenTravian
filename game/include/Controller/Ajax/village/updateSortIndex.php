<?php

namespace Controller\Ajax\village;

use Core\Caching\Caching;
use Core\Caching\ProfileCache;
use Core\Database\DB;
use Core\Helper\StringChecker;
use Core\Session;
use Core\Village;
use Game\Formulas;
use Game\NoticeHelper;
use Model\MapPageModel;
use Controller\Ajax\AjaxBase;

class updateSortIndex extends AjaxBase
{
    public function dispatch(): void
    {
        if (!isset($_GET['did']) or !isset($_POST['to'])) {
            $this->response['success'] = false;
            $this->response['message'] = T('map', 'please_resend_all_data');
            return;
        }

        if (!Session::getInstance()->isValid()) {
            $this->response['success'] = false;
            return;
        }

        if (Session::getInstance()->isSitter()) {
            $this->response['success'] = false;
            $this->response['message'] = 'As a sitter you don`t have access to this part.';
            return;
        }

        if (!is_scalar($_GET['did']) || !ctype_digit((string)$_GET['did'])
            || !is_scalar($_POST['to']) || !ctype_digit((string)$_POST['to'])) {
            $this->response['success'] = false;
            $this->response['message'] = T('map', 'please_resend_all_data');
            return;
        }

        $did = (int)$_GET['did'];
        $km = new MapPageModel();
        if ($km->getVillageOwner($did) <> Session::getInstance()->getPlayerId()) {
            $this->response['success'] = false;
            $this->response['message'] = T('map', 'please_resend_all_data');
            return;
        }
        $to = (int)$_POST['to'];
        if ($to < 0 || $to > 100) {
            $this->response['success'] = false;
            $this->response['message'] = T('map', 'please_resend_all_data');
            return;
        }

        $cache = new ProfileCache(Session::getInstance()->get("profileCacheVersion"));
        $cache->reValidateProfileVillages(Session::getInstance()->getPlayerId());
        $db = DB::getInstance();
        if (!$db->run('UPDATE vdata SET seq=? WHERE kid=?', [$to, $did])) {
            $this->response['success'] = false;
            $this->response['message'] = 'Could not update the village sort order.';
            return;
        }
        $this->response['success'] = true;
    }
}
