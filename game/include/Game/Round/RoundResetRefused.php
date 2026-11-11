<?php

declare(strict_types=1);

namespace Game\Round;

/** A round reset that did not start: the world has not been changed. The message is shown to the administrator. */
final class RoundResetRefused extends \RuntimeException
{
}
