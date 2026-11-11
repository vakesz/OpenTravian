<?php

declare(strict_types=1);

namespace Model\Movements {
    function mt_rand(int $min, int $max): int
    {
        return match ([$min, $max]) {
            [1, 14] => 1,
            [1, 18] => 15,
            default => $min,
        };
    }
}

namespace {
    use Core\Config;
    use Core\Database\DB;
    use Core\Session;
    use Game\Hero\Artwork;
    use Game\Hero\HeroItems;
    use Game\Hero\SessionHero;
    use Model\HeroInventoryModel;
    use Model\Movements\AdventureProcessor;

    require dirname(__DIR__, 2) . '/game/deployment/include/env.php';
    require dirname(__DIR__, 2) . '/game/include/bootstrap.php';
    require_once dirname(__DIR__) . '/support/village-fixture.php';

    set_exception_handler(static function (Throwable $exception): never {
        fwrite(STDERR, (string)$exception . PHP_EOL);
        exit(1);
    });
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    function assertArtwork(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    final class ArtworkFixtureSession extends Session
    {
        public function __construct(private readonly int $fixtureUid) {}

        public function getPlayerId(): int
        {
            return $this->fixtureUid;
        }
    }

    $db = DB::getInstance();
    // A throwaway Roman player with a hero and an inventory, cloned from a world capital and removed at shutdown,
    // so the test depends on no installed account.
    $fixture = fixtureVillageCreate(1);
    $uid = $fixture['id'];
    $kid = $fixture['kid'];
    if ((int)$db->fetchScalar("SELECT COUNT(*) FROM hero WHERE uid=$uid") !== 1 || (int)$db->fetchScalar("SELECT COUNT(*) FROM inventory WHERE uid=$uid") !== 1) {
        throw new RuntimeException('The world has no player with a hero and an inventory to clone the fixture from.');
    }
    $config = Config::getInstance();
    $originalSpeed = $config->game->speed;
    $sessionProperty = new ReflectionProperty(Session::class, '_self');
    $originalSession = $sessionProperty->getValue();
    $sessionProperty->setValue(null, new ArtworkFixtureSession($uid));
    $sessionHero = (new ReflectionClass(SessionHero::class))->newInstanceWithoutConstructor();
    // The use dialog of a consumable reads the hero's health and experience, so the bare instance needs the fixture hero row.
    $sessionHero->hero = $db->query("SELECT uid, kid, exp, health, power, offBonus, itemHealth, lastupdate, defBonus, production, productionType, hide FROM hero WHERE uid=$uid")->fetch_assoc();
    $db->begin_transaction();
    try {
        $db->query("DELETE FROM items WHERE uid=$uid AND btype=15");
        $db->query("INSERT INTO items (uid, btype, type, num, proc, placeId) VALUES ($uid, 15, 111, 20, 0, 1000)");
        $itemId = (int)$db->lastInsertId();
        $db->query("UPDATE vdata SET cp=0 WHERE owner=$uid");
        $db->query("UPDATE vdata SET cp=1200 WHERE kid=$kid");
        $model = new HeroInventoryModel();
        $snapshot = static function () use ($db, $uid, $itemId): array {
            return [
                (float)$db->fetchScalar("SELECT cp FROM users WHERE id=$uid"),
                (int)$db->fetchScalar("SELECT num FROM items WHERE id=$itemId"),
                (int)$db->fetchScalar("SELECT lastArtworkUse FROM inventory WHERE uid=$uid"),
            ];
        };
        $reject = static function (int $amount) use ($model, $uid, $kid, $itemId, $snapshot): void {
            $before = $snapshot();
            try {
                $model->apply($uid, $kid, $itemId, 'bag', $amount, true);
                throw new RuntimeException('Invalid artwork use unexpectedly succeeded.');
            } catch (DomainException $exception) {
                assertArtwork($snapshot() === $before, 'Rejected artwork use changed CP, inventory, or cooldown.');
            }
        };
        foreach ([1 => 1200, 2 => 800, 3 => 600, 5 => 400, 10 => 200] as $speed => $expected) {
            $config->game->speed = $speed;
            $db->query("UPDATE inventory SET lastArtworkUse=0 WHERE uid=$uid");
            $reject(2);
            $before = $snapshot();
            $preview = array_values(array_filter($sessionHero->getHeroItems(), static fn(array $item): bool => $item['id'] === $itemId))[0]['artwork'];
            assertArtwork($preview['cp'] === $expected && $preview['availableAt'] === 0, 'Initial inventory preview must match the server reward and allow first use.');
            $start = time();
            assertArtwork($model->apply($uid, $kid, $itemId, 'bag', 1, true) === 1, 'Artwork must consume exactly one item.');
            $after = $snapshot();
            assertArtwork($after[0] - $before[0] === (float)$expected && $after[1] === $before[1] - 1
                && $after[2] >= $start, "Incorrect CP credit, consumption, or persisted cooldown at x$speed.");
            $preview = array_values(array_filter($sessionHero->getHeroItems(), static fn(array $item): bool => $item['id'] === $itemId))[0]['artwork'];
            assertArtwork($preview['availableAt'] === Artwork::availableAt($speed, $after[2]), 'Refreshed inventory must expose the persisted cooldown.');
            $reject(1);
            $cooldown = Artwork::rules($speed)['cooldown'];
            $db->query("UPDATE inventory SET lastArtworkUse=" . (time() - $cooldown) . " WHERE uid=$uid");
            assertArtwork($model->apply($uid, $kid, $itemId, 'bag', 1, true) === 1, 'Expired cooldown must allow use.');
            $properties = (new HeroItems())->getHeroItemProperties(15, 111);
            assertArtwork($properties['cp'] === Artwork::rules($speed)['limit']
                && !str_contains(implode(' ', $properties['attributes']), '%s'), 'Artwork tooltip must use the shared rule.');
        }

        $config->game->speed = 2;
        $db->query("UPDATE inventory SET lastArtworkUse=0 WHERE uid=$uid");
        $db->query("UPDATE vdata SET cp=0 WHERE owner=$uid");
        $reject(1);
        $db->query("UPDATE vdata SET cp=99999 WHERE kid=$kid");
        $before = $snapshot();
        $model->apply($uid, $kid, $itemId, 'bag', 1, true);
        assertArtwork($snapshot()[0] - $before[0] === 1300.0, 'Production above the cap must grant exactly 1300 CP at x2.');

        $target = $kid + 1;
        $db->query("UPDATE inventory SET bag=0, helmet=0, body=0, rightHand=0, leftHand=0, shoes=0, horse=0 WHERE uid=$uid");
        $db->query("UPDATE hero SET health=100, power=1000, offBonus=0, itemHealth=0, lastupdate=" . time() . " WHERE uid=$uid");
        $db->query("UPDATE users SET signupTime=" . time() . " WHERE id=$uid");
        $db->query("UPDATE adventure SET end=1 WHERE uid=$uid AND kid=$target");
        $db->query("INSERT INTO adventure (uid, kid, dif, created, end) VALUES ($uid, $target, 0, " . (time() - 3600) . ", 0)");
        $adventureId = (int)$db->lastInsertId();
        foreach ([30 => 0, 31 => 1] as $previous => $expectedDrop) {
            $db->query("UPDATE users SET success_adventures_count=$previous WHERE id=$uid");
            $db->query("UPDATE adventure SET end=0 WHERE id=$adventureId");
            $before = (int)$db->fetchScalar("SELECT COALESCE(SUM(num), 0) FROM items WHERE uid=$uid AND btype=15");
            new AdventureProcessor([
                'kid' => $kid, 'to_kid' => $target, 'race' => 1,
                'start_time' => (time() - 10) * 1000, 'end_time' => time() * 1000,
                'attack_type' => 50, 'data' => '',
            ]);
            $after = (int)$db->fetchScalar("SELECT COALESCE(SUM(num), 0) FROM items WHERE uid=$uid AND btype=15");
            assertArtwork($after - $before === $expectedDrop, 'Actual adventure processor must exclude artwork through adventure 31 and permit it on adventure 32.');
        }
        echo "Artwork use, atomic rejections, cooldown expiry, caps, tooltips and real adventure 31/32 drops passed (rolled back).\n";
    } finally {
        $db->rollback();
        $config->game->speed = $originalSpeed;
        $sessionProperty->setValue(null, $originalSession);
    }
}
