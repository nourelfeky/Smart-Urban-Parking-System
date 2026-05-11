<?php

class PricingModel
{
    /**
     * @return array{base_price: float, multiplier: float, adjustment_amount: float, final_price: float, is_peak: bool, reason: string}
     */
    public function calculatePeakPrice(float $basePrice, string $startTime): array
    {
        $multiplier = PeakPricingConfig::effectiveMultiplierFromGlobals();
        $isPeakHour = $this->isWithinPeakHours($startTime);
        $isSpecialEvent = $this->isWithinSpecialEvent($startTime);
        $isPeak = $isPeakHour || $isSpecialEvent;

        if (!$isPeak) {
            return [
                'base_price' => round($basePrice, 2),
                'multiplier' => 1.0,
                'adjustment_amount' => 0.0,
                'final_price' => round($basePrice, 2),
                'is_peak' => false,
                'reason' => '',
            ];
        }

        $final = round($basePrice * $multiplier, 2);
        return [
            'base_price' => round($basePrice, 2),
            'multiplier' => $multiplier,
            'adjustment_amount' => round($final - $basePrice, 2),
            'final_price' => $final,
            'is_peak' => true,
            'reason' => $isSpecialEvent ? 'special_event' : 'peak_hour',
        ];
    }

    private function isWithinPeakHours(string $dateTime): bool
    {
        $peakHours = defined('PEAK_HOURS') && is_array(PEAK_HOURS) ? PEAK_HOURS : [
            ['start' => '08:00', 'end' => '10:00'],
            ['start' => '17:00', 'end' => '20:00'],
        ];

        $time = date('H:i', strtotime($dateTime));
        foreach ($peakHours as $range) {
            if (empty($range['start']) || empty($range['end'])) {
                continue;
            }
            if ($time >= $range['start'] && $time <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    private function isWithinSpecialEvent(string $dateTime): bool
    {
        $events = defined('SPECIAL_EVENT_WINDOWS') && is_array(SPECIAL_EVENT_WINDOWS) ? SPECIAL_EVENT_WINDOWS : [];
        $targetTs = strtotime($dateTime);
        if ($targetTs === false) {
            return false;
        }

        foreach ($events as $event) {
            if (empty($event['start']) || empty($event['end'])) {
                continue;
            }
            $startTs = strtotime($event['start']);
            $endTs = strtotime($event['end']);
            if ($startTs === false || $endTs === false) {
                continue;
            }
            if ($targetTs >= $startTs && $targetTs <= $endTs) {
                return true;
            }
        }

        return false;
    }
}
