<?php

declare(strict_types=1);

/**
 * Driver booking suspension when unpaid fines accumulate (admin/officer fine issuance).
 */
final class DriverSanctionPolicy
{
    public static function shouldSuspendForUnpaidFines(int $pendingUnpaidFineCount): bool
    {
        return $pendingUnpaidFineCount >= 3;
    }
}
