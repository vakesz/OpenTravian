<?php

declare(strict_types=1);

namespace Game\Payment;

final class Money
{
    private const SYMBOLS = ['USD' => '$', 'EUR' => '€', 'GBP' => '£'];

    /** "$19.99", or "CHF 19.99" for currencies without a well-known symbol. */
    public static function format(int $minor, string $currency): string
    {
        return (self::SYMBOLS[$currency] ?? $currency . ' ') . number_format($minor / 100, 2, '.', ',');
    }
}
