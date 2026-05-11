<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OverstayPenaltyCalculatorTest extends TestCase
{
    public function testCsvCatalogRowsMatchImplementation(): void
    {
        $m = new PenaltyModel(null);
        foreach (CsvFixtureReader::rowsForFunction('overstayPenalty') as $row) {
            $in = json_decode($row['input_json'], true);
            $exp = json_decode($row['expected_json'], true);
            $this->assertIsArray($in);
            $this->assertIsArray($exp);
            $penalty = $m->calculateOverstayPenalty((string)$in['reserved_end'], (string)$in['actual_end']);
            $this->assertSame((float)$exp['penalty'], $penalty, $row['test_id'] ?? '');
            $break = $m->calculateOverstayPenaltyBreakdown((string)$in['reserved_end'], (string)$in['actual_end']);
            $this->assertSame((int)$exp['minutes'], $break['overstay_minutes'], $row['test_id'] ?? '');
        }
    }

    public function testInvalidDatetimesReturnZeroPenalty(): void
    {
        $m = new PenaltyModel(null);
        $this->assertSame(0.0, $m->calculateOverstayPenalty('not-a-date', '2026-01-01 00:00:00'));
    }
}
