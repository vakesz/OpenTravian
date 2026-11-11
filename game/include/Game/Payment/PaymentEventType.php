<?php

declare(strict_types=1);

namespace Game\Payment;

enum PaymentEventType: string
{
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Disputed = 'disputed';
}
