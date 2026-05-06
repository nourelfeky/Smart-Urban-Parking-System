<?php

class PenaltyModel
{
    /**
     * @return array{reserved_end_time: string, actual_checkout_time: string, overstay_minutes: int, penalty_rate: float, penalty_amount: float}
     */
    public function calculateOverstayPenalty(string $reservedEnd, string $actualEnd): array
    {
        $rate = defined('PENALTY_RATE_PER_MINUTE') ? (float)PENALTY_RATE_PER_MINUTE : 0.5;
        $reservedTs = strtotime($reservedEnd);
        $actualTs = strtotime($actualEnd);

        if ($reservedTs === false || $actualTs === false || $actualTs <= $reservedTs) {
            return [
                'reserved_end_time' => $reservedEnd,
                'actual_checkout_time' => $actualEnd,
                'overstay_minutes' => 0,
                'penalty_rate' => $rate,
                'penalty_amount' => 0.0,
            ];
        }

        $overstayMinutes = (int)floor(($actualTs - $reservedTs) / 60);
        $penalty = round($overstayMinutes * $rate, 2);

        return [
            'reserved_end_time' => $reservedEnd,
            'actual_checkout_time' => $actualEnd,
            'overstay_minutes' => $overstayMinutes,
            'penalty_rate' => $rate,
            'penalty_amount' => $penalty,
        ];
    }
}
