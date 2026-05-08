<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/BookingManager.php';

/**
 * Observer + notification façade (class diagram: NotificationService).
 * sendEmail / sendSMS / pushNotification are the diagram entry points; push maps to in-app rows.
 */
final class NotificationService implements ReservationObserver
{
    public function sendEmail(string $userID, string $message): void
    {
        $this->pushNotification($userID, $message, 'email');
    }

    public function sendSMS(string $userID, string $message): void
    {
        $this->pushNotification($userID, $message, 'sms');
    }

    public function pushNotification(string $userID, string $message, string $channel = 'in_app'): void
    {
        $pdo = Database::getConnection();
        $recipient = (int)$userID;
        if ($recipient <= 0 || $message === '') {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$recipient, $channel, $message, 'system', 'sent']);
    }

    public function update(ReservationEvent $event): void
    {
        $pdo = $event->pdo;
        switch ($event->type) {
            case ReservationEvent::TYPE_CREATED:
                $driverId = (int)($event->payload['driver_id'] ?? 0);
                $ownerId = (int)($event->payload['owner_id'] ?? 0);
                $address = (string)($event->payload['address'] ?? '');
                $start = (string)($event->payload['start'] ?? '');
                if ($driverId > 0 && $address !== '') {
                    $msg = 'Booking confirmed for ' . $address . ' on ' . date('d M, H:i', strtotime($start));
                    $this->insertInApp($pdo, $driverId, $msg, 'booking');
                }
                if ($ownerId > 0 && $address !== '') {
                    $this->insertInApp($pdo, $ownerId, 'New booking received for your spot at ' . $address . '.', 'booking');
                }
                break;

            case ReservationEvent::TYPE_SUBSCRIPTION_CREATED:
                $driverId = (int)($event->payload['driver_id'] ?? 0);
                $ownerId = (int)($event->payload['owner_id'] ?? 0);
                $address = (string)($event->payload['address'] ?? 'the selected spot');
                if ($driverId > 0) {
                    $this->insertInApp(
                        $pdo,
                        $driverId,
                        'Subscription created for ' . $address . '. Recurring reservations were generated.',
                        'booking'
                    );
                }
                if ($ownerId > 0) {
                    $this->insertInApp(
                        $pdo,
                        $ownerId,
                        'New subscription bookings were created for your spot at ' . $address . '.',
                        'booking'
                    );
                }
                break;

            case ReservationEvent::TYPE_CANCELLED:
                $driverId = (int)($event->payload['driver_id'] ?? 0);
                /** @var array<string, mixed> $r */
                $r = $event->payload['reservation_row'] ?? [];
                $spotId = (int)($r['spot_id'] ?? 0);
                $address = (string)($r['address'] ?? '');
                if ($driverId > 0 && $spotId > 0) {
                    $wait = $pdo->prepare('SELECT driver_id FROM waitlist WHERE spot_id=? ORDER BY joined_at ASC LIMIT 1');
                    $wait->execute([$spotId]);
                    $wdriver = $wait->fetch(PDO::FETCH_ASSOC);
                    if ($wdriver) {
                        $this->insertInApp(
                            $pdo,
                            (int)$wdriver['driver_id'],
                            "A spot you're watching at {$address} just became available!",
                            'waitlist'
                        );
                    }
                    $spotRef = [
                        'spot_id' => $spotId,
                        'latitude' => $r['latitude'] ?? null,
                        'longitude' => $r['longitude'] ?? null,
                    ];
                    $bookingManager = new BookingManager($pdo);
                    $alternatives = $bookingManager->getAlternativeSpots($spotRef, 3);
                    if ($alternatives !== []) {
                        $lines = array_map(
                            static fn(array $alt): string => $alt['address'] . ' (' . number_format((float)$alt['distance_km'], 2) . ' km)',
                            $alternatives
                        );
                        $this->insertInApp(
                            $pdo,
                            $driverId,
                            'Suggested nearby alternatives: ' . implode(' | ', $lines),
                            'booking'
                        );
                    }
                }
                break;

            case ReservationEvent::TYPE_COMPLETED:
            default:
                break;
        }
    }

    private function insertInApp(PDO $pdo, int $recipientId, string $message, string $type): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$recipientId, 'in_app', $message, $type, 'sent']);
    }
}
