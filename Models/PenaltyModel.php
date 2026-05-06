<?php

class PenaltyModel
{
    public function __construct(private ?PDO $pdo = null)
    {
        if ($this->pdo) {
            $this->ensurePenaltySchema();
        }
    }

    /**
     * Returns penalty amount only (EGP).
     * Uses DateTime comparison (not string/strtotime).
     */
    public function calculateOverstayPenalty(string $reservedEnd, string $actualEnd): float
    {
        $rate = defined('PENALTY_RATE_PER_MINUTE') ? (float)PENALTY_RATE_PER_MINUTE : 0.5;
        try {
            $reserved = new DateTime($reservedEnd);
            $actual = new DateTime($actualEnd);
        } catch (Exception) {
            return 0.0;
        }

        if ($actual <= $reserved) {
            return 0.0;
        }

        $diff = $reserved->diff($actual);
        $minutes = ((int)$diff->days * 24 * 60) + ((int)$diff->h * 60) + (int)$diff->i;
        if ($minutes <= 0) {
            return 0.0;
        }

        return round($minutes * $rate, 2);
    }

    /**
     * @return array{reserved_end_time: string, actual_checkout_time: string, overstay_minutes: int, penalty_rate: float, penalty_amount: float}
     */
    public function calculateOverstayPenaltyBreakdown(string $reservedEnd, string $actualEnd): array
    {
        $rate = defined('PENALTY_RATE_PER_MINUTE') ? (float)PENALTY_RATE_PER_MINUTE : 0.5;
        try {
            $reserved = new DateTime($reservedEnd);
            $actual = new DateTime($actualEnd);
        } catch (Exception) {
            return [
                'reserved_end_time' => $reservedEnd,
                'actual_checkout_time' => $actualEnd,
                'overstay_minutes' => 0,
                'penalty_rate' => $rate,
                'penalty_amount' => 0.0,
            ];
        }

        if ($actual <= $reserved) {
            return [
                'reserved_end_time' => $reservedEnd,
                'actual_checkout_time' => $actualEnd,
                'overstay_minutes' => 0,
                'penalty_rate' => $rate,
                'penalty_amount' => 0.0,
            ];
        }

        $diff = $reserved->diff($actual);
        $minutes = ((int)$diff->days * 24 * 60) + ((int)$diff->h * 60) + (int)$diff->i;
        $minutes = max(0, $minutes);
        $penalty = round($minutes * $rate, 2);

        return [
            'reserved_end_time' => $reservedEnd,
            'actual_checkout_time' => $actualEnd,
            'overstay_minutes' => $minutes,
            'penalty_rate' => $rate,
            'penalty_amount' => $penalty,
        ];
    }

    private function ensurePenaltySchema(): void
    {
        // reservations.penalty_amount
        $col = $this->pdo->query("SHOW COLUMNS FROM reservations LIKE 'penalty_amount'");
        if (!$col->fetch()) {
            $this->pdo->exec("ALTER TABLE reservations ADD COLUMN penalty_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount");
        }

        // reservations.overstay_minutes
        $col = $this->pdo->query("SHOW COLUMNS FROM reservations LIKE 'overstay_minutes'");
        if (!$col->fetch()) {
            $this->pdo->exec("ALTER TABLE reservations ADD COLUMN overstay_minutes INT NOT NULL DEFAULT 0 AFTER hold_id");
        }
    }
}
