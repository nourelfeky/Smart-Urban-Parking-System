<?php

declare(strict_types=1);

/**
 * Rolling 30-day loyalty tier and booking discount (see DriverController::refreshLoyaltyRolling / book()).
 */
final class LoyaltyProgram
{
    public static function tierFromRollingBookingCount(int $countLast30Days): string
    {
        return $countLast30Days >= 20 ? 'gold' : ($countLast30Days >= 5 ? 'silver' : 'bronze');
    }

    public static function bookingDiscountPercent(int $countLast30Days): int
    {
        if ($countLast30Days >= 20) {
            return 15;
        }
        if ($countLast30Days >= 10) {
            return 10;
        }
        if ($countLast30Days >= 5) {
            return 5;
        }

        return 0;
    }
}
