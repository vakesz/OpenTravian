<?php

namespace Controller\Ajax\map;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Game\Map\MarkOwnership;
use Model\AllianceModel;

class flag extends AjaxBase
{
    /**
     * @return mixed
     */
    public function dispatch()
    {
        if (!$this->session->isValid()) {
            return $this->error('You must be logged in to manage map flags.', 403);
        }

        $data = $_POST;

        switch ($_SERVER['REQUEST_METHOD'] ?? '') {
            case 'DELETE':
                return $this->performDelete($data);
            case 'POST':
                return $this->performPost($data);
            case 'PATCH':
                return $this->performPatch($data);
            default:
                return $this->error('Unsupported map flag operation.', 405);
        }
    }

    /**
     * Deletes a flag (type "flag") or a player/alliance mark (type "marker").
     *
     * @param array<mixed, mixed> $data
     * @return mixed
     */
    public function performDelete($data)
    {
        $id = $this->positiveInt($data['id'] ?? null);
        $kind = $data['type'] ?? null;
        if ($id === null || ($kind !== MarkOwnership::FLAG && $kind !== MarkOwnership::MARKER)) {
            return $this->error('Invalid map flag data.');
        }
        $owned = MarkOwnership::resolve($id, $kind);
        if ($owned === null) {
            return $this->error(T('map', 'mark_not_found'));
        }

        $db = DB::getInstance();
        $session = Session::getInstance();
        if ($owned['owner'] === 'alliance') {
            $db->query('DELETE FROM mapflag WHERE aid=' . (int)$session->getAllianceId() . " AND id=$id");
        } else {
            $db->query('DELETE FROM mapflag WHERE uid=' . (int)$session->getPlayerId() . " AND id=$id");
        }
        if (!$db->affectedRows()) {
            return $this->error(T('map', 'mark_not_found'));
        }
        if ($kind === MarkOwnership::MARKER) {
            MarkOwnership::refreshCache($owned['owner'], $owned['target'], $owned['targetId']);
        }
        $this->response['result'] = true;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return mixed
     */
    public function performPost($data)
    {
        $owner = $this->owner($data);
        $x = filter_var($data['x'] ?? null, FILTER_VALIDATE_INT);
        $y = filter_var($data['y'] ?? null, FILTER_VALIDATE_INT);
        $color = filter_var($data['variant'] ?? null, FILTER_VALIDATE_INT);
        $text = trim(strip_tags((string)($data['text'] ?? '')));

        if ($owner === null || $x === false || $y === false || abs($x) > MAP_SIZE || abs($y) > MAP_SIZE) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'invalid_coordinates');
            return;
        }
        if ($owner === 'alliance' && !Session::getInstance()->hasAlliancePermission(AllianceModel::MANAGE_MARKS)) {
            return $this->error('You are not allowed to manage alliance flags.', 403);
        }

        $minimumColor = $owner === 'player' ? 0 : 10;
        $maximumColor = $owner === 'player' ? 10 : 20;
        if ($color === false || $color < $minimumColor || $color > $maximumColor) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'colour_does_not_exists');
            return;
        }
        if ($text === '') {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'please_resend_all_data');
            return;
        }
        if (strlen($text) > 50) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'message_to_long');
            return;
        }

        $kid = Formulas::xy2kid($x, $y);
        if ($kid < 0 || $kid > pow((2 * MAP_SIZE + 1), 2)) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'invalid_coordinates');
            return;
        }

        $db = DB::getInstance();
        $ownerColumn = $owner === 'player' ? 'uid' : 'aid';
        $ownerId = $owner === 'player' ? (int)Session::getInstance()->getPlayerId() : (int)Session::getInstance()->getAllianceId();
        $totalNow = (int)$db->fetchScalar("SELECT COUNT(id) FROM mapflag WHERE $ownerColumn=$ownerId AND type=2");
        if ($totalNow >= 5) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'mapFlagsLimitReached');
            return;
        }

        $escapedText = $db->real_escape_string($text);
        if ($owner === 'player') {
            $db->query("INSERT INTO mapflag (aid, uid, targetId, text, color, type) VALUES (0,$ownerId,$kid,'$escapedText',$color,2)");
        } else {
            $db->query("INSERT INTO mapflag (aid, uid, targetId, text, color, type) VALUES ($ownerId,0,$kid,'$escapedText',$color,2)");
        }

        $position = Formulas::kid2xy($kid);
        $this->response['text'] = $text;
        $this->response['index'] = (int)$color;
        $this->response['kid'] = (int)$kid;
        $this->response['position'] = $position;
        $this->response['x'] = $position['x'];
        $this->response['y'] = $position['y'];
        $this->response['dataId'] = $db->lastInsertId();
    }

    /**
     * @param array<mixed, mixed> $data
     * @return mixed
     */
    public function performPatch($data)
    {
        $id = $this->positiveInt($data['id'] ?? null);
        $index = filter_var($data['variant'] ?? null, FILTER_VALIDATE_INT);
        $text = trim(strip_tags((string)($data['text'] ?? '')));
        if ($id === null || $index === false) {
            return $this->error('Invalid map flag data.');
        }
        $owned = MarkOwnership::resolve($id, MarkOwnership::FLAG);
        if ($owned === null) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'mark_not_found');
            return;
        }

        $minimumColor = $owned['owner'] === 'player' ? 0 : 10;
        $maximumColor = $owned['owner'] === 'player' ? 10 : 20;
        if ($index < $minimumColor || $index > $maximumColor) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'colour_does_not_exists');
            return;
        }
        if ($text === '' || strlen($text) > 50) {
            $this->response['error'] = true;
            $this->response['message'] = $text === '' ? T('map', 'please_resend_all_data') : T('map', 'message_to_long');
            return;
        }

        $db = DB::getInstance();
        $escapedText = $db->real_escape_string($text);
        $db->query("UPDATE mapflag SET text='$escapedText', color=$index WHERE type=2 AND id=$id");
        if ($db->affectedRows()) {
            $this->response['result'] = true;
            return;
        }

        $this->response['error'] = true;
        $this->response['message'] = T('map', 'mark_not_found');
    }

    /**
     * @param array<mixed> $data
     * @return string|null
     */
    private function owner(array $data)
    {
        $owner = $data['owner'] ?? null;
        return $owner === 'player' || $owner === 'alliance' ? $owner : null;
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    private function positiveInt($value)
    {
        $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $result === false ? null : $result;
    }
}
