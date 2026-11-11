<?php

declare(strict_types=1);

namespace Model;

use Core\Database\DB;
use Game\Formulas;
use Game\NoticeHelper;
use Game\ResourcesHelper;
use Game\Ships;

class MarketPlaceProcessor
{
    /**
     * Processes one locked send row. The caller (TransactionalTask) deletes the row in the same transaction.
     *
     * @param array<string, mixed> $row
     */
    public function processRow(array $row): void
    {
        if ((int) $row['mode'] === 1) {
            $this->processReturn($row);
            return;
        }
        $this->processGo($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function processGo(array $row): void
    {
        $kid = (int) $row['kid'];
        $toKid = (int) $row['to_kid'];
        $m = new AutomationModel();
        $sender = $m->getVillage($kid, 'owner');
        if ($sender === false) {
            return;
        }
        $sender = $m->getUser((int) $sender['owner'], 'id, reportFilters, name, race');
        $receiver = $m->getVillage($toKid, 'owner');
        if ($receiver === false) {
            return;
        }
        $receiver = $m->getUser((int) $receiver['owner'], 'id, reportFilters, name, race');
        $resources = [
            1 => (int) $row['wood'],
            (int) $row['clay'],
            (int) $row['iron'],
            (int) $row['crop'],
        ];
        $report = [
            'sender' => [
                'uid' => $sender['id'],
                'uname' => $sender['name'],
                'kid' => $kid,
            ],
            'receiver' => [
                'uid' => $receiver['id'],
                'uname' => $receiver['name'],
                'kid' => $toKid,
            ],
            'resources' => $resources,
            'timeTaken' => Ships::tradeTravelSeconds($kid, $toKid, (int) $sender['race'], (int) $row['ships'] > 0),
        ];
        $reportType = [
            1 => NoticeHelper::TYPE_RESOURCES_MOST_WOOD,
            NoticeHelper::TYPE_RESOURCES_MOST_CLAY,
            NoticeHelper::TYPE_RESOURCES_MOST_IRON,
            NoticeHelper::TYPE_RESOURCES_MOST_CROP,
        ][array_search(max($resources), $resources)];
        $senderId = (int) $sender['id'];
        $receiverId = (int) $receiver['id'];
        $sender_reportFilters = explode(',', $sender['reportFilters']);
        if ($senderId === $receiverId ? $sender_reportFilters[0] == 0 : $sender_reportFilters[1] == 0) {
            NoticeHelper::addNotice(0, $senderId, $kid, $toKid, $reportType, '', $report, $row['end_time']);
        }
        $db = DB::getInstance();
        if ($senderId !== $receiverId) {
            MultiAccount::addMultiAccountLog($senderId, $receiverId, 1);
            $receiver_reportFilters = explode(',', $receiver['reportFilters']);
            if ($receiver_reportFilters[2] == 0) {
                NoticeHelper::addNotice(0, $receiverId, $kid, $toKid, $reportType, '', $report, $row['end_time']);
            }
            // Trading volume behind the General statistics resource rank (own transfers are not trade).
            $volume = array_sum($resources);
            $db->query("UPDATE users SET stat_trade_out=stat_trade_out+$volume WHERE id=$senderId");
            $db->query("UPDATE users SET stat_trade_in=stat_trade_in+$volume WHERE id=$receiverId");
        }
        $db->query("UPDATE vdata SET wood=wood+{$resources[1]}, clay=clay+{$resources[2]}, iron=iron+{$resources[3]}, crop=crop+{$resources[4]} WHERE kid=$toKid");
        ResourcesHelper::updateVillageResources($toKid);
        $this->returnMerchants($row, $report['timeTaken']);
        (new MasterBuilder())->updateCommence($toKid, false);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function returnMerchants(array $row, int|float $timeTaken): void
    {
        $this->insert(
            (int) $row['to_kid'],
            (int) $row['kid'],
            [(int) $row['wood'], (int) $row['clay'], (int) $row['iron'], (int) $row['crop']],
            (int) $row['x'] - 1,
            1,
            $row['end_time'] + $timeTaken,
            (int) $row['ships']
        );
    }

    /**
     * @param list<int> $resources wood, clay, iron, crop
     */
    private function insert(int $kid, int $to_kid, array $resources, int $x2, int $mode, int|float|string $end_time, int $ships): void
    {
        [$r1, $r2, $r3, $r4] = $resources;
        DB::getInstance()->query("INSERT INTO send (`kid`, `to_kid`, `wood`, `clay`, `iron`, `crop`, `x`, `ships`, `mode`, `end_time`) VALUES ($kid, $to_kid, $r1, $r2, $r3, $r4,$x2, $ships, $mode, $end_time)");
    }

    /**
     * @param array<string, mixed> $row
     */
    private function processReturn(array $row): void
    {
        $kid = (int) $row['kid'];
        $toKid = (int) $row['to_kid'];
        if (!$row['x']) {
            // The last return of a delivery: trade ships dock at their home Harbor.
            Ships::dockTradeShips($toKid, (int) $row['ships']);
            return;
        }
        $m = new AutomationModel();
        ResourcesHelper::updateVillageResources($toKid);
        $res = $m->getVillage($toKid, 'owner, wood, clay, iron, crop');
        if ($res === false) {
            return;
        }
        $resources = [
            (int) floor(max(0, min((float) $res['wood'], (float) $row['wood']))),
            (int) floor(max(0, min((float) $res['clay'], (float) $row['clay']))),
            (int) floor(max(0, min((float) $res['iron'], (float) $row['iron']))),
            (int) floor(max(0, min((float) $res['crop'], (float) $row['crop']))),
        ];
        $end_time = $row['end_time'] + Ships::tradeTravelSeconds($kid, $toKid, (int) $m->getUser((int) $res['owner'], 'race')['race'], (int) $row['ships'] > 0);
        DB::getInstance()->query("UPDATE vdata SET wood=wood-{$resources[0]}, clay=clay-{$resources[1]}, iron=iron-{$resources[2]}, crop=crop-{$resources[3]} WHERE kid=$toKid");
        $this->insert($toKid, $kid, $resources, (int) $row['x'], 0, $end_time, (int) $row['ships']);
        (new MasterBuilder())->updateCommence($toKid, false, false);
    }
}