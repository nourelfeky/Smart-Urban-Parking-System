<?php

declare(strict_types=1);

/**
 * Payload emitted by reservation lifecycle (Observer pattern — diagram: Reservation / ReservationEvent).
 */
final class ReservationEvent
{
    public const TYPE_CREATED = 'created';
    public const TYPE_SUBSCRIPTION_CREATED = 'subscription_created';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_COMPLETED = 'completed';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly PDO $pdo,
        public readonly array $payload,
    ) {
    }

    public static function bookingCreated(
        PDO $pdo,
        int $driverId,
        int $ownerId,
        int $spotId,
        string $address,
        string $start,
        int $reservationId,
    ): self {
        return new self(self::TYPE_CREATED, $pdo, [
            'driver_id' => $driverId,
            'owner_id' => $ownerId,
            'spot_id' => $spotId,
            'address' => $address,
            'start' => $start,
            'reservation_id' => $reservationId,
        ]);
    }

    public static function subscriptionCreated(
        PDO $pdo,
        int $driverId,
        int $ownerId,
        int $spotId,
        string $address,
    ): self {
        return new self(self::TYPE_SUBSCRIPTION_CREATED, $pdo, [
            'driver_id' => $driverId,
            'owner_id' => $ownerId,
            'spot_id' => $spotId,
            'address' => $address,
        ]);
    }

    /**
     * @param array<string, mixed> $reservationRow joined row as in booking detail / cancel flow
     */
    public static function bookingCancelled(
        PDO $pdo,
        int $driverId,
        array $reservationRow,
        int $refundPercent,
    ): self {
        return new self(self::TYPE_CANCELLED, $pdo, [
            'driver_id' => $driverId,
            'reservation_row' => $reservationRow,
            'refund_percent' => $refundPercent,
        ]);
    }

    /**
     * @param array<string, mixed> $reservationRow
     */
    public static function bookingCompleted(PDO $pdo, int $driverId, array $reservationRow): self
    {
        return new self(self::TYPE_COMPLETED, $pdo, [
            'driver_id' => $driverId,
            'reservation_row' => $reservationRow,
        ]);
    }
}
