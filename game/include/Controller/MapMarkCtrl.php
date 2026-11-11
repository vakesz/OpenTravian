<?php

namespace Controller;

use Core\Config;
use Core\Database\DB;
use Core\Session;
use Game\Formulas;
use Game\Map\ImageMapCache;
use Model\MapModel;

class MapMarkCtrl extends AnyCtrl
{
    /** @var mixed */
    private $block;
    /** @var int|null */
    private $image_size;

    public function __construct()
    {
        $tx0 = isset($_REQUEST['tx0']) && is_numeric($_REQUEST['tx0']) ? (int)$_REQUEST['tx0'] : null; //x
        $tx1 = isset($_REQUEST['tx1']) && is_numeric($_REQUEST['tx1']) ? (int)$_REQUEST['tx1'] : null; //right
        $ty0 = isset($_REQUEST['ty0']) && is_numeric($_REQUEST['ty0']) ? (int)$_REQUEST['ty0'] : null; //y
        $ty1 = isset($_REQUEST['ty1']) && is_numeric($_REQUEST['ty1']) ? (int)$_REQUEST['ty1'] : null; //bottom
        if (!is_numeric($tx0) || !is_numeric($tx1) || !is_numeric($ty0) || !is_numeric($ty1)) {
            return;
        }
        // The overlay only reads the player's session: release its lock before rendering,
        // so the player's other requests do not queue behind the image.
        $session = Session::getInstance();
        session_write_close();
        if (!$session->isValid()) {
            $this->renderEmptyOverlay();
            return;
        }
        $this->renderImage($tx0, $ty0, $tx1, $ty1);
    }

    private function renderEmptyOverlay(): void
    {
        $image = imagecreatetruecolor(600, 600);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        header('Content-Type: image/png');
        header('Cache-Control: no-cache');
        imagepng($image);
        imagedestroy($image);
    }

    /**
     * @param int $tx0
     * @param int $ty0
     * @param int $tx1
     * @param int $ty1
     */
    public function renderImage($tx0, $ty0, $tx1, $ty1): void
    {
        $width = $height = 600;
        $this->block = imagecreatetransparent($width, $height);
        $count = $this->blockCount($tx0, $tx1) * $this->blockCount($ty0, $ty1);
        switch ($count) {
            case 100:
                $this->image_size = 60;
                $zoomLevel = 1;
                break;
            case 400:
                $this->image_size = 30;
                $zoomLevel = 2;
                break;
            case 10000:
                $this->image_size = 6;
                $zoomLevel = 3;
                break;
            default:
                MapBlockCtrl::notFound();
        }
        if ($zoomLevel == 3 && !Config::getProperty("settings", "smallMapEnabled")) die;
        $session = Session::getInstance();
        $own_uid = $session->getPlayerId();
        $own_aid = $session->getAllianceId();
        $mapModel = new MapModel();
        $block = $mapModel->getMapMark($tx0, $ty0, $tx1, $ty1, $own_uid, $zoomLevel, true);
        $version = 0;
        if ($block !== false) {
            $version = $block[1];
        }
        $mapMarkSettings = explode(",", $session->getMapSettings());
        $mapMarkSettings = [
            "ownMarks" => $mapMarkSettings[0] == 1,
            "allianceMarks" => $mapMarkSettings[1] == 1,
        ];
        // Everything the image depends on: the tile, its version, the viewer, their alliance and which flags they show.
        $filename = "map_mark_{$tx0}_{$ty0}_{$tx1}_{$ty1}_{$zoomLevel}_{$version}_u{$own_uid}_a{$own_aid}_f"
            . (int)$mapMarkSettings['ownMarks'] . (int)$mapMarkSettings['allianceMarks'];
        ImageMapCache::checkCache($filename, 'png');
        set_time_limit($zoomLevel == 1 ? 2 : ($zoomLevel == 3 ? 8 : 10));
        $dst_x = 0;
        $dst_y = 0;
        $db = DB::getInstance();
        $size_per_row = ceil($width / $this->image_size);
        $x = $tx0;
        $y = $ty1;
        $kidInfo = [];
        $proceed_count = 1;
        $kidBatchArray = $kidBatchAdd = [];
        while ($proceed_count <= $count) {
            $x = Formulas::coordinateFixer($x);
            $y = Formulas::coordinateFixer($y);
            $kid = Formulas::xy2kid($x, $y);
            $kidInfo[$kid] = ['dst_x' => $dst_x, 'dst_y' => $dst_y];
            $kidBatchArray[] = $kid;
            if ($block[2]) {
                $kidBatchAdd[] = "({$kid}, " . ($block[0]) . ")";
            }
            ++$x;
            ++$dst_x;
            if ($proceed_count % $size_per_row == 0) {
                $x = $tx0;
                --$y;
                $dst_x = 0;
                ++$dst_y;
            }
            ++$proceed_count;
        }
        // Diplomacy of the player's alliance, by the other alliance (the first pact wins).
        $pacts = [];
        if ($own_aid) {
            $result = $db->query("SELECT aid1, aid2, type FROM diplomacy WHERE accepted=1 AND (aid1=$own_aid OR aid2=$own_aid) ORDER BY id");
            while ($pact = $result->fetch_assoc()) {
                $pacts[(int)($pact['aid1'] == $own_aid ? $pact['aid2'] : $pact['aid1'])] ??= (int)$pact['type'];
            }
        }
        // The player's and the alliance's flags on players (type 0) and alliances (type 1), oldest first.
        // Personal flags are stored with aid=0 and alliance flags with uid=0 (Ajax/map/multiMark.php).
        $cond = [];
        if ($mapMarkSettings['ownMarks']) {
            $cond[] = 'uid=' . $own_uid;
        }
        if ($mapMarkSettings['allianceMarks'] && $own_aid > 0) {
            $cond[] = '(uid=0 AND aid=' . $own_aid . ')';
        }
        $flags = $cond === [] ? [] : $db->query("SELECT targetId, type, color FROM mapflag WHERE type IN(0, 1) AND (" . implode(" OR ", $cond) . ") ORDER BY id")->fetch_all(MYSQLI_ASSOC);
        // Each mark image is decoded once per overlay, not once per tile.
        $images = [];
        $mark = function (string $name, int $dst_x, int $dst_y) use (&$images): void {
            $size = $this->image_size;
            $images[$name] ??= imagecreatefrompng("img/map/grid/{$size}x{$size}/$name.png");
            imagecopy($this->block, $images[$name], $dst_x * $size, $dst_y * $size, 0, 0, $size, $size);
        };
        $result = $db->query("SELECT w.id, w.x, w.y, w.oasistype, w.occupied, IF(w.oasistype, o.owner, v.owner) AS owner, u.aid AS ownerAid
            FROM wdata w
            LEFT JOIN odata o ON o.kid=w.id
            LEFT JOIN vdata v ON v.kid=w.id
            LEFT JOIN users u ON u.id=IF(w.oasistype, o.owner, v.owner)
            WHERE w.id IN(" . implode(',', $kidBatchArray) . ")");
        while ($row = $result->fetch_assoc()) {
            $dst_x = $kidInfo[$row['id']]['dst_x'];
            $dst_y = $kidInfo[$row['id']]['dst_y'];
            if ($row['x'] == $row['y'] && $row['x'] == 0) {
                $row['occupied'] = 0;
            }
            if ($zoomLevel <= 2) {
                if ($row['x'] == MAP_SIZE) {
                    $mark('rulers/east', $dst_x, $dst_y);
                } else if ($row['x'] == -MAP_SIZE) {
                    $mark('rulers/west', $dst_x, $dst_y);
                }
                if ($row['y'] == MAP_SIZE) {
                    $mark('rulers/north', $dst_x, $dst_y);
                } else if ($row['y'] == -MAP_SIZE) {
                    $mark('rulers/south', $dst_x, $dst_y);
                }
            }
            if (!$row['occupied'] || !($uid = (int)$row['owner'])) {
                continue;
            }
            if ($uid == $own_uid) {
                $mark('marks/own', $dst_x, $dst_y);
            }
            $aid = $own_aid && $row['ownerAid'] !== null ? (int)$row['ownerAid'] : 0;
            if ($aid > 0 && $uid <> $own_uid) {
                if ($aid == $own_aid) {
                    $mark('marks/member', $dst_x, $dst_y);
                } elseif (($pacts[$aid] ?? 0) > 0) {
                    $mark($pacts[$aid] == 1 ? 'marks/conf' : ($pacts[$aid] == 2 ? 'marks/nap' : 'marks/war'), $dst_x, $dst_y);
                }
            }
            foreach ($flags as $flag) {
                if ($flag['targetId'] == ($flag['type'] == 0 ? $uid : $aid)) {
                    if ($flag['color']) {
                        $mark("border/border{$flag['color']}", $dst_x, $dst_y);
                    }
                    break;
                }
            }
        }
        if ($block[2]) {
            $mapModel->addTileMarks($kidBatchAdd);
        }
        ob_start();
        imagesavealpha($this->block, true);
        imagepng($this->block);//one output
        ImageMapCache::checkCache($filename, 'png', ob_get_contents());
    }

    /**
     * @param mixed $x0
     * @param mixed $x1
     */
    public function blockCount($x0, $x1): int
    {
        $x0 = Formulas::coordinateFixer($x0);
        $x1 = Formulas::coordinateFixer($x1);
        $_c = 1;
        while ($x0 <> $x1 && $_c <= 10000) {
            $x0 = Formulas::coordinateFixer($x0 + 1);
            $_c++;
        }

        return $_c;
    }
}
