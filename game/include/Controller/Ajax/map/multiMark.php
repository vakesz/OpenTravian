<?php

namespace Controller\Ajax\map;

use Controller\Ajax\AjaxBase;
use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Game\Map\MarkOwnership;
use Model\AllianceModel;

class multiMark extends AjaxBase
{
    /**
     * @return mixed
     */
    public function dispatch()
    {
        if (!$this->session->isValid()) {
            return $this->error('You must be logged in to manage map marks.', 403);
        }

        $data = $_POST;

        // Marks are deleted through map/flag with type "marker", like the reference.
        switch ($_SERVER['REQUEST_METHOD'] ?? '') {
            case 'POST':
                return $this->performPost($data);
            case 'PATCH':
                return $this->performPatch($data);
            default:
                return $this->error('Unsupported map mark operation.', 405);
        }
    }

    /**
     * @param array<mixed, mixed> $data
     * @return mixed
     */
    public function performPost($data)
    {
        $owner = $this->owner($data);
        $type = $this->targetType($data);
        $x = filter_var($data['x'] ?? null, FILTER_VALIDATE_INT);
        $y = filter_var($data['y'] ?? null, FILTER_VALIDATE_INT);
        $color = filter_var($data['variant'] ?? null, FILTER_VALIDATE_INT);
        if ($owner === null || $type === null || $x === false || $y === false || abs($x) > MAP_SIZE || abs($y) > MAP_SIZE) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'invalid_coordinates');
            return;
        }
        if (!$this->mayManageOwner($owner)) {
            return $this->error('You are not allowed to manage alliance marks.', 403);
        }
        if ($color === false || $color < 0 || $color > 9) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'colour_does_not_exists');
            return;
        }

        $kid = Formulas::xy2kid($x, $y);
        if ($kid < 0 || $kid > pow((2 * MAP_SIZE + 1), 2)) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'invalid_coordinates');
            return;
        }

        $db = DB::getInstance();
        $tileResult = $db->query("SELECT oasistype, occupied FROM wdata WHERE id=$kid");
        if (!$tileResult || !$tileResult->num_rows || !($tile = $tileResult->fetch_assoc()) || !(int)$tile['occupied']) {
            if ($tileResult) {
                $tileResult->free();
            }
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'invalid_coordinates');
            return;
        }
        $tileResult->free();

        $ownerColumn = $owner === 'player' ? 'uid' : 'aid';
        $ownerId = $owner === 'player' ? (int)Session::getInstance()->getPlayerId() : (int)Session::getInstance()->getAllianceId();
        $totalNow = (int)$db->fetchScalar("SELECT COUNT(id) FROM mapflag WHERE $ownerColumn=$ownerId AND type IN (0, 1)");
        if ($totalNow >= 10) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'mapMarksLimitReached');
            return;
        }

        $playerId = (int)$db->fetchScalar($tile['oasistype'] ? "SELECT owner FROM odata WHERE kid=$kid" : "SELECT owner FROM vdata WHERE kid=$kid");
        if ($playerId < 1) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'invalid_coordinates');
            return;
        }

        $targetId = $playerId;
        if ($type === 'player') {
            $targetName = (string)$db->fetchScalar("SELECT name FROM users WHERE id=$playerId");
        } else {
            $targetId = (int)$db->fetchScalar("SELECT aid FROM users WHERE id=$playerId");
            if ($targetId < 1) {
                $this->response['error'] = true;
                $this->response['message'] = T('map', 'no_alliance_map_mark_error');
                return;
            }
            $targetName = (string)$db->fetchScalar("SELECT tag FROM alidata WHERE id=$targetId");
        }

        $storedType = $type === 'player' ? 0 : 1;
        if ($owner === 'player') {
            $db->query("INSERT INTO mapflag (aid, uid, targetId, text, color, type) VALUES (0,$ownerId,$targetId,'',$color,$storedType)");
        } else {
            $db->query("INSERT INTO mapflag (aid, uid, targetId, text, color, type) VALUES ($ownerId,0,$targetId,'',$color,$storedType)");
        }

        $insertId = $db->lastInsertId();
        if (!$insertId) {
            return $this->error('The map mark could not be created.', 500);
        }
        $this->response['owner'] = $owner;
        $this->response['color'] = (int)$color;
        $this->response['text'] = strip_tags($targetName);
        $this->response['position'] = Formulas::kid2xy($kid);
        $this->response['markId'] = $targetId;
        $this->response['dataId'] = $insertId;
        MarkOwnership::refreshCache($owner, $type, $targetId);
    }

    /**
     * @param array<mixed, mixed> $data
     * @return mixed
     */
    public function performPatch($data)
    {
        $id = $this->positiveInt($data['id'] ?? null);
        $color = filter_var($data['variant'] ?? null, FILTER_VALIDATE_INT);
        if ($id === null || $color === false) {
            return $this->error('Invalid map mark data.');
        }
        if ($color < 0 || $color > 9) {
            $this->response['error'] = true;
            $this->response['message'] = T('map', 'colour_does_not_exists');
            return;
        }
        $owned = MarkOwnership::resolve($id, MarkOwnership::MARKER);
        if ($owned === null) {
            $this->markNotFound();
            return;
        }

        $db = DB::getInstance();
        $db->query('UPDATE mapflag SET color=' . (int)$color . " WHERE type IN (0, 1) AND id=$id");
        if (!$db->affectedRows()) {
            $this->markNotFound();
            return;
        }
        MarkOwnership::refreshCache($owned['owner'], $owned['target'], $owned['targetId']);
        $this->response['result'] = true;
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
     * @param array<mixed> $data
     * @return string|null
     */
    private function targetType(array $data)
    {
        $type = $data['type'] ?? null;
        return $type === 'player' || $type === 'alliance' ? $type : null;
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

    /**
     * @param mixed $owner
     */
    private function mayManageOwner($owner): bool
    {
        return $owner !== 'alliance' || Session::getInstance()->hasAlliancePermission(AllianceModel::MANAGE_MARKS);
    }

    private function markNotFound(): void
    {
        $this->response['error'] = true;
        $this->response['message'] = T('map', 'mark_not_found');
    }
}
