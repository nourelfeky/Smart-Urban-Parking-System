<?php

declare(strict_types=1);

/**
 * Strategy interface for payment method metadata (class diagram: Payment + PaymentMethod strategy).
 */
interface PaymentMethodStrategy
{
    /**
     * @param array<string, mixed> $context e.g. card_number for card flow
     * @return array{payment_method: string, token_ref: string, transaction_id: string}
     */
    public function buildPaymentMetadata(array $context): array;
}

final class CreditCardPaymentStrategy implements PaymentMethodStrategy
{
    public function buildPaymentMetadata(array $context): array
    {
        $raw = preg_replace('/\D/', '', (string)($context['card_number'] ?? '')) ?: '0000';
        $last4 = substr($raw, -4);

        return [
            'payment_method' => 'credit_card',
            'token_ref' => 'CARD-****' . $last4,
            'transaction_id' => 'CC' . time() . random_int(1000, 9999),
        ];
    }
}

final class SubscriptionPaymentStrategy implements PaymentMethodStrategy
{
    public function buildPaymentMetadata(array $context): array
    {
        return [
            'payment_method' => 'subscription',
            'token_ref' => 'SUBSCRIPTION',
            'transaction_id' => 'SUB' . time() . random_int(1000, 9999),
        ];
    }
}

/**
 * Inserts a held escrow row using the selected strategy (Strategy pattern).
 */
final class PaymentProcessingService
{
    /**
     * @param array{base: float, tax: float, total: float, escrow: float, discount: float} $cost
     */
    public static function insertHeldPayment(
        PDO $pdo,
        PaymentMethodStrategy $strategy,
        int $driverId,
        array $cost,
        array $context = [],
    ): int {
        $meta = $strategy->buildPaymentMetadata($context);
        $commissionAmt = round($cost['total'] * 0.15, 2);
        $p = $pdo->prepare(
            'INSERT INTO payments (driver_id, amount, tax_amount, commission_amt, escrow_status, payment_status, penalty_buffer, final_amount, discount_applied, payment_method, token_ref, transaction_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $p->execute([
            $driverId,
            $cost['base'],
            $cost['tax'],
            $commissionAmt,
            'held',
            'pending',
            round($cost['escrow'] - $cost['total'], 2),
            $cost['total'],
            $cost['discount'],
            $meta['payment_method'],
            $meta['token_ref'],
            $meta['transaction_id'],
        ]);

        return (int)$pdo->lastInsertId();
    }
}
