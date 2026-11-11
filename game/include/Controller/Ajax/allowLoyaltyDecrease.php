<?php

declare(strict_types=1);

namespace Controller\Ajax;

use Core\Database\DB;
use Core\Session;

/** Lets the player allow or forbid loyalty drops by administrators of their alliance: body {villageId, state}. */
final class allowLoyaltyDecrease extends AjaxBase
{
    public function dispatch(): void
    {
        $session = Session::getInstance();
        if (!$session->isValid()) {
            $this->error('You must be logged in to change this setting.', 403);
            return;
        }
        if ($session->isSitter()) {
            $this->error('As a sitter you don`t have access to this part.', 403);
            return;
        }
        // The ajax dispatcher stringifies the JSON body: true arrives as "1", false as "".
        $villageId = $_POST['villageId'] ?? null;
        $state = $_POST['state'] ?? null;
        if (!is_string($villageId) || !ctype_digit($villageId) || !in_array($state, ['1', '', '0'], true)) {
            $this->error(T('map', 'please_resend_all_data'));
            return;
        }
        $db = DB::getInstance();
        if ((int) $db->fetchScalar('SELECT COUNT(kid) FROM vdata WHERE kid=? AND owner=?', [(int) $villageId, $session->getPlayerId()], 0) !== 1) {
            $this->error('You tried to change another player\'s village!', 403);
            return;
        }
        $db->run('UPDATE vdata SET allowLoyaltyDecrease=? WHERE kid=?', [$state === '1' ? 1 : 0, (int) $villageId]);
    }
}
