<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AutomatedFineGenerationTest extends TestCase
{
    public function testFineValidationRowsFromCsv(): void
    {
        foreach (CsvFixtureReader::rowsForFunction('fineValidation') as $row) {
            $in = json_decode($row['input_json'], true);
            $exp = json_decode($row['expected_json'], true);
            $this->assertIsArray($in);
            $this->assertIsArray($exp);
            $errs = FineIssueValidator::validateIssuePayload(
                (int)$in['driver_id'],
                (int)$in['spot_id'],
                (string)$in['amount'],
                'Penalty amount'
            );
            $this->assertCount((int)$exp['error_count'], $errs, $row['test_id'] ?? '');
            if (!empty($exp['has_driver_error'])) {
                $this->assertStringContainsString('Driver is required', implode(' ', $errs));
            }
            if (!empty($exp['has_spot_error'])) {
                $this->assertStringContainsString('Spot is required', implode(' ', $errs));
            }
            if (!empty($exp['has_amount_error'])) {
                $this->assertTrue(
                    str_contains(implode(' ', $errs), 'Penalty amount must be between'),
                    $row['test_id'] ?? ''
                );
            }
        }
    }

    public function testSanctionThresholdFromCsv(): void
    {
        foreach (CsvFixtureReader::rowsForFunction('sanction') as $row) {
            $in = json_decode($row['input_json'], true);
            $exp = json_decode($row['expected_json'], true);
            $this->assertIsArray($in);
            $this->assertIsArray($exp);
            $suspend = DriverSanctionPolicy::shouldSuspendForUnpaidFines((int)$in['pending_fines']);
            $this->assertSame((bool)$exp['suspend'], $suspend, $row['test_id'] ?? '');
        }
    }

    public function testNormalizeFineType(): void
    {
        $this->assertSame('unauthorized', FineIssueValidator::normalizeFineType('invalid'));
        $this->assertSame('overstay', FineIssueValidator::normalizeFineType('overstay'));
    }
}
