<?php

declare(strict_types=1);

namespace Game\Conquest;

/** BattleModel::$info['oasisCapture'] (hero oasis conquest). Any other value is a [before, after] loyalty pair. */
final class OasisCaptureResult
{
    public const CAPTURED = 0;
    /** The Hero's Mansion has no free oasis slot. */
    public const NO_FREE_SLOT = -1;
}
