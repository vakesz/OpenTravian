<?php

declare(strict_types=1);

namespace Model;

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Core\Helper\StringChecker;
use Core\Session;
use Game\Formulas;
use Game\NoticeHelper;
use function clean_string_from_white;

class ProfileModel
{
    /**
     * @param string $cols trusted, hard-coded column list
     * @return array<string, mixed> the requested columns, empty when the player does not exist
     */
    public function getPlayer(int $uid, string $cols = '*'): array
    {
        return DB::getInstance()->query("SELECT $cols FROM users WHERE id=$uid")->fetch_assoc() ?? [];
    }

    /**
     * @return array{total_pop: string, village_count: string}|null
     */
    public function getPlayerTotalVillagesAndPop(int $uid): ?array
    {
        return DB::getInstance()->query("SELECT total_pop, total_villages village_count FROM users WHERE id=$uid")->fetch_assoc();
    }

    /**
     * Equipped hero items as rendered on the profile overview, keyed by slot.
     * Values are the `items.type` of the equipped item (0 when the slot is empty).
     *
     * @return array<string,int>
     */
    public function getEquippedHeroItemTypes(int $uid): array
    {
        $db = DB::getInstance();
        $slots = ['helmet', 'body', 'shoes', 'leftHand', 'rightHand', 'horse'];
        $equipped = array_fill_keys($slots, 0);
        $inventory = $db->query("SELECT " . implode(',', $slots) . " FROM inventory WHERE uid=$uid")->fetch_assoc();
        if (!$inventory) {
            return $equipped;
        }
        foreach ($slots as $slot) {
            $itemId = (int)$inventory[$slot];
            if ($itemId > 0) {
                $equipped[$slot] = (int)$db->fetchScalar("SELECT type FROM items WHERE id=$itemId AND uid=$uid");
            }
        }
        return $equipped;
    }

    public function getHeroGender(int $uid): string
    {
        $gender = DB::getInstance()->fetchScalar("SELECT gender FROM face WHERE uid=$uid");
        return $gender === 'female' ? 'female' : 'male';
    }

    /** Whole days since the player registered (users.signupTime). */
    public function getDaysPlaying(int $uid): int
    {
        $signedUp = (int)DB::getInstance()->fetchScalar("SELECT signupTime FROM users WHERE id=$uid");
        return $signedUp > 0 ? max(0, (int)floor((time() - $signedUp) / 86400)) : 0;
    }

    public function getPlayerVillages(int $uid, bool $byName = false): \mysqli_result|bool
    {
        return DB::getInstance()->query(
            "SELECT kid, seq, name, owner, isWW, pop, capital FROM vdata WHERE owner=$uid ORDER BY " . ($byName ? 'name' : 'seq ASC')
        );
    }

    /**
     * React-shaped profile save used by /api/v1/profile. Language codes are
     * already whitelisted by Game\ProfileLanguages; keep the database value
     * as compact JSON so an existing world can migrate without a join table.
     *
     * @param list<string> $additionalLanguages
     */
    public function updateProfileV2(
        int $uid,
        string $desc1,
        string $desc2,
        int $gender,
        string $location,
        bool $showCountryFlag,
        array $additionalLanguages
    ): bool {
        if (!StringChecker::isValidMessage($desc1)
            || !StringChecker::isValidMessage($desc2)
            || !StringChecker::isValidName($location)
            || strlen($location) > 30) {
            return false;
        }

        $encodedLanguages = json_encode($additionalLanguages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedLanguages === false) {
            return false;
        }

        $db = DB::getInstance();
        $saved = $db->run(
            'UPDATE users SET desc1=?, desc2=?, gender=?, location=?, showCountryFlag=?, additionalLanguages=? WHERE id=?',
            [
                htmlspecialchars(strip_tags($desc1), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars(strip_tags($desc2), ENT_QUOTES, 'UTF-8'),
                $gender,
                htmlspecialchars(strip_tags($location), ENT_QUOTES, 'UTF-8'),
                $showCountryFlag ? 1 : 0,
                $encodedLanguages,
                $uid,
            ]
        );

        return $saved !== false;
    }

    public function updateVillageName(int $kid, mixed $name): void
    {
        $name = clean_string_from_white(strip_tags(is_string($name) ? $name : ''));
        if (!StringChecker::isValidName($name) || strlen($name) > 45) {
            return;
        }
        $db = DB::getInstance();
        $row = $db->query("SELECT name, owner FROM vdata WHERE kid=$kid")->fetch_assoc();
        if ($row !== null) {
            if ($row['owner'] == Session::getInstance()->getPlayerId() && $row['name'] != $name) {
                Quest::getInstance()->setQuestBitwise('world', 2, 1);
                $name = $db->real_escape_string(htmlspecialchars($name, ENT_QUOTES));
                $db->query("UPDATE vdata SET name='$name' WHERE kid=$kid");
                $xy = Formulas::kid2xy($kid);
                NoticeHelper::addSurrounding(
                    $xy['x'],
                    $xy['y'],
                    NoticeHelper::SURROUNDING_VILLAGE_RENAME,
                    [
                        Session::getInstance()->getPlayerId(),
                        Session::getInstance()->getName(),
                        $kid,
                        $row['name'],
                        $name,
                    ],
                    time()
                );
                Quest::getInstance()->setQuestBitwise('world', 2, \Model\Quest::QUEST_FINISHED);
                Caching::getInstance()->delete("ActiveVillageBox:{$kid}");
            }
        }
    }

    public function getVillageOases(int $kid): \mysqli_result|bool
    {
        return DB::getInstance()->query("SELECT type FROM odata WHERE did=$kid");
    }

    public function isThereAnArtifact(int $kid): bool
    {
        return Config::getInstance()->dynamic->ArtifactsReleased
            && (int) DB::getInstance()->fetchScalar("SELECT COUNT(id) FROM artefacts WHERE kid=$kid") > 0;
    }
}
