<?php

use Core\Database\DB;
use Core\Helper\WebService;
use Model\RegisterModel;

class AddVillageCtrl
{
    public function __construct()
    {
        $params = [
            'uid' => is_string($_REQUEST['uid'] ?? null) && ctype_digit($_REQUEST['uid']) ? (int)$_REQUEST['uid'] : null,
            'fieldtype' => is_string($_REQUEST['fieldtype'] ?? null) && in_array($_REQUEST['fieldtype'], array_map('strval', range(1, 12)), true) ? (int)$_REQUEST['fieldtype'] : 3,
            'sector' => is_string($_REQUEST['sector'] ?? null) && in_array($_REQUEST['sector'], ['ne', 'se', 'nw', 'sw'], true) ? $_REQUEST['sector'] : 'se',
        ];
        if (!isServerFinished() && WebService::isPost()) {
            $m = new RegisterModel();
            $db = DB::getInstance();
            $result = $params['uid'] === null ? false : $db->run('SELECT id, race FROM users WHERE id=?', [$params['uid']])->get_result();
            if ($result && $result->num_rows) {
                $user = $result->fetch_assoc();
                $generate = $m->generateBase($params['sector'], $params['fieldtype']);
                if($generate){
                    $m->createNewVillage($params['uid'], $user['race'], $generate);
                    $params['result'] = 'Village created successfully.';
                } else {
                    $params['result'] = 'Failed to generate base.';
                }
            } else {
                $params['result'] = 'User not found.';
            }
        }
        Dispatcher::getInstance()->appendContent(Template::getInstance()->load($params, 'tpl/addVillage.tpl')->getAsString());
    }
}