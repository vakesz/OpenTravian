<?php

declare(strict_types=1);

namespace Game\Payment;

/** A provider call or webhook that must not be trusted or completed. */
final class PaymentException extends \RuntimeException
{
}
