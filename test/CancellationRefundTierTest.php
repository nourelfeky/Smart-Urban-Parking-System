<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CancellationRefundTierTest extends TestCase
{
    public function testCsvCatalogRowsMatchImplementation(): void
    {
        foreach (CsvFixtureReader::rowsForFunction('cancelRefund') as $row) {
            $in = json_decode($row['input_json'], true);
            $exp = json_decode($row['expected_json'], true);
            $this->assertIsArray($in);
            $this->assertIsArray($exp);
            $now = AppClock::parseSqlDatetime((string)$in['now']);
            $out = ReservationTimeService::cancelRefundPercent($now, (string)$in['start']);
            $this->assertSame((int)$exp['refund'], $out['refund'], $row['test_id'] ?? '');
        }
    }

    
    public function testBoundarySeconds7200And3600(): void
    {
        $start = '2026-05-10 14:00:00';
        $at7200 = AppClock::parseSqlDatetime('2026-05-10 12:00:00');
        $out7200 = ReservationTimeService::cancelRefundPercent($at7200, $start);
        $this->assertSame(50, $out7200['refund']);

        $at7201 = AppClock::parseSqlDatetime('2026-05-10 11:59:59');
        $out7201 = ReservationTimeService::cancelRefundPercent($at7201, $start);
        $this->assertSame(100, $out7201['refund']);

        $at3600 = AppClock::parseSqlDatetime('2026-05-10 13:00:00');
        $out3600 = ReservationTimeService::cancelRefundPercent($at3600, $start);
        $this->assertSame(0, $out3600['refund']);

        $at3601 = AppClock::parseSqlDatetime('2026-05-10 12:59:59');
        $out3601 = ReservationTimeService::cancelRefundPercent($at3601, $start);
        $this->assertSame(50, $out3601['refund']);
    }
}
