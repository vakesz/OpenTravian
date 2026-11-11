<?php

declare(strict_types=1);

namespace Model\Movements;

use Core\Database\DB;
use Game\ResourcesHelper;
use Model\HeroResourceInventory;
use Model\MovementsModel;

class ReturnProcessor
{
    /**
     * @param array<string, mixed> $row the arriving movement row
     */
    public function __construct(array $row)
    {
        if ((int) $row['race'] === 5) {
            return;
        }
        $db = DB::getInstance();
        $toKid = (int) $row['to_kid'];
        $modify = [];
        for ($i = 1; $i <= 11; ++$i) {
            $count = (int) $row['u' . $i];
            if ($count < 0) {
                return; //There is a bug certainly
            }
            if ($count > 0) {
                $modify[] = "u{$i}=u{$i}+" . $count;
            }
        }
        if ($modify !== []) {
            $db->query('UPDATE units SET ' . implode(',', $modify) . " WHERE kid=$toKid");
            if ((int) $row['kid'] !== 0) {
                $db->query('UPDATE vdata SET lastReturn=' . (int) ceil($row['end_time'] / 1000) . " WHERE kid=$toKid");
            }
        }
        $data = (string) ($row['data'] ?? '');
        if ($data === '') {
            return;
        }
        $carryAndLoot = explode(',', $data);
        if (!isset($carryAndLoot[4])) {
            return;
        }
        $resources = [
            'wood' => max(0, (int) $carryAndLoot[0]),
            'clay' => max(0, (int) $carryAndLoot[1]),
            'iron' => max(0, (int) $carryAndLoot[2]),
            'crop' => max(0, (int) $carryAndLoot[3]),
        ];
        if (array_sum($resources) === 0) {
            return;
        }

        if ((int) $row['attack_type'] === MovementsModel::ATTACKTYPE_ADVENTURE) {
            $owner = (int) $db->fetchScalar('SELECT owner FROM vdata WHERE kid=?', [$toKid], 0);
            if ($owner <= 0 || !(new HeroResourceInventory())->add($owner, $resources)) {
                throw new \RuntimeException('Could not add adventure resources to the hero inventory.');
            }
            return;
        }
        $db->run(
            'UPDATE vdata SET wood=wood+?, clay=clay+?, iron=iron+?, crop=crop+? WHERE kid=?',
            [$resources['wood'], $resources['clay'], $resources['iron'], $resources['crop'], $toKid]
        );
        ResourcesHelper::updateVillageResources($toKid);
    }
}
