<?php

declare(strict_types=1);

/**
 * Shared validation for issuing fines (Admin + Officer flows).
 */
final class FineIssueValidator
{
    public const AMOUNT_MIN = 1.0;
    public const AMOUNT_MAX = 10000.0;

    /**
     * @return list<string>
     */
    public static function validateIssuePayload(int $driverId, int $spotId, string $penaltyRaw, string $amountLabel = 'Penalty amount'): array
    {
        $errs = [];
        if ($driverId <= 0) {
            $errs[] = 'Driver is required.';
        }
        if ($spotId <= 0) {
            $errs[] = 'Spot is required.';
        }
        $penaltyRaw = trim($penaltyRaw);
        $penalty = is_numeric($penaltyRaw) ? (float)$penaltyRaw : -1.0;
        if ($penaltyRaw === '' || !is_numeric($penaltyRaw) || $penalty < self::AMOUNT_MIN || $penalty > self::AMOUNT_MAX) {
            $errs[] = $amountLabel . ' must be between ' . self::AMOUNT_MIN . ' and ' . self::AMOUNT_MAX . '.';
        }

        return $errs;
    }

    public static function normalizeFineType(string $type): string
    {
        $type = trim($type);
        $allowedTypes = ['unauthorized', 'overstay'];

        return in_array($type, $allowedTypes, true) ? $type : 'unauthorized';
    }
}
