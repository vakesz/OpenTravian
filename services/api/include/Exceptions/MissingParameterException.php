<?php

declare(strict_types=1);

namespace Exceptions;

use PHPMailer\PHPMailer\Exception;
use Throwable;

class MissingParameterException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct('Missing parameter "' . $message . '"', $code, $previous);
    }
}
