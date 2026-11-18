<?php

namespace App\Helper;

/**
 * Splits VAT-inclusive amounts using the configured rate (config/orders.php).
 * Net and VAT are rounded to 2 decimals so that net + vat === total.
 */
class Vat
{
    public static function rate(): float
    {
        return (float) config('orders.vat_rate', 11);
    }

    /** Rate as a fraction, e.g. 0.11 (stored in cotatva columns). */
    public static function fraction(): float
    {
        return self::rate() / 100;
    }

    /** Net amount (without VAT) of a VAT-inclusive amount, rounded to 2 decimals. */
    public static function net(float $gross): float
    {
        return round($gross / (1 + self::fraction()), 2);
    }

    /** VAT part of a VAT-inclusive amount, so that net() + vat() === $gross. */
    public static function vat(float $gross): float
    {
        return round($gross - self::net($gross), 2);
    }
}
