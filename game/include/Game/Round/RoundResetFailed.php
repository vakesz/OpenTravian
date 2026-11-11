<?php

declare(strict_types=1);

namespace Game\Round;

/** A round reset that failed after it changed the world; the world is held in recovery mode. */
final class RoundResetFailed extends \RuntimeException
{
}
