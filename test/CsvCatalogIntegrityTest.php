<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CsvCatalogIntegrityTest extends TestCase
{
    public function testCatalogFileExistsAndIsReadable(): void
    {
        $path = CsvFixtureReader::catalogPath();
        $this->assertFileExists($path);
        $this->assertNotSame([], CsvFixtureReader::allRows());
    }

    public function testExpectedFunctionKeysPresent(): void
    {
        $keys = [];
        foreach (CsvFixtureReader::allRows() as $row) {
            $keys[$row['function_key'] ?? ''] = true;
        }
        foreach (['cancelRefund', 'overstayPenalty', 'peakMultiplierBound', 'peakPricing', 'loyaltyTier', 'fineValidation', 'sanction', 'qrToken', 'blackbox_placeholder'] as $required) {
            $this->assertArrayHasKey($required, $keys, 'CSV catalog missing function_key: ' . $required);
        }
    }
}
