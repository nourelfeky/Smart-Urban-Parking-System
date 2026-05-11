<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LoyaltyDiscountProgramTest extends TestCase
{
    public function testCsvCatalogRowsMatchImplementation(): void
    {
        foreach (CsvFixtureReader::rowsForFunction('loyaltyTier') as $row) {
            $in = json_decode($row['input_json'], true);
            $exp = json_decode($row['expected_json'], true);
            $this->assertIsArray($in);
            $this->assertIsArray($exp);
            $b = (int)$in['bookings_30d'];
            $this->assertSame((string)$exp['tier'], LoyaltyProgram::tierFromRollingBookingCount($b), $row['test_id'] ?? '');
            $this->assertSame((int)$exp['discount'], LoyaltyProgram::bookingDiscountPercent($b), $row['test_id'] ?? '');
        }
    }

    public function testTierFourWaySplit(): void
    {
        $this->assertSame('bronze', LoyaltyProgram::tierFromRollingBookingCount(4));
        $this->assertSame('silver', LoyaltyProgram::tierFromRollingBookingCount(5));
        $this->assertSame('gold', LoyaltyProgram::tierFromRollingBookingCount(20));
    }
}
