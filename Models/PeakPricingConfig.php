<?php

declare(strict_types=1);

/**
 * Peak multiplier bounds (see bootstrap PEAK_MULTIPLIER / PEAK_MULTIPLIER_MIN / PEAK_MULTIPLIER_MAX).
 */
final class PeakPricingConfig
{
    public static function bounded(float $raw, float $min, float $max): float
    {
        return max($min, min($max, $raw));
    }

    public static function effectiveMultiplierFromGlobals(): float
    {
        $raw = defined('PEAK_MULTIPLIER') ? (float)PEAK_MULTIPLIER : 1.25;
        $min = defined('PEAK_MULTIPLIER_MIN') ? (float)PEAK_MULTIPLIER_MIN : 1.0;
        $max = defined('PEAK_MULTIPLIER_MAX') ? (float)PEAK_MULTIPLIER_MAX : 3.0;

        return self::bounded($raw, $min, $max);
    }
}
