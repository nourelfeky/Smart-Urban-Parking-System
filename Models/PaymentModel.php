<?php

class PaymentModel
{
    public function __construct(private PDO $pdo)
    {
        $this->ensurePaymentStatusSchema();
    }

    public function lockFunds(int $bookingId, float $amount): void
    {
        $paymentId = $this->getPaymentIdByBooking($bookingId);
        if ($paymentId === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE payments SET payment_status='locked', escrow_status='held', final_amount=? WHERE payment_id=?"
        );
        $stmt->execute([round($amount, 2), $paymentId]);
    }

    public function releaseFunds(int $bookingId): void
    {
        $paymentId = $this->getPaymentIdByBooking($bookingId);
        if ($paymentId === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE payments SET payment_status='completed', escrow_status='released' WHERE payment_id=?"
        );
        $stmt->execute([$paymentId]);
    }

    public function refundFunds(int $bookingId): void
    {
        $paymentId = $this->getPaymentIdByBooking($bookingId);
        if ($paymentId === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE payments SET payment_status='refunded', escrow_status='refunded' WHERE payment_id=?"
        );
        $stmt->execute([$paymentId]);
    }

    private function getPaymentIdByBooking(int $bookingId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT payment_id FROM reservations WHERE reservation_id = ? LIMIT 1');
        $stmt->execute([$bookingId]);
        $paymentId = $stmt->fetchColumn();
        return $paymentId !== false ? (int)$paymentId : null;
    }

    private function ensurePaymentStatusSchema(): void
    {
        $col = $this->pdo->query("SHOW COLUMNS FROM payments LIKE 'payment_status'");
        if (!$col->fetch()) {
            $this->pdo->exec("ALTER TABLE payments ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER escrow_status");
        }
    }
}
