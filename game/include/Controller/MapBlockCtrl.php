<?php
namespace Controller;
use Core\Config;
use Core\Database\DB;
use Core\ErrorHandler;
use function detect_season;
use Game\Formulas;
use Game\Map\ImageMapCache;
use Model\MapModel;
use Model\WonderOfTheWorldModel;

class MapBlockCtrl extends AnyCtrl
{
    private const SPRITE_CACHE_REVISION = 4;
    /** @var mixed */
    private $imgResource;
    /** @var int|null */
    private $tileSize;
    /** @var (bool|\GdImage)|null */
    private $normalColor;
    /** @var (bool|\GdImage)|null */
    private $snowColor;
    /** @var (bool|\GdImage)|null */
    private $darkColor;
    /** @var bool */
    private $cacheEnabled = true;

    public function __construct()
    {
        $tx0 = isset($_REQUEST['tx0']) && is_numeric($_REQUEST['tx0']) ? (int)$_REQUEST['tx0'] : NULL; //x
        $tx1 = isset($_REQUEST['tx1']) && is_numeric($_REQUEST['tx1']) ? (int)$_REQUEST['tx1'] : NULL; //right
        $ty0 = isset($_REQUEST['ty0']) && is_numeric($_REQUEST['ty0']) ? (int)$_REQUEST['ty0'] : NULL; //y
        $ty1 = isset($_REQUEST['ty1']) && is_numeric($_REQUEST['ty1']) ? (int)$_REQUEST['ty1'] : NULL; //bottom
        $version = 0;
        if (!is_numeric($tx0) || !is_numeric($tx1) || !is_numeric($ty0) || !is_numeric($ty1) || !is_numeric($version)) {
            return;
        }
        $this->renderMap($tx0, $ty0, $tx1, $ty1);
    }

    /**
     * @param int $tx0
     * @param int $ty0
     * @param int $tx1
     * @param int $ty1
     */
    public function renderMap($tx0, $ty0, $tx1, $ty1): void
    {
        if (($zoomLevel = $this->getZoomLevel($tx0, $ty0, $tx1, $ty1)) === -1) {
            self::notFound();
        }
        if($zoomLevel == 3 && !Config::getProperty("settings", "smallMapEnabled")) die;
        set_time_limit($zoomLevel == 1 ? 2 : ($zoomLevel == 2 ? 3 : 10));
        list($block, $filename) = $this->checkForCache($tx0, $ty0, $tx1, $ty1, $zoomLevel);
        list($kidBatchArray, $kidBatchAdd, $resultRows) = $this->getData($tx0, $ty1, $zoomLevel, $block);
        $isWinter = detect_season() == 'winter';
        foreach ($kidBatchArray as $kid) {
            $this->setBackground($resultRows[$kid]['isDark'], $resultRows[$kid]['dst_x'], $resultRows[$kid]['dst_y'], $isWinter);
            $this->setGrassland($resultRows[$kid]['isDark'], $resultRows[$kid]['fieldtype'], $resultRows[$kid]['dst_x'], $resultRows[$kid]['dst_y'], $isWinter);
            $this->setDark($kid, $resultRows, $isWinter);
            $this->setWald($kid, $resultRows);
            $this->setSea($kid, $resultRows);
            $this->setLayouts($resultRows[$kid]);
            $this->setOasis($resultRows[$kid]['oasistype'], $resultRows[$kid]['occupied'], $zoomLevel, $resultRows[$kid]['dst_x'], $resultRows[$kid]['dst_y']);
            $this->setVillages($kid, $resultRows[$kid]['fieldtype'], $resultRows[$kid]['occupied'], $resultRows[$kid]['dst_x'], $resultRows[$kid]['dst_y']);
        }
        $this->finalizeNewBlocks($block, $kidBatchAdd);
        $this->finalizeOutput($filename);
    }

    /**
     * @param mixed $kid
     * @param mixed $resultRows
     */
    private function setWald($kid, $resultRows): void
    {
        if (1 == $resultRows[$kid]['landscape'] || in_array($resultRows[$kid]['oasistype'], [2, 3, 4])) {
            $img = '';
            $hasNorth = $this->isWaldInCoordinates($resultRows[$kid]['x'], $resultRows[$kid]['y'] + 1, $resultRows);
            $hasWest = $this->isWaldInCoordinates($resultRows[$kid]['x'] - 1, $resultRows[$kid]['y'], $resultRows);
            $hasSouth = $this->isWaldInCoordinates($resultRows[$kid]['x'], $resultRows[$kid]['y'] - 1, $resultRows);
            $hasEast = $this->isWaldInCoordinates($resultRows[$kid]['x'] + 1, $resultRows[$kid]['y'], $resultRows);
            if ($hasNorth) {
                $img .= 'n';
            }
            if ($hasWest) {
                $img .= 'w';
            }
            if ($hasSouth) {
                $img .= 's';
            }
            if ($hasEast) {
                $img .= 'e';
            }
            imagecopy($this->getImgResource(), imagecreatefrompng("img/map/forest/{$this->getTileSize()}x{$this->getTileSize()}/" . self::maskedTile('forest', $img) . ".png"), $resultRows[$kid]['dst_x'] * $this->getTileSize(), $resultRows[$kid]['dst_y'] * $this->getTileSize(), 0, 0, $this->getTileSize(), $this->getTileSize());
        }
    }

    /**
     * Border tiles are named after the neighbours that continue them (forest-nw.png); the tile with
     * none is the bare name (forest.png).
     */
    private static function maskedTile(string $name, string $mask): string
    {
        return $mask === '' ? $name : $name . '-' . $mask;
    }

    /**
     * Deep water tiles pick their art by which of the four neighbours are deep water too (sea-<n|w|s|e>.png).
     *
     * @param mixed $kid
     * @param mixed $resultRows
     */
    private function setSea($kid, $resultRows): void
    {
        if (!$resultRows[$kid]['deepwater']) {
            return;
        }
        $x = $resultRows[$kid]['x'];
        $y = $resultRows[$kid]['y'];
        $img = '';
        foreach (['n' => [0, 1], 'w' => [-1, 0], 's' => [0, -1], 'e' => [1, 0]] as $side => [$dx, $dy]) {
            if ($this->isSeaInCoordinates($x + $dx, $y + $dy, $resultRows)) {
                $img .= $side;
            }
        }
        $size = $this->getTileSize();
        imagecopy($this->getImgResource(), imagecreatefrompng("img/map/sea/{$size}x{$size}/" . self::maskedTile('sea', $img) . ".png"), $resultRows[$kid]['dst_x'] * $size, $resultRows[$kid]['dst_y'] * $size, 0, 0, $size, $size);
    }

    /**
     * @param (float|int) $x
     * @param (float|int) $y
     * @param mixed $resultRows
     */
    private function isSeaInCoordinates($x, $y, $resultRows): bool
    {
        $kid = Formulas::xy2kid($x, $y);
        if (isset($resultRows[$kid])) {
            return (bool)$resultRows[$kid]['deepwater'];
        }

        return (bool)DB::getInstance()->fetchScalar("SELECT deepwater FROM wdata WHERE id=?", $kid);
    }

    /**
     * @param mixed $kid
     * @param mixed $resultRows
     * @param bool $isWinter
     */
    private function setDark($kid, $resultRows, $isWinter): void
    {
        if (!$resultRows[$kid]['isDark']) return;
        if (round(hypot($resultRows[$kid]['x'], $resultRows[$kid]['y'])) <= 21) {
            return;
        }
        $img = '';
        $hasNorth = $this->checkDarkSides($resultRows[$kid]['x'], $resultRows[$kid]['y'] + 1, $resultRows);
        $hasWest = $this->checkDarkSides($resultRows[$kid]['x'] - 1, $resultRows[$kid]['y'], $resultRows);
        $hasSouth = $this->checkDarkSides($resultRows[$kid]['x'], $resultRows[$kid]['y'] - 1, $resultRows);
        $hasEast = $this->checkDarkSides($resultRows[$kid]['x'] + 1, $resultRows[$kid]['y'], $resultRows);
        if ($hasNorth) {
            $img .= 'n';
        }
        if ($hasWest) {
            $img .= 'w';
        }
        if ($hasSouth) {
            $img .= 's';
        }
        if ($hasEast) {
            $img .= 'e';
        }
        if ($img == 'nwse') {
            return;
        }
        if($isWinter){
            $img = "img/map/dark/{$this->getTileSize()}x{$this->getTileSize()}/snow/" . self::maskedTile('dark', $img) . ".png";
        } else {
            $img = "img/map/dark/{$this->getTileSize()}x{$this->getTileSize()}/" . self::maskedTile('dark', $img) . ".png";
        }
        imagecopy($this->getImgResource(), imagecreatefrompng($img), $resultRows[$kid]['dst_x'] * $this->getTileSize(), $resultRows[$kid]['dst_y'] * $this->getTileSize(), 0, 0, $this->getTileSize(), $this->getTileSize());
    }

    /**
     * @param mixed $x
     * @param mixed $y
     * @param mixed $resultRows
     * @return mixed
     */
    private function checkDarkSides($x, $y, $resultRows)
    {
        $kid = Formulas::xy2kid($x, $y);
        if (isset($resultRows[$kid])) {
            return $resultRows[$kid]['isDark'];
        }
        return Formulas::isGrayArea($kid);
    }

    /**
     * @param mixed $x
     * @param mixed $y
     * @param mixed $resultRows
     */
    private function isWaldInCoordinates($x, $y, $resultRows): bool
    {
        $kid = Formulas::xy2kid($x, $y);
        if (isset($resultRows[$kid])) {
            return 1 == $resultRows[$kid]['landscape'] || in_array($resultRows[$kid]['oasistype'], [2, 3, 4]);
        }
        $db = DB::getInstance();
        $map = $db->query("SELECT oasistype, landscape FROM wdata WHERE id=$kid LIMIT 1")->fetch_assoc();
        return 1 == $map['landscape'] || in_array($map['oasistype'], [2, 3, 4]);
    }

    /**
     * @param mixed $oasisType
     * @param mixed $occupied
     * @param mixed $zoomLevel
     * @param mixed $dst_x
     * @param mixed $dst_y
     */
    private function setOasis($oasisType, $occupied, $zoomLevel, $dst_x, $dst_y): void
    {
        if (0 == $oasisType || $zoomLevel == 3) return;
        $occupied = $occupied ? "occupied" : "free";
        imagecopy($this->getImgResource(), imagecreatefrompng("img/map/oasis/{$this->getTileSize()}x{$this->getTileSize()}/oasis-type{$oasisType}-{$occupied}.png"), $dst_x * $this->getTileSize(), $dst_y * $this->getTileSize(), 0, 0, $this->getTileSize(), $this->getTileSize());
    }

    /**
     * @param mixed $kid
     * @param mixed $fieldType
     * @param mixed $occupied
     * @param mixed $dst_x
     * @param mixed $dst_y
     */
    private function setVillages($kid, $fieldType, $occupied, $dst_x, $dst_y): void
    {
        if (0 == $fieldType || 0 == $occupied) return;
        $db = DB::getInstance();
        $village = $db->query("SELECT v.owner, v.pop, v.isWW, f.f99 FROM vdata v LEFT JOIN fdata f ON f.kid=v.kid WHERE v.kid=$kid")->fetch_assoc();
        if (!is_array($village) || !is_numeric($village['owner'])) {
            return;
        }
        $tribeId = $db->fetchScalar("SELECT race FROM users WHERE id={$village['owner']}");
        if ($village['pop'] <= 100) {
            $index = 0;
        } else if ($village['pop'] <= 249) {
            $index = 1;
        } else if ($village['pop'] <= 499) {
            $index = 2;
        } else {
            $index = 3;
        }
        if($tribeId === false){
            logError("Could not detect tribe for village with id {$kid}");
            return;
        }
        $spritePath = "img/map/village/tribe{$tribeId}/{$this->getTileSize()}x{$this->getTileSize()}/village{$index}.png";
        if ((int)$village['isWW'] === 1) {
            // A Wonder shows its own art at its current stage (dev/wonder-sprites/generate.py) instead of a village.
            $stage = WonderOfTheWorldModel::stage((int)($village['f99'] ?? 0));
            $wonderPath = "img/map/wonder/tribe{$tribeId}/{$this->getTileSize()}x{$this->getTileSize()}/wonder{$stage}.png";
            if (is_file($wonderPath)) {
                $spritePath = $wonderPath;
            }
        }
        // A missing tribe sprite must not make the complete 10x10 map block
        // unavailable. This can happen on worlds where an optional tribe is
        // enabled before its map-art set has been installed.
        if (!is_file($spritePath)) {
            logError("Map village sprite is missing for tribe %s at %s; rendering the tile without its village overlay.", [$tribeId, $spritePath]);
            return;
        }
        $sprite = @imagecreatefrompng($spritePath);
        if ($sprite === false) {
            logError("Map village sprite could not be decoded for tribe %s at %s; rendering the tile without its village overlay.", [$tribeId, $spritePath]);
            return;
        }
        imagecopy($this->getImgResource(), $sprite, $dst_x * $this->getTileSize(), $dst_y * $this->getTileSize(), 0, 0, $this->getTileSize(), $this->getTileSize());
    }

    /**
     * @param mixed $row
     */
    private function setLayouts(&$row): void
    {
        $row['map'] = $this->explodeParams($row['map']);
        if ($row['map']['parent']['img'] != '') {
            $this->setLayer($row['map']['parent'], $row['dst_x'], $row['dst_y']);
        }
        if ($row['map']['child']['img'] != '') {
            $this->setLayer($row['map']['child'], $row['dst_x'], $row['dst_y']);
        }
    }

    /**
     * @param mixed $isDark
     * @param mixed $dst_x
     * @param mixed $dst_y
     * @param bool $isWinter
     */
    private function setBackground($isDark, $dst_x, $dst_y, $isWinter): void
    {
        $color = $isDark ? $this->getDarkColorResource() : $this->getNormalColorResource($isWinter);
        imagecopy($this->getImgResource(), $color, $dst_x * $this->getTileSize(), $dst_y * $this->getTileSize(), 0, 0, $this->getTileSize(), $this->getTileSize());
        if($isDark){
            $img = "img/map/empty/".$this->getTileSize()."x".$this->getTileSize()."/empty.png";
            imagecopy($this->getImgResource(), imagecreatefrompng($img), $dst_x * $this->getTileSize(), $dst_y * $this->getTileSize(), 0, 0, $this->getTileSize(), $this->getTileSize());
        }
    }

    /**
     * @param mixed $isDark
     * @param mixed $fieldType
     * @param mixed $dst_x
     * @param mixed $dst_y
     * @param bool $isWinter
     */
    private function setGrassland($isDark, $fieldType, $dst_x, $dst_y, $isWinter): void
    {
        if (0 == $fieldType || $isDark) return;
        if($isWinter){
            $img = "img/map/grassland/{$this->getTileSize()}x{$this->getTileSize()}/snow/grassland" . ($fieldType - 1) . ".png";
        } else {
            $img = "img/map/grassland/{$this->getTileSize()}x{$this->getTileSize()}/grassland" . ($fieldType - 1) . ".png";
        }
        imagecopy($this->getImgResource(), imagecreatefrompng($img), $dst_x * $this->getTileSize(), $dst_y * $this->getTileSize(), 0, 0, $this->getTileSize(), $this->getTileSize());
    }

    /**
     * @return mixed
     */
    private function getTileSize()
    {
        return $this->tileSize;
    }

    /**
     * @param mixed $map
     * @param mixed $dst_x
     * @param mixed $dst_y
     * @return bool|int
     */
    private function setLayer($map, $dst_x, $dst_y)
    {
        $map['img'] = explode("_", $map['img']);
        if ($map['img'][0] == 1) return -1;
        switch ($map['img'][0]) {
            case 0:
                $img = 'vulcano';
                break;
            case 1:
                $img = 'forest';
                break;
            case 2:
                $img = 'clay';
                break;
            case 3:
                $img = 'hill';
                break;
            case 4:
                $img = 'lake';
                break;
            default:
                return FALSE;
        }
        $imgId = $img . $map['img'][1];
        $img = imagecreatefrompng("img/map/{$img}/{$this->getTileSize()}x{$this->getTileSize()}/{$imgId}.png");
        imagecopy($this->getImgResource(), $img, $dst_x * $this->getTileSize(), $dst_y * $this->getTileSize(), $map['x'] * $this->getTileSize(), $map['y'] * $this->getTileSize(), imagesx($img), imagesy($img));
        return true;
    }

    /**
     * @param mixed $filename
     */
    private function finalizeOutput($filename): void
    {
        ob_start();
        $output = imagecreatetruecolor(600, 600);
        imagefilledrectangle($output, 0, 0, 600, 600, imagecolorallocate($output, 255, 255, 255));
        imagecopy($output, $this->getImgResource(), 0, 0, 0, 0, 600, 600);
        if($this->cacheEnabled){
            imagejpeg($output);
            ImageMapCache::checkCache($filename, 'jpeg', ob_get_contents());
        } else {
            header("Content-Type: image/jpeg");
            imagejpeg($output);
            exit();
        }

    }

    /**
     * @param mixed $tx0
     * @param mixed $ty1
     * @param mixed $zoomLevel
     * @param mixed $block
     * @return list<array<mixed>>
     */
    private function getData($tx0, $ty1, $zoomLevel, $block)
    {
        $count = $zoomLevel == 1 ? 100 : ($zoomLevel == 2 ? 400 : 10000);
        $size_per_row = $this->getSizePerRow();
        $x = $tx0;
        $y = $ty1;
        $dst_x = $dst_y = 0;
        $proceed_count = 1;
        $kidBatchArray = $kidBatchAdd = $kidInfo = [];
        while ($proceed_count <= $count) {
            $x = Formulas::coordinateFixer($x);
            $y = Formulas::coordinateFixer($y);
            $kid = Formulas::xy2kid($x, $y);
            $kidBatchArray[] = $kid;
            $kidInfo[$kid] = ['dst_x' => $dst_x, 'dst_y' => $dst_y];
            if ($block[2]) {//is new
                $kidBatchAdd[] = "({$kid}, " . ($block[0]) . ")";
            }
            ++$dst_x;
            ++$x;
            if ($proceed_count % $size_per_row == 0) {
                $x = $tx0;
                $dst_x = 0;
                --$y;
                ++$dst_y;
            }
            ++$proceed_count;
        }
        $db = DB::getInstance();
        $result = $db->query("SELECT * FROM wdata WHERE id IN(" . implode(',', $kidBatchArray) . ")");
        $resultRows = [];
        while ($current = $result->fetch_assoc()) {
            $current['dst_x'] = $kidInfo[$current['id']]['dst_x'];
            $current['dst_y'] = $kidInfo[$current['id']]['dst_y'];
            $current['isDark'] = Formulas::isGrayArea(['x' => $current['x'], 'y' => $current['y']]);
            if ($current['x'] == $current['y'] && $current['x'] == 0) {
                $current['occupied'] = 0;
            }
            $resultRows[$current['id']] = $current;

        }
        return [$kidBatchArray, $kidBatchAdd, $resultRows];
    }

    /**
     * @param mixed $block
     * @param mixed $kidBatchAdd
     */
    private function finalizeNewBlocks($block, $kidBatchAdd): void
    {
        if ($block[2]) {//is new
            (new MapModel())->addTileBlocks($kidBatchAdd);
        }
    }

    /**
     * @param mixed $tx0
     * @param mixed $ty0
     * @param mixed $tx1
     * @param mixed $ty1
     * @param mixed $zoomLevel
     * @return list<bool|list<bool|int|string>|string>
     */
    private function checkForCache($tx0, $ty0, $tx1, $ty1, $zoomLevel)
    {
        $mapModel = new MapModel();
        $block = $mapModel->getMapBlock($tx0, $ty0, $tx1, $ty1, $zoomLevel);
        $version = 0;
        if ($block !== FALSE) {
            $version = $block[1];
        }
        // Terrain blocks are cached independently from deployment assets. Bump
        // this revision when a village-sprite set changes so previously cached
        // blank optional-tribe blocks are regenerated for every player.
        $filename = "map_block_{$tx0}_{$ty0}_{$tx1}_{$ty1}_{$zoomLevel}_{$version}_sprites" . self::SPRITE_CACHE_REVISION;
        if($this->cacheEnabled)
            ImageMapCache::checkCache($filename, 'jpeg');
        return [$block, $filename];
    }

    /**
     * @param mixed $x0
     * @param mixed $x1
     */
    /** Like the reference: a block that is not 10x10, 20x20 or 100x100 tiles is a 404 without a body. */
    public static function notFound(): never
    {
        http_response_code(404);
        header('Cache-Control: no-store');
        exit;
    }

    private static function blockCount($x0, $x1): int
    {
        $x0 = Formulas::coordinateFixer($x0);
        $x1 = Formulas::coordinateFixer($x1);
        $_c = 1;
        while ($x0 <> $x1 && $_c <= 121) {
            $x0 = Formulas::coordinateFixer($x0 + 1);
            $_c++;
        }
        return $_c;
    }

    /**
     * @param mixed $tx0
     * @param mixed $ty0
     * @param mixed $tx1
     * @param mixed $ty1
     */
    private function getZoomLevel($tx0, $ty0, $tx1, $ty1): int
    {
        $count = self::blockCount($tx0, $tx1) * self::blockCount($ty0, $ty1);
        switch ($count) {
            case 100:
                $this->tileSize = 60;
                return 1;
            case 400:
                $this->tileSize = 30;
                return 2;
            case 10000:
                $this->tileSize = 6;
                return 3;
        }
        return -1;
    }

    /**
     * @return mixed
     */
    private function getImgResource()
    {
        // GD returns a GdImage object on PHP 8 (rather than the resource it
        // returned on PHP 7). A resource check therefore recreated the map
        // canvas for every tile and left the final JPEG completely white.
        if ($this->imgResource === null) {
            $this->imgResource = imagecreatetransparent(600, 600);
        }
        return $this->imgResource;
    }

    /**
     * @return mixed
     */
    private function getSnowColorResource()
    {
        if (!($this->snowColor)) {
            $this->snowColor = imagecreate($this->getTileSize(), $this->getTileSize());
            //205 | 222 |  232
            imagefill($this->snowColor, 0, 0, imagecolorallocate($this->snowColor, 241, 246, 255));
        }
        return $this->snowColor;
    }
    /**
     * @param mixed $isWinter
     * @return mixed
     */
    private function getNormalColorResource($isWinter)
    {
        if($isWinter) return $this->getSnowColorResource();
        if (!($this->normalColor)) {
            $this->normalColor = imagecreate($this->getTileSize(), $this->getTileSize());
            imagefill($this->normalColor, 0, 0, imagecolorallocate($this->normalColor, 185, 213, 128));
            // Old color
            //imagefill($this->normalColor, 0, 0, imagecolorallocate($this->normalColor, 195, 237, 174));
        }
        return $this->normalColor;
    }

    /**
     * @return mixed
     */
    private function getDarkColorResource()
    {
        if ($this->darkColor === null) {
            $this->darkColor = imagecreate($this->getTileSize(), $this->getTileSize());
            imagefill($this->darkColor, 0, 0, imagecolorallocate($this->darkColor, 155, 165, 157));
        }
        return $this->darkColor;
    }

    /**
     * @param mixed $data
     * @return array<string, array<string, string>>
     */
    private function explodeParams($data)
    {
        //||=||
        list($parent, $child) = explode("=", $data);
        $parent = explode('|', $parent);
        $child = explode('|', $child);

        return [
            'parent' => [
                "x" => $parent[0], "y" => $parent[1], 'img' => $parent[2],
            ], 'child' => [
                "x" => $child[0], "y" => $child[1], 'img' => $child[2],
            ],
        ];
    }

    private function getSizePerRow(): float
    {
        return ceil(600 / $this->getTileSize());
    }
}
