<?php

require_once __DIR__ . '/../Core/SimplePdf.php';

final class OwnerReportModel
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Deterministic pseudo-random int for "fake" reports (no global RNG state).
     */
    private function prandInt(string $seed, int $min, int $max): int
    {
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }
        $hex = substr(hash('sha256', $seed), 0, 8);
        $n = hexdec($hex);
        $range = max(1, $max - $min + 1);
        return $min + ($n % $range);
    }

    /**
     * @return array{
     *   month: string,
     *   spot_count: int,
     *   booked_minutes: int,
     *   available_minutes: int,
     *   occupancy_rate: float,
     *   top_slots: array<int, array{hour: int, sessions: int}>
     * }
     */
    public function getFakeMonthlyOwnerMetrics(int $ownerId, string $month): array
    {
        // month format: YYYY-MM
        $monthStart = new DateTime($month . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('first day of next month');
        $days = (int)$monthStart->diff($monthEnd)->days;

        // Prefer real spot count for credibility; if owner has none, still show a fake report.
        $spotStmt = $this->pdo->prepare('SELECT COUNT(*) FROM parking_spots WHERE owner_id = ?');
        $spotStmt->execute([$ownerId]);
        $spotCount = (int)$spotStmt->fetchColumn();
        if ($spotCount <= 0) {
            $spotCount = $this->prandInt("{$ownerId}|{$month}|spots", 3, 9);
        }

        $availableMinutes = max(0, $spotCount * $days * 24 * 60);

        // "Realistic" occupancy: 18%..78%
        $occPercent = (float)$this->prandInt("{$ownerId}|{$month}|occ", 18, 78);
        $bookedMinutes = (int)round(($occPercent / 100.0) * $availableMinutes);

        // Fake top slots: 5 hours, descending sessions.
        $baseSessions = $this->prandInt("{$ownerId}|{$month}|baseSessions", 18, 95);
        $hoursPool = range(6, 22);
        $topSlots = [];
        for ($i = 0; $i < 5; $i++) {
            $hIdx = $this->prandInt("{$ownerId}|{$month}|hour|{$i}", 0, count($hoursPool) - 1);
            $hour = $hoursPool[$hIdx];
            unset($hoursPool[$hIdx]);
            $hoursPool = array_values($hoursPool);

            $sessions = max(1, $baseSessions - ($i * $this->prandInt("{$ownerId}|{$month}|drop|{$i}", 4, 14)));
            $topSlots[] = ['hour' => (int)$hour, 'sessions' => (int)$sessions];
        }

        usort(
            $topSlots,
            static fn(array $a, array $b): int => ($b['sessions'] <=> $a['sessions']) ?: ($a['hour'] <=> $b['hour'])
        );

        return [
            'month' => $month,
            'spot_count' => $spotCount,
            'booked_minutes' => $bookedMinutes,
            'available_minutes' => $availableMinutes,
            'occupancy_rate' => round($occPercent, 2),
            'top_slots' => $topSlots,
        ];
    }

    /**
     * @return array{
     *   month: string,
     *   spot_count: int,
     *   booked_minutes: int,
     *   available_minutes: int,
     *   occupancy_rate: float,
     *   top_slots: array<int, array{hour: int, sessions: int}>
     * }
     */
    public function getMonthlyOwnerMetrics(int $ownerId, string $month): array
    {
        // month format: YYYY-MM
        $monthStart = new DateTime($month . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('first day of next month');

        $spotStmt = $this->pdo->prepare('SELECT COUNT(*) FROM parking_spots WHERE owner_id = ?');
        $spotStmt->execute([$ownerId]);
        $spotCount = (int)$spotStmt->fetchColumn();

        // Total booked minutes (simple assumption: reservations are within month).
        $bookedStmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)), 0)
             FROM reservations r
             JOIN parking_spots ps ON ps.spot_id = r.spot_id
             WHERE ps.owner_id = ?
               AND r.status IN ("confirmed","active","completed")
               AND r.start_time >= ?
               AND r.start_time < ?'
        );
        $bookedStmt->execute([$ownerId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
        $bookedMinutes = (int)$bookedStmt->fetchColumn();

        // Available minutes = spots * minutes in month (simple, ignores availability_start/end).
        $days = (int)$monthStart->diff($monthEnd)->days;
        $availableMinutes = max(0, $spotCount * $days * 24 * 60);
        $occ = $availableMinutes > 0 ? ($bookedMinutes / $availableMinutes) : 0.0;

        $topStmt = $this->pdo->prepare(
            'SELECT HOUR(r.start_time) AS hour, COUNT(*) AS sessions
             FROM reservations r
             JOIN parking_spots ps ON ps.spot_id = r.spot_id
             WHERE ps.owner_id = ?
               AND r.status IN ("confirmed","active","completed")
               AND r.start_time >= ?
               AND r.start_time < ?
             GROUP BY hour
             ORDER BY sessions DESC
             LIMIT 5'
        );
        $topStmt->execute([$ownerId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
        $top = $topStmt->fetchAll() ?: [];

        $topSlots = array_map(
            static fn(array $row): array => ['hour' => (int)$row['hour'], 'sessions' => (int)$row['sessions']],
            $top
        );

        return [
            'month' => $month,
            'spot_count' => $spotCount,
            'booked_minutes' => $bookedMinutes,
            'available_minutes' => $availableMinutes,
            'occupancy_rate' => round($occ * 100, 2),
            'top_slots' => $topSlots,
        ];
    }

    /**
     * @return array<int, array{date: string, sessions: int, gross: float}>
     */
    public function getDailySessions(int $ownerId, string $month): array
    {
        $monthStart = new DateTime($month . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('first day of next month');

        $stmt = $this->pdo->prepare(
            'SELECT DATE(r.start_time) AS day,
                    COUNT(*) AS sessions,
                    COALESCE(SUM(r.final_cost), 0) AS gross
             FROM reservations r
             JOIN parking_spots ps ON ps.spot_id = r.spot_id
             WHERE ps.owner_id = ?
               AND r.status IN ("confirmed","active","completed")
               AND r.start_time >= ?
               AND r.start_time < ?
             GROUP BY day
             ORDER BY day ASC'
        );
        $stmt->execute([$ownerId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll() ?: [];

        // Fill missing days with zeros so the line chart is continuous.
        $map = [];
        foreach ($rows as $r) {
            $d = (string)($r['day'] ?? '');
            if ($d !== '') {
                $map[$d] = [
                    'date' => $d,
                    'sessions' => (int)($r['sessions'] ?? 0),
                    'gross' => (float)($r['gross'] ?? 0),
                ];
            }
        }

        $out = [];
        $cursor = clone $monthStart;
        while ($cursor < $monthEnd) {
            $d = $cursor->format('Y-m-d');
            $out[] = $map[$d] ?? ['date' => $d, 'sessions' => 0, 'gross' => 0.0];
            $cursor->modify('+1 day');
        }

        return $out;
    }

    /**
     * @return array<int, array{hour: int, sessions: int}>
     */
    public function getHourlySessions(int $ownerId, string $month): array
    {
        $monthStart = new DateTime($month . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('first day of next month');

        $stmt = $this->pdo->prepare(
            'SELECT HOUR(r.start_time) AS hour,
                    COUNT(*) AS sessions
             FROM reservations r
             JOIN parking_spots ps ON ps.spot_id = r.spot_id
             WHERE ps.owner_id = ?
               AND r.status IN ("confirmed","active","completed")
               AND r.start_time >= ?
               AND r.start_time < ?
             GROUP BY hour
             ORDER BY hour ASC'
        );
        $stmt->execute([$ownerId, $monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll() ?: [];

        $map = [];
        foreach ($rows as $r) {
            $h = (int)($r['hour'] ?? 0);
            $map[$h] = (int)($r['sessions'] ?? 0);
        }

        $out = [];
        for ($h = 0; $h <= 23; $h++) {
            $out[] = ['hour' => $h, 'sessions' => (int)($map[$h] ?? 0)];
        }
        return $out;
    }

    public function downloadMonthlyPdf(int $ownerId, string $month, string $ownerName, bool $fake = true): void
    {
        $m = $fake
            ? $this->getFakeMonthlyOwnerMetrics($ownerId, $month)
            : $this->getMonthlyOwnerMetrics($ownerId, $month);

        $pdf = new SimplePdf("Owner Monthly Report - {$month}");
        $pdf->addLine("Owner: {$ownerName}");
        $pdf->addLine("Report type: " . ($fake ? 'FAKE (demo)' : 'LIVE'));
        $pdf->addLine("Spots: " . $m['spot_count']);
        $pdf->addLine("Occupancy rate: " . number_format($m['occupancy_rate'], 2) . "%");
        $pdf->addLine("Booked minutes: " . $m['booked_minutes']);
        $pdf->addLine("");
        $pdf->addLine("Top time slots (by booking start hour):");
        if (empty($m['top_slots'])) {
            $pdf->addLine("  - No bookings in this month.");
        } else {
            foreach ($m['top_slots'] as $row) {
                $h = str_pad((string)$row['hour'], 2, '0', STR_PAD_LEFT);
                $pdf->addLine("  - {$h}:00 — {$row['sessions']} sessions");
            }
        }

        $pdf->outputDownload("owner_report_{$month}.pdf");
    }
}

