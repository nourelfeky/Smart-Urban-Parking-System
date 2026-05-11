<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class QrCheckInStateMachineTest extends TestCase
{
    public function testQrTokenRowsFromCsv(): void
    {
        foreach (CsvFixtureReader::rowsForFunction('qrToken') as $row) {
            $in = json_decode($row['input_json'], true);
            $exp = json_decode($row['expected_json'], true);
            $this->assertIsArray($in);
            $this->assertIsArray($exp);
            $posted = $in['posted'] ?? null;
            $stored = (string)$in['stored'];
            $ok = ReservationTimeService::verifyQrToken($posted, $stored);
            $this->assertSame((bool)$exp['valid'], $ok, $row['test_id'] ?? '');
        }
    }

    public function testCheckInAllowedHappyPathWithinEarlyAndGrace(): void
    {
        $start = '2026-07-01 12:00:00';
        $end = '2026-07-01 14:00:00';
        $r = [
            'status' => 'confirmed',
            'start_time' => $start,
            'end_time' => $end,
            'grace_period_mins' => 10,
        ];
        $now = AppClock::parseSqlDatetime('2026-07-01 11:50:00');
        $this->assertNull(ReservationTimeService::checkInAllowed($now, $r));
    }

    public function testCheckInRejectedWhenTooEarly(): void
    {
        $r = [
            'status' => 'confirmed',
            'start_time' => '2026-07-01 12:00:00',
            'end_time' => '2026-07-01 14:00:00',
            'grace_period_mins' => 10,
        ];
        $now = AppClock::parseSqlDatetime('2026-07-01 11:40:00');
        $err = ReservationTimeService::checkInAllowed($now, $r);
        $this->assertIsString($err);
        $this->assertStringContainsString('15 minutes before', $err);
    }

    public function testCheckInRejectedAfterReservationEnd(): void
    {
        $r = [
            'status' => 'confirmed',
            'start_time' => '2026-07-01 12:00:00',
            'end_time' => '2026-07-01 14:00:00',
            'grace_period_mins' => 10,
        ];
        $now = AppClock::parseSqlDatetime('2026-07-01 14:01:00');
        $this->assertStringContainsString('ended', (string)ReservationTimeService::checkInAllowed($now, $r));
    }

    public function testCheckInRejectedAfterGrace(): void
    {
        $r = [
            'status' => 'confirmed',
            'start_time' => '2026-07-01 12:00:00',
            'end_time' => '2026-07-01 14:00:00',
            'grace_period_mins' => 5,
        ];
        $now = AppClock::parseSqlDatetime('2026-07-01 12:06:00');
        $this->assertStringContainsString('grace period', (string)ReservationTimeService::checkInAllowed($now, $r));
    }

    public function testCheckInRejectedWhenNotConfirmed(): void
    {
        $r = [
            'status' => 'active',
            'start_time' => '2026-07-01 12:00:00',
            'end_time' => '2026-07-01 14:00:00',
            'grace_period_mins' => 5,
        ];
        $now = AppClock::parseSqlDatetime('2026-07-01 12:00:00');
        $this->assertStringContainsString('confirmed', (string)ReservationTimeService::checkInAllowed($now, $r));
    }

    public function testCheckOutAllowedOnlyWhenActive(): void
    {
        $rActive = ['status' => 'active'];
        $this->assertNull(ReservationTimeService::checkOutAllowed(AppClock::now(), $rActive));
        $rConfirmed = ['status' => 'confirmed'];
        $this->assertNotNull(ReservationTimeService::checkOutAllowed(AppClock::now(), $rConfirmed));
    }
}
