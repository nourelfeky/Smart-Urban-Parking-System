<?php

declare(strict_types=1);

/**
 * Pure subscription period math: calendar day span and billed "week" count (ceiling of partial weeks).
 * All date logic uses date-only instants at 00:00:00 in the app timezone.
 */
final class SubscriptionPeriodService
{
    /**
     * Whole calendar days between two date-only instants (exclusive of the same moment; e.g. Mon→next Mon = 7).
     */
    public static function calendarDayDelta(DateTimeImmutable $startDate, DateTimeImmutable $endDate): int
    {
        return (int) floor(($endDate->getTimestamp() - $startDate->getTimestamp()) / 86400);
    }

    /**
     * DurationWeeks = ceil(dayDelta / 7). Caller must ensure dayDelta ≥ 0.
     */
    public static function durationWeeksFromCalendarDayDelta(int $dayDelta): int
    {
        return (int) ceil($dayDelta / 7);
    }
}
