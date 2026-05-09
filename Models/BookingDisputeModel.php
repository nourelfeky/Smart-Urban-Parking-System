<?php

declare(strict_types=1);

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/DriverWalletModel.php';

/**
 * Listing-accuracy / partial refund disputes (admin reconciliation workflow).
 */
final class BookingDisputeModel
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS booking_disputes (
                dispute_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reservation_id INT UNSIGNED NOT NULL,
                driver_id INT UNSIGNED NOT NULL,
                reason TEXT NOT NULL,
                status ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
                refund_percent_requested DECIMAL(5,2) NULL,
                refund_percent_approved DECIMAL(5,2) NULL,
                admin_note VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME DEFAULT NULL,
                INDEX idx_dispute_reservation (reservation_id),
                FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE,
                FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return list<array<string,mixed>> */
    public function listPendingWithContext(): array
    {
        $sql = 'SELECT d.*, r.final_cost, r.status AS res_status, r.spot_id,
                       ps.address, u.name AS driver_name, u.email AS driver_email
                FROM booking_disputes d
                JOIN reservations r ON r.reservation_id = d.reservation_id
                JOIN parking_spots ps ON ps.spot_id = r.spot_id
                JOIN users u ON u.id = d.driver_id
                WHERE d.status = "pending"
                ORDER BY d.created_at ASC';

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function driverHasOpenDispute(int $reservationId): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM booking_disputes WHERE reservation_id=? AND status="pending" LIMIT 1');
        $st->execute([$reservationId]);

        return (bool)$st->fetchColumn();
    }

    public function create(int $reservationId, int $driverId, string $reason, ?float $requestedPercent): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Reason is required.');
        }
        $dup = $this->pdo->prepare('SELECT 1 FROM booking_disputes WHERE reservation_id=? AND status="pending" LIMIT 1');
        $dup->execute([$reservationId]);
        if ($dup->fetchColumn()) {
            throw new InvalidArgumentException('You already have an open dispute for this booking.');
        }
        $pct = $requestedPercent !== null ? max(0.0, min(100.0, $requestedPercent)) : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO booking_disputes (reservation_id, driver_id, reason, refund_percent_requested, status)
             VALUES (?,?,?,?, "pending")'
        );
        $stmt->execute([$reservationId, $driverId, $reason, $pct]);
    }

    public function resolveApprove(int $disputeId, float $approvedPercent, string $note, ?int $adminActorId): array
    {
        $approvedPercent = max(0.0, min(100.0, $approvedPercent));
        $note = trim($note);

        $this->pdo->beginTransaction();
        try {
            $q = $this->pdo->prepare(
                'SELECT d.*, r.reservation_id, r.final_cost, r.payment_id, r.spot_id, r.driver_id, r.status,
                        ps.owner_id, p.escrow_status, p.payment_status
                 FROM booking_disputes d
                 JOIN reservations r ON r.reservation_id = d.reservation_id
                 JOIN parking_spots ps ON ps.spot_id = r.spot_id
                 LEFT JOIN payments p ON p.payment_id = r.payment_id
                 WHERE d.dispute_id=? AND d.status="pending"
                 LIMIT 1 FOR UPDATE'
            );
            $q->execute([$disputeId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->pdo->rollBack();
                return ['ok' => false, 'error' => 'Dispute not found or already resolved.'];
            }

            $finalCost = (float)$row['final_cost'];
            $refundAmt = round($finalCost * ($approvedPercent / 100.0), 2);
            $ownerId = (int)$row['owner_id'];
            $driverId = (int)$row['driver_id'];
            $reservationId = (int)$row['reservation_id'];
            $paymentId = $row['payment_id'] !== null ? (int)$row['payment_id'] : null;

            // Reverse the owner's share of the refunded slice (85% rule matches checkout credit).
            $ownerSlice = round($refundAmt * 0.85, 2);
            if ($ownerSlice > 0) {
                $this->pdo->prepare('UPDATE space_owners SET earnings_balance = GREATEST(0, earnings_balance - ?) WHERE owner_id=?')
                    ->execute([$ownerSlice, $ownerId]);
            }

            if ($paymentId !== null && $refundAmt > 0) {
                $this->pdo->prepare(
                    'UPDATE payments SET refund_percent=?, refund_amount=? WHERE payment_id=?'
                )->execute([$approvedPercent, $refundAmt, $paymentId]);
            }

            if ($refundAmt > 0) {
                DriverWalletModel::credit($this->pdo, $driverId, $refundAmt);
            }

            $this->pdo->prepare(
                'UPDATE booking_disputes SET status="approved", refund_percent_approved=?, admin_note=?, resolved_at=NOW() WHERE dispute_id=?'
            )->execute([$approvedPercent, $note !== '' ? $note : null, $disputeId]);

            $this->pdo->prepare(
                'INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)'
            )->execute([
                $driverId,
                'in_app',
                'Your listing dispute was approved. Refund: ' . $approvedPercent . '% (~' . $refundAmt . ' EGP).',
                'dispute',
                'sent',
            ]);

            $this->pdo->prepare(
                'INSERT INTO audit_log (event_type, entity_id, actor_id, spot_id, new_state) VALUES (?,?,?,?,?)'
            )->execute(['DISPUTE_APPROVED', (string)$reservationId, $adminActorId, $row['spot_id'], 'refund_' . $approvedPercent]);

            $this->pdo->commit();

            return ['ok' => true, 'refund_amount' => $refundAmt];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'error' => 'Could not resolve dispute.'];
        }
    }

    public function resolveReject(int $disputeId, string $note): bool
    {
        $sel = $this->pdo->prepare(
            'SELECT d.driver_id FROM booking_disputes d WHERE d.dispute_id=? AND d.status="pending" LIMIT 1'
        );
        $sel->execute([$disputeId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE booking_disputes SET status="rejected", admin_note=?, resolved_at=NOW() WHERE dispute_id=? AND status="pending"'
        );
        $ok = $stmt->execute([trim($note) !== '' ? trim($note) : null, $disputeId]) && $stmt->rowCount() > 0;
        if ($ok) {
            $this->pdo->prepare(
                'INSERT INTO notifications (recipient_id, channel, message, type, status) VALUES (?,?,?,?,?)'
            )->execute([
                (int)$row['driver_id'],
                'in_app',
                'Your listing dispute was rejected.' . (trim($note) !== '' ? ' Note: ' . $note : ''),
                'dispute',
                'sent',
            ]);
        }

        return $ok;
    }
}
