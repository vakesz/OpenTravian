<?php

declare(strict_types=1);

namespace Model\Movements;

use Core\Config;
use Model\MovementsModel;

class EvasionProcessor
{
    /**
     * @param array<string, mixed> $row the arriving movement row
     */
    public function __construct(array $row)
    {
        $seconds = (int) Config::getInstance()->game->evasion_time * 1000;
        (new MovementsModel())->addMovement(
            (int) $row['to_kid'],
            (int) $row['kid'],
            (int) $row['race'],
            array_filter_units($row),
            0,
            0,
            0,
            0,
            1,
            MovementsModel::ATTACKTYPE_EVASION,
            $row['end_time'],
            $row['end_time'] + $seconds
        );
    }
}
