<?php

declare(strict_types=1);

namespace Model;

use Core\Caching\Caching;
use Core\Config;
use Core\Database\DB;
use Game\Tribes;
use function isServerFinished;

class SummaryModel
{
    /**
     * @return array<string, mixed>|null
     */
    public function getSummary(): ?array
    {
        $cache = Caching::getInstance();
        if ($cached = $cache->get('Statistics:General:Tribes')) {
            return $cached;
        }
        $result = DB::getInstance()->query('SELECT * FROM summary LIMIT 1')->fetch_assoc();
        $cache->add('Statistics:General:Tribes', $result, 86400);

        return $result;
    }

    public function setFirstVillageUser(string $playerName): void
    {
        $this->setFirst('first_village', $playerName);
    }

    public function setFirstArtifactUser(string $playerName): void
    {
        $this->setFirst('first_art', $playerName);
    }

    public function setFirstWWUser(string $playerName): void
    {
        $this->setFirst('first_ww', $playerName);
    }

    public function setFirstWWPlanUser(string $playerName): void
    {
        $this->setFirst('first_ww_plan', $playerName);
    }

    /**
     * @return array<string, int|float>
     */
    public function getTotalSummary(): array
    {
        $cache = Caching::getInstance();
        if ($cached = $cache->get('Statistics:General:Players')) {
            return $cached;
        }
        $online_timeout = (int) Config::getProperty('settings', 'online_timeout');
        $row = DB::getInstance()->query('SELECT
        (SELECT COUNT(id) FROM users WHERE id>1 AND hidden=0 AND access>0) `active`,
        (SELECT COUNT(id) FROM users WHERE id>1 AND hidden=0 AND access=3) `fakeCount`,
        (SELECT COUNT(id) FROM users WHERE id>1 AND hidden=0 AND access>0 AND access!=3 AND email_verified=1) `verified`,
        (SELECT COUNT(id) FROM users WHERE id>1 AND hidden=0 AND access>0 AND last_login_time >= ' . (time() - $online_timeout) . ') `real_online`,
        (SELECT COUNT(id) FROM activation) `activating`')->fetch_assoc();
        $result = array_map('intval', $row);
        $result['registered'] = $result['active'] + $result['activating'];
        $result['online'] = $result['real_online'];
        $result['real_players'] = $result['active'] - $result['fakeCount'];
        if (!isServerFinished()) {
            $result['online'] += mt_rand(40, 60);
            if ($result['online'] >= $result['active']) {
                $result['online'] *= 0.65;
            }
        }
        $result['online'] = min(floor($result['online']), $result['active']);
        $cache->add('Statistics:General:Players', $result, 60);

        return $result;
    }

    public function addPlayerToSummary(int $race): void
    {
        $this->adjustTribeCount($race, '+');
    }

    public function deletePlayerFromSummary(int $race): void
    {
        $this->adjustTribeCount($race, '-');
    }

    /** Records the first player to reach a milestone, once. */
    private function setFirst(string $prefix, string $playerName): void
    {
        DB::getInstance()->run(
            "UPDATE summary SET {$prefix}_player_name=?, {$prefix}_time=? WHERE {$prefix}_time=0",
            [$playerName, time()]
        );
    }

    private function adjustTribeCount(int $race, string $sign): void
    {
        $column = Tribes::summaryColumn($race);
        if ($column === null) {
            return;
        }
        DB::getInstance()->query("UPDATE summary SET players_count=players_count{$sign}1, `{$column}`=`{$column}`{$sign}1");
        $cache = Caching::getInstance();
        $cache->delete('Statistics:General:Players');
        $cache->delete('Statistics:General:Tribes');
    }
}
