<?php

declare(strict_types=1);

namespace Game\SocialAuth;

/** An id_token or provider response failed verification. The message never carries token material. */
final class TokenException extends \RuntimeException
{
}
