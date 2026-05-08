<?php

declare(strict_types=1);

require_once __DIR__ . '/PricingModel.php';

/**
 * Class diagram: PricingEngine — calculates pre-adjustment price from duration and rate.
 * Peak / special-event adjustments stay in PricingModel::calculatePeakPrice().
 */
final class PricingEngine
{
    public function calculatePrice(float $duration, float $rate): float
    {
        return round($duration * $rate, 2);
    }

    /**
     * @return array{base_price: float, multiplier: float, adjustment_amount: float, final_price: float, is_peak: bool, reason: string}
     */
    public function calculatePriceWithPeak(float $baseBeforePeak, string $startTime): array
    {
        return (new PricingModel())->calculatePeakPrice($baseBeforePeak, $startTime);
    }
}
