<?php

use Core\Database\DBI;

class ActivationListCtrl
{
    /** @var \Core\Database\DBI */
    private $db;
    public function __construct()
    {
        $this->db = DBI::getInstance();
        if (!isServerFinished() && ($_REQUEST['section'] ?? null) === 'del' && is_string($_REQUEST['id'] ?? null)) {
            $this->db->run("DELETE FROM activation WHERE id=?", (int)$_REQUEST['id']);
        }
        $result = $this->db->run("SELECT * FROM activation ORDER BY id DESC");
        $params['total'] = $this->db->fetchScalar("SELECT COUNT(id) FROM activation");
        $params['content'] = null;
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $params['content'] .= '<tr>';
            $params['content'] .= '<td class="center"><a href="admin.php?action=activationList&id=' . (int)$row['id'] . '&section=del">Delete</a></td>';
            $params['content'] .= '<td class="center">' . \Game\AdminUi::esc($row['name']) . '</td>';
            $params['content'] .= '<td class="center">' . \Game\AdminUi::esc($row['email']) . '</td>';
            $link = '/activate.php?token=' . rawurlencode((string)$row['token']);
            $params['content'] .= '<td class="center"><a href="' . $link . '">Activation</a></td>';
            $params['content'] .= '</tr>';
        }
        if (!$result->rowCount()) {
            $params['content'] .= '<tr><td colspan="4" class="center adminMuted">No pending activations.</td></tr>';
        }
        \Dispatcher::getInstance()->appendContent(\Template::getInstance()->load($params, 'tpl/activationList.tpl')->getAsString());
    }
}