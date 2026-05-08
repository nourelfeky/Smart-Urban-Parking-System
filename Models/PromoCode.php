<?php

declare(strict_types=1);

/**
 * Promo code entity (class diagram: PromoCode — codeID, code, discountAmount, expiryDate).
 * Persistence remains in `promo_codes`; this is the OO projection used for documentation alignment.
 */
final class PromoCode
{
    public function __construct(
        public string $codeID,
        public string $code,
        public float $discountAmount,
        public string $expiryDate,
    ) {
    }

    /**
     * @param array<string, mixed> $row from promo_codes
     */
    public static function fromDatabaseRow(array $row): self
    {
        $amount = $row['discount_type'] === 'percentage'
            ? (float)($row['discount_value'] ?? 0)
            : (float)($row['discount_value'] ?? 0);

        return new self(
            (string)($row['code_id'] ?? ''),
            (string)($row['code'] ?? ''),
            $amount,
            (string)($row['expiry_date'] ?? ''),
        );
    }
}
