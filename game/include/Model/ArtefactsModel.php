<?php

declare(strict_types=1);

namespace Model;

use Core\Config;
use Core\Database\DB;
use Game\Formulas;
use Game\ResourcesHelper;
use mysqli_result;

use function getGameSpeed;
use function logError;
use function shuffle_assoc;

use const MAP_SIZE;

class ArtefactsModel
{
    public const ARTIFACT_INCREASE_BUILDINGS_STABILITY = 2;
    public const ARTIFACT_INCREASE_SPEED = 4;
    public const ARTIFACT_SPY = 5;
    public const ARTIFACT_DIET = 6;
    public const ARTIFACT_CRANNY = 10;
    public const ARTIFACT_INCREASE_TRAINING_SPEED = 8;
    public const ARTIFACT_GREAT_STORE = 9;
    public const ART_FOOL = 11;
    public const WW_PLAN = 12;

    public static function getArtifactActivationTime(): int|float
    {
        $rate = (int) getGameSpeed();
        if ($rate <= 1) {
            return 86400;
        } else if ($rate <= 2) {
            return 86400 * 2 / 3;
        } else if ($rate <= 3) {
            return 86400 * 1 / 2;
        } else if ($rate <= 5) {
            return 8 * 3600;
        } else if ($rate === 10) {
            // OpenTravian publishes a four-hour activation at x10.
            return 4 * 3600;
        }
        // Unsupported speeds retain the established local high-speed policy.
        return max(86400 / $rate, 600);
    }

    public static function getFoolArtifactChangeEffectInterval(): int|float
    {
        $rate = (int) getGameSpeed();
        // The official rule is a fixed 24-hour Fool-effect interval on every
        // published OpenTravian speed.  Keep unlisted custom speeds on
        // the existing bounded local curve below.
        if ($rate == 1 || $rate == 2 || $rate == 3 || $rate == 5 || $rate === 10) {
            return 86400;
        }
        return max(86400 / $rate, 1800);
    }

    /**
     * @param array<string, mixed> $row artefacts row
     */
    public function activateArtifact(array $row): void
    {
        $db = DB::getInstance();
        $db->run('UPDATE artefacts SET active=1 WHERE id=?', [(int) $row['id']]);
        if ((int) $row['effecttype'] === self::ARTIFACT_DIET || (int) $row['type'] === self::ARTIFACT_DIET) {
            if ((int) $row['size'] > 1) {
                $this->refreshUpkeepOfPlayer((int) $row['uid']);
            } else {
                ResourcesHelper::updateVillageUpkeep(-1, (int) $row['kid']);
            }
        }
    }

    /** Recomputes the crop upkeep of every village of a player (the Diet artifact changed). */
    private function refreshUpkeepOfPlayer(int $uid): void
    {
        $stmt = DB::getInstance()->query("SELECT owner, kid, isWW FROM vdata WHERE owner=$uid");
        while ($stmt && ($vi = $stmt->fetch_assoc())) {
            ResourcesHelper::updateVillageUpkeep((int) $vi['owner'], (int) $vi['kid'], (int) $vi['isWW'] === 1);
        }
    }

    /** Multiplier (or capacity) the artifact of the given type has in the village; 1 or 0 when none applies. */
    public static function getArtifactEffectByType(int|string $uid, int|string $kid, int $type): int|float
    {
        switch ($type) {
            case self::ARTIFACT_CRANNY:
            case self::ARTIFACT_DIET:
            case self::ARTIFACT_INCREASE_BUILDINGS_STABILITY:
            case self::ARTIFACT_INCREASE_SPEED:
            case self::ARTIFACT_INCREASE_TRAINING_SPEED:
            case self::ARTIFACT_SPY:
                $artEffect = self::getActiveArtifactInVillage((int) $uid, (int) $kid, $type);

                return $artEffect > 0 ? $artEffect : 1;
            case self::ARTIFACT_GREAT_STORE:
                return self::getActiveArtifactInVillage((int) $uid, (int) $kid, $type) <> 0 ? 1 : 0;
            default:
                return 0;
        }
    }

    private static function getActiveArtifactInVillage(int $uid, int $kid, int $type): int|float
    {
        if (!self::artifactsReleased()) {
            return 0;
        }
        $db = DB::getInstance();
        if ($uid === 0) {
            $uid = (int) $db->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$kid], 0);
        }
        if ($uid === 0) {
            return 0;
        }
        $effect = $db->fetchScalar(
            'SELECT effect FROM artefacts a WHERE uid=? AND status=1 AND active=1 AND effecttype=? AND ((aoe=1 AND kid=?) OR aoe>1) ORDER BY size ASC LIMIT 1',
            [$uid, $type, $kid],
            0
        );

        return is_numeric($effect) ? $effect + 0 : 0;
    }

    public static function artifactsReleased(): bool
    {
        if (php_sapi_name() === 'cli') {
            return (int) DB::getInstance()->fetchScalar('SELECT ArtifactsReleased FROM config LIMIT 1') === 1;
        }

        return (bool) Config::getInstance()->dynamic->ArtifactsReleased;
    }

    public static function wwPlansReleased(): bool
    {
        if (php_sapi_name() === 'cli') {
            return (int) DB::getInstance()->fetchScalar('SELECT WWPlansReleased FROM config LIMIT 1') === 1;
        }

        return (bool) Config::getInstance()->dynamic->WWPlansReleased;
    }

    /**
     * @param array<int, array{item_id: int, level: int}> $defBuildings
     * @param array<int, int> $attackerBuildings level per building slot
     *
     * @return bool|int true when the artifact can be claimed, otherwise:
     * -1 => NoFreeAttackerTreasurySpace
     * -3 => TreasuryExists
     *
     * Owning is not capped (Artifacts wiki): only three artefacts work at once, see fixArtifactOrderForPlayer().
     */
    public function canClaimArtifact(int $size, int $atkKid, array $attackerBuildings, array $defBuildings): bool|int
    {
        $db = DB::getInstance();
        if ((int) $db->fetchScalar('SELECT COUNT(id) FROM artefacts WHERE kid=?', [$atkKid]) >= 1) {
            return -1;
        }
        for ($i = 19; $i <= 38; $i++) {
            if ($defBuildings[$i]['item_id'] == 27 && $defBuildings[$i]['level'] > 0) {
                return -3;
            }
        }
        $needTreasury = $size === 1 ? 10 : 20;

        return $attackerBuildings[27] >= $needTreasury ? true : -1;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getArtefactDetails(int $id): ?array
    {
        $state = DB::getInstance()->query("SELECT * FROM artefacts WHERE id = $id");
        $row = $state instanceof mysqli_result ? $state->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }

    /**
     * Replaces an artifact whose village is gone, keeping the original release spot when it is still free.
     *
     * @param array<string, mixed> $row artefacts row
     */
    public function reSpawnArtifact(array $row): void
    {
        DB::getInstance()->run('DELETE FROM artefacts WHERE id=?', [(int) $row['id']]);
        $this->respawnAtReleaseSpot($row);
    }

    /**
     * @param array<string, mixed> $row artefacts row
     */
    public function clearArtifactFromVillage(array $row): void
    {
        DB::getInstance()->run('DELETE FROM artefacts WHERE id=?', [(int) $row['id']]);
        $this->fixArtifactOrderForPlayer((int) $row['uid']);
        $this->respawnAtReleaseSpot($row);
        $this->fixArtifactOrderForPlayer((int) $row['uid']);
    }

    /**
     * @param array<string, mixed> $row artefacts row
     */
    private function respawnAtReleaseSpot(array $row): void
    {
        $releaseKid = (int) $row['release_kid'];
        if ($releaseKid && (int) DB::getInstance()->fetchScalar("SELECT occupied FROM wdata WHERE id=$releaseKid") !== 1) {
            $this->Artifact((int) $row['type'], (int) $row['size'], (int) $row['num'], $row['effect'], $releaseKid);

            return;
        }
        $this->Artifact((int) $row['type'], (int) $row['size'], (int) $row['num'], $row['effect']);
    }

    /**
     * Creates the artifact village (on $kid, or on a random free spot) and records the artifact.
     *
     * @param array<int, int|float>|null $units garrison of the artifact village; defaults to the size's standard
     * @return int|false the village id, false when no spot could be found
     */
    public function Artifact(int $type, int $size, int $num, string|int|float $effect, int|false $kid = false, ?array $units = null): int|false
    {
        $db = DB::getInstance();
        $db->begin_transaction();
        if (!$kid) {
            $min_r = (int) ceil(MAP_SIZE / 8);
            $max_r = Formulas::getMapMinDistanceFromCenter() + 15;
            $tries = 0;
            $find = 0;
            while ($find <= 0 && $tries <= 10) {
                $max_r -= $tries * 3;
                $find = (int) $db->fetchScalar("SELECT kid FROM available_villages WHERE occupied=0 AND fieldtype=3 AND r >= $min_r AND r <= $max_r ORDER BY rand LIMIT 1");
                $tries++;
            }

            if ($find <= 0) {
                $db->rollback();
                logError('Unable to find a suitable village to create artifact on.');

                return false;
            }

            $kid = $find;
        }
        $units ??= $this->getArtifactUnits()[$size === 1 ? 'small' : ($size === 2 ? 'big' : 'unique')];
        if ((new RegisterModel())->createArtifactVillage($kid, $type, $size, $units)) {
            $db->run(
                'INSERT INTO artefacts (uid, type, kid, release_kid, size, conquered, num, effecttype, effect, aoe) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$type, $kid, $kid, $size, time(), $num, $type, self::effectValue($effect), $size]
            );
            $db->commit();
        } else {
            $db->rollback();
        }

        return $kid;
    }

    /** Effects are stored as numbers; the release tables write fractions as "1/2". */
    private static function effectValue(string|int|float $effect): float
    {
        if (is_string($effect) && str_contains($effect, '/')) {
            [$numerator, $denominator] = explode('/', $effect, 2);

            return (float) $denominator === 0.0 ? 0.0 : (float) $numerator / (float) $denominator;
        }

        return (float) $effect;
    }

    /**
     * @return array{small: array<int, int|float>, big: array<int, int|float>, unique: array<int, int|float>}
     */
    public function getArtifactUnits(): array
    {
        $gameSpeed = (int) getGameSpeed();
        if ($gameSpeed <= 10) {
            $speed = $gameSpeed <= 5 ? 2 : 5;
        } else {
            $speed = (int) ceil($gameSpeed / (isInstantFinishEnabled() ? 40 : 50));
        }
        $units = [
            1 => mt_rand(3000, 5000),
            mt_rand(1000, 3000),
            mt_rand(2000, 5000),
            mt_rand(70, 160),
            mt_rand(1500, 2500),
            mt_rand(1500, 2500),
            mt_rand(850, 2000),
            mt_rand(300, 800),
            1,
            1,
        ];
        $result = [
            'small' => $units,
            'big' => $units,
            'unique' => $units,
        ];
        for ($i = 1; $i <= 8; ++$i) {
            $result['small'][$i] = round($result['small'][$i] * $speed);
            $result['big'][$i] = round($result['big'][$i] * $speed * 1.5389380530973451327433628318584);
            $result['unique'][$i] = round($result['unique'][$i] * $speed * 2.3079646017699115044247787610619);
        }

        return $result;
    }

    public function arteIsMine(int|string $id, int|string $kid, int|string $new_uid): void
    {
        DB::getInstance()->run('UPDATE artefacts SET uid=? WHERE id=?', [(int) $new_uid, (int) $id]);
        $this->captureArtefact($id, $kid, $new_uid);
    }

    /**
     * @param array<string, mixed>|int|string $currentArtifact artefacts row, or its id
     */
    public function captureArtefact(array|int|string $currentArtifact, int|string $kid, int|string $uid): void
    {
        $db = DB::getInstance();
        $kid = (int) $kid;
        $uid = (int) $uid;
        if (!is_array($currentArtifact)) {
            $result = $db->query('SELECT * FROM artefacts WHERE id=' . (int) $currentArtifact);
            $currentArtifact = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
            if (!is_array($currentArtifact)) {
                return;
            }
        }
        $artifactId = (int) $currentArtifact['id'];
        $previousOwner = (int) $currentArtifact['uid'];
        $playerName = (string) $db->fetchScalar('SELECT name FROM users WHERE id=?', [$uid]);

        if ((int) $currentArtifact['type'] === self::WW_PLAN) {
            (new SummaryModel())->setFirstWWPlanUser($playerName);
        } else {
            (new SummaryModel())->setFirstArtifactUser($playerName);
        }

        $time = time();
        $db->run('INSERT INTO artlog (artId, uid, name, kid, time) VALUES (?, ?, ?, ?, ?)', [$artifactId, $uid, $playerName, $kid, $time]);
        if ((int) $currentArtifact['type'] !== self::WW_PLAN) {
            $db->query("UPDATE artefacts SET kid=$kid, uid=$uid, conquered=$time, active=0 WHERE id=$artifactId");
            $this->fixArtifactOrderForPlayer($uid);
            $this->fixArtifactOrderForPlayer($previousOwner);
        } else {
            $db->query("UPDATE artefacts SET status=2 WHERE type=12 AND uid=$uid");
            $db->query("UPDATE artefacts SET kid=$kid, uid=$uid, conquered=$time, status=1, active=0 WHERE id=$artifactId");
        }
        if ((int) $currentArtifact['type'] === self::ART_FOOL) {
            $this->updateFoolArtifact($artifactId);
        }
        $find = $db->query("SELECT kid FROM vdata WHERE (owner=$previousOwner OR owner=$uid)");
        while ($find && ($row = $find->fetch_assoc())) {
            ResourcesHelper::updateVillageResources((int) $row['kid'], false);
        }
    }

    /**
     * Only the three oldest artefacts of a player work (status 1), and at most one of them is large or unique;
     * the rest are kept but disabled (status 2). World Wonder plans have their own status handling.
     */
    public function fixArtifactOrderForPlayer(int $uid): void
    {
        $db = DB::getInstance();
        $db->run('UPDATE artefacts SET status=2 WHERE uid=? AND type<>?', [$uid, self::WW_PLAN]);
        $rows = $db->query('SELECT id, size FROM artefacts WHERE uid=' . $uid . ' AND type<>' . self::WW_PLAN . ' ORDER BY conquered ASC, id ASC');
        $enabled = [];
        $hasBig = false;
        while ($rows && ($row = $rows->fetch_assoc()) && count($enabled) < 3) {
            if ((int) $row['size'] > 1) {
                if ($hasBig) {
                    continue;
                }
                $hasBig = true;
            }
            $enabled[] = (int) $row['id'];
        }
        if ($enabled !== []) {
            $db->query('UPDATE artefacts SET status=1 WHERE id IN (' . implode(',', $enabled) . ')');
        }
    }

    public function releaseArtifacts(): void
    {
        Config::getInstance()->dynamic->ArtifactsReleased = 1;
        $db = DB::getInstance();
        $db->query('UPDATE config SET ArtifactsReleased=1');
        \Game\Webhook\WorldEvents::record('artifactsReleased');
        $stmt = $db->query('SELECT kid FROM artefacts WHERE uid=1');
        while ($stmt && ($row = $stmt->fetch_assoc())) {
            (new AccountDeleter())->deleteVillage((int) $row['kid']);
        }
        $db->query('TRUNCATE TABLE artefacts');
        $units = [];
        foreach ([
            self::ARTIFACT_INCREASE_BUILDINGS_STABILITY,
            self::ARTIFACT_INCREASE_SPEED,
            self::ARTIFACT_SPY,
            self::ARTIFACT_DIET,
            self::ARTIFACT_INCREASE_TRAINING_SPEED,
            self::ARTIFACT_GREAT_STORE,
            self::ARTIFACT_CRANNY,
            self::ART_FOOL,
        ] as $type) {
            $units[$type] = $this->getArtifactUnits();
        }
        $scale = MAP_SIZE / 100;
        $min = 10 * $scale;

        $this->releaseSmallArtifacts($min + 6 * $scale + (3 * $scale * 2), $scale * 2, $units); //far
        $this->releaseBigArtifacts($min + 4 * $scale, $scale * 2, $units); //near
        $this->releaseUniqueArtifacts($min, $units); //nearest

        $this->updateFoolArtes();
        (new PublicMsgModel())->haveNewMessage('[ArtifactsReleaseMessage]');
        (new InfoBoxModel())->deleteInfoByTypeInServer(8);
    }

    /**
     * @param array<int, array{small: array<int, int|float>, big: array<int, int|float>, unique: array<int, int|float>}> $units
     */
    private function releaseSmallArtifacts(int|float $r, int|float $scalePart, array $units): void
    {
        $types = [
            self::ARTIFACT_INCREASE_BUILDINGS_STABILITY => '4',
            self::ARTIFACT_INCREASE_SPEED => '2',
            self::ARTIFACT_SPY => '5',
            self::ARTIFACT_DIET => '1/2',
            self::ARTIFACT_INCREASE_TRAINING_SPEED => '1/2',
            self::ARTIFACT_GREAT_STORE => '1',
            self::ARTIFACT_CRANNY => '200',
            self::ART_FOOL => '0',
        ];
        $types = shuffle_assoc($types);
        $x = 0;
        foreach ($types as $type => $effect) {
            $total = $type === self::ART_FOOL ? 10 : 6;
            $baseAngle = (360 / 8) * $x++;
            for ($i = 1; $i <= $total; ++$i) {
                $angle = $baseAngle + ($i - 1) * (360 / $total);
                while ($angle >= 360) {
                    $angle -= 360;
                }
                if (($kid = $this->findArtifactVillageWithDegree($r, $angle))) {
                    $this->Artifact((int) $type, 1, $i, $effect, $kid, $units[$type]['small']);
                } else {
                    logError("Failed to find a spot for artifact (small) at angle $angle and radii $r.");
                }
            }
            $r += $scalePart;
        }
    }

    private function findArtifactVillageWithDegree(int|float $r, int|float $degree): int
    {
        return (int) DB::getInstance()->fetchScalar("SELECT kid FROM available_villages WHERE occupied=0 AND fieldtype=3 ORDER BY (ABS($r-r)+ABS(angle-$degree)) ASC LIMIT 1");
    }

    /**
     * @param array<int, array{small: array<int, int|float>, big: array<int, int|float>, unique: array<int, int|float>}> $units
     */
    private function releaseBigArtifacts(int|float $r, int|float $scalePart, array $units): void
    {
        $types = [
            self::ARTIFACT_INCREASE_BUILDINGS_STABILITY => '3',
            self::ARTIFACT_INCREASE_SPEED => '1.5',
            self::ARTIFACT_SPY => '3',
            self::ARTIFACT_DIET => '3/4',
            self::ARTIFACT_INCREASE_TRAINING_SPEED => '3/4',
            self::ARTIFACT_GREAT_STORE => '1',
            self::ARTIFACT_CRANNY => '100',
        ];
        $types = shuffle_assoc($types);
        $x = 0;
        foreach ($types as $type => $effect) {
            $baseAngle = 360 / 7 * $x++;
            for ($i = 1; $i <= 4; ++$i) {
                $angle = $baseAngle + ($i - 1) * 90;
                while ($angle >= 360) {
                    $angle -= 360;
                }
                if (($kid = $this->findArtifactVillageWithDegree($r, $angle))) {
                    $this->Artifact((int) $type, 2, $i, $effect, $kid, $units[$type]['big']);
                } else {
                    logError("Failed to find a spot for artifact (big) at angle $angle and radii $r.");
                }
            }
            $r += $scalePart;
        }
    }

    /**
     * @param array<int, array{small: array<int, int|float>, big: array<int, int|float>, unique: array<int, int|float>}> $units
     */
    private function releaseUniqueArtifacts(int|float $r, array $units): void
    {
        $types = [
            self::ARTIFACT_INCREASE_BUILDINGS_STABILITY => '5',
            self::ARTIFACT_INCREASE_SPEED => '2',
            self::ARTIFACT_SPY => '10',
            self::ARTIFACT_DIET => '1/2',
            self::ARTIFACT_INCREASE_TRAINING_SPEED => '1/2',
            self::ARTIFACT_CRANNY => '500',
            self::ART_FOOL => '0',
        ];
        $types = shuffle_assoc($types);
        $angles = $this->getRandomAngles(7);
        $x = 0;
        foreach ($types as $type => $effect) {
            $angle = $angles[$x++];
            if (($kid = $this->findArtifactVillageWithDegree($r, $angle))) {
                $this->Artifact((int) $type, 3, 0, $effect, $kid, $units[$type]['unique']);
            } else {
                logError("Failed to find a spot for artifact (unique) at angle $angle and radii $r.");
            }
        }
    }

    /**
     * @return list<int|float> evenly spread angles from a random start, in random order
     */
    private function getRandomAngles(int $count): array
    {
        $interval = 360 / $count;
        $startAngle = mt_rand(0, 360);
        $angles = [$startAngle];
        for ($i = 1; $i <= ($count - 1); ++$i) {
            $angle = $startAngle + $interval * $i;
            while ($angle >= 360) {
                $angle -= 360;
            }
            $angles[] = $angle;
        }
        shuffle($angles);

        return $angles;
    }

    public function updateFoolArtes(): void
    {
        $db = DB::getInstance();
        $activationTime = self::getArtifactActivationTime();
        $changeInterval = self::getFoolArtifactChangeEffectInterval();
        $time = time() - $changeInterval;
        $find = $db->query('SELECT * FROM artefacts WHERE type=' . self::ART_FOOL . " AND status=1 AND (lastupdate=0 OR GREATEST(lastupdate, conquered+$changeInterval+$activationTime) <= $time)");
        while ($find && ($row = $find->fetch_assoc())) {
            $this->updateFoolArtifact($row);
        }
    }

    public function reSpawnMissingArtifacts(): void
    {
        $artifacts = DB::getInstance()->query('SELECT a.* FROM artefacts a WHERE (SELECT COUNT(v.kid) FROM vdata v WHERE v.kid=a.kid)=0');
        while ($artifacts && ($row = $artifacts->fetch_assoc())) {
            $this->reSpawnArtifact($row);
        }
    }

    /**
     * Rolls a new random effect for a Fool artifact.
     *
     * @param array<string, mixed>|int|string $row artefacts row, or its id
     */
    public function updateFoolArtifact(array|int|string $row): void
    {
        $time = time();
        $db = DB::getInstance();
        if (!is_array($row)) {
            $result = $db->query('SELECT * FROM artefacts WHERE id=' . (int) $row);
            $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
            if (!is_array($row)) {
                return;
            }
        }
        $effect_arr = [
            self::ARTIFACT_INCREASE_BUILDINGS_STABILITY,
            self::ARTIFACT_INCREASE_SPEED,
            self::ARTIFACT_SPY,
            self::ARTIFACT_DIET,
            self::ARTIFACT_CRANNY,
            self::ARTIFACT_INCREASE_TRAINING_SPEED,
        ];
        shuffle($effect_arr);
        $effecttype = $effect_arr[mt_rand(0, 5)];
        $aoe = mt_rand(1, 100) <= 60 ? 1 : 2;
        $size = (int) $row['size'];
        if ($size === 3) {
            $aoe = 3;
        }
        $negative = $size === 1 && mt_rand(1, 3) === 3;
        $effect = match ($effecttype) {
            self::ARTIFACT_INCREASE_BUILDINGS_STABILITY => $negative ? [1 / 2, 1 / 3, 1 / 4][mt_rand(0, 2)] : [2, 3, 4][mt_rand(0, 2)],
            self::ARTIFACT_INCREASE_SPEED => $negative ? [2 / 3, 1 / 2][mt_rand(0, 1)] : [3 / 2, 2][mt_rand(0, 1)],
            self::ARTIFACT_SPY => $negative ? [1 / 3, 1 / 5, 1 / 10][mt_rand(0, 2)] : [3, 5, 10][mt_rand(0, 2)],
            self::ARTIFACT_DIET => $negative ? [4 / 3, 2, 3][mt_rand(0, 2)] : [3 / 4, 1 / 2][mt_rand(0, 1)],
            self::ARTIFACT_CRANNY => $negative ? [3 / 4, 1 / 2, 1 / 4, 1 / 10][mt_rand(0, 3)] : mt_rand(100, 500),
            self::ARTIFACT_INCREASE_TRAINING_SPEED => $negative ? [3 / 2, 2, 4 / 3][mt_rand(0, 2)] : [1 / 2, 3 / 4][mt_rand(0, 1)],
        };
        $id = (int) $row['id'];
        $db->run('UPDATE artefacts SET lastupdate=?, effecttype=?, effect=?, aoe=? WHERE id=?', [$time, $effecttype, $effect, $aoe, $id]);
        if ($effecttype === self::ARTIFACT_DIET || (int) $row['effecttype'] === self::ARTIFACT_DIET) {
            if ($size === 1 && $aoe === 1) {
                ResourcesHelper::updateVillageResources((int) $row['kid'], false);
            } else {
                $this->refreshUpkeepOfPlayer((int) $row['uid']);
            }
        }
    }

    public function releaseWWPlans(): void
    {
        Config::getInstance()->dynamic->WWPlansReleased = 1;
        $db = DB::getInstance();
        $db->query('UPDATE config SET WWPlansReleased=1');
        \Game\Webhook\WorldEvents::record('wwPlansReleased');
        $plansEnabled = (bool) Config::getProperty('custom', 'wwPlansEnabled');
        if ($plansEnabled) {
            $count = (int) Config::getProperty('custom', 'wwCount');
            foreach ((new WonderOfTheWorldModel())->findPositionsForWWPlan($count) as $kid) {
                $this->Artifact(12, 1, 0, 0, (int) $kid);
            }
        }
        $find = $db->query('SELECT kid, isWW, owner FROM vdata WHERE isWW=1');
        while ($find && ($row = $find->fetch_assoc())) {
            ResourcesHelper::updateVillageUpkeep((int) $row['owner'], (int) $row['kid'], (int) $row['isWW'] === 1, VillageModel::getHDP((int) $row['kid']));
        }
        (new PublicMsgModel())->haveNewMessage($plansEnabled ? '[WWPlansReleaseMessage]' : '[WWConstructStart]');
        (new InfoBoxModel())->deleteInfoByTypeInServer(9);
        if (Config::getProperty('custom', 'removeProtectionAfterReleaseOfWWPlans')) {
            $db->run('UPDATE users SET protection=? WHERE protection > ?', [time(), time()]);
            (new InfoBoxModel())->deleteInfoByTypeInServer(6);
        }
    }
}
