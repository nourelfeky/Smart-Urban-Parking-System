<?php

/**
 * Validates promo codes for bookings so discounts cannot expire during the rented window.
 *
 * Rules:
 * - Code must exist, have remaining uses, and `expiry_date` must be **>= reservation end**
 *   (promo stays valid through the entire booked session, not only at checkout time).
 */
final class PromotionalCodeValidator
{
    public const WELCOME_DRIVER_CODE = 'WELCOMEDRIVER';
    public const GOLD_LOYALTY_CODE = 'GOLDLOYALTY';

    private const GOLD_PROMO_NOTIFICATION_DAYS = 30;

    public static function ensureDefaultPromotionalCodes(PDO $pdo): void
    {
        $months = defined('DEFAULT_PROMO_VALIDITY_MONTHS') ? max(3, (int)DEFAULT_PROMO_VALIDITY_MONTHS) : 6;
        $expiryBase = strtotime('+' . $months . ' months');
        /** @phpstan-ignore-next-line intentional dynamic date */
        $expiryIso = date('Y-m-d 23:59:59', $expiryBase);

        $pairs = [
            [self::WELCOME_DRIVER_CODE, 15.0],
            [self::GOLD_LOYALTY_CODE, 20.0],
        ];
        foreach ($pairs as [$code, $pct]) {
            $chk = $pdo->prepare('SELECT code_id FROM promo_codes WHERE code = ? LIMIT 1');
            $chk->execute([$code]);
            if ($chk->fetch()) {
                continue;
            }
            $pdo->prepare(
                'INSERT INTO promo_codes (code, discount_type, discount_value, expiry_date, usage_limit)
                 VALUES (?,?,?,?,?)'
            )->execute([$code, 'percentage', $pct, $expiryIso, 0]);
        }
    }

    /**
     * @return array<string,mixed>|null promo row when valid for the whole booking window
     */
    public static function getRowForBookingWindow(PDO $pdo, string $code, string $reservationWindowEndDatetime): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        try {
            $end = new DateTimeImmutable($reservationWindowEndDatetime);
        } catch (Exception) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM promo_codes
             WHERE code = ?
               AND (usage_limit = 0 OR usage_count < usage_limit)
               AND expiry_date >= ?'
        );
        // Compare using MySQL: expiry covers the reservation end instant
        $stmt->execute([$code, $end->format('Y-m-d H:i:s')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public static function promoInvalidBookingMessage(): string
    {
        return 'This promo code is invalid, exhausted, or would expire before the end of your booking window. Remove it or shorten your stay.';
    }

    public static function notifyNewDriverWelcomePromo(PDO $pdo, int $driverUserId): void
    {
        self::ensureDefaultPromotionalCodes($pdo);
        $stmt = $pdo->prepare('SELECT code, expiry_date, discount_value, discount_type FROM promo_codes WHERE code = ? LIMIT 1');
        $stmt->execute([self::WELCOME_DRIVER_CODE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $exp = date('d M Y', strtotime((string)$row['expiry_date']));
        $val = $row['discount_type'] === 'percentage'
            ? (float)$row['discount_value'] . '%'
            : (float)$row['discount_value'] . ' EGP';
        $msg = 'Welcome! Use promo code "' . $row['code'] . '" at checkout — '
            . $val . ' off. Valid until ' . $exp
            . ' (discount applies only while the promo remains valid through the end time of each booking).';
        self::sendNotificationSafe($pdo, $driverUserId, $msg, 'driver_welcome_promo');
    }

    /** Notify Gold-tier drivers periodically with loyalty promo details. */
    public static function maybeNotifyGoldTierPromo(PDO $pdo, int $driverUserId): void
    {
        $tierStmt = $pdo->prepare('SELECT current_tier FROM loyalty_accounts WHERE driver_id = ? LIMIT 1');
        $tierStmt->execute([$driverUserId]);
        $tier = $tierStmt->fetchColumn();
        if ($tier !== 'gold') {
            return;
        }

        $recentStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE recipient_id = ?
               AND type = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)self::GOLD_PROMO_NOTIFICATION_DAYS . ' DAY)'
        );
        $recentStmt->execute([$driverUserId, 'gold_loyalty_promo']);
        if ((int)$recentStmt->fetchColumn() > 0) {
            return;
        }

        self::ensureDefaultPromotionalCodes($pdo);
        $stmt = $pdo->prepare('SELECT code, expiry_date, discount_value, discount_type FROM promo_codes WHERE code = ? LIMIT 1');
        $stmt->execute([self::GOLD_LOYALTY_CODE]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $exp = date('d M Y', strtotime((string)$row['expiry_date']));
        $val = $row['discount_type'] === 'percentage'
            ? (float)$row['discount_value'] . '%'
            : (float)$row['discount_value'] . ' EGP';
        $msg = 'Gold tier reward: use promo "' . $row['code'] . '" — '
            . $val . ' off at checkout until ' . $exp
            . '. The code must remain valid until the end time of each reservation.';
        self::sendNotificationSafe($pdo, $driverUserId, $msg, 'gold_loyalty_promo');
    }

    private static function sendNotificationSafe(PDO $pdo, int $recipientId, string $messagePlain, string $type): void
    {
        $pdo->prepare(
            'INSERT INTO notifications (recipient_id, channel, message, type, status)
             VALUES (?,?,?,?,?)'
        )->execute([$recipientId, 'in_app', $messagePlain, $type, 'sent']);
    }
}
