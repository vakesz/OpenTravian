<?php

declare(strict_types=1);

namespace Game\Map;

use Core\Database\DB;
use Core\Session;
use Model\AllianceModel;

/**
 * Finds a stored map flag or mark and decides whether the signed-in player owns it.
 * The reference client sends only the row id, so ownership comes from the row: a
 * player's own rows, or the alliance's rows for a member allowed to manage marks.
 */
final class MarkOwnership
{
    public const FLAG = 'flag';
    public const MARKER = 'marker';

    /**
     * @return array{owner: string, targetId: int, target: string}|null null when the row
     *         does not exist, is not of the requested kind or belongs to someone else
     */
    public static function resolve(int $id, string $kind): ?array
    {
        $row = DB::getInstance()->query("SELECT uid, aid, targetId, type FROM mapflag WHERE id=$id")->fetch_assoc();
        if (!is_array($row)) {
            return null;
        }
        $type = (int)$row['type'];
        if (($kind === self::FLAG) !== ($type === 2)) {
            return null;
        }
        $session = Session::getInstance();
        if ((int)$row['uid'] > 0 && (int)$row['uid'] === (int)$session->getPlayerId()) {
            return ['owner' => 'player', 'targetId' => (int)$row['targetId'], 'target' => $type === 0 ? 'player' : 'alliance'];
        }
        $aid = (int)$session->getAllianceId();
        if ($aid > 0 && (int)$row['aid'] === $aid && $session->hasAlliancePermission(AllianceModel::MANAGE_MARKS)) {
            return ['owner' => 'alliance', 'targetId' => (int)$row['targetId'], 'target' => $type === 0 ? 'player' : 'alliance'];
        }

        return null;
    }

    /** @param string $target "player" or "alliance": what the mark points at */
    public static function refreshCache(string $owner, string $target, int $targetId): void
    {
        $session = Session::getInstance();
        if ($owner === 'alliance') {
            Map::removeMapCacheForAlliance($session->getAllianceId(), $target, $targetId);
            return;
        }
        Map::removeMapCacheForPlayer($session->getPlayerId(), $target, $targetId);
    }
}
