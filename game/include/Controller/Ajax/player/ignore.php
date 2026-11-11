<?php
namespace Controller\Ajax\player;
use Controller\Ajax\AjaxBase;

use Core\Session;
use Game\ProfileActions;
use Core\Locale;
use Model\MessageModel;
use resources\View\PHPBatchView;

class ignore extends AjaxBase
{
    public function dispatch(): void
    {
        switch($_POST['method']) {
            case 'render_profile_action_buttons':
                $this->renderPlayerMessageIgnoreButtons();
                break;
            case 'render_ignore_list':
                $this->renderIgnoreList();
                break;
            case 'ignore_target_player':
                $params = (array)$_POST['params'];
                $m = new MessageModel();
                $m->ignorePlayer(Session::getInstance()->getPlayerId(), (int)$params['targetPlayer']);
                break;
            case 'stop_ignore_target_player':
                $params = (array)$_POST['params'];
                $m = new MessageModel();
                $m->unIgnorePlayer(Session::getInstance()->getPlayerId(), (int)$params['targetPlayer']);
                break;
        }
        if(isset($_POST['renderPlayerMessageIgnoreButtons']) && $_POST['renderPlayerMessageIgnoreButtons']) {
            $this->renderPlayerMessageIgnoreButtons();
        }
        if(isset($_POST['renderIgnoreList']) && $_POST['renderIgnoreList']) {
            $this->renderIgnoreList();
        }
    }

    private function renderPlayerMessageIgnoreButtons(): void
    {
        $this->response['result'] = '';
        $params = $_POST['params'];
        if($params['targetPlayer'] == Session::getInstance()->getPlayerId()) {
            if(Session::getInstance()->isSitter() || Session::getInstance()->banned()) {
                $this->response['result'] = '<a class="arrow disabled">' . T("Profile", "Edit Profile") . '</a>';
            } else {
                $this->response['result'] = '<a class="arrow" href="/profile/edit">' . T("Profile", "Edit Profile") . '</a>';
            }
        } else {
            if($params['targetPlayer'] > 1) {
                $actions = ProfileActions::render((int) $params['targetPlayer']);
                $this->response['result'] = $actions['result'];
                $this->response['notification'] = $actions['notification'];
            }
            if(Session::getInstance()->isAdmin()) {
                $this->response['result'] .= '<br />';
                $this->response['result'] .= '<a href="' . profileUrl(null, ['adminLogin' => $params['targetPlayer']]) . '" title="' . T("Profile", "Login to player account") . '">' . T("Profile", "Login to player account") . '.</a>';
            }
        }
    }

    private function renderIgnoreList(): void
    {
        $columns = "\n";
        $m = new MessageModel();
        $ignoreList = $m->getIgnoreList(Session::getInstance()->getPlayerId());
        if($ignoreList->num_rows) {
            $view = new PHPBatchView("ajax/ignoreListColumns");
            $view->vars['ignoreList'] = [];
            for($i = 1; $i <= 20; ++$i) {
                $view->vars['ignoreList'][$i] = '<td class="end">&nbsp;</td><td>&nbsp;</td><td class="end">&nbsp;</td>';
            }
            $i = 0;
            while($row = $ignoreList->fetch_assoc()) {
                ++$i;
                $view->vars['ignoreList'][$i] = '';
                $view->vars['ignoreList'][$i] .= '<td class="end"><button type="submit" class="icon " onclick="unignoreUser(' . $row['ignore_id'] . ');"><img src="img/spacer.gif" class="del unignore-user" alt="del unignore-user"></button></td>';
                $view->vars['ignoreList'][$i] .= '<td><a href="' . profileUrl((int) $row['ignore_id']) . '">' . $m->getPlayerName($row['ignore_id']) . '</a></td>';
                $view->vars['ignoreList'][$i] .= '<td class="end">&nbsp;</td>';
            }
            $columns = $view->output();
        }
        $this->response['result'] = '<div id="ignore-list-columns">' . $columns . '</div>' . "\n<div class=\"clear\"></div>\n";
        $count = "\u{202D}\u{202D}" . $ignoreList->num_rows . "\u{202C}/\u{202D}20\u{202C}\u{202C}";
        $hint = T("Profile", 'To ignore messages from a specific player, go to its profile and click on "Ignore"!');
        $this->response['result'] .= "<div>\n\t<table>\n\t\t<tfoot>\n\t\t\t<tr>\n\t\t\t\t<td colspan=\"6\">\n"
            . "                    {$count}\t\t\t\t</td>\n\t\t\t</tr>\n\t\t\t<tr>\n\t\t\t\t<td colspan=\"6\">\n"
            . "\t\t\t\t\t{$hint}\t\t\t\t</td>\n\t\t\t</tr>\n\t\t</tfoot>\n\t</table>\n</div>\n<div class=\"clear\"></div>";
    }
}
