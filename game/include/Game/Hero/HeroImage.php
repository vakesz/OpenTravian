<?php

declare(strict_types=1);

namespace Game\Hero;

use Core\Caching\Caching;
use Core\Database\DB;
use RuntimeException;

/**
 * The single hero picture pipeline.
 *
 * Every hero portrait (top bar, inventory body, profile, forum, ...) is a PNG
 * composed server-side from the same Hero V2 atlases the appearance editor
 * draws on the client (assets/gpack/travian/mainPage/textures/hero). The
 * URL carries a hash of the saved appearance so a change of look produces a
 * new, immutably cacheable URL:
 *
 *   /heroV2/body/<hash>.<uid>.1.png   278x500 (875x1574 canvas fit to 500 high)
 *   /heroV2/head/<hash>.<uid>.1.png   120x120 crop of the body at (82, 30)
 *
 * Equipped gear is part of the picture, as on the reference: the hash covers
 * the equipped item art, so equipping or removing an item gives a new URL.
 * Gear layers are 278x500 PNGs under textures/hero/items/<gender>-<slot>-<art>.png,
 * stacked in layerConfigs order: boots, body, helmet, leftHand,
 * rightHand. The horse stays a CSS overlay on the inventory figure.
 */
final class HeroImage
{
    public const BODY_WIDTH = 278;
    public const BODY_HEIGHT = 500;
    public const HEAD_SIZE = 120;
    public const HEAD_LEFT = 82;
    public const HEAD_TOP = 30;
    /** Boots only hide default feet below this row. */
    private const FOOT_TOP = 400;

    /** Bump when the composition changes so every cached file is regenerated. */
    private const COMPOSITION_VERSION = 2;
    private const SOURCE_WIDTH = 875;
    private const SOURCE_HEIGHT = 1574;
    private const KINDS = ['head', 'body'];
    private const HAIR_COLORS = [
        0 => 'black', 1 => 'brown', 2 => 'brightbrown', 3 => 'blonde', 4 => 'red', 5 => 'grey',
        6 => 'white', 7 => 'violet', 8 => 'signalred', 9 => 'blondepink', 10 => 'blondered', 11 => 'redblack',
    ];
    /** Gear slots in the reference layerConfigs stacking order (bottom first). */
    public const GEAR_SLOTS = ['shoes', 'body', 'helmet', 'leftHand', 'rightHand'];
    /** An item type shares the layer of the nearest lower art id within this distance (tier ids). */
    private const GEAR_TIER_SPAN = 2;
    private const TRIBES = [1 => 'roman', 2 => 'teuton', 3 => 'gaul', 6 => 'egyptian', 7 => 'hun'];

    /** @var array<string, array{data: array<mixed>, image: \GdImage}> */
    private static array $atlases = [];

    /** @var array<string, list<int>> */
    private static array $gearArt = [];

    public static function headUrl(int $uid): string
    {
        return self::url('head', $uid);
    }

    public static function bodyUrl(int $uid): string
    {
        return self::url('body', $uid);
    }

    public static function hash(int $uid): string
    {
        $cache = Caching::getInstance();
        $key = 'heroImageHash' . $uid;
        $hash = $cache->get($key);
        if (!is_string($hash) || $hash === '') {
            $hash = self::computeHash(self::appearance($uid));
            $cache->set($key, $hash, 300);
        }

        return $hash;
    }

    /** Drop the memoised hash after the appearance changed. */
    public static function forget(int $uid): void
    {
        Caching::getInstance()->delete('heroImageHash' . $uid);
    }

    /**
     * PNG bytes for a hero picture, composed once per appearance hash and
     * kept in the state volume.
     */
    public static function png(int $uid, string $kind): ?string
    {
        if (!in_array($kind, self::KINDS, true)) {
            return null;
        }
        $appearance = self::appearance($uid);
        if ($appearance === null) {
            return null;
        }
        $path = self::cachePath(self::computeHash($appearance), $kind);
        if (is_file($path)) {
            $bytes = file_get_contents($path);
            if ($bytes !== false && $bytes !== '') {
                return $bytes;
            }
        }

        $body = self::composeBody($appearance);
        $image = $kind === 'body' ? $body : self::cropHead($body);
        ob_start();
        imagepng($image, null, 6);
        $bytes = (string)ob_get_clean();

        if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            return $bytes;
        }
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $bytes) !== false) {
            rename($temporary, $path);
        }

        return $bytes;
    }

    private static function url(string $kind, int $uid): string
    {
        return '/heroV2/' . $kind . '/' . self::hash($uid) . '.' . $uid . '.1.png';
    }

    private static function cachePath(string $hash, string $kind): string
    {
        return rtrim(STATE_DIR, '/') . '/heroV2/' . $hash . '.' . $kind . '.png';
    }

    /**
     * Layer art id for an equipped item type, or null when no layer exists
     * (unknown type, or a tier whose art was never captured).
     */
    public static function gearArt(string $gender, string $slot, int $type): ?int
    {
        if (!in_array($slot, self::GEAR_SLOTS, true) || !in_array($gender, ['male', 'female'], true)) {
            return null;
        }
        $key = $gender . '-' . $slot;
        if (!isset(self::$gearArt[$key])) {
            $ids = [];
            foreach (glob(self::gearDirectory() . "/$key-*.png") ?: [] as $file) {
                if (preg_match('/-(\d+)\.png$/', $file, $match)) {
                    $ids[] = (int)$match[1];
                }
            }
            rsort($ids);
            self::$gearArt[$key] = $ids;
        }
        foreach (self::$gearArt[$key] as $id) {
            if ($id <= $type) {
                return $type - $id <= self::GEAR_TIER_SPAN ? $id : null;
            }
        }

        return null;
    }

    public static function gearLayerPath(string $gender, string $slot, int $art): string
    {
        return self::gearDirectory() . "/$gender-$slot-$art.png";
    }

    private static function gearDirectory(): string
    {
        return dirname(__DIR__, 4) . '/assets/gpack/travian/mainPage/textures/hero/items';
    }

    /**
     * Equipped item art per slot (slot => art id), only for slots that have a layer.
     *
     * @return array<string, int>
     */
    private static function equippedGear(int $uid, string $gender): array
    {
        $columns = '';
        foreach (self::GEAR_SLOTS as $slot) {
            $columns .= ", (SELECT it.type FROM items it WHERE it.id = v.`$slot` AND it.uid = v.uid AND it.proc = 1) AS `$slot`";
        }
        $statement = DB::getInstance()->run('SELECT v.uid' . $columns . ' FROM inventory v WHERE v.uid = ?', [$uid]);
        $row = $statement ? $statement->get_result()->fetch_assoc() : null;
        $gear = [];
        foreach (self::GEAR_SLOTS as $slot) {
            if (is_array($row) && $row[$slot] !== null) {
                $art = self::gearArt($gender, $slot, (int)$row[$slot]);
                if ($art !== null) {
                    $gear[$slot] = $art;
                }
            }
        }

        return $gear;
    }

    /**
     * Atlas-ready appearance of a hero: names and 1-based frame indexes, or
     * null for an unknown hero.
     *
     * @return array<string, mixed>|null
     */
    private static function appearance(int $uid): ?array
    {
        $statement = DB::getInstance()->run(
            'SELECT f.*, u.race FROM face f JOIN users u ON u.id = f.uid WHERE f.uid = ?',
            [$uid]
        );
        $row = $statement ? $statement->get_result()->fetch_assoc() : null;
        if (!is_array($row)) {
            return null;
        }
        $gender = $row['gender'] === 'female' ? 'female' : 'male';
        $face = new HeroFace();
        // encodeAttribute() turns the stored indexes into the editor values
        // (headProfile 50.., hairStyle 1000.., scar 8001..) that the atlas
        // frame numbers are derived from, exactly like hero-v2-appearance.js.
        $editor = $face->encodeAttribute($row);
        $start = static fn (string $name): int => (int)$face->faceData[$gender][$name]['start'];
        $index = static fn (string $name): int => max(1, (int)$editor[$name] - $start($name) + 1);
        $optional = static fn (string $name): ?int => (int)$editor[$name] === (int)$face->faceData[$gender][$name]['none']
            ? null
            : $index($name);

        $appearance = [
            'gender' => $gender,
            'skinColor' => (string)$row['skinColor'],
            'eyeColor' => (string)$row['eyeColor'],
            'hairColor' => self::HAIR_COLORS[(int)$row['hairColor']] ?? 'brown',
            'tribeColor' => self::TRIBES[(int)$row['race']] ?? 'roman',
            'jaw' => $index('headProfile'),
            'eyes' => $index('eyes'),
            'brows' => $index('eyebrow'),
            'nose' => $index('nose'),
            'mouth' => $index('mouth'),
            'ears' => $index('ears'),
            'hair' => $optional('hairStyle'),
            'beard' => $gender === 'male' ? $optional('beard') : null,
            'scar' => $optional('scar'),
            'tattoo' => $optional('tattoo'),
        ];
        $gear = self::equippedGear($uid, $gender);
        if ($gear !== []) {
            $appearance['gear'] = $gear;
        }

        return $appearance;
    }

    /** @param array<string, mixed>|null $appearance */
    private static function computeHash(?array $appearance): string
    {
        return sha1(self::COMPOSITION_VERSION . ':' . json_encode($appearance));
    }

    /**
     * Same layer order as hero-v2-appearance.js (and the reference).
     *
     * @param array<string, mixed> $a
     */
    private static function composeBody(array $a): \GdImage
    {
        $gender = $a['gender'];
        $skin = self::atlas($gender, 'skinColor', $a['skinColor']);
        $hair = self::atlas($gender, 'hairColor', $a['hairColor']);
        $eye = self::atlas($gender, 'eyeColor', $a['eyeColor']);
        $tribe = self::atlas($gender, 'tribeColor', $a['tribeColor']);
        $tattoo = self::atlas($gender, 'tattoo', '');
        $skinPrefix = "$gender/skinColor/{$a['skinColor']}/";
        $hairPrefix = "$gender/hairColor/{$a['hairColor']}/";
        $eyePrefix = "$gender/eyeColor/{$a['eyeColor']}/";
        $tribePrefix = "$gender/tribeColor/{$a['tribeColor']}/";
        $s = $a['skinColor'];

        $canvas = imagecreatetruecolor(self::SOURCE_WIDTH, self::SOURCE_HEIGHT);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        $layers = [];
        if ($a['hair'] !== null) {
            $layers[] = [$hair, $hairPrefix . "backHair/backHair{$a['hair']}-{$a['hairColor']}", true];
        }
        array_push(
            $layers,
            [$skin, $skinPrefix . "base/base-$s", false],
            [$skin, $skinPrefix . "leftArm/leftArmDefault-$s", false],
            [$skin, $skinPrefix . "rightArm/rightArmDefault-$s", false],
        );
        // Body armour replaces the tribe tunic ("body" layer) rather than tinting it.
        if (!isset($a['gear']['body'])) {
            $layers[] = [$tribe, $tribePrefix . "body/body-{$a['tribeColor']}", false];
        }
        array_push(
            $layers,
            [$skin, $skinPrefix . "ears/ears{$a['ears']}-$s", false],
            [$skin, $skinPrefix . "jaw/jaw{$a['jaw']}-$s", false],
            [$skin, $skinPrefix . "eyesBase/eyesBase{$a['eyes']}-$s", false],
            [$eye, $eyePrefix . "eyes/eyes{$a['eyes']}-{$a['eyeColor']}", false],
            [$skin, $skinPrefix . "mouth/mouth{$a['mouth']}-$s", false],
            [$skin, $skinPrefix . "nose/nose{$a['nose']}-$s", false],
            [$skin, $skinPrefix . "browsBase/browsBase{$a['brows']}-$s", false],
            [$hair, $hairPrefix . "brows/brows{$a['brows']}-{$a['hairColor']}", false],
        );
        if ($a['scar'] !== null) {
            $layers[] = [$skin, $skinPrefix . "scar/scar{$a['scar']}-$s", true];
        }
        // A helmet hides the front hair, as on the reference.
        if ($a['hair'] !== null && !isset($a['gear']['helmet'])) {
            $layers[] = [$hair, $hairPrefix . "frontHair/frontHair{$a['hair']}-{$a['hairColor']}", false];
        }
        if ($a['beard'] !== null) {
            $layers[] = [$hair, $hairPrefix . "beard/beard{$a['beard']}-{$a['hairColor']}", true];
        }
        if ($a['tattoo'] !== null) {
            $layers[] = [$tattoo, "$gender/tattoo/tattoo{$a['tattoo']}", true];
        }
        foreach ($layers as [$atlas, $frame, $optional]) {
            self::drawFrame($canvas, $atlas, $frame, $optional);
        }

        $body = imagecreatetruecolor(self::BODY_WIDTH, self::BODY_HEIGHT);
        imagealphablending($body, false);
        imagesavealpha($body, true);
        imagefill($body, 0, 0, imagecolorallocatealpha($body, 0, 0, 0, 127));
        imagecopyresampled(
            $body,
            $canvas,
            0,
            0,
            0,
            0,
            self::BODY_WIDTH,
            self::BODY_HEIGHT,
            self::SOURCE_WIDTH,
            self::SOURCE_HEIGHT
        );

        self::drawGear($body, $a);

        return $body;
    }

    /** @param array<string, mixed> $a */
    private static function drawGear(\GdImage $body, array $a): void
    {
        imagealphablending($body, true);
        foreach (self::GEAR_SLOTS as $slot) {
            $art = $a['gear'][$slot] ?? null;
            if ($art === null) {
                continue;
            }
            $layer = @imagecreatefrompng(self::gearLayerPath($a['gender'], $slot, (int)$art));
            if ($layer === false) {
                continue;
            }
            if ($slot === 'shoes') {
                self::hideFeetUnder($body, $layer);
            }
            imagecopy($body, $layer, 0, 0, 0, 0, self::BODY_WIDTH, self::BODY_HEIGHT);
        }
        imagealphablending($body, false);
    }

    /**
     * Boots cover the feet on the reference: clear the default sandals and
     * feet under the (mostly opaque) boots pixels before drawing them.
     */
    private static function hideFeetUnder(\GdImage $body, \GdImage $boots): void
    {
        $clear = imagecolorallocatealpha($body, 0, 0, 0, 127);
        imagealphablending($body, false);
        for ($y = self::FOOT_TOP; $y < self::BODY_HEIGHT; ++$y) {
            for ($x = 0; $x < self::BODY_WIDTH; ++$x) {
                if (((imagecolorat($boots, $x, $y) >> 24) & 127) < 40) {
                    imagesetpixel($body, $x, $y, $clear);
                }
            }
        }
        imagealphablending($body, true);
    }

    private static function cropHead(\GdImage $body): \GdImage
    {
        $head = imagecreatetruecolor(self::HEAD_SIZE, self::HEAD_SIZE);
        imagealphablending($head, false);
        imagesavealpha($head, true);
        imagefill($head, 0, 0, imagecolorallocatealpha($head, 0, 0, 0, 127));
        imagecopy($head, $body, 0, 0, self::HEAD_LEFT, self::HEAD_TOP, self::HEAD_SIZE, self::HEAD_SIZE);

        return $head;
    }

    /**
     * @param array{data: array, image: \GdImage} $atlas
     *
     * @param array<string, array<mixed>|\GdImage> $atlas
     */
    private static function drawFrame(\GdImage $canvas, array $atlas, string $name, bool $optional): void
    {
        $frame = $atlas['data']['frames'][$name] ?? null;
        if ($frame === null) {
            if ($optional) {
                return;
            }
            throw new RuntimeException('Hero atlas frame is missing: ' . $name);
        }
        $packed = $frame['frame'];
        $target = $frame['spriteSourceSize'];
        if (empty($frame['rotated'])) {
            imagecopy($canvas, $atlas['image'], $target['x'], $target['y'], $packed['x'], $packed['y'], $packed['w'], $packed['h']);

            return;
        }

        // TexturePacker stores rotated sprites turned clockwise, so the atlas
        // rectangle is h wide and w tall; turn it back counter-clockwise.
        $piece = imagecreatetruecolor($packed['h'], $packed['w']);
        imagealphablending($piece, false);
        imagesavealpha($piece, true);
        imagefill($piece, 0, 0, imagecolorallocatealpha($piece, 0, 0, 0, 127));
        imagecopy($piece, $atlas['image'], 0, 0, $packed['x'], $packed['y'], $packed['h'], $packed['w']);
        $rotated = imagerotate($piece, 90, imagecolorallocatealpha($piece, 0, 0, 0, 127));
        if ($rotated === false) {
            return;
        }
        imagealphablending($rotated, true);
        imagecopy($canvas, $rotated, $target['x'], $target['y'], 0, 0, imagesx($rotated), imagesy($rotated));
    }

    /**
     * @return array{data: array<mixed>, image: \GdImage}
     */
    private static function atlas(string $gender, string $category, string $value): array
    {
        $key = $value === '' ? "$gender-$category" : "$gender-$category-$value";
        if (!isset(self::$atlases[$key])) {
            $base = dirname(__DIR__, 4) . '/assets/gpack/travian/mainPage/textures/hero/' . $key;
            $json = is_file($base . '.json') ? file_get_contents($base . '.json') : false;
            $data = $json === false ? null : json_decode($json, true);
            $image = is_file($base . '.png') ? imagecreatefrompng($base . '.png') : false;
            if (!is_array($data) || !isset($data['frames']) || $image === false) {
                throw new RuntimeException('Hero atlas is unavailable: ' . $key);
            }
            imagealphablending($image, false);
            self::$atlases[$key] = ['data' => $data, 'image' => $image];
        }

        return self::$atlases[$key];
    }
}
