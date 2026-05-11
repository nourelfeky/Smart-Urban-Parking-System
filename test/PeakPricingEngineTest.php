<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PeakPricingEngineTest extends TestCase
{
    public function testMultiplierBoundsFromCsv(): void
    {
        foreach (CsvFixtureReader::rowsForFunction('peakMultiplierBound') as $row) {
            $in = json_decode($row['input_json'], true);
            $exp = json_decode($row['expected_json'], true);
            $this->assertIsArray($in);
            $this->assertIsArray($exp);
            $eff = PeakPricingConfig::bounded((float)$in['raw'], (float)$in['min'], (float)$in['max']);
            $this->assertSame((float)$exp['effective'], $eff, $row['test_id'] ?? '');
        }
    }

    public function testPeakPricingRowFromCsv(): void
    {
        foreach (CsvFixtureReader::rowsForFunction('peakPricing') as $row) {
            $in = json_decode($row['input_json'], true);
            $exp = json_decode($row['expected_json'], true);
            $this->assertIsArray($in);
            $this->assertIsArray($exp);
            $pm = new PricingModel();
            $out = $pm->calculatePeakPrice((float)$in['base'], (string)$in['start']);
            $this->assertSame((bool)$exp['is_peak'], $out['is_peak'], $row['test_id'] ?? '');
            $this->assertSame((string)$exp['reason'], $out['reason'], $row['test_id'] ?? '');
        }
    }

    public function testPricingEngineDelegatesPeakToPricingModel(): void
    {
        $engine = new PricingEngine();
        $out = $engine->calculatePriceWithPeak(80.0, '2026-06-03 18:00:00');
        $this->assertTrue($out['is_peak']);
        $this->assertSame('peak_hour', $out['reason']);
        $this->assertSame(100.0, $out['final_price']);
    }

    public function testNonPeakPreservesBasePrice(): void
    {
        $pm = new PricingModel();
        $out = $pm->calculatePeakPrice(50.0, '2026-06-03 12:00:00');
        $this->assertFalse($out['is_peak']);
        $this->assertSame(50.0, $out['final_price']);
        $this->assertSame(1.0, $out['multiplier']);
    }
}
