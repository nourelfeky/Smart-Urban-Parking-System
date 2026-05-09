<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/AppClock.php';

/**
 * Grace period, no-show transitions, and QR check-in/out window rules using one clock.
 */
final class ReservationTimeService
{
    /** Minutes before start_time when QR check-in is allowed (early arrival). */
    private const CHECK_IN_EARLY_MINS = 15;

    public static function verifyQrToken(?string $posted, string $stored): bool
    {
        $posted = $posted !== null ? trim($posted) : '';
        if ($posted === '' || $stored === '') {
            return false;
        }

        return hash_equals($stored, $posted);
    }

    /**
     * Mark confirmed bookings as no_show when start + grace has passed and driver never checked in.
     * Uses DB NOW() after PDO session time_zone is aligned with PHP (see Database::getConnection).
     *
     * @return positive-int|0 rows updated
     */
    public static function syncNoShowStatuses(PDO $pdo): int
    {
        $stmt = $pdo->exec(
            'UPDATE reservations
             SET status = "no_show"
             WHERE status = "confirmed"
               AND check_in_time IS NULL
               AND DATE_ADD(start_time, INTERVAL grace_period_mins MINUTE) < NOW()'
        );

        return max(0, (int)$stmt);
    }

    /**
     * @param array<string,mixed> $r reservation row including start_time, end_time, grace_period_mins, status
     */
    public static function checkInAllowed(DateTimeImmutable $now, array $r): ?string
    {
        if (($r['status'] ?? '') !== 'confirmed') {
            return 'Check-in is only available for confirmed bookings.';
        }

        $start = AppClock::parseSqlDatetime((string)$r['start_time']);
        $end = AppClock::parseSqlDatetime((string)$r['end_time']);
        $early = $start->modify('-' . self::CHECK_IN_EARLY_MINS . ' minutes');
        $graceEnd = $start->modify('+' . max(0, (int)($r['grace_period_mins'] ?? 5)) . ' minutes');

        if ($now < $early) {
            return 'Check-in opens ' . self::CHECK_IN_EARLY_MINS . ' minutes before your reservation start time.';
        }
        if ($now > $end) {
            return 'This reservation window has ended; you cannot check in.';
        }
        if ($now > $graceEnd) {
            return 'The grace period for check-in has expired. If you believe this is wrong, contact support or file a dispute.';
        }

        return null;
    }

    /**
     * @param array<string,mixed> $r
     */
    public static function checkOutAllowed(DateTimeImmutable $now, array $r): ?string
    {
        if (($r['status'] ?? '') !== 'active') {
            return 'Check-out is only available after check-in.';
        }

        return null;
    }

    /**
     * Cancellation refund tiers relative to reservation start (same wall-clock basis as DB datetimes).
     *
     * @return array{refund:int, seconds_until_start:int}
     */
    public static function cancelRefundPercent(DateTimeImmutable $now, string $startTimeSql): array
    {
        $start = AppClock::parseSqlDatetime($startTimeSql);
        $secondsUntilStart = $start->getTimestamp() - $now->getTimestamp();
        $refund = 0;
        if ($secondsUntilStart > 7200) {
            $refund = 100;
        } elseif ($secondsUntilStart > 3600) {
            $refund = 50;
        }

        return ['refund' => $refund, 'seconds_until_start' => $secondsUntilStart];
    }
}
